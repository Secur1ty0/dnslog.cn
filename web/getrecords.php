<?php
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

// 获取请求头中的 PHPSESSID
$phpsession = $_COOKIE['PHPSESSID'] ?? '';
if (empty($phpsession)) {
    echo json_encode([]);
    exit;
}

// 初始化数据库连接
$db = get_db_connection();

// 查询最近 5 条 create_domains 记录
$stmt1 = $db->prepare("SELECT domain FROM create_domains WHERE phpsession = :phpsession ORDER BY id DESC LIMIT 5");
$stmt1->bindValue(':phpsession', $phpsession, SQLITE3_TEXT);
$result1 = $stmt1->execute();

$domains = [];
while ($row = $result1->fetchArray(SQLITE3_ASSOC)) {
    if (!empty($row['domain'])) {
        $domains[] = $row['domain'];
    }
}
$stmt1->close();

// 如果没有找到任何域名，返回空数组
if (empty($domains)) {
    echo json_encode([]);
    exit;
}

// 查询 dns_requests 中匹配这些域名的数据
$records = [];
foreach ($domains as $domain) {
    $like_domain = '%' . $domain;
    $stmt2 = $db->prepare("SELECT domain, ip, timestamp FROM dns_requests WHERE domain LIKE :domain");
    $stmt2->bindValue(':domain', $like_domain, SQLITE3_TEXT);
    $result2 = $stmt2->execute();

    while ($row = $result2->fetchArray(SQLITE3_ASSOC)) {
        $records[] = [
            $row['domain'],
            $row['ip'],
            $row['timestamp']
        ];
    }

    $stmt2->close();
}

$db->close();

// 输出 JSON 数据
echo json_encode($records);

?>
