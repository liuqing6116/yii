<?php
/**
    --------------------------------------------------
    swoole客户端
    --------------------------------------------------
   
    --------------------------------------------------
    Author: 刘青
    --------------------------------------------------
*/

namespace app\Base;

use app\Base\SwooleClient;

class SwooleClientMain{    

    private static $guid;

    private static function getSwooleClient($app_name = '')
    {        
        $connect_obj = new SwooleClient();

        return $connect_obj->getConnect($app_name);        
    }

    private static function filterApiUrl($params)
    {
        foreach ($params as $key => $value) {
            $params[$key] = $value;
            $params[$key]['a'] = trim($value['api'], '/ ');
            // $params[$key]['_HTTP_SERVER'] = array(
            //                                     'HTTP_X_FORWARDED_PROTO'=>$_SERVER['HTTP_X_FORWARDED_PROTO'],
            //                                 );
        }
        return $params;
    }

    public static function invokeAPI($params, $appname = 'default' ,$sync = false, $timeout = 0.2)
    {
        //$params = self::filterApiUrl($params);
        
        if (count($params) > \Constant::MAX_PARR) {
            $packet = $this->packFormat("并发请求不能超过20个", 700005);
            return $packet;
        }

        if (self::$guid == "") {
            self::$guid = md5(time() . microtime(true) . mt_rand(0, 90000000));
        }

        $packet = array(
            'u' => self::$guid,
            'o' => $timeout,
            'api' => $params
        );

        if (count($params)>1 || (count($params) == 1 && empty($params['one'])) ) {
            $packet['t'] = ($sync)?\Constant::SW_SYNC_MULTI:\Constant::SW_RSYNC_MULTI;
        } else {
            $packet['t'] = ($sync)?\Constant::SW_SYNC_SINGLE:\Constant::SW_RSYNC_SINGLE;
        }

        $sendData = SwooleClientUtil::packEncode($packet);
        $errX = 1000000;

        $sw = self::getSwooleClient($appname);
        if(empty($sw)){
            return SwooleClientUtil::packFormat('get sw connect error', -111*$errX);
        }

        $ret = $sw->send($sendData);
        if (!$ret) 
        {
            $errorcode = $sw->errCode;
            $msg = socket_strerror($errorcode);
            file_put_contents(\Constant::SW_lOG_FILE, date("Y-m-d H:i:s") . "\tSEND error code {$errorcode}, {$msg}");            
            $packet = SwooleClientUtil::packFormat($msg, "{$errorcode}000000");
            return $packet;
        }

        $result = $sw->recv();
        if(!$result)
        {
            $errorcode = $sw->errCode;
            $msg = socket_strerror($errorcode);
            file_put_contents(\Constant::SW_lOG_FILE, date("Y-m-d H:i:s") . "\tRECV error code {$errorcode}, {$msg}");                              
            return SwooleClientUtil::packFormat($msg, "{$errorcode}000000");
        }

        $result = SwooleClientUtil::packDecode($result);
        if ($result["status"] === 0) {
            $result = $result["data"];
        }
        return $result;
    }

    public static function invokeCommand($appname, $cmd = "", $timeout = 0.2)
    {
        $cmd = trim($cmd);
        if (empty($cmd)) {
            return false;
        }

        $packet = array(
            'u' => self::$guid,
            'o' => $timeout,
            'cmd' => $cmd,
            't' => \Constant::SW_CTRL_CMD
        );

        $sendData = SwooleClientUtil::packEncode($packet);
        $errX = 1000000;

        $swoole_obj = self::getSwooleClient($appname);
        if (empty($swoole_obj)) return SwooleClientUtil::packFormat('get sw connect error', -111*$errX);
        $ret = $swoole_obj->send($sendData);

        $result = $swoole_obj->recv();
        $result = SwooleClientUtil::packDecode($result);
        if ($result["status"] === 0) {
            $result = $result["data"];
        }
        return $result;
    }    
}
?>