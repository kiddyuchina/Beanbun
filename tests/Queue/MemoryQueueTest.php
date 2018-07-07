<?php
namespace Beanbun\Tests\Queue;

require __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Beanbun\Queue\MemoryQueue;

class MemoryQueueTest extends TestCase
{
    public function testConnectQueue()
    {
        $queue = new MemoryQueue([
            'host' => '0.0.0.0',
            'port' => 2207,
            'name' => 'phpunitTest',
            'bloomFilter' => false
        ]);

        $this->assertInstanceOf(MemoryQueue::class, $queue);

        return $queue;
    }

    /**
     * @depends testConnectQueue
     */
    public function testEmpty(MemoryQueue $queue)
    {
        $this->assertEquals(0, $queue->count());

        return $queue;
    }

    /**
     * @depends testEmpty
     */
    public function testAdd(MemoryQueue $queue)
    {
        $queue->add('http://www.baidu.com/');
        $this->assertEquals(1, $queue->count());

        $queue->add('http://www.google.com/');
        $this->assertEquals(2, $queue->count());
    }

    /**
     * @depends testConnectQueue
     */
    public function testClean(MemoryQueue $queue)
    {
        $queue->clean();
        $this->assertEquals(0, $queue->count());
    }
}
