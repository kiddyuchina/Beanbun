<?php
namespace Beanbun\Queue;

class RedisQueue implements QueueInterface
{
    public $redis = null;
    public $config = [];
    public $maxQueueSize = 10000;
    public $maxQueuedCount = 0;
    public $bloomFilter = true;

    protected $name = '';
    protected $key = '';
    protected $queuedKey = '';
    protected $algorithm = 'depth';

    protected $bfSize;
    protected $bfHashCount;

    public function __construct($config)
    {
        $this->config = $config;
        $this->name = $config['name'];
        $this->key = $config['name'] . 'Queue';
        $this->queuedKey = $config['name'] . 'Queued';
        $this->bfSize = isset($config['size']) ? $config['size'] : 400000;
        $this->bfHashCount = isset($config['hash_count']) ? $config['hash_count'] : 14;
        if (isset($config['bloomFilter']) && !$config['bloomFilter']) {
            $this->bloomFilter = false;
        }

        if (isset($config['algorithm'])) {
            $this->algorithm = $config['algorithm'] != 'breadth' ? 'depth' : 'breadth';
        }
        $this->getInstance()->sAdd('beanbun', $this->name);
    }

    public function getInstance()
    {
        if (!$this->redis) {
            $this->redis = new \Redis();
            $this->redis->connect($this->config['host'], $this->config['port']);
        }
        return $this->redis;
    }

    public function add($url, $options = [])
    {
        if ($this->maxQueueSize != 0 && $this->count() >= $this->maxQueueSize) {
            return;
        }

        $queue = serialize([
            'url' => $url,
            'options' => $options,
        ]);

        if ($this->isQueued($queue)) {
            return;
        }

        $this->getInstance()->rPush($this->key, $queue);
    }

    public function next()
    {
        if ($this->algorithm == 'depth') {
            $queue = $this->getInstance()->lPop($this->key);
        } else {
            $queue = $this->getInstance()->rPop($this->key);
        }

        if ($this->isQueued($queue)) {
            return $this->next();
        } else {
            return unserialize($queue);
        }
    }

    public function count()
    {
        return $this->getInstance()->lSize($this->key);
    }

    public function queued($queue)
    {
        if ($this->bloomFilter) {
            $this->bfAdd(md5(serialize($queue)));
        } else {
            $this->getInstance()->sAdd($this->queuedKey, serialize($queue));
        }
    }

    public function isQueued($queue)
    {
        if ($this->bloomFilter) {
            return $this->bfHas(md5($queue));
        } else {
            return $this->getInstance()->sIsMember($this->queuedKey, $queue);
        }
    }

    public function queuedCount()
    {
        if ($this->bloomFilter) {
            return 0;
        } else {
            return $this->getInstance()->sSize($this->queuedKey);
        }
    }

    public function clean()
    {
        $this->getInstance()->delete($this->key);
        $this->getInstance()->delete($this->queuedKey);
        $this->getInstance()->sRem('beanbun', $this->name);
    }

    protected function bfAdd($item)
    {
        $index = 0;
        $pipe = $this->getInstance()->pipeline();
        while ($index < $this->bfHashCount) {
            $crc = $this->hash($item, $index);
            $pipe->setbit($this->queuedKey, $crc, 1);
            $index++;
        }
        $pipe->exec();
    }

    protected function bfHas($item)
    {
        $index = 0;
        $pipe = $this->getInstance()->pipeline();
        while ($index < $this->bfHashCount) {
            $crc = $this->hash($item, $index);
            $pipe->getbit($this->queuedKey, $crc);
            $index++;
        }
        $result = $pipe->exec();
        return !in_array(0, $result);
    }

    protected function hash($item, $index)
    {
        return abs(crc32(md5('m' . $index . $item))) % $this->bfSize;
    }
}
