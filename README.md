#Beanbun
简介
----
Beanbun 是一个简单可扩展的爬虫框架，支持守护进程模式与普通模式，守护进程模式基于 Workerman，下载器基于 Guzzle。

特点
----
- 支持守护进程与普通两种模式
- 默认使用guzzle进行爬取
- 支持分布式
- 支持内存、Redis等多种队列方式
- 支持自定义URI过滤
- 支持广度优先和深度优先两种爬取方式
- 遵循PSR-4标准
- 爬取网页分为多步，每步均支持自定义动作（如添加代理、修改user-agent等）
- 灵活的扩展机制，可方便的为框架制作插件：自定义队列、自定义爬取方式...

安装
----
```
$ composer require kiddyu/beanbun
```

示例
----
创建一个文件start.php，包含以下内容
``` php
<?php
use Beanbun\Beanbun;
$beanbun = new Beanbun;
$beanbun->name = '950d';
$beanbun->count = 5;
$beanbun->seed = 'http://www.950d.com/';
$beanbun->max = 100;
$beanbun->logFile = __DIR__ . '/950d_access.log';
$beanbun->afterDownloadPage = function($beanbun) {
	file_put_contents(__DIR__ . '/' . md5($beanbun->url), $beanbun->page);
};
$beanbun->start();
```
在命令行中执行
```
$ php start.php start
```

更多详细内容，请查看[文档 http://www.beanbun.org](http://www.beanbun.org)


