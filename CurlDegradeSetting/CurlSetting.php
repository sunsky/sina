<?php

/**
 * curl配置类
 * @author  qiangjian@staff.sina.com.cn sunsky303@gmail.com
 * Date: 2017/2/16
 * Time: 13:58
 * @version $Id: $
 * @since 1.0
 * @copyright Sina Corp.
 */
class CurlSetting
{
    protected static function whitelist($confKey){
        return front_fetch_conf($confKey);
    }
    protected static function whitelistKey(){
        return 'curlCache.whitelist';
    }
    public static function matchedWhitelist(&$url){
        static $localOpts;
        $confKey = static::whitelistKey();
        if(!isset($localOpts[$url])){
            $whitelist = static::whitelist($confKey);
            list($uriMatched, $optionMatched) = self::findWhitelist($whitelist, $url);
            $localOpts[$url] = [$uriMatched, $optionMatched];
        }
        return $localOpts[$url];
    }
    /**
     * @param $cacheConf
     * @param $url
     * @return array [$matchedKey, $matchedOpt]
     */
    protected static function findWhitelist(&$whitelist, $url)
    {
        $matchedKey = $matchedOpt = false;
        foreach ($whitelist as $_path => $_opt) {
            if (false !== strpos($url, $_path)) {
                $matchedKey = $_path;
                $matchedOpt = $_opt;
                break;
            }
        }
        if(!$matchedKey && isset($whitelist['*'])){
            $matchedKey = '*';
            $matchedOpt = $whitelist[$matchedKey];
        }

        return array($matchedKey, $matchedOpt);
    }

    /**
     * @param $url
     * @return array
     */
    public static function assembleURL(&$url, $withQuery = false)
    {
        $urlComps = parse_url($url);
        $port = '';
        if (isset($urlComps['port'])) {
            if ($urlComps['port'] == 80) $port = '';
            else $port = $urlComps['port'];
        }
        return $urlComps['host'] . ($port ? ':' . $port : '') . $urlComps['path'] . ($withQuery ? '?'.$urlComps['query'] : '');
    }

    public static function overrideRate(){
        return front_fetch_conf('curlCache.overrideRate');
    }
    public static function canOverride(){//cache key是否能覆盖，目的是提高性能
        switch (static::overrideRate()){
            case 0:
                $ret = false;
                break;
            case 1:
                $ret = true;
                break;
            default:
                $ret =  mt_rand(0,100) <= 100 * static::overrideRate();
        }
        return $ret;
    }
}