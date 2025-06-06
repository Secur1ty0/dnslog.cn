# 🧪 DNSLog 平台克隆版（自定义域名支持）

本项目是对 [dnslog.cn](https://dnslog.cn) 平台的简洁克隆，支持自定义域名部署，便于内网渗透测试与痕迹收集，同时绕过部分安全设备对 `dnslog.cn` 的拦截。

> ✅ 适合集成在各类漏洞验证框架或红队测试中使用

---

## 🚀 特性

* 支持临时 DNS 记录收集
* 支持 SQLite 存储记录
* 支持前端 DNS 查询追踪
* 支持自定义域名后缀
* 启动即用，无需依赖复杂后端服务

---

## 🛠️ 环境部署（CentOS 7.9）

```bash
# 添加 EPEL 和 Remi 源
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm

# 启用 PHP 7.4
sudo yum install -y yum-utils
sudo yum-config-manager --enable remi-php74

# 安装 PHP 7.4 + SQLite 支持
sudo yum install -y php74-php php74-php-cli php74-php-fpm php74-php-mbstring php74-php-opcache php74-php-sqlite3

# 启动 php-fpm 服务
sudo systemctl start php74-php-fpm
sudo systemctl enable php74-php-fpm

# 安装并启用 Nginx
sudo yum install -y nginx
sudo systemctl enable nginx
```

### 配置 PHP session 权限

```bash
vim /etc/opt/remi/php74/php.ini
# 找到并取消注释
session.save_path = "/tmp"

chmod 777 /tmp
chmod 777 /var/www/dnslogcn/data
chown -R nginx:nginx /var/lib/php/session
chmod -R 700 /var/lib/php/session
```

### 配置 Nginx 示例

```nginx
server {
    listen       80;
    server_name  example.com;

    root   /var/www/dnslogcn/web;
    index  index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 🌐 域名配置说明

1. 新增A记录

   ![image-20250107093536565](./img/image3.png)

2. 自定义dns服务器

   ![image-20250107092715723](./img/image2.png)

3. 修改dns服务器为自定义服务器

   ![image-202501070940238961](./img/image4.png)

---

## 📦 启动 DNS 服务

1. 编辑 `db.php`，设置你绑定的域名后缀：

```php
// SQLite 数据库文件路径
$db_file = '../data/domain.db';
// Dnslog平台域名
$domain_suffix = "example.cn";
```

2. 启动 DNS 服务：

```bash
php dnsServer.php &
```

3. 访问 Web 页面：

```
http://<your-server-ip>/index.php
```

---

## 📁 文件结构

```bash
.
├── data
│   ├── archive
│   │   ├── archive_create_domains_2025-06.sql
│   │   └── archive_dns_requests_2025-06.sql
│   └── domain.db
└── web
    ├── archive.php //定时归档文件，0-13点，每月归档一次
    ├── banner.png
    ├── db.php      //配置文件
    ├── dnsServer.php 
    ├── favicon.ico
    ├── getdomain.php
    └── getrecords.php
```

---

## 📝 更新日志（Changelog）

| 日期       | 更新内容                                   |
| ---------- | ------------------------------------------ |
| 2025-06-06 | ✅ 优化 SQLite 写入机制，避免并发冲突       |
|            | ✅ 支持 DNS 查询记录展示最近 5 条           |
|  | ✅ 增加 SQLite 文件备份与数据库清理机制，减轻读写压力 |


---

## ✅ TODO

* Docker 快速部署

---

## 📬 联系反馈

如有建议或功能需求，欢迎提交 Issue 或 PR，感谢支持！
