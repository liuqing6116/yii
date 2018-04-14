<?php
namespace app\Base;
class SwooleClientUtil
{
    private static $_config = array(
            '_cmd_' => array('host'=>'127.0.0.1', 'port'=>9501), //用于start,stop,reload操作
            'default' => array('host'=>'127.0.0.1', 'port'=>9501),    
        );

    public static function packFormat($msg = "OK", $code = 0, $data = array(), $len = 0)
    {
        $pack = array(
            "status" => $code,
            "msg" => $msg,
            "len" => empty($len)?strlen(serialize($data)):$len,
            "data" => $data,
        );
        return $pack;
    }
    
    public static function packEncode($data)
    {
        $sendStr = serialize($data);
        $sendStr = pack('N', strlen($sendStr)) . $sendStr;
        return $sendStr;
    }

    public static function packDecode($str)
    {
        if (empty($str)) {
            return false;
        }
        $header = substr($str, 0, 4);
        $result = substr($str, 4);

        $len = unpack("Nlen", $header);

        if ($len["len"] != strlen($result)) {
            //结果长度不对
            return self::packFormat("包长度非法", 700007);
        }
        $result = unserialize($result);

        return self::packFormat("OK", 0, $result, $len["len"]);
    }

    public static function getHostEnv()
    {
        $type = get_cfg_var("qyer_env");
        if (empty($type)) {
            return 'product';
        }

        $type = trim($type," ,./\/'][");
        $type = strtolower($type);

        return $type;
    }

    public static function getSwooleConfig($appname = "", $getall = false)
    {
        if ($appname && self::$_config[$appname]) {
            return self::$_config[$appname];
        }        
    }

}