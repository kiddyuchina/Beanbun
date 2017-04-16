<?php
namespace Beanbun\Queue;

class RedisQueue implements QueueInterface
{
    public $redis = null;
    public $config = [];
    public $maxQueueSize = 10000;
    public $maxQueuedCount = 0;

    protected $name = '';
    protected $key = '';
    protected $queuedKey = '';
    protected $algorithm = 'depth';

    public function __construct($config)
    {
        $this->config = $config;
        $this->name = $config['name'];
        $this->key = $config['name'] . 'Queue';
        $this->queuedKey = $config['name'] . 'Queued';
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
            return ;
        }

        $queue = serialize([
            'url' => $url,
            'options' => $options,
        ]);

        if ($this->isQueued($queue)) {
            return ;
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
        $this->getInstance()->sAdd($this->queuedKey, serialize($queue));
    }

    public function isQueued($queue)
    {
        return $this->getInstance()->sIsMember($this->queuedKey, $queue);
    }

    public function queuedCount()
    {
        return $this->getInstance()->sSize($this->queuedKey);
    }

    public function clean()
    {
        $this->getInstance()->delete($this->key);
        $this->getInstance()->delete($this->queuedKey);
        $this->getInstance()->sRem('beanbun', $this->name);
    }
}
