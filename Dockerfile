FROM alpine:3.18

# 安装必要组件
RUN apk add --no-cache \
    php81 \
    php81-fpm \
    php81-sqlite3 \
    php81-pdo_sqlite \
    php81-json \
    php81-sockets \
    php81-session \
    nginx \
    sqlite \
    tzdata \
    curl \
    bash \
    shadow && \
    rm -rf /var/cache/apk/*

# 添加 www-data 用户
RUN useradd -u 1000 -g www-data -s /bin/sh -d /var/www www-data

# 设置时区
RUN cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    echo "Asia/Shanghai" > /etc/timezone

# PHP 配置
RUN sed -i 's/;error_log = log\/php-fpm.log/error_log = \/var\/log\/php_errors.log/' /etc/php81/php-fpm.conf && \
    echo "date.timezone = Asia/Shanghai" >> /etc/php81/php.ini

# PHP-FPM 配置（设置 user 为 www-data）
RUN sed -i 's/user = nobody/user = www-data/' /etc/php81/php-fpm.d/www.conf && \
    sed -i 's/group = nobody/group = www-data/' /etc/php81/php-fpm.d/www.conf && \
    echo "clear_env = no" >> /etc/php81/php-fpm.d/www.conf && \
    echo "env[DOMAIN_SUFFIX] = \$DOMAIN_SUFFIX" >> /etc/php81/php-fpm.d/www.conf

# 创建目录并设置权限
RUN mkdir -p /var/www/dnslog/data && \
    mkdir -p /var/www/dnslog/web && \
    mkdir -p /run/php-fpm && \
    chown -R www-data:www-data /var/www && \
    chmod -R 755 /var/www && \
    chmod 755 /run/php-fpm
# RUN echo "display_errors = On" >> /etc/php81/php.ini && \
#     echo "log_errors = On" >> /etc/php81/php.ini && \
#     echo "error_reporting = E_ALL" >> /etc/php81/php.ini

# 设置工作目录
WORKDIR /var/www/dnslog

# 拷贝文件
COPY ./web/ /var/www/dnslog/web/
COPY ./data/ /var/www/dnslog/data/
COPY nginx.conf /etc/nginx/nginx.conf
COPY start.sh /start.sh

# 权限处理
RUN chmod +x /start.sh && \
    chown -R www-data:www-data /var/www/dnslog/data && \
    chmod -R 777 /var/www/dnslog/data

# 暴露端口
EXPOSE 80 53/udp

# 启动命令
CMD ["/start.sh"]

