<?php
namespace app\Base;

class SwooleServerUtil
{
    private static $_config = NULL;

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
            echo "error length...\n";
            return self::packFormat("包长度非法", 700007);
        }
        $result = unserialize($result);

        return self::packFormat("OK", 0, $result, $len["len"]);
    }

    public static function splitData($data = '', $tag = '*#*')
    {
        if (empty($data)) {
            return $data;
        }
        $ser_data = serialize($data);
        $split_str = chunk_split(base64_encode($ser_data), \Constant::DATA_SPLIT_LEN, $tag);
        $split_ary = explode($tag, $split_str);

        $result = array();
        foreach ($split_ary as $k => $str) {
            if ($str) {
                $result[$k]['key'] = md5($str);
                $result[$k]['str'] = $str;
            }
        }
        
        return $result;
    }
}