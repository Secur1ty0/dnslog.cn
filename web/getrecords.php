<?php
require 'db.php';

// 获取请求头中的 PHPSESSID
$phpsession = $_COOKIE['PHPSESSID'] ?? '';

if (empty($phpsession)) {
    echo json_encode([]);
    exit;
}

// 初始化数据库连接
$db = get_db_connection();

// 查询 create_domains 表中的域名
$stmt = $db->prepare("SELECT domain FROM create_domains WHERE phpsession = :phpsession  ORDER BY id DESC LIMIT 1");
$stmt->bindValue(':phpsession', $phpsession, SQLITE3_TEXT);
$result = $stmt->execute();

$domain = null;
$row = $result->fetchArray(SQLITE3_ASSOC);

if ($row) {
    $domain = $row['domain'];
} else {
    // 如果没有找到域名，返回空数组
    echo json_encode([]);
    exit;
}


// 查询 dns_requests 表中的记录
$stmt = $db->prepare("SELECT domain, ip, timestamp FROM dns_requests WHERE domain LIKE :domain");
$stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
$result = $stmt->execute();

// 后缀匹配
$like_domain = '%'.$domain;
$stmt->bindValue(':domain', $like_domain, SQLITE3_TEXT);
$result = $stmt->execute();

$records = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $records[] = [
        $row['domain'],
        $row['ip'],
        $row['timestamp']
    ];
}
// 设置Content-Type
header('Content-Type: application/json; charset=utf-8');
// 返回结果
echo json_encode($records);
?>