<?php

/**
 * curl自动降级与恢复
 * @author  qiangjian@staff.sina.com.cn sunsky303@gmail.com
 * Date: 2017/2/16
 * Time: 14:11
 * @version $Id: $
 * @since 1.0
 * @copyright Sina Corp.
 */
class CurlDegradeSetting extends CurlSetting
{
    const CONF_PREFIX = 'curlDegrade';
    protected static $degradeInfo  = [
        'ok' => 0,          //curl成功
        'fail' => 0,        //curl失败
        'degrade' => 0,     //相邻时间内连续降级的次数
        'end' => 0,         //若启用降级时，end就是降级截止的时间戳
    ];
    protected static function whitelistKey(){
        return self::CONF_PREFIX.'.tasksWhenDegrade.curlCache.whitelist';
    }
    public static function matchedWhitelist(&$url){
        return parent::matchedWhitelist($url);
    }
    public static function updateDegradeInfo(&$degradeInfo){
        if(!is_array($degradeInfo) || false === $degradeInfo) return false;
        self::$degradeInfo = $degradeInfo;
    }
    public static function degradeInfo(){
        return self::$degradeInfo;
    }
    public static function curlFail(){
        ++self::$degradeInfo['fail'];
    }
    public static function curlOk(){
        ++self::$degradeInfo['ok'];
    }
    /**
     * @return array|mixed
     * @example
     *   'tryTimes' => 20,       //尝试次数
     */
    public static function degradingParams(){
        return front_fetch_conf(self::CONF_PREFIX.'.enableTrigger');
    }
    /**
     * @return array|mixed
     * @example
     *   'failRatio' => 0.5,     //失败比率
     *   'nextTryIntervalCoefficient' => 2,     //当有连续降级时，下次降级时距离本次降级的时间间隔的系数，比如 2 就是 2*degrade（连续降级次数）的时间（分钟）
     */

    public static function degradingCacheParams(){
        return front_fetch_conf(self::CONF_PREFIX.'.tasksWhenDegrade');
    }

    public static function mcCacheKey(&$url)
    {
        return 'degradeCache_' . $url;
    }


    public static function mcDegradeInfoKey(&$url)
    {
        return 'degradeInfo_' . parent::assembleURL($url);
    }

    public static function canDegrading(){
        $okNum = empty(self::$degradeInfo['ok']) ? 0 : self::$degradeInfo['ok'];
        $failNum = empty(self::$degradeInfo['fail']) ? 0 : self::$degradeInfo['fail'];
        $degradingParams = self::degradingParams();
        $total = $okNum + $failNum;
        return ($total == $degradingParams['tryTimes']) &&  $failNum/$total >= $degradingParams['failRatio'];
    }
    public static function enableDegradeConf(){
        self::$degradeInfo['ok'] = self::$degradeInfo['fail'] = 0;
        if(self::$degradeInfo['degrade'] <= self::degradingParams()['maxDegradeTimes']) ++self::$degradeInfo['degrade'];
        self::$degradeInfo['end'] = time() + self::nextTryInterval();
    }
    public static function recordDegradeConf(){
        if(self::$degradeInfo['ok'] + self::$degradeInfo['fail'] >= self::degradingParams()['tryTimes'] ){
            self::$degradeInfo['ok'] = self::$degradeInfo['fail'] = 0;
        }
        if(self::$degradeInfo['degrade'] > self::degradingParams()['maxDegradeTimes'])self::$degradeInfo['degrade']=0; //reset degrade数

        self::$degradeInfo['end']=0;
    }
    /**
     * 当有连续降级时，下次降级时距离本次降级的时间间隔秒
     */
    public static function nextTryInterval(){
        return  intval(self::$degradeInfo['degrade']) * intval(self::degradingParams()['nextTryIntervalCoefficient']) * 60;
    }

    public static function isInDegraded()
    {
        if(!empty(self::$degradeInfo['end']) && self::$degradeInfo['end'] >= time()) return true;
        else return false;
    }
    public static function overrideRate(){
        return front_fetch_conf(self::CONF_PREFIX.'.tasksWhenDegrade.curlCache.overrideRate');
    }
}