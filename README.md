
# PushService

> PushService 是一个推送服务平台，客户端基于订阅模式，兼容 pusher，创建应用信息即可快速使用。

> 使用 [webman](https://github.com/walkor/webman "webman") + [GatewayWorker](https://github.com/webman-php/gateway-worker "GatewayWorker") 开发实现 客户端连接、应用管理、数据统计、订阅发布数据等。

# 项目地址

- https://github.com/hsk99/push-service


# 安装

## composer安装

创建项目

`composer create-project hsk99/push-service`

## 下载安装

1、下载 或 `git clone https://github.com/hsk99/push-service`

2、执行命令 `composer install`

## 导入数据库

- sql文件位置：` database/push.sql `

## 配置修改

1、修改文件 `config/redis.php` 设置 Redis

2、修改文件 `config/server.php` 设置 HTTP

3、修改目录 `config/plugin/webman/gateway-worker/` 设置 GatewayWorker

4、修改文件 ` config/thinkorm.php ` 设置 MySql 相关信息


# 运行

执行命令 `php start.php start`


# 查看统计

- 浏览器访问 `http://ip地址:8789`

- 默认账号：` admin `

- 默认密码：` admin888 `

- 相关信息可在 ` 系统管理--系统设置 ` 中进行设置


# 订阅发布

- 使用 [push-client](https://github.com/hsk99/push-client "push-client") 插件
