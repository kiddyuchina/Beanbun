<?php
namespace Beanbun\Lib;

use Workerman\Worker;

/**
 * Global data server.
 */
class Server
{
    /**
     * Worker instance.
     * @var worker
     */
    protected $_worker = null;

    /**
     * All data.
     * @var array
     */
    protected $_dataArray = array();

    /**
     * Construct.
     * @param string $ip
     * @param int $port
     */
    public function __construct($ip = '0.0.0.0', $port = 2207)
    {
        $worker = new Worker("frame://$ip:$port");
        $worker->count = 1;
        $worker->name = 'beanbunDataServer';
        $worker->onMessage = array($this, 'onMessage');
        $worker->reloadable = false;
        $this->_worker = $worker;
    }

    /**
     * onMessage.
     * @param TcpConnection $connection
     * @param string $buffer
     */
    public function onMessage($connection, $buffer)
    {
        if ($buffer === 'ping') {
            return;
        }
        $data = unserialize($buffer);
        if (!$buffer || !isset($data['cmd']) || !isset($data['key'])) {
            return $connection->close(serialize('bad request'));
        }
        $cmd = $data['cmd'];
        $key = $data['key'];
        switch ($cmd) {
            case 'get':
                if (!isset($this->_dataArray[$key])) {
                    return $connection->send('N;');
                }
                return $connection->send(serialize($this->_dataArray[$key]));
                break;
            case 'set':
                $this->_dataArray[$key] = $data['value'];
                $connection->send('b:1;');
                break;
            case 'add':
                if (isset($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                $this->_dataArray[$key] = $data['value'];
                return $connection->send('b:1;');
                break;
            case 'increment':
                if (!isset($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                if (!is_numeric($this->_dataArray[$key])) {
                    $this->_dataArray[$key] = 0;
                }
                $this->_dataArray[$key] = $this->_dataArray[$key] + $data['step'];
                return $connection->send(serialize($this->_dataArray[$key]));
                break;
            case 'cas':
                if (isset($this->_dataArray[$key]) && md5(serialize($this->_dataArray[$key])) === $data['md5']) {
                    $this->_dataArray[$key] = $data['value'];
                    return $connection->send('b:1;');
                }
                $connection->send('b:0;');
                break;
            case 'push':
                if (!isset($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                if (!is_array($this->_dataArray[$key])) {
                    $this->_dataArray[$key] = array();
                }
                return $connection->send(serialize(array_push($this->_dataArray[$key], $data['value'])));
                break;
            case 'shift':
                if (!isset($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                if (!is_array($this->_dataArray[$key])) {
                    $this->_dataArray[$key] = array();
                }
                return $connection->send(serialize(array_shift($this->_dataArray[$key])));
                break;
            case 'pop':
                if (!isset($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                if (!is_array($this->_dataArray[$key])) {
                    $this->_dataArray[$key] = array();
                }
                return $connection->send(serialize(array_pop($this->_dataArray[$key])));
                break;
            case 'count':
                if (!isset($this->_dataArray[$key]) || !is_array($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                return $connection->send(serialize(count($this->_dataArray[$key])));
                break;
            case 'rand':
                if (!isset($this->_dataArray[$key]) || !is_array($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                return $connection->send(serialize($this->_dataArray[$key][array_rand($this->_dataArray[$key])]));
                break;
            case 'in':
                if (!isset($this->_dataArray[$key]) || !is_array($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                return $connection->send(serialize(in_array($data['value'], $this->_dataArray[$key])));
                break;
            case 'delete':
                unset($this->_dataArray[$key]);
                $connection->send('b:1;');
                break;
            case 'bfNew':
                if (isset($this->_dataArray[$key])) {
                    return $connection->send('b:0;');
                }
                $m = isset($data['value'][0]) ? $data['value'][0] : 40000;
                $k = isset($data['value'][1]) ? $data['value'][1] : 14;
                $this->_dataArray[$key] = new BloomFilter($m, $k);
                return $connection->send('b:1;');
                break;
            case 'bfAdd':
                if (!isset($this->_dataArray[$key]) || !($this->_dataArray[$key] instanceof BloomFilter)) {
                    return $connection->send('b:0;');
                }
                $this->_dataArray[$key]->add($data['value']);
                return $connection->send('b:1;');
                break;
            case 'bfIn':
                if (!isset($this->_dataArray[$key]) || !($this->_dataArray[$key] instanceof BloomFilter)) {
                    return $connection->send('b:0;');
                }
                return $connection->send(serialize($this->_dataArray[$key]->maybeInSet($data['value'])));
                break;
            default:
                $connection->close(serialize('bad cmd ' . $cmd));
        }
    }
}
