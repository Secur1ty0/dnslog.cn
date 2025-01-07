<?php
// SQLite 数据库文件路径
$db_file = '../data/domain.db';

$domain_suffix = "sz-g0v.cc";

// 全局数据库连接变量
$gdb = null;

// 初始化数据库连接
function get_db_connection() {
    global $gdb, $db_file; // 声明 $db 和 $db_file 为全局变量
    // 如果数据库连接已经存在，则直接返回
    // 如果数据库连接已经存在，则直接返回
    if ($gdb === null) {
        try {
            $gdb = new SQLite3($db_file);
        } catch (Exception $e) {
            // 捕捉异常并处理，例如权限不够或文件不存在
            error_log("数据库连接失败: " . $e->getMessage());
            echo "无法连接到数据库，请检查文件权限或路径。";
            exit; // 停止脚本执行
        }
    }
    return $gdb;
}


// 记录合法的DNS请求
function log_dns_request($db, $domain, $ip) {
    $stmt = $db->prepare("INSERT INTO dns_requests (domain, ip) VALUES (:domain, :ip)");
    $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->execute();
}


// 记录生成的域名
function log_generated_domain($db, $domain,$phpsessions) {
    $stmt = $db->prepare("INSERT INTO create_domains (domain, phpsession) VALUES (:domain, :phpsess)");
    $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
    $stmt->bindValue(':phpsess', $phpsessions, SQLITE3_TEXT);
    $stmt->execute();
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


// 记录无效请求
// function log_invalid_request($db, $ip, $port, $reason) {
//     $db = get_db_connection();
//     $stmt = $db->prepare("INSERT INTO invalid_requests (ip, port, reason) VALUES (:ip, :port, :reason)");
//     $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
//     $stmt->bindValue(':port', $port, SQLITE3_INTEGER);
//     $stmt->bindValue(':reason', $reason, SQLITE3_TEXT);
//     $stmt->execute();
// }
?>
