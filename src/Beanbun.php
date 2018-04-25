<?php

namespace Beanbun;

use Beanbun\Exception\BeanbunException;
use Beanbun\Lib\Db;
use Beanbun\Lib\Helper;
use Beanbun\Queue\MemoryQueue;
use Beanbun\Queue\RedisQueue;
use Exception;
use GuzzleHttp\Client;
use Workerman\Lib\Timer;
use Workerman\Worker;

/**
 * Class Beanbun
 *
 * @package Beanbun
 */
class Beanbun
{
    const VERSION = '1.0.4';

    public $id = NULL;
    public $name = NULL;

    /**
     * @var int 最大抓取网页数量，为 0 时不限制抓取数量。
     */
    public $max = 0;

    /**
     * @var int (守护进程模式)爬虫进程数，默认为 5。
     */
    public $count = 5;

    /**
     * @var array
     */
    public $seed = [];

    /**
     * @var bool 是否守护进程方式运行
     */
    public $daemonize = TRUE;

    /**
     * @var array 采集地址入列规则
     */
    public $urlFilter = [];

    /**
     * @var int 每个爬虫进程抓取网页的间隔时间，默认为1，最低为0.01
     */
    public $interval = 5;

    /**
     * @var int 爬虫全局下载单个网页超时时间，秒
     */
    public $timeout = 5;

    /**
     * @var string [pc, ios, android, mobile]
     */
    public $userAgent = 'pc';

    /**
     * @var mixed|string 当前爬虫日志文件路径，仅守护进程模式下有效
     */
    public $logFile = '';
    public $commands = [];

    /**
     * 返回当前爬取网页的队列信息，array 类型
     *
     * @var array {url, options}
     */
    public $queue = '';

    /**
     * @var string url信息
     */
    public $url = '';

    /**
     * @var string 请求方法
     */
    public $method = '';

    /**
     * @var array 附加信息
     */
    public $options = [];

    /**
     * @var string 下载网页的网页内容
     */
    public $page = '';

    /**
     * 爬虫进程启动时执行。
     * 多进程时，每个进程都会执行一次，
     * 如果一个爬虫只想执行一次，可用 id 判断
     *
     * @var \Closure
     */
    public $startWorker = '';

    /**
     * 在每次爬取网页前时执行此回调。
     *
     * @var \Closure
     */
    public $beforeDownloadPage = '';

    /**
     * 执行爬取，并把网页内容写入 page 属性。
     * 默认使用 Guzzle 进行网页爬取，如需替换爬取过程，则使用此回调。
     *
     * @var \Closure
     */
    public $downloadPage = '';

    /**
     * 爬取网页后执行此回调。
     *
     * @var \Closure
     */
    public $afterDownloadPage = '';

    /**
     * 爬取网页完毕后，把 page 内容中的链接加入队列。
     * 默认把发现的新 url 根据 urlFilter 进行过滤。
     * 如需替换发现新链接的方法，则使用此回调。
     *
     * @var \Closure
     */
    public $discoverUrl = '';

    /**
     * 发现新的 url 加入队列后，执行此回调
     *
     * @var \Closure
     */
    public $afterDiscover = '';

    /**
     * 在每个爬虫进程正常关闭时执行。
     *
     * @var \Closure
     */
    public $stopWorker = '';

    /**
     * @var string
     */
    public $exceptionHandler = '';

    public $hooks = [
        'startWorkerHooks',
        'beforeDownloadPageHooks',
        'downloadPageHooks',
        'afterDownloadPageHooks',
        'discoverUrlHooks',
        'afterDiscoverHooks',
        'stopWorkerHooks',
    ];
    public $startWorkerHooks = [];
    public $beforeDownloadPageHooks = [];
    public $downloadPageHooks = [];
    public $afterDownloadPageHooks = [];
    public $discoverUrlHooks = [];
    public $afterDiscoverHooks = [];
    public $stopWorkerHooks = [];

    protected $queues = NULL;
    /** @var Client|mixed 下载器 */
    protected $downloader = NULL;
    /** @var Worker 任务进程 */
    protected $worker = NULL;
    protected $timer_id = NULL;
    protected $queueFactory = NULL;
    protected $queueArgs = [];
    protected $downloaderFactory = NULL;
    protected $downloaderArgs = [];
    protected $logFactory = NULL;

    /**
     * @param       $interval
     * @param       $callback
     * @param array $args
     * @param bool  $persistent
     *
     * @return int
     */
    public static function timer($interval, $callback, $args = [], $persistent = TRUE)
    {
        return Timer::add($interval, $callback, $args, $persistent);
    }

    /**
     * @param $time_id
     */
    public static function timerDel($time_id)
    {
        Timer::del($time_id);
    }

    public static function run()
    {
        @Worker::runAll();
    }

    /**
     * Beanbun constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        global $argv;
        $this->commands = $argv;
        $this->name = isset($config['name'])
        ? $config['name']
        : current(explode('.', $this->commands[0]));
        $this->logFile = isset($config['logFile']) ? $config['logFile'] : __DIR__ . '/' . $this->name . '_access.log';
        $this->setQueue();
        $this->setDownloader();
        $this->setLog();
    }

    public function command()
    {
        switch ($this->commands[1]) {
            case 'start':
                foreach ((array) $this->seed as $url) {
                    if (is_string($url)) {
                        $this->queue()->add($url);
                    } elseif (is_array($url)) {
                        $this->queue()->add($url[0], $url[1]);
                    }
                }
                $this->queues = null;
                echo "Beanbun is starting...\n";
                fclose(STDOUT);
                @$STDOUT = fopen($this->logFile, "a");
                break;
            case 'clean':
                $this->queue()->clean();
                unlink($this->logFile);
                die();
                break;
            case 'stop':
                break;
            default:
                break;
        }
    }

    // 执行爬虫
    public function start()
    {
        if (!isset($this->commands[1])) {
            $this->daemonize = false;
        }

        if ($this->daemonize) {
            $this->check();

            $worker = new Worker;
            $worker->count = $this->count;
            $worker->name = $this->name;
            $worker->onWorkerStart = [$this, 'onWorkerStart'];
            $worker->onWorkerStop = [$this, 'onWorkerStop'];
            $this->worker = $worker;

            Worker::$daemonize = true;
            Worker::$stdoutFile = $this->logFile;
            Db::closeAll();

            $this->queueArgs['name'] = $this->name;
            $this->initHooks();
            $this->command();

            self::run();
        } else {
            $this->initHooks();
            $this->seed = (array) $this->seed;
            while (count($this->seed)) {
                $this->crawler();
            }
        }
    }

    public function check()
    {
        $error = false;
        $text = '';
//        $version_ok = $pcntl_loaded = $posix_loaded = true;
        if (!version_compare(phpversion(), "5.3.3", ">=")) {
            $text .= "PHP Version >= 5.3.3                 \033[31;40m [fail] \033[0m\n";
            $error = true;
        }

        if (!in_array("pcntl", get_loaded_extensions())) {
            $text .= "Extension posix check                \033[31;40m [fail] \033[0m\n";
            $error = true;
        }

        if (!in_array("posix", get_loaded_extensions())) {
            $text .= "Extension posix check                \033[31;40m [fail] \033[0m\n";
            $error = true;
        }

        $check_func_map = array(
            "stream_socket_server",
            "stream_socket_client",
            "pcntl_signal_dispatch",
        );

        if ($disable_func_string = ini_get("disable_functions")) {
            $disable_func_map = array_flip(explode(",", $disable_func_string));
        }

        foreach ($check_func_map as $func) {
            if (isset($disable_func_map[$func])) {
                $text .= "\033[31;40mFunction " . implode(', ', $check_func_map) . "may be disabled. Please check disable_functions in php.ini\033[0m\n";
                $error = true;
                break;
            }
        }

        if ($error) {
            echo $text;
            exit;
        }
    }

    public function shutdown()
    {
        $master_pid = is_file(Worker::$pidFile) ? file_get_contents(Worker::$pidFile) : 0;
        $master_pid && posix_kill($master_pid, SIGINT);
        $timeout = 5;
        $start_time = time();
        while (1) {
            $master_is_alive = $master_pid && posix_kill($master_pid, 0);
            if ($master_is_alive) {
                if (time() - $start_time >= $timeout) {
                    exit;
                }
                usleep(10000);
                continue;
            }
            exit(0);
            break;
        }
    }

    public function initHooks()
    {
        $this->startWorkerHooks[] = function (Beanbun $beanbun) {
            $beanbun->id = $beanbun->worker->id;
            $beanbun->log("Beanbun worker {$beanbun->id} is starting ...");
        };

        if ($this->startWorker) {
            $this->startWorkerHooks[] = $this->startWorker;
        }

        $this->startWorkerHooks[] = function (Beanbun $beanbun) {
            $beanbun->queue()->maxQueueSize = $beanbun->max;
            $beanbun->timer_id = Beanbun::timer($beanbun->interval, [$beanbun, 'crawler']);
        };

        $this->beforeDownloadPageHooks[] = [$this, 'defaultBeforeDownloadPage'];

        if ($this->beforeDownloadPage) {
            $this->beforeDownloadPageHooks[] = $this->beforeDownloadPage;
        }

        if ($this->downloadPage) {
            $this->downloadPageHooks[] = $this->downloadPage;
        } else {
            $this->downloadPageHooks[] = [$this, 'defaultDownloadPage'];
        }

        if ($this->afterDownloadPage) {
            $this->afterDownloadPageHooks[] = $this->afterDownloadPage;
        }

        if ($this->discoverUrl) {
            $this->discoverUrlHooks[] = $this->discoverUrl;
        } elseif ($this->daemonize) {
            $this->discoverUrlHooks[] = [$this, 'defaultDiscoverUrl'];
        }

        if ($this->afterDiscover) {
            $this->afterDiscoverHooks[] = $this->afterDiscover;
        }

        if ($this->daemonize) {
            $this->afterDiscoverHooks[] = function (Beanbun $beanbun) {
                if ($beanbun->options['reserve'] == false) {
                    $beanbun->queue()->queued($beanbun->queue);
                }
            };
        }

        if ($this->stopWorker) {
            $this->stopWorkerHooks[] = $this->stopWorker;
        }

        if (!$this->exceptionHandler) {
            $this->exceptionHandler = [$this, 'defaultExceptionHandler'];
        }
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStart(Worker $worker)
    {
        foreach ($this->startWorkerHooks as $hook) {
            call_user_func($hook, $this, $worker);
        }
    }

    /**
     * 获取队列实例
     *
     * @return MemoryQueue|RedisQueue
     */
    public function queue()
    {
        if ($this->queues == null) {
            $this->queues = call_user_func($this->queueFactory, $this->queueArgs);
        }
        return $this->queues;
    }

    public function setQueue($callback = null, $args = [
        'host' => '127.0.0.1',
        'port' => '2207',
    ]) {
        if ($callback === 'memory' || $callback === null) {
            $this->queueFactory = function ($args) {
                return new MemoryQueue($args);
            };
        } elseif ($callback == 'redis') {
            $this->queueFactory = function ($args) {
                return new RedisQueue($args);
            };
        } else {
            $this->queueFactory = $callback;
        }

        $this->queueArgs = $args;
    }

    /**
     * @return Client
     */
    public function downloader()
    {
        if ($this->downloader === null) {
            $this->downloader = call_user_func($this->downloaderFactory, $this->downloaderArgs);
        }
        return $this->downloader;
    }

    public function setDownloader($callback = null, $args = [])
    {
        if ($callback === null) {
            $this->downloaderFactory = function ($args) {
                return new Client($args);
            };
        } else {
            $this->downloaderFactory = $callback;
        }
        $this->downloaderArgs = $args;
    }

    public function log($msg)
    {
        call_user_func($this->logFactory, $msg, $this);
    }

    public function setLog($callback = null)
    {
        $this->logFactory = $callback === null
        ? function ($msg, $beanbun) {
            echo date('Y-m-d H:i:s') . " {$beanbun->name} : $msg\n";
        }
        : $callback;
    }

    /**
     * @param mixed $msg
     *
     * @throws BeanbunException
     */
    public function error($msg = null)
    {
        throw new BeanbunException($msg);
    }

    public function crawler()
    {
        try {
            $allHooks = $this->hooks;
            array_shift($allHooks);
            array_pop($allHooks);

            foreach ($allHooks as $hooks) {
                foreach ($this->$hooks as $hook) {
                    call_user_func($hook, $this);
                }
            }
        } catch (Exception $e) {
            call_user_func($this->exceptionHandler, $e);
        }

        $this->queue = '';
        $this->url = '';
        $this->method = '';
        $this->page = '';
        $this->options = [];
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStop(Worker $worker)
    {
        foreach ($this->stopWorkerHooks as $hook) {
            call_user_func($hook, $this, $worker);
        }
    }

    public function defaultExceptionHandler(Exception $e)
    {
        if ($e instanceof BeanbunException) {
            if ($e->getMessage()) {
                $this->log($e->getMessage());
            }
        } elseif ($e instanceof Exception) {
            $this->log($e->getMessage());
            if ($this->daemonize) {
                $this->queue()->add($this->queue['url'], $this->queue['options']);
            } else {
                $this->seed[] = $this->queue;
            }
        }
    }

    /**
     * @throws BeanbunException
     */
    public function defaultBeforeDownloadPage()
    {
        if ($this->daemonize) {
            if ($this->max > 0 && $this->queue()->queuedCount() >= $this->max) {
                $this->log("Download to the upper limit, Beanbun worker {$this->id} stop downloading.");
                self::timerDel($this->timer_id);
                $this->error();
            }

            $this->queue = $queue = $this->queue()->next();
        } else {
            $queue = array_shift($this->seed);
        }

        if (is_null($queue) || !$queue) {
            sleep(30);
            $this->error();
        }

        if (!is_array($queue)) {
            $this->queue = $queue = [
                'url' => $queue,
                'options' => [],
            ];
        } else{
            $this->queue = $queue;
        }

        $options = array_merge([
            'headers' => $this->options['headers'] ?: [],
            'reserve' => false,
            'timeout' => $this->timeout,
        ], (array) $queue['options']);

        if ($this->daemonize && !$options['reserve'] && $this->queue()->isQueued($queue)) {
            $this->error();
        }

        $this->url = $queue['url'];
        $this->method = isset($options['method']) ? $options['method'] : 'GET';
        $this->options = $options;
        if (!isset($this->options['headers']['User-Agent'])) {
            $this->options['headers']['User-Agent'] = Helper::randUserAgent($this->userAgent);
        }
    }

    /**
     * @throws BeanbunException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function defaultDownloadPage()
    {
        $response = $this->downloader()->request($this->method, $this->url, $this->options);
        $this->page = $response->getBody();
        if ($this->page) {
            $worker_id = isset($this->id) ? $this->worker->id : '';
            $this->log("Beanbun worker {$worker_id} download {$this->url} success.");
        } else {
            $this->error();
        }
    }

    /**
     * @throws BeanbunException
     */
    public function defaultDiscoverUrl()
    {
        $countUrlFilter = count($this->urlFilter);
        if ($countUrlFilter === 1 && !$this->urlFilter[0]) {
            $this->error();
        }

        $urls = Helper::getUrlByHtml($this->page, $this->url);

        if ($countUrlFilter > 0) {
            foreach ($urls as $url) {
                foreach ($this->urlFilter as $urlPattern) {
                    if (preg_match($urlPattern, $url)) {
                        $this->queue()->add($url);
                    }
                }
            }
        } else {
            foreach ($urls as $url) {
                $this->queue()->add($url);
            }
        }
    }

    /**
     * @param        $middleware
     * @param string $action
     */
    public function middleware($middleware, $action = 'handle')
    {
        if (is_object($middleware)) {
            $middleware->$action($this);
        } else {
            call_user_func($middleware, $this);
        }
    }
}
