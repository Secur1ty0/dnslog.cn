<?php
// 包含数据库操作文件
include 'db.php';

// 开启或继续现有的会话
session_start();

// 获取数据库连接
$db = get_db_connection();

// 获取当前会话的PHPSESSID
$session_id = session_id();

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


// 生成并记录随机域名
$random_domain = generate_random_domain();
log_generated_domain($db, $random_domain,$session_id);
// 示例返回内容
echo $random_domain;
?>
