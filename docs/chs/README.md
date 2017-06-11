# Beanbun

Beanbun 是用 PHP 编写的多进程网络爬虫框架，具有良好的开放性、高可扩展性。

## 简介

Beanbun 是一个简单可扩展的爬虫框架，支持守护进程模式与普通模式，守护进程模式基于 [Workerman](http://www.workerman.net)，下载器基于 [Guzzle](http://guzzle.org)。  
框架名称来自于作者家的猫，此猫名叫门丁，“门丁”是北方的一种面点。门丁 -> 豆包 -> bean bun  

<img src="/images/mending.jpg" alt="label" width="300">

## 特点
- 支持守护进程与普通两种模式（守护进程模式只支持 Linux 服务器）
- 默认使用 Guzzle 进行爬取
- 支持分布式
- 支持内存、Redis 等多种队列方式
- 支持自定义URI过滤
- 支持广度优先和深度优先两种爬取方式
- 遵循 PSR-4 标准
- 爬取网页分为多步，每步均支持自定义动作（如添加代理、修改 user-agent 等）
- 灵活的扩展机制，可方便的为框架制作插件：自定义队列、自定义爬取方式...

## 安装

Beanbun 可以通过 composer 进行安装。

```
$ composer require kiddyu/beanbun
```

## 快速开始

创建一个文件 start.php，包含以下内容

``` php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

use Beanbun\Beanbun;
$beanbun = new Beanbun;
$beanbun->seed = [
  'http://www.950d.com/',
  'http://www.950d.com/list-1.html',
  'http://www.950d.com/list-2.html',
];
$beanbun->afterDownloadPage = function($beanbun) {
  file_put_contents(__DIR__ . '/' . md5($beanbun->url), $beanbun->page);
};
$beanbun->start();
```
在命令行中执行
```
$ php start.php
```
接下来就可以看到抓取的日志了。

## 使用

### 启动与停止

上面的例子中，爬虫是以普通模式运行的，上面的代码放在网站项目中，也可以正常执行，如果我们想让爬虫一直执行，就需要使用守护进程模式。同样是上面的代码，我们只需要把执行的命令增加一个 start 参数，即会变成守护进程模式。

```
$ php start.php start
```

需要说明的是，普通模式下不依赖队列，爬虫只爬取 seed 中得地址，依次爬取完成后，程序即结束。而守护进程模式需要另外开启队列（内存队列、Redis 队列等），但拥有更多的功能，如可以自动发现页面中的链接加入队列，循环爬取。以下是守护进程模式下的说明。

*启动*

```
// 启动爬虫，开启所有爬虫进程
$ php start.php start
```

*停止*

```
// 停止爬虫，关闭所有爬虫进程
php start.php stop
```

*清理*

```
// 删除日志文件，清空队列信息
php start.php clean
```
<p class="danger">
  在守护模式中，如果需要使用数据库、redis 等连接，需要在各种回调函数中建立连接，否则可能会发生意想不到的错误。<br>
  建议使用单例模式，并在 [startWorker](#startworker) 中关闭之前建立的连接。
</p>


### 例子

#### 例子一

爬取糗事百科热门列表页，采用守护进程模式。在开始爬取前，我们需要一个队列，在这里使用框架中带有的内存队列。  
首先建立一个队列文件 queue.php，写入下列内容
``` php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
// 启动队列
\Beanbun\Queue\MemoryQueue::server();
```

建立爬虫文件 start.php，写入下列内容
``` php
<?php
use Beanbun\Beanbun;
use Beanbun\Lib\Helper;

require_once(__DIR__ . '/vendor/autoload.php');

$beanbun = new Beanbun;
$beanbun->name = 'qiubai';
$beanbun->count = 5;
$beanbun->seed = 'http://www.qiushibaike.com/';
$beanbun->max = 30;
$beanbun->logFile = __DIR__ . '/qiubai_access.log';
$beanbun->urlFilter = [
  '/http:\/\/www.qiushibaike.com\/8hr\/page\/(\d*)\?s=(\d*)/'
];
// 设置队列
$beanbun->setQueue('memory', [
  'host' => '127.0.0.1',
  'port' => '2207'
 ]);
$beanbun->afterDownloadPage = function($beanbun) {
  file_put_contents(__DIR__ . '/' . md5($beanbun->url), $beanbun->page);
};
$beanbun->start();
```

接下来在命令行中执行

```
$ php queue.php start
$ php start.php start
```

先启动队列进程，再启动爬虫。


## Beanbun 类

### 属性

Beanbun 对象实例化后，可以对对象的一些属性进行设置，这样爬虫爬取网页时，就会按照这些设置进行爬取。

#### name
<p class="tip">
  定义当前爬虫名称，string 类型，可选设置。
</p>

示例

``` php
$beanbun->name = 'demo';
```

#### daemonize
<p class="tip">
  定义当前爬虫运行方式，bool 类型，可选设置。<br>
  true 为守护进程模式，false 为普通模式。<br>
  CLI 模式下默认为 true，http请求下或CLI模式下没有`start`参数，默认为 false。
</p>

示例

``` php
$beanbun->daemonize = false;
```

#### count
<p class="tip">
  定义当前爬虫进程数，仅守护进程模式下有效。int 类型，可选设置，默认为 5。
</p>

示例

``` php
$beanbun->count = 10;
```

#### seed
<p class="tip">
  定义爬虫入口，string 或 array 类型，必选设置。
</p>

示例

``` php
$beanbun->seed = 'http://www.950.com/';
// or
$beanbun->seed = [
  'http://www.950d.com/',
  'http://www.950d.com/list-1.html',
  [
    'http://www.950d.com/list-2.html',
    [
      'timeout' => 10,
      'headers' => [
        'user-agent' => 'beanbun-spider',
      ]
    ]
  ]
];
```

#### urlFilter
<p class="tip">
  定义当前爬取网页url的正则表达式，符合表达式规则的 url 才会被加入队列，
  array 类型，可选设置。
</p>

示例

``` php
$beanbun->urlFilter = [
  '/http:\/\/www.950d.com\/list-(\d*).html/'
];
```

#### max
<p class="tip">
  定义当前爬虫最大抓取网页数量，如抓取达到此数则停止抓取，为0时不限制抓取数量，默认为0。int 类型，可选设置。<br>
</p>

示例

``` php
$beanbun->max = 100;
```

#### interval
<p class="tip">
  定义当前每个爬虫进程抓取网页的间隔时间，默认为1，最低为0.01。double 类型，可选设置。<br>
</p>

示例

``` php
$beanbun->interval = 0.1;
```

#### timeout
<p class="tip">
  定义爬虫全局下载单个网页超时时间，单位为秒，默认为5秒。int 类型，可选设置。<br>
  如果为单个网页单独设置了超时时间(如在 options 内)，则覆盖此项。
</p>

示例

``` php
$beanbun->timeout = 10;
```

#### userAgent
<p class="tip">
  定义爬虫全局下载单个网页 user-agent 属性，string 类型，可选设置。<br>
  `pc`时随机生成 PC 浏览器 user-agent，<br>
  `ios`时随机生成 iOS 浏览器 user-agent，<br>
  `android`时随机生成 android 浏览器 user-agent，<br>
  `mobile`时随机生成 iOS 或 android 浏览器 user-agent，<br>
  默认值为`pc`，如不为以上值，则直接使用定义值。如果为单个网页单独设置了 user-agent(如在 options 内)，则覆盖此项。
</p>

示例

``` php
$beanbun->userAgent = 'ios';
// or
$beanbun->userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:29.0) Gecko/20100101 Firefox/29.0';
```

#### logFile
<p class="tip">
  定义当前爬虫日志文件路径，仅守护进程模式下有效。string 类型，可选设置。
</p>

示例

``` php
$beanbun->logFile = __DIR__ . '/beanbun_access.log';
```

#### hooks
<p class="tip">
  定义爬虫执行钩子，也是爬虫每次爬取网页的执行顺序。array 类型，可选设置。<br>
  默认为['startWorkerHooks',  'beforeDownloadPageHooks', 'downloadPageHooks', 'afterDownloadPageHooks', 'discoverUrlHooks', 'afterDiscoverHooks', 'stopWorkerHooks',
  ]
</p>

示例

``` php
$beanbun->hooks = [
    'startWorkerHooks',
    'beforeDownloadPageHooks',
    'downloadPageHooks',
    'afterDownloadPageHooks',
    'discoverUrlHooks',
    'afterDiscoverHooks',
    'customHooks',
    'stopWorkerHooks',
];
```

#### id
<p class="tip">
  返回当前爬虫进程id，int 类型
</p>

示例

``` php
// 下载页面后写一条日志，记录进程下载页面成功
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->log("beanbun worker id {$beanbun->id} download page success!");
};
// 2017-01-01 00:00:00 beanbun worker id 0 download page success!
```

#### queue
<p class="tip">
  返回当前爬取网页的队列信息，array 类型
</p>

示例

``` php
// 下载页面后写一条日志，记录爬取页面地址
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->log("beanbun worker download {$beanbun->queue['url']} success!");
};
// 2017-01-01 00:00:00 beanbun worker download http://www.950d.com/ success!
```

#### url
<p class="tip">
  返回当前爬取网页url信息，string 类型
</p>

示例

``` php
// 下载页面后写一条日志，记录爬取页面地址
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->log("beanbun worker download {$beanbun->url} success!");
};
// 2017-01-01 00:00:00 beanbun worker GET http://www.950d.com/ success!
```

#### method
<p class="tip">
  返回当前爬取网页method信息，string 类型
</p>

示例

``` php
// 下载页面后写一条日志，记录爬取页面地址
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->log("beanbun worker {$beanbun->method} {$beanbun->queue['url']} success!");
};
// 2017-01-01 00:00:00 beanbun worker GET http://www.950d.com/ success!
```

#### options
<p class="tip">
  返回当前爬取网页的附加信息，array 类型
</p>

示例

``` php
// 下载页面后写一条日志，记录user-agent信息
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->log("user-agent is {beanbun->options['headers']['user-agent']}.");
};
// 2017-01-01 00:00:00 user-agent is Mozilla/5.0 (Windows NT 6.1; WOW64; rv:29.0) Gecko/20100101 Firefox/29.0.
```

#### page
<p class="tip">
  返回当前下载网页的网页内容，string 类型
</p>

示例

``` php
// 下载页面后把网页内容保存在文件中
$beanbun->afterDownloadPage = function($beanbun) {
  file_put_contents(__DIR__ . '/' . md5($beanbun->url), $beanbun->page);
};

// or 页面内容为JSON格式，转换成数组格式以备后用
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->page = json_decode($beanbun->page, true);
}
```

### 回调函数

回调函数是在 Beanbun 爬取并处理网页的过程中设置的一些系统钩子, 通过这些钩子可以完成一些特殊的处理逻辑。
<p class="warning">
  所有回调函数唯一参数为 Beanbun 对象实例本身。
</p>

下图是采集爬虫爬取并处理网页的流程图, 矩形方框中标识了采集爬虫运行过程中所使用的回调函数:

#### startWorker
<p class="tip">
  在每个爬虫进程启动时执行。(多进程时，每个进程都会执行一次，如果一个爬虫只想执行一次，可用 id 判断)
</p>

示例

``` php
// 启动爬虫进程后写一条日志，记录进程启动成功
$beanbun->startWorker = function($beanbun) {
  $beanbun->log("beanbun worker id {$beanbun->id} start success.");
};
// 2017-01-01 00:00:00 beanbun worker id 0 start success.
```

#### stopWorker
<p class="tip">
  在每个爬虫进程正常关闭时执行。
</p>

示例

``` php
// 关闭爬虫进程时写一条日志，记录进程关闭成功
$beanbun->stopWorker = function($beanbun) {
  $beanbun->log("beanbun worker id {$beanbun->id} stop success.");
};
// 2017-01-01 00:00:00 beanbun worker id 0 stop success.
```

#### beforeDownloadPage
<p class="tip">
  在每次爬取网页前时执行此回调。
</p>

示例

``` php
// 爬取网页前给网页请求加上代理
$beanbun->beforeDownloadPage = function($beanbun) {
  $beanbun->options['proxy'] = '123.123.123.123:88';
};
// or
functioin addProxy($beanbun) {
  $beanbun->options['proxy'] = '123.123.123.123:88';
}
$beanbun->beforeDownloadPage = 'addProxy';
```

#### downloadPage
<p class="tip">
  执行爬取，并把网页内容写入 page 属性。默认使用 Guzzle 进行网页爬取，如需替换爬取过程，则使用此回调。
</p>

示例

``` php
// 改成用 file_get_contents 进行爬取
$beanbun->downloadPage = function($beanbun) {
  $beanbun->page = file_get_contents($beanbun->url);
};
```

#### afterDownloadPage
<p class="tip">
  爬取网页后执行此回调。
</p>

示例

``` php
// 下载页面后把网页内容保存在文件中
$beanbun->afterDownloadPage = function($beanbun) {
  file_put_contents(__DIR__ . '/' . md5($beanbun->url), $beanbun->page);
};

// or 页面内容为JSON格式，转换成数组格式以备后用
$beanbun->afterDownloadPage = function($beanbun) {
  $beanbun->page = json_decode($beanbun->page, true);
}
```

#### discoverUrl
<p class="tip">
  爬取网页完毕后，把 page 内容中的链接加入队列。默认把发现的新 url 根据 urlFilter 进行过滤。如需替换发现新链接的方法，则使用此回调。
</p>

示例

``` php
// 输出下载页面后发现的所有链接，不加入队列
$beanbun->discoverUrl = function($beanbun) {
  $urls = Helper::getUrlByHtml($beanbun->page, $beanbun->url);
  print_r($urls);
};
```

#### afterDiscover
<p class="tip">
  发现新的 url 加入队列后，执行此回调。
</p>

示例

``` php
// 发现新的 url 加入队列后，在日志中记录当前队列长度。
$beanbun->afterDiscover = function($beanbun) {
  $beanbun->log("the queue number is {$beanbun->queue()->count()}.");
};
// 2017-01-01 00:00:00 the queue number is 28.
```

### 可用方法

#### start
<p class="tip">
  启动爬虫。无参数。如在守护进程模式下启动，进程将被阻塞，此行之后的代码不会被执行。
</p>

示例

``` php
$beanbun = new Beanbun;
$beanbun->seed = 'http://www.950d.com/';
$beanbun->start();
```

#### log
<p class="tip">
  记录一条日志，日志前会自动加入当前时间，日志尾部会自动加入换行符。<br>
  在守护进程模式下会写入日志文件，普通模式下会直接输出。参数为 string 类型。
</p>

示例

``` php
$beanbun->beforeDownloadPage = function($beanbun) {
  $beanbun->log('this is a log.');
}; 
// 2017-01-01 00:00:00 this is a log.
```

#### error
<p class="tip">
  立刻抛出一条 BeanbunException 异常。
  参数为 string 类型。参数可选。
</p>

示例

``` php
// 如下载网页内容小于 100 字符，则抛出异常，直接下载下一个网页。
$beanbun->afterDownloadPage = function($beanbun) {
  if (strlen($beanbun->page) < 100) {
    $beanbun->error();
  }
}; 
```

#### queue
<p class="tip">
  返回队列对象实例。
</p>

示例

``` php
// 如下载网页内容小于 100 字符，则把地址重新加入队列。
$beanbun->afterDownloadPage = function($beanbun) {
  if (strlen($beanbun->page) < 100) {
    $beanbun->queue()->add($beanbun->url);
  }
}; 
```

#### setQueue
<p class="tip">
  此方法用来设置队列，接受两个参数，第一个参数为回调函数，需返回一个队列对象，队列对象需继承自 Beanbun\Queue\QueueInterface 接口；第二个参数将作为参数传入第一个参数。
</p>

示例

``` php
// 使用框架带有的内存队列
$beanbun->setQueue('memory', [
  'host' => '127.0.0.1',
  'port' => '2207',
]); 
// $beanbun->queue() 将返回 new \Beanbun\Queue\MemoryQueue(['host' => '127.0.0.1', 'port' => '2207']);
// or
$beanbun->setQueue(function($args){
  return new \customQueue($args);
}, $args); 
// $beanbun->queue() 将返回 new \customQueue($args);
```

#### downloader
<p class="tip">
  返回下载器实例。因框架默认使用 Guzzle 作为下载器，所以默认返回 \GuzzleHttp\Client 实例。
</p>

示例

``` php
// 如下载网页内容小于 100 字符，则手动再重新下载一次。
$beanbun->afterDownloadPage = function($beanbun) {
  if (strlen($beanbun->page) < 100) {
    $request = $beanbun->downloader()->request($beanbun->method, $beanbun->url, $beanbun->options);
    $beanbun->page = $request->getBody();
  }
}; 
```

#### setDownloader
<p class="tip">
  此方法用来设置下载器，接受两个参数，第一个参数为回调函数，需返回下载器对象；第二个参数将作为参数传入第一个参数。
</p>

示例

``` php
// 使用 php-curl-class 作为下载器。
// $ composer require php-curl-class/php-curl-class
$beanbun->setDownloader(functioin(){
  return new \Curl\Curl;
}); 
// 修改下载网页过程
$beanbun->downloadPage = function($beanbun) {
  $method = strtolower($beanbun->method);
  $beanbun->page = $beanbun->downloader()->$method($beanbun->url);
};
```

#### middleware
<p class="tip">
  此方法可以加载中间件，中间件会在爬虫进程启动前执行。<br>
  可接受两个参数，第一个参数为可执行函数或对象，第二个参数为 string 类型。<br>
  如第一个参数为可执行函数，则直接执行；如为实例对象，则执行以第二个参数为名的方法，默认执行 handle 方法。<br>
  以上函数或方法均会传入一个参数，即 Beanbun 实例本身。
</p>

示例

``` php
$beanbun->middleware('customFunction');
// or
$beanbun->middleware(function($beanbun) {
  $beanbun->log('middleware loading is complete.');
});
// or
$beanbun->middleware(new \CustomMiddleware);
// or 
$beanbun->middleware(new \CustomMiddleware, 'load');
```

### 静态方法

#### timer
<p class="tip">
  在爬虫进程添加一个定时器。接受三个参数：<br>
  第一个参数为 double 类型，为定时器程序执行的间隔时间，单位为秒，最小0.01；<br>
  第二个参数为要执行的程序；<br>
  第三个参数作为参数传入第二个参数的程序。<br>
  方法返回一个定时器 id，int 类型，可用来销毁此定时器。
</p>

示例

``` php
use Beanbun\Beanbun;
// 每隔一天重新把首页加入队列
$beanbun->startWorker = function($beanbun) {
  if ($beanbun->id == 0) {
    Beanbun::timer(86400, function() use($beanbun){
      $beanbun->queue()->add('http://www.950d.com/');
    });
  } 
};
```

#### timerDel
<p class="tip">
  删除一个定时器，接受一个参数，int 类型，为定时器 id。
</p>

示例

``` php
use Beanbun\Beanbun;
$beanbun->beforDownloadPage = function($beanbun) {
  Beanbun::timerDel($timer_id);
};
```

## Queue 队列

如果爬虫在守护进程模式下运行，那么需要开启一个单独的队列来对需要爬取的 url 等数据进行管理。框架默认包含了内存和 Redis 两种队列来供使用。另外用户也可以编写自己的队列类，需要继承自 QueueInterface 接口。每个队列名称都会记录在总得名为 beanbun 的队列信息中。下面列出一些通用方法。

### 可用方法

#### add
<p class="tip">
  入队，即把待爬取的网址加入队列末尾。接受两个参数，<br>
  第一个参数 $url 为需要爬取的 url，string 类型，必填；<br>
  第二个参数 $options 为请求此 url 时可配置的参数，array 选填。<br>
  $options 参数：<br>
  $options['method']: 请求类型, String类型, 可不填, 默认值是 GET。<br>
  $options['headers']：请求的Headers，其中的 user-agent 默认使用 [Beanban::userAgent](#useragent)<br>
  更多其他参数可参考 Guzzle 的 [Request Options](http://docs.guzzlephp.org/en/latest/request-options.html)<br>
</p>

示例

``` php
// 取出页面中所有的 url 加入队列，均以 POST 来请求
$beanbun->discoverUrl = function ($beanbun) {
    $urls = Helper::getUrlByHtml($beanbun->page, $beanbun->url);
    foreach ($urls as $url) {
        $beanbun->queue()->add($url, [
            'method' => 'POST'
        ]);
    }
};
```

#### next
<p class="tip">
  出队，从队列首取出一条待爬取 url。返回类型为 array。
</p>

示例

``` php
// 取出下一条待爬数据输出后再放回队列末尾
$beanbun->afterDownloadPage = function ($beanbun) {
    $queue = $beanbun->queue()->next();
    print_r($queue);
    $beanbun->queue()->add($queue['url'], $queue['options']);
};
// Array
// (
//     [url] => http://www.beanbun.org/one.html
//     [options] => Array
//     (
//         [method] => POST
//     )
// )
```

#### count
<p class="tip">
  队列长度，即还有多少条待爬 url，默认最大为 10000。返回类型为 int。
</p>

示例

``` php
// 每次爬取完成后记录队列长度到日志
$beanbun->afterDownloadPage = function ($beanbun) {
    $beanbun->log("the queue number is {$beanbun->queue()->count()}.");
};
// 2017-01-01 00:00:00 the queue number is 28.
```

#### queued
<p class="tip">
  记录已爬取。接受一个参数，为队列信息。
</p>

示例

``` php
// 每次爬取完成后取出下一条记录为已爬取
$beanbun->afterDownloadPage = function ($beanbun) {
    $queue = $beanbun->queue()->next();
    $beanbun->queue()->queued($queue);
};
```

#### isQueued
<p class="tip">
  验证某 url 是否爬取过。返回类型为 bool。
</p>

示例

``` php
// 每次爬取前验证 url 是否爬取过
$beanbun->beforeDownloadPage = function ($beanbun) {
    if ($beanbun->queue()->isQueued($beanbun->queue)) {
        $beanbun->log('this url has crawled over.');
    } else {
        $beanbun->log('this url has not crawled.');
    }
};
// 2017-01-01 00:00:00 this url has not crawled.
```

#### queuedCount
<p class="tip">
  已爬取数量。返回类型为 int。
</p>

示例

``` php
// 每次爬取前验证 url 是否爬取过
$beanbun->beforeDownloadPage = function ($beanbun) {
    if ($beanbun->queue()->isQueued($beanbun->queue)) {
        $beanbun->log('this url has crawled over.');
    } else {
        $beanbun->log('this url has not crawled.');
    }
};
// 2017-01-01 00:00:00 this url has not crawled.
```

#### clean
<p class="tip">
  清空队列信息。并删除在 beanbun 队列中的信息。
</p>

示例

``` php
// 停止爬虫时，自动清空队列信息
$beanbun->stopWorker = function ($beanbun) {
    $beanbun->queue()->clean();
};
```

### Queue\MemoryQueue 类

框架提供的内存队列，使用方便，多个爬虫可共用一个内存队列服务端，除 Beanbun 外不依赖任何外部应用，但没有提供持久化方案，是在 [Workerman\GlobalData](http://doc3.workerman.net/component/global-data.html) 基础上做的二次开发。

#### 客户端 __construct
<p class="tip">
  队列构造方法。接受一个 $config 参数，array 类型，选填。<br>
  $config 参数：<br>
  $config['name']，string 类型，队列名称，默认使用 [Beanbun::name](#name)<br>
  $config['host']，string 类型，内存队列ip，默认 127.0.0.1<br>
  $config['port']，string 类型，内存队列端口，默认 2207<br>
  $config['algorithm']，string 类型，爬取方式，depth 为深度优先，breadth 为广度优先，默认为 depth
</p>

示例

``` php
$beanbun->setQueue('memory', [
    'host' => '127.0.0.1',
    'port' => '2217',
    'algorithm' => 'breadth'
]);
```

#### 服务端 server
<p class="tip">
  静态方法，开启内存队列服务端。接受两个参数。<br>
  第一个参数为服务端 ip，默认是 0.0.0.0<br>
  第二个参数为监听端口，默认是 2207<br>
  更多信息可参考 [Workerman\GlobalData Server](http://doc3.workerman.net/component/global-data-server.html)
</p>

示例

``` php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
// 启动队列，改成监听 2217 端口
\Beanbun\Queue\MemoryQueue::server('0.0.0.0', '2217');
```

### Queue\RedisQueue 类

使用 Redis 作为队列。

#### 客户端 __construct
<p class="tip">
  队列构造方法。接受一个 $config 参数，array 类型，选填。<br>
  $config 参数：<br>
  $config['name']，string 类型，队列名称，默认使用 [Beanbun::name](#name)<br>
  $config['host']，string 类型，内存队列ip，默认 127.0.0.1<br>
  $config['port']，string 类型，内存队列端口，默认 6379<br>
  $config['algorithm']，string 类型，爬取方式，depth 为深度优先，breadth 为广度优先，默认为 depth
</p>

示例

``` php
// 使用远程 redis 队列
$beanbun->setQueue('redis', [
    'host' => '123.123.123.123',
    'port' => '6379'
]);
```

## Lib\Helper 类

Helper 类中定义了一些辅助方法，帮助用户更方便的爬取网页

### 静态方法

#### getUrlByHtml
<p class="tip">
  返回网页中的完整链接。接受两个参数，第一个参数为网页 html，第二个参数为网页的 url
</p>

示例

``` php
use Beanbun\Lib\Helper;
$url = 'http://www.beanbun.org/1/2/demo.html';
$html =<<<STR
<ul>
  <li><a href="/one.html">第一个链接</a></li>
  <li><a href="two.html">第二个链接</a></li>
  <li><a href="../three.html">第三个链接</a></li>
</ul>
STR;

$urls = Helper::getUrlByHtml($html, $url);
print_r($urls);

// Array
// (
//     [0] => http://www.beanbun.org/one.html
//     [1] => http://www.beanbun.org/1/2/two.html
//     [2] => http://www.beanbun.org/1/three.html
// )
```

#### formatUrl
<p class="tip">
  根据相对地址和页面地址返回完整链接。接受两个参数，第一个参数为相对 uri，第二个参数为网页的 url
</p>

示例

``` php
use Beanbun\Lib\Helper;
$href = '/one.html';
$url = 'http://www.beanbun.org/1/2/demo.html';

echo Helper::formatUrl($href, $url);
// http://www.beanbun.org/one.html
```

#### randUserAgent
<p class="tip">
  根据设备随机获取user-agent，接受一个参数，即设备类型，有 pc/ios/android/mobile 可选
</p>

示例

``` php
use Beanbun\Lib\Helper;
echo Helper::randUserAgent('pc');
// Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)11

echo Helper::randUserAgent('mobile');
// Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) CriOS/34.0.1847.18 Mobile/11B554a Safari/9537.53444
```

## 数据库

框架提供的数据库操作类，修改自 [Medoo](http://medoo.in/)。支持 MySQL, MariaDB, MSSQL (Windows/Linux/UNIX), Oracle, SQLite, PostgreSQL, Sybase。依赖相应的 pdo 扩展。


### Lib\Db 类

#### config
<p class="tip">
  DbConnection 的配置参数，array 类型。
</p>

示例

``` php
use Beanbun\Lib\Db;
Db::$config = [
    'zhihu' => [
        'server' => '127.0.0.1',
        'port' => '3306',
        'username' => 'zhihu',
        'password' => 'xxxxxx',
        'database_name' => 'zhihu',
        'database_type' => 'mysql',
        'charset' => 'utf8',
    ],
    'qiushibaike' => [
        'server' => '127.0.0.1',
        'port' => '3306',
        'username' => 'qiushibaike',
        'password' => 'xxxxxx',
        'database_name' => 'qiushibaike',
        'database_type' => 'mysql',
        'charset' => 'utf8',
    ],
];
```

#### instance()
<p class="tip">
  根据名称返回 DbConnection 实例。接受一个参数，string 类型，即实例名称
</p>

示例

``` php
use Beanbun\Lib\Db;
Db::instance('zhihu');
```

#### close()
<p class="tip">
  根据名称关闭 DbConnection 连接。接受一个参数，string 类型，即实例名称
</p>

示例

``` php
use Beanbun\Lib\Db;
Db::close('zhihu');
```

#### closeAll()
<p class="tip">
  关闭所有 DbConnection 连接。
</p>

示例

``` php
use Beanbun\Lib\Db;
Db::closeAll();
```

### Lib\DbConnection 类

``` php
use Beanbun\Lib\Db;

// select
Db::instance('zhihu')->select("account", [
    "user_name",
    "email"
], [
    "user_id[>]" => 100
]);

// insert
Db::instance('zhihu')->insert("account", [
    "user_name" => "foo",
    "email" => "foo@bar.com",
    "age" => 25
]);

// update
Db::instance('zhihu')->update("account", [
    "type" => "user",
 
    // All age plus one
    "age[+]" => 1,
 
    // All level subtract 5
    "level[-]" => 5,
 
    // All score multiplied by 2
    "score[*]" => 2,
 
    // Like insert, you can assign the serialization
    "lang" => ["en", "fr", "jp", "cn", "de"],
 
    "(JSON) fav_lang" => ["en", "fr", "jp", "cn", "de"],
 
    // You can also assign # for using SQL functions
    "#uid" => "UUID()"
], [
    "user_id[<]" => 1000
]);

// delete
Db::instance('zhihu')->delete("account", [
    "AND" => [
        "type" => "business",
        "age[<]" => 18
    ]
]);

// 事务
Db::instance('zhihu')->action(function($database) {
    $database->insert("account", [
        "name" => "foo",
        "email" => "bar@abc.com"
    ]);
 
    $database->delete("account", [
        "user_id" => 2312
    ]);
 
    // If you want to  find something wrong, just return false to rollback the whole transaction.
    if ($database->has("post", ["user_id" => 2312]))
    {
        return false;
    }
});
```

更多用法，请参考 Medoo [文档](http://medoo.in/doc)。





