<?php
namespace Beanbun\Queue;

use Beanbun\Lib\Client;
use Beanbun\Lib\Server;
use Workerman\Worker;

class MemoryQueue implements QueueInterface
{
    public $globalData = null;
    public $maxQueueSize = 10000;
    public $maxQueuedCount = 0;
    public $bloomFilter = true;

    protected static $server = [];

    protected $name = '';
    protected $key = '';
    protected $queuedKey = '';
    protected $algorithm = 'depth';

    public static function server($ip = '0.0.0.0', $port = 2207)
    {
        global $argv;
        $key = "$ip:$port";
        if ($argv[1] == 'start') {
            if (isset(self::$server[$key])) {
                echo "\n";
                exit;
            }
            echo "Memory queue is starting...\n";
            fclose(STDOUT);
            $STDOUT = fopen(__DIR__ . '/server.log', "a");

            self::$server[$key] = '';
        } elseif ($argv[1] == 'stop') {
            unset(self::$server[$key]);
        }

        $globalServer = new Server($ip, $port);
        Worker::$daemonize = true;
        Worker::$stdoutFile = __DIR__ . '/server.log';
        @Worker::runAll();
    }

    public function __construct($config)
    {
        $this->globalData = new Client($config['host'] . ':' . $config['port']);

        $this->name = $config['name'];
        $this->key = $config['name'] . 'Queue';
        $this->queuedKey = $config['name'] . 'Queued';
        if (isset($config['algorithm'])) {
            $this->algorithm = $config['algorithm'] != 'breadth' ? 'depth' : 'breadth';
        }
        if (isset($config['bloomFilter']) && !$config['bloomFilter']) {
            $this->bloomFilter = false;
        }

        $this->globalData->add($this->key, []);
        if ($this->bloomFilter) {
            $this->globalData->bfNew($this->queuedKey, [400000, 14]);
        } else {
            $this->globalData->add($this->queuedKey, []);
        }

        $this->globalData->add('beanbun', []);

        if (!isset($this->globalData->beanbun[$this->name])) {
            $name = $this->name;
            $this->globalData->up('beanbun', function ($value) use ($name) {
                if (!in_array($name, $value)) {
                    $value[] = $name;
                }
                return $value;
            });
        }
    }

    public function add($url, $options = [])
    {
        if ($this->maxQueueSize != 0 && $this->count() >= $this->maxQueueSize) {
            return;
        }

        $queue = [
            'url' => $url,
            'options' => $options,
        ];

        if ($this->isQueued($queue)) {
            return;
        }

        $this->globalData->push($this->key, $queue);
    }

    public function next()
    {
        if ($this->algorithm == 'depth') {
            $queue = $this->globalData->shift($this->key);
        } else {
            $queue = $this->globalData->pop($this->key);
        }

        if ($this->isQueued($queue)) {
            return $this->next();
        } else {
            return $queue;
        }
    }

    public function count()
    {
        return $this->globalData->count($this->key);
    }

    public function queued($queue)
    {
        if ($this->bloomFilter) {
            $this->globalData->bfAdd($this->queuedKey, md5(serialize($queue)));
        } else {
            $this->globalData->push($this->queuedKey, serialize($queue));
        }
    }

    public function isQueued($queue)
    {
        if ($this->bloomFilter) {
            return $this->globalData->bfIn($this->queuedKey, md5(serialize($queue)));
        } else {
            return in_array(serialize($queue), $this->globalData->{$this->queuedKey});
        }
    }

    public function queuedCount()
    {
        if ($this->bloomFilter) {
            return 0;
        } else {
            return $this->globalData->count($this->queuedKey);
        }
    }

    public function clean()
    {
        unset($this->globalData->{$this->key});
        unset($this->globalData->{$this->queuedKey});
        $name = $this->name;
        $this->globalData->up('beanbun', function ($value) use ($name) {
            $key = array_search($name, $value);
            if ($key !== false) {
                unset($value[$key]);
            }
            return $value;
        });
    }
}
