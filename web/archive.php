<?php
//30 天未归档 + 在 0~13 点	立即归档
//30 天未归档 + 非 0~13 点	不归档，不更新状态
//未到 30 天	不归档
function auto_monthly_archive_tables($db_file,$backup_dir,$meta_file)
{
    $interval_seconds = 30 * 86400; // 30天
    $now = time();

    // 当前小时（0-23）
    $current_hour = intval(date('G', $now));

    // 默认认为未归档
    $last_archive_time = 0;
    if (file_exists($meta_file)) {
        $last_archive_time = intval(file_get_contents($meta_file));
    }

    // 判断是否到归档周期
    $need_archive = ($now - $last_archive_time >= $interval_seconds);
    $in_time_window = ($current_hour >= 0 && $current_hour < 13);

    if (!$need_archive) {
        return; // 还不到归档周期
    }

    if (!$in_time_window) {
        // 到了归档时间，但当前不在允许时间段，延迟归档
        error_log("⏳ 已到归档周期，但当前时间不在 0~13 点内，延迟归档");
        return;
    }

    // 创建归档目录（如不存在）
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0775, true)) {
            error_log("❌ 无法创建归档目录：$backup_dir");
            return;
        }
    }

    // 构造归档文件名（如 archive_2025-06.sql）
    $date_str = date('Y-m');
    $tables = ['dns_requests', 'create_domains'];
    foreach ($tables as $table) {
        $archive_file = "$backup_dir/archive_{$table}_$date_str.sql";

        $cmd = "sqlite3 $db_file \".dump $table\" > $archive_file";
        exec($cmd, $output, $ret);
        if ($ret !== 0) {
            error_log("归档失败：表 $table 导出错误，返回码 $ret");
            continue;
        }

        // 连接数据库，清空该表
        $db = get_db_connection();
        $db->exec("DELETE FROM $table");
        $db->exec('VACUUM');
        $db->close();

        error_log("✅ 表 $table 已归档至：$archive_file");
    }

    // 更新归档时间戳（现在才更新）
    file_put_contents($meta_file, strval($now));

    error_log("✅ DNS 数据已归档至：$archive_file");
}
