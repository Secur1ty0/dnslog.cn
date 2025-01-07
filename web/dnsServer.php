<?php
// 包含数据库操作文件
include 'db.php';

// 检查数据库文件是否存在
function check_and_initialize_db($db_file) {
    if (!file_exists($db_file)) {
        // 如果数据库文件不存在，初始化数据库
        init_db($db_file);
    }
}
// 初始化数据库
function init_db($db_file) {
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

    echo "Database initialized successfully.";
}

// 执行检查和初始化
check_and_initialize_db($db_file);

 // 监听所有IP地址
$port = 53;
$host = '0.0.0.0';

// 定义允许的域名后缀
$allowed_domain_suffix = $domain_suffix;


// 获取数据库连接
$db = get_db_connection();

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
while (true) {
    $buf = '';
    $from = '';
    $port = 0;
    $timestamp = date('Y-m-d H:i:s');
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
    if (!ends_with($domain, $allowed_domain_suffix)) {
        //log_invalid_request($db, $from, $port, "Invalid domain: $domain");
        continue;
    }
    // 使用 $from 作为源 IP 地址
    $ip = $from; 
    // 记录合法请求
    log_dns_request($db, $domain, $ip);
	// 生成并发送 DNS 响应，固定 IP 为 127.0.0.1
    $response = generate_dns_response($buf, $domain, '127.0.0.1');

    // 将响应发送回客户端
    socket_sendto($socket, $response, strlen($response), 0, $from, $port);
}

// 解析DNS查询包
function parse_dns_query($query) {
    $domain = '';
    $len = ord($query[12]);
    $pos = 13;

    while ($len > 0) {
        $domain .= substr($query, $pos, $len) . '.';
        $pos += $len;
        $len = ord($query[$pos]);
        $pos++;
    }

    return rtrim($domain, '.');
}

// 检查字符串后缀
function ends_with($haystack, $needle) {
    return substr($haystack, -strlen($needle)) === $needle;
}

?>
