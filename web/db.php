<?php

error_log("DOMAIN_SUFFIX from getenv(): " . getenv('DOMAIN_SUFFIX'));
// SQLite 数据库文件路径
$root_dir = "/var/www/dnslog/";
$db_file = $root_dir .'data/domain.db';
// export DOMAIN_SUFFIX=officeredir-microsoft.rest
$domain_suffix = getenv('DOMAIN_SUFFIX') ?: 'example.com'; // 从环境变量获取域名后缀，默认为 example.com
$backup_dir = $root_dir .'data/archive';
$meta_file = $root_dir .'data/.last_archive_time';

error_log("Final domain_suffix value: " . $domain_suffix);

// 初始化数据库连接
function get_db_connection() {
    global $db_file;
    $retries = 3;
    $wait = 100000;
    
    // 检查数据库目录
    $db_dir = dirname($db_file);
    if (!is_dir($db_dir)) {
        //error_log("Creating database directory: $db_dir");
        if (!mkdir($db_dir, 0777, true)) {
            //error_log("Failed to create database directory");
            return null;
        }
    }
    
    // 检查目录权限
    //error_log("Directory permissions: " . substr(sprintf('%o', fileperms($db_dir)), -4));
    
    while ($retries > 0) {
        try {
            //error_log("Attempting to connect to database: $db_file");
            
            // 检查文件是否存在且可写
            if (file_exists($db_file)) {
                // error_log("Database file permissions: " . substr(sprintf('%o', fileperms($db_file)), -4));
            }
            
            $gdb = new SQLite3($db_file, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            if (!$gdb) {
                throw new Exception("Failed to create/open database connection");
            }
            
            // 设置超时
            $gdb->busyTimeout(5000);
            
            // 验证连接是否真的可用
            $test = $gdb->query('SELECT 1');
            if (!$test) {
                throw new Exception("Database connection test failed");
            }
            
            return $gdb;
        } catch (Exception $e) {
            error_log("Database connection attempt $retries failed: " . $e->getMessage());
            $retries--;
            if ($retries <= 0) {
                throw new Exception("Database connection failed after $retries retries: " . $e->getMessage());
            }
            usleep($wait);
        }
    }
    return null;
}


// 记录合法的DNS请求
function log_dns_request($domain, $ip) {
    $db = get_db_connection();
    $stmt = $db->prepare("INSERT INTO dns_requests (domain, ip) VALUES (:domain, :ip)");
    $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->execute();
    $stmt->close(); // 显式释放语句资源
    $db->close();   // 关闭连接
}


// 记录生成的域名
function log_generated_domain($domain, $phpsessions) {
    error_log("Attempting to log domain: $domain with session: $phpsessions");
    
    $db = null;
    try {
        $db = get_db_connection();
        if (!$db) {
            throw new Exception("Could not establish database connection");
        }
        
        // 开始事务前测试连接
        if (!$db->query('SELECT 1')) {
            throw new Exception("Database connection is not valid");
        }
        
        $db->exec('BEGIN');
        
        $stmt = $db->prepare("INSERT INTO create_domains (domain, phpsession) VALUES (:domain, :phpsess)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $db->lastErrorMsg());
        }
        
        $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
        $stmt->bindValue(':phpsess', $phpsessions, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $db->lastErrorMsg());
        }
        
        $db->exec('COMMIT');
        $stmt->close();
        
        error_log("Successfully logged domain: $domain");
        return true;
    } catch (Exception $e) {
        error_log("Error in log_generated_domain: " . $e->getMessage());
        if ($db) {
            try {
                $db->exec('ROLLBACK');
            } catch (Exception $rollbackError) {
                error_log("Rollback failed: " . $rollbackError->getMessage());
            }
        }
        throw $e;
    } finally {
        if ($db) {
            try {
                $db->close();
            } catch (Exception $closeError) {
                error_log("Error closing database: " . $closeError->getMessage());
            }
        }
    }
}

// 生成 DNS 响应数据包
function generate_dns_response($query, $domain, $ip) {
    // 生成标头部分 (Transaction ID 和 Flags)
    $transaction_id = substr($query, 0, 2); // 获取原始查询的 Transaction ID
    $flags = "\x81\x80"; // 标志字段，设置为标准查询响应
    $questions = "\x00\x01"; // QDCOUNT: 1
    $answer_rrs = "\x00\x01"; // ANCOUNT: 1
    $authority_rrs = "\x00\x00"; // NSCOUNT: 0
    $additional_rrs = "\x00\x00"; // ARCOUNT: 0

    // 构建 Question 部分（保留原始查询的 Question 部分）
    $query_body = substr($query, 12); // 跳过前12个字节（标头部分）
    $question_section = $query_body;

    // 构建 Answer 部分
    $name = "\xc0\x0c"; // 指针（压缩域名）
    $type = "\x00\x01"; // TYPE: A
    $class = "\x00\x01"; // CLASS: IN
    $ttl = "\x00\x00\x00\x3c"; // TTL: 60 seconds
    $rdlength = "\x00\x04"; // RDLENGTH: 4 bytes (IPv4 地址长度)
    
    // 将IP地址转换为字节形式
    $rdata = implode('', array_map('chr', explode('.', $ip))); // 转换IP地址为字节流

    // 合并各部分构建完整的响应
    $response = $transaction_id . $flags . $questions . $answer_rrs . $authority_rrs . $additional_rrs;
    $response .= $question_section . $name . $type . $class . $ttl . $rdlength . $rdata;

    return $response;
}

// 添加队列相关常量
define('DNS_QUEUE_FILE', $root_dir.'data/dns_queue.txt');
define('QUEUE_LOCK_FILE', $root_dir .'data/queue.lock');

// 添加队列处理函数
function queue_dns_request($domain, $ip) {
    $data = json_encode(['domain' => $domain, 'ip' => $ip, 'timestamp' => date('Y-m-d H:i:s')]) . "\n";
    file_put_contents(DNS_QUEUE_FILE, $data, FILE_APPEND | LOCK_EX);
}

function process_dns_queue() {
    // 如果锁文件存在，说明其他进程正在处理
    if (file_exists(QUEUE_LOCK_FILE)) {
        return;
    }

    // 创建锁文件
    touch(QUEUE_LOCK_FILE);

    try {
        if (!file_exists(DNS_QUEUE_FILE)) {
            return;
        }

        $queue_content = file_get_contents(DNS_QUEUE_FILE);
        if (empty($queue_content)) {
            return;
        }

        // 清空队列文件
        file_put_contents(DNS_QUEUE_FILE, '');

        $lines = array_filter(explode("\n", $queue_content));
        $db = get_db_connection();

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $stmt = $db->prepare("INSERT INTO dns_requests (domain, ip, timestamp) VALUES (:domain, :ip, :timestamp)");
                $stmt->bindValue(':domain', $data['domain'], SQLITE3_TEXT);
                $stmt->bindValue(':ip', $data['ip'], SQLITE3_TEXT);
                $stmt->bindValue(':timestamp', $data['timestamp'], SQLITE3_TEXT);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->close();
    } finally {
        // 清理锁文件
        unlink(QUEUE_LOCK_FILE);
    }
}

// 记录无效请求
// function log_invalid_request($db, $ip, $port, $reason) {
//     $db = get_db_connection();
//     $stmt = $db->prepare("INSERT INTO invalid_requests (ip, port, reason) VALUES (:ip, :port, :reason)");
//     $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
//     $stmt->bindValue(':port', $port, SQLITE3_INTEGER);
//     $stmt->bindValue(':reason', $reason, SQLITE3_TEXT);
//     $stmt->execute();
// }

// 立即处理的DNS请求（高优先级）
function process_dns_request_immediate($domain, $ip) {
    $retries = 3;
    $wait = 100000; // 100ms
    
    while ($retries > 0) {
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("INSERT INTO dns_requests (domain, ip) VALUES (:domain, :ip)");
            $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result) {
                $stmt->close();
                $db->close();
                return true;
            }
        } catch (Exception $e) {
            error_log("DNS immediate insert retry $retries: " . $e->getMessage());
        }
        $retries--;
        if ($retries > 0) usleep($wait);
    }
    return false;
}
?>
