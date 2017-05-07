<?php
use Beanbun\Beanbun;
use Beanbun\Lib\Db;

require_once(__DIR__ . '/vendor/autoload.php');

// 数据库配置
Db::$config['zhihu'] = [
    'server' => '127.0.0.1',
    'port' => '3306',
    'username' => 'xxx',
    'password' => 'xxx',
    'database_name' => 'zhihu',
    'charset' => 'utf8',
];

function getProxies($beanbun) {
    $client = new \GuzzleHttp\Client();
    $beanbun->proxies = [];
    $pattern = '/<tr><td>(.+)<\/td><td>(\d+)<\/td>(.+)(HTTP|HTTPS)<\/td><td><div class=\"delay (fast_color|mid_color)(.+)<\/tr>/isU';
    
    for ($i = 1; $i < 5; $i++) {
        $res = $client->get("http://www.mimiip.com/gngao/$i");
        $html = str_replace(['  ', "\r", "\n"], '', $res->getBody());
        preg_match_all($pattern, $html, $match);
        foreach ($match[1] as $k => $v) {
            $proxy = strtolower($match[4][$k]) . "://{$v}:{$match[2][$k]}";
            echo "get proxy $proxy ";
            try {
                $client->get('http://mail.163.com', [
                    'proxy' => $proxy,
                    'timeout' => 6
                ]);
                $beanbun->proxies[] = $proxy;
                echo "success.\n";
            } catch (\Exception $e) {
                echo "error.\n";
            }
        }
    }
}

$beanbun = new Beanbun;
$beanbun->name = 'zhihu_user';
$beanbun->count = 5;
$beanbun->interval = 4;
$beanbun->seed = 'https://www.zhihu.com/api/v4/members/zhang-jia-wei/followers?include=data%5B*%5D.following_count%2Cfollower_count&limit=20&offset=0';
$beanbun->logFile = __DIR__ . '/zhihu_user_access.log';

if ($argv[1] == 'start') {
    getProxies($beanbun);
}

$beanbun->startWorker = function($beanbun) {
    // 每隔半小时，更新一下代理池
    Beanbun::timer(1800, 'getProxies', $beanbun);
};

$beanbun->beforeDownloadPage = function ($beanbun) {
    // 在爬取前设置请求的 headers 
    $beanbun->options['headers'] = [
        'Host' => 'www.zhihu.com',
        'Connection' => 'keep-alive',
        'Cache-Control' => 'max-age=0',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
        'Accept' => 'application/json, text/plain, */*',
        'Accept-Encoding' => 'gzip, deflate, sdch, br',
        'authorization' => 'oauth c3cef7c66a1843f8b3a9e6a1e3160e20',
    ];

    if (isset($beanbun->proxies) && count($beanbun->proxies)) {
	    $beanbun->options['proxy'] = $beanbun->proxies[array_rand($beanbun->proxies)];
	}
};

$beanbun->afterDownloadPage = function ($beanbun) {
    // 获取的数据为 json，先解析
    $data = json_decode($beanbun->page, true);

    // 如果没有数据或报错，那可能是被屏蔽了。就把地址才重新加回队列
    if (isset($data['error']) || !isset($data['data'])) {        
        $beanbun->queue()->add($beanbun->url);
        $beanbun->error();
    }
    
    // 如果本次爬取的不是最后一页，就把下一页加入队列
    if ($data['paging']['is_end'] == false) {
        $beanbun->queue()->add($data['paging']['next']);
    }

    $insert = [];
    $date = date('Y-m-d H:i:s');

    foreach ($data['data'] as $user) {
        // 如果关注者或者关注的人小于5个，就不保存了
        if ($user['follower_count'] < 5 || $user['following_count'] < 5) {
            continue ;
        }
        $insert[] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'follower' => $user['follower_count'],
            'following' => $user['following_count'],
            'created_at' => $date,
        ];
        // 把用户的关注者和关注的人列表加入队列
        $beanbun->queue()->add('https://www.zhihu.com/api/v4/members/' . $user['url_token'] . '/followers?include=data%5B*%5D.following_count%2Cfollower_count&limit=20&offset=0');
        $beanbun->queue()->add('https://www.zhihu.com/api/v4/members/' . $user['url_token'] . '/followees?include=data%5B*%5D.following_count%2Cfollower_count&limit=20&offset=0');
    }

    if (count($insert)) {
        Db::instance('zhihu')->insert('zhihu_user', $insert);
    }
    // 把刚刚爬取的地址标记为已经爬取
    $beanbun->queue()->queued($beanbun->queue);
};
// 不需要框架来发现新的网址，
$beanbun->discoverUrl = function () {};
$beanbun->start();