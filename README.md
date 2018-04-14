Yii 2 Advanced Project Template
===============================

Yii 2 Advanced Project Template is a skeleton [Yii 2](http://www.yiiframework.com/) application best for
developing complex Web applications with multiple tiers.

The template includes three tiers: front end, back end, and console, each of which
is a separate Yii application.

The template is designed to work in a team development environment. It supports
deploying the application in different environments.

Documentation is at [docs/guide/README.md](docs/guide/README.md).

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii2-app-advanced/v/stable.png)](https://packagist.org/packages/yiisoft/yii2-app-advanced)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii2-app-advanced/downloads.png)](https://packagist.org/packages/yiisoft/yii2-app-advanced)
[![Build Status](https://travis-ci.org/yiisoft/yii2-app-advanced.svg?branch=master)](https://travis-ci.org/yiisoft/yii2-app-advanced)

swoole安装和使用文档
-------------------

```
注：php版本7.0             swoole版本：1.9

1、下载解压：
wget https://github.com/swoole/swoole-src/archive/v1.9.22.zip
   unzip v1.9.22.zip
2、安装
    1、进入解压swoole目录
  2、执行：phpize
          ./configure --with-php-config=/usr/local/php/bin/php-config
                   make
          sudo make install
 
3、杀掉swoole服务
kill -9 $(ps aux|grep swoole/start|grep -v grep|awk '{print $2}')
kill -9 $(ps aux|grep phpworker|grep -v grep|awk '{print $2}')
```
4、启动swoole：
php /自己的目录/advanced/yii swoole/start

# yii
