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

    /**
     * 将抓取的链接格式化
     *
     * @param string $href
     * @param string $seed
     * @return string
     */
    public static function formatUrl($href, $seed)
    {
        $href = str_replace([chr(34), chr(39)], '', $href);
        if (empty($href)) {
            return $href;
        }

        $seedParsed = parse_url($seed);
        // origin协议前缀
        $seedScheme = isset($seedParsed['scheme']) ? $seedParsed['scheme'] : '';
        if (!empty($seedScheme)) {
            $seedScheme .= '://';
        }
        // origin host
        $seedHost = isset($seedParsed['host']) ? $seedParsed['host'] : '';
        if (empty($seedHost)) {
            return $href;
        }
        $seedOrigin = $seedScheme . $seedHost . '/';

        $hrefHostExist = false;

        $hrefParsed = parse_url($href);
        if (isset($hrefParsed['scheme'])) {
            return $href;
        } else {
            // href path
            $hrefPath = isset($hrefParsed['path']) ? $hrefParsed['path'] : '';
            // href host
            $hrefHost = isset($hrefParsed['host']) ? $hrefParsed['host'] : '';

            if (substr($href, 0, 2) == '//') {
                if (preg_match('/([a-zA-Z0-9]+\.){2}[a-zA-z]+/i', $hrefHost)) {
                    $hrefHostExist = true;
                    $path = $hrefPath;
                } else {
                    $path = $hrefHost . $hrefPath;
                }
            } elseif (substr($href, 0 , 1) == '/') {
                $path = $hrefPath;
            }
            // 相对路径
            else {
                $seedPath = isset($seedParsed['path']) ? $seedParsed['path'] : '';
                $path     = $seedPath . '/' . $hrefPath;
            }

            // 分割做路劲处理
            $splitPath = explode('/', $path);
            $splitBox  = [];

            foreach($splitPath as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if ($value == '.') {
                    continue;
                } elseif ($value == '..') {
                    array_pop($splitBox);
                } else {
                    array_push($splitBox, $value);
                }
            }

            // 拼接出转换之后的完整URL
            if ($hrefHostExist) {
                $url = $seedScheme . $hrefHost . '/' . implode($splitBox, '/');
            } else {
                $url = $seedOrigin . implode($splitBox, '/');
            }

            // query_string参数
            if (isset($hrefParsed['query'])) {
                $url .= '?' . $hrefParsed['query'];
            }
            // route参数
            if (isset($hrefParsed['fragment'])) {
                $url .= '#' . $hrefParsed['fragment'];
            }

            return $url;
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
