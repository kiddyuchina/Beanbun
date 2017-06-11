<?php
namespace Beanbun;

use Beanbun\Exception\BeanbunException;
use Beanbun\Lib\Helper;
use Exception;
use GuzzleHttp\Client;
use Workerman\Lib\Timer;
use Workerman\Worker;

class Beanbun
{
    const VERSION = '1.0.4';

    public $id = null;
    public $name = null;
    public $max = 0;
    public $seed = [];
    public $daemonize = true;
    public $urlFilter = [];
    public $interval = 5;
    public $timeout = 5;
    public $userAgent = 'pc';
    public $logFile = '';
    public $commands = [];

    public $queue = '';
    public $url = '';
    public $method = '';
    public $options = [];
    public $page = '';

    public $startWorker = '';
    public $beforeDownloadPage = '';
    public $downloadPage = '';
    public $afterDownloadPage = '';
    public $discoverUrl = '';
    public $afterDiscover = '';
    public $stopWorker = '';
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

    protected $queues = null;
    protected $downloader = null;
    protected $worker = null;
    protected $timer_id = null;
    protected $queueFactory = null;
    protected $queueArgs = [];
    protected $downloaderFactory = null;
    protected $downloaderArgs = [];
    protected $logFactory = null;

    public static function timer($interval, $callback, $args = [], $persistent = true)
    {
        return Timer::add($interval, $callback, $args, $persistent);
    }

    public static function timerDel($time_id)
    {
        Timer::del($time_id);
    }

    public static function run()
    {
        @Worker::runAll();
    }

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
                $STDOUT = fopen($this->logFile, "a");
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
            \Beanbun\Lib\Db::closeAll();

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
        $version_ok = $pcntl_loaded = $posix_loaded = true;
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

    public function initHooks()
    {
        $this->startWorkerHooks[] = function ($beanbun) {
            $beanbun->id = $beanbun->worker->id;
            $beanbun->log("Beanbun worker {$beanbun->id} is starting ...");
        };

        if ($this->startWorker) {
            $this->startWorkerHooks[] = $this->startWorker;
        }

        $this->startWorkerHooks[] = function ($beanbun) {
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
            $this->afterDiscoverHooks[] = function ($beanbun) {
                $beanbun->queue()->queued($beanbun->queue);
            };
        }

        if ($this->stopWorker) {
            $this->stopWorkerHooks[] = $this->stopWorker;
        }

        if (!$this->exceptionHandler) {
            $this->exceptionHandler = [$this, 'defaultExceptionHandler'];
        }
    }

    // 爬虫进程
    public function onWorkerStart($worker)
    {
        foreach ($this->startWorkerHooks as $hook) {
            call_user_func($hook, $this);
        }
    }

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
                return new \Beanbun\Queue\MemoryQueue($args);
            };
        } elseif ($callback == 'redis') {
            $this->queueFactory = function ($args) {
                return new \Beanbun\Queue\RedisQueue($args);
            };
        } else {
            $this->queueFactory = $callback;
        }

        $this->queueArgs = $args;
    }

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
            $this->log($e->getMessage());
            if ($this->daemonize) {
                $this->queue()->add($this->queue['url'], $this->queue['options']);
            } else {
                $this->seed[] = $this->queue;
            }
            call_user_func($this->exceptionHandler, $e);
        }

        $this->queue = '';
        $this->url = '';
        $this->method = '';
        $this->page = '';
        $this->options = [];
    }

    public function onWorkerStop($worker)
    {
        foreach ($this->stopWorkerHooks as $hook) {
            call_user_func($hook, $this);
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
        }

        $options = array_merge([
            'headers' => [],
            'reserve' => true,
            'timeout' => $this->timeout,
        ], (array) $queue['options']);

        if ($this->daemonize && $options['reserve'] && $this->queue()->isQueued($queue)) {
            $this->error();
        }

        $this->url = $queue['url'];
        $this->method = isset($options['method']) ? $options['method'] : 'GET';
        $this->options = $options;
        if (!isset($this->options['headers']['User-Agent'])) {
            $this->options['headers']['User-Agent'] = Helper::randUserAgent($this->userAgent);
        }
    }

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

    public function middleware($middleware, $action = 'handle')
    {
        if (is_object($middleware)) {
            $middleware->$action($this);
        } else {
            call_user_func($middleware, $this);
        }
    }
}
