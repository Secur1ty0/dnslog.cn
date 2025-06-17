<?php
// 包含数据库操作文件
require 'db.php';

// 开启或继续现有的会话
session_start();

// 获取当前会话的PHPSESSID
$session_id = session_id();
if (empty($session_id)) {
    echo "Session ID is empty!";
}

// 设置Content-Type
header('Content-Type: text/html; charset=UTF-8');

// 手动设置Set-Cookie，如果需要自定义路径或其他选项
setcookie("PHPSESSID", $session_id, 0, "/");
// 生成随机域名
function generate_random_domain($length = 6) {
    global $domain_suffix;
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $domain = '';
    for ($i = 0; $i < $length; $i++) {
        $domain .= $characters[mt_rand(0, strlen($characters) - 1)];
    }
    return $domain . "." .$domain_suffix;
}

try {
    // 生成并记录随机域名
    $random_domain = generate_random_domain();
    log_generated_domain($random_domain, $session_id);
    // 返回内容
    echo $random_domain;
} catch (Exception $e) {
    error_log("Error in getdomain.php: " . $e->getMessage());
    http_response_code(500);
    echo "Error generating domain: " . $e->getMessage();
}
?>
