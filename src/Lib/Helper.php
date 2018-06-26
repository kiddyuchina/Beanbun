<?php
namespace Beanbun\Lib;

class Helper
{
    public static $userAgentArray = [
        'pc' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/537.75.14',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)',
            'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
        ],
        'android' => [
            'Mozilla/5.0 (Android; Mobile; rv:29.0) Gecko/29.0 Firefox/29.0',
            'Mozilla/5.0 (Linux; Android 4.4.2; Nexus 4 Build/KOT49H) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.114 Mobile Safari/537.36',
        ],
        'ios' => [
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) CriOS/34.0.1847.18 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4',
        ],
    ];

    public static function getUrlByHtml($html, $url)
    {
        $pattern = "'<\s*a\s.*?href\s*=\s*([\"\'])?(?(1) (.*?)\\1 | ([^\s\>]+))'isx";
        preg_match_all($pattern, $html, $match);
        $match = array_merge($match[2], $match[3]);
        $hrefs = array_flip(array_flip(array_filter($match)));
        foreach ($hrefs as &$href) {
            $href = self::formatUrl($href, $url);
        }

        return array_flip(array_flip(array_filter($hrefs)));
    }

    public static function formatUrl($href, $origin)
    {
        if (strlen($href) > 0) {
            $transHref = str_replace([chr(34), chr(39)], '', $href);
        } else {
            return $href;
        }

        $urlParsed = parse_url($origin);

        $scheme = isset($urlParsed['scheme']) ? $urlParsed['scheme'] : '';
        if ($scheme != '') {
            $scheme .= '://';
        }

        $host = isset($urlParsed['host']) ? $urlParsed['host'] : '';
        if (strlen($host) == 0) {
            return $href;
        }
        $host = $scheme . $host . '/';

        $path = isset($urlParsed['path']) ? $urlParsed['path'] : '';
        if ($path[0] == '\\' || $path == '/') {
            $path = '';
        }

        $pos = strpos($transHref, '#');
        if ($pos > 0) {
            $transHref = substr($transHref, 0, $pos);
        }

        $splitTransHref = explode('/', $transHref);

        // 完整路径
        if ($splitTransHref[0] == 'http' || $splitTransHref[0] == 'https' || $splitTransHref[0] == 'ftp') {
            return $transHref;
        }
        // 绝对路径
        elseif ($splitTransHref[0] == '') {
            return ($host . ltrim($transHref, '/'));
        }
        // 相对路径
        elseif ($splitTransHref[0] == '.') {
            return ($host . substr($transHref, 2));
        }
        // 相对路径
        else {
            // todo: 移动至上方，处理相对路径后再判断href
            $splitPath = explode('/', $path);
            $splitTransHref = array_merge($splitPath, $splitTransHref);
            $splitBox = [];

            foreach ($splitTransHref as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if ($value == '..') {
                    array_shift($splitBox);
                } else {
                    array_push($splitBox, $value);
                }
            }

            return $host . implode($splitBox, '/');
        }
    }

    public static function getDomain($url)
    {
        $parseUrl = parse_url($url);
        $domain = $parseUrl['scheme'] . '://';

        return $domain;
    }

    public static function randUserAgent($type = 'pc')
    {
        switch ($type) {
            case 'pc':
                return self::$userAgentArray['pc'][array_rand(self::$userAgentArray['pc'])] . rand(0, 10000);
                break;
            case 'android':
                return self::$userAgentArray['android'][array_rand(self::$userAgentArray['android'])] . rand(0, 10000);
                break;
            case 'ios':
                return self::$userAgentArray['ios'][array_rand(self::$userAgentArray['ios'])] . rand(0, 10000);
                break;
            case 'mobile':
                $userAgentArray = array_merge(self::$userAgentArray['android'], self::$userAgentArray['ios']);
                return $userAgentArray[array_rand($userAgentArray)] . rand(0, 10000);
            default:
                return $type;
                break;
        }
    }
}
