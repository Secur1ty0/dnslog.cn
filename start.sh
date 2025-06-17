#!/bin/bash

echo "[+] 启动 php-fpm..."
php-fpm &

echo "[+] 启动 nginx..."
nginx

echo "[+] 启动 DNS 服务监听 53 端口..."
su -s /bin/sh -c "php /var/www/dnslog/web/dnsServer.php" www-data
#sleep infinity
