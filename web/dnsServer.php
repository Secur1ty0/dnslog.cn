<?php
// 包含数据库操作文件
include 'db.php';
include 'archive.php';


// 执行检查和初始化
error_log("Checking and initializing database at: $db_file");
check_and_initialize_db($db_file);



 // 监听所有IP地址
$port = 53;
$host = '0.0.0.0';

// 检查数据库文件是否存在
function check_and_initialize_db($db_file) {
    $db = new SQLite3($db_file);
    // 强制执行建表逻辑，确保表存在（IF NOT EXISTS 保证幂等）
    $db->exec("CREATE TABLE IF NOT EXISTS dns_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT,
        ip TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS create_domains (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT,
        phpsession TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS invalid_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT,
        port INTEGER,
        reason TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->close();
}

// 初始化数据库
function init_db($db_file) {
    try {
    // 创建数据库连接
    $newdb = new SQLite3($db_file);
    
    // 创建表格
    $newdb->exec("CREATE TABLE IF NOT EXISTS dns_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT,
        ip TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $newdb->exec("CREATE TABLE IF NOT EXISTS create_domains (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT,
        phpsession TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $newdb->exec("CREATE TABLE IF NOT EXISTS invalid_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT,
        port INTEGER,
        reason TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // 赋予数据库文件 777 权限
    // chmod($db_file, 0777);
    echo "Database initialized successfully.";
    } catch (Exception $e) {
        echo "Database initialization failed: " . $e->getMessage();
        exit(1);
    }
}




// 备份检查
auto_monthly_archive_tables($db_file,$backup_dir,$meta_file);

// 创建一个UDP socket
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

if (!$socket) {
    die("Unable to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

// 绑定socket到指定的端口
if (!socket_bind($socket, $host, $port)) {
    die("Unable to bind socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "DNS server started on $host:$port...\n";

// 获取请求的IP地址
date_default_timezone_set('Asia/Shanghai');

// 初始化归档标记
$last_archive_check = 0;
$archive_check_interval = 600*6*3; // 3个小时

// 检查是否是最近生成的域名（高优先级处理）
function is_recent_generated_domain($domain) {
    global $domain_suffix;
    // error_log("Checking domain: $domain against suffix: $domain_suffix");
    
    // 如果域名与后缀长度相同或更短，直接返回false
    if (strlen($domain) <= strlen($domain_suffix)) {
        // error_log("Domain is too short");
        return false;
    }
    
    // 移除后缀部分来获取子域名
    if (!ends_with($domain, $domain_suffix)) {
        error_log("Domain does not end with allowed suffix");
        return false;
    }
    
    // 获取子域名部分（不包括后缀）
    $subDomain = substr($domain, 0, -(strlen($domain_suffix) + 1)); // +1 for the dot
    $parts = explode('.', $subDomain);
    
    // 获取随机生成的域名部分（最后一段）
    $randomPart = end($parts);
    $checkDomain = $randomPart . '.' . $domain_suffix;
    
    // error_log("Checking generated domain part: $checkDomain");
    
    try {
        $db = get_db_connection();
        if (!$db) {
            error_log("Failed to connect to database");
            return false;
        }
        
        $stmt = $db->prepare("SELECT 1 FROM create_domains 
                             WHERE domain = :domain 
                             AND timestamp >= datetime('now', '-5 minutes')");
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $db->lastErrorMsg());
            return false;
        }
        
        $stmt->bindValue(':domain', $checkDomain, SQLITE3_TEXT);
        $result = $stmt->execute();
        $exists = $result && $result->fetchArray() !== false;
        
        //error_log("Domain check result for $checkDomain: " . ($exists ? "found" : "not found"));
        
        $stmt->close();
        return $exists;
    } catch (Exception $e) {
        error_log("Error checking domain: " . $e->getMessage());
        return false;
    } finally {
        if (isset($db)) {
            $db->close();
        }
    }
}

while (true) {
    $buf = '';
    $from = '';
    $port = 0;
    $timestamp = date('Y-m-d H:i:s');

    // 每3个小时尝试归档一次
    $now = time();
    if ($now - $last_archive_check >= $archive_check_interval) {
        auto_monthly_archive_tables($db_file,$backup_dir,$meta_file);
        $last_archive_check = $now;
    }

    // 接收 DNS 查询
    socket_recvfrom($socket, $buf, 512, 0, $from, $port);

    // 检查包是否为UDP包且在合理范围内
    if (strlen($buf) < 12 || strlen($buf) > 512) {
        //log_invalid_request($db, $from, $port, "Invalid packet size: " . strlen($buf));
        continue;
    }

    // 解析 DNS 查询
    $domain = parse_dns_query($buf);

    // 检查域名是否符合指定后缀
    if (!ends_with($domain, $domain_suffix)) {
        continue;
    }
    
    $ip = $from;
    
    // 如果是最近生成的域名，立即处理
    if (is_recent_generated_domain($domain)) {
        process_dns_request_immediate($domain, $ip);
    } else {
        // 其他请求放入队列
        queue_dns_request($domain, $ip);
    }
    
    // 每处理 10 个请求尝试处理一次队列
    static $request_count = 0;
    if (++$request_count >= 10) {
        process_dns_queue();
        $request_count = 0;
    }
    
    // 生成并发送 DNS 响应，固定 IP 为 127.0.0.1
    $response = generate_dns_response($buf, $domain, '127.0.0.1');

    // 将响应发送回客户端
    socket_sendto($socket, $response, strlen($response), 0, $from, $port);
}

// 解析DNS查询包
function parse_dns_query($query) {
    $domain = '';
    $pos = 12;  // 跳过头部12字节
    
    // 解析所有子域名部分
    while (true) {
        $len = ord($query[$pos]);  // 获取当前标签长度
        
        // 如果长度为0，说明域名结束
        if ($len === 0) {
            break;
        }
        
        // 如果有压缩指针（高两位为1），则结束
        if (($len & 0xC0) === 0xC0) {
            break;
        }
        
        $pos++;
        // 添加当前标签到域名
        $domain .= substr($query, $pos, $len) . '.';
        $pos += $len;
    }
    
    // 移除末尾的点号并返回完整域名
    return rtrim($domain, '.');
}

// 检查字符串后缀
function ends_with($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 || 
           (substr($haystack, -$length) === $needle);
}

function cleanup() {
    process_dns_queue(); // 确保处理所有剩余的队列项
    if (file_exists(QUEUE_LOCK_FILE)) {
        unlink(QUEUE_LOCK_FILE);
    }
}

register_shutdown_function('cleanup');

?>
