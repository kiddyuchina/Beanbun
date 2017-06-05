<?php
use Beanbun\Beanbun;
use JonnyW\PhantomJs\Client;

require_once(__DIR__ . '/vendor/autoload.php');

$beanbun = new Beanbun;
$beanbun->name = 'phantom';
$beanbun->count = 5;
$beanbun->interval = 2;
$beanbun->timeout = 10;
$beanbun->seed = [
    'https://www.zhihu.com/question/23660494',
    'https://www.zhihu.com/question/23010225',
];
$beanbun->logFile = __DIR__ . '/phantom_access.log';

$beanbun->downloadPage = function ($beanbun) {
    $client = Client::getInstance();
    $client->getEngine()->setPath('/Users/kiddyu/Documents/phantomjs/bin/phantomjs');
    
    $width  = 1440;
    $height = 6000;
    $top    = 0;
    $left   = 0;

    $request = $client->getMessageFactory()->createCaptureRequest($beanbun->url, $beanbun->method, 6000);
    $request->setOutputFile('zhihu_test_' . md5($beanbun->url) .'.jpg');
    $request->setViewportSize($width, $height);
    $request->setCaptureDimensions($width, $height, $top, $left);
    $response = $client->getMessageFactory()->createResponse();

    $client->send($request, $response);

    $worker_id = $beanbun->daemonize ? $this->id : '';
    $beanbun->log("Beanbun worker {$worker_id} download {$beanbun->url} success.");
};

$beanbun->discoverUrl = function () {};

$beanbun->start();
