<?php
/**
    --------------------------------------------------
    swoole客户端应用
    --------------------------------------------------
   
    --------------------------------------------------
    Author: 刘青
    --------------------------------------------------
*/

namespace app\Base;

use app\Base\SwooleClientUtil;
class SwooleClient{    
    private static $_connectPool = array();
    private $_conNum = 0;

    private $_timeout = 3.0;

    private $_clientSet;
    

    function __construct()
    {
        $this->_clientSet = array(
                'open_length_check' => 1,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 4931584,
                'open_tcp_nodelay' => 1,
            );
    }

    public function getConnect($app_name = '')
    {
        if (empty($app_name)) {
            $app_name = 'default';
        }

        $config = SwooleClientUtil::getSwooleConfig($app_name);
        if (empty($config)) {
            $app_name = 'default';
            $config = SwooleClientUtil::getSwooleConfig($app_name);
        }

        if (isset(self::$_connectPool[$app_name]) && self::$_connectPool[$app_name] && self::$_connectPool[$app_name]->isConnected()) {
            return self::$_connectPool[$app_name];
        } elseif (isset(self::$_connectPool[$app_name]) && self::$_connectPool[$app_name]) {            
            unset(self::$_connectPool[$app_name]);
        } 

        $config = SwooleClientUtil::getSwooleConfig($app_name);
        $resutl = self::createConnect($config, $app_name);
        if (!$resutl) {
            self::createConnect($config, $app_name);
        }

        return isset(self::$_connectPool[$app_name])?self::$_connectPool[$app_name]:false;
    }

    private function createConnect($swconfig, $app_name)
    {
        static $_conNum = 0;
        $swc = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
        $swc->set($this->_clientSet);
        $connRet = $swc->connect($swconfig['host'], $swconfig['port'], $this->_timeout);
        if (!$connRet) 
        {
            unset(self::$_connectPool[$app_name]);
            $errorcode = $swc->errCode;
            $msg = socket_strerror($errorcode);
            file_put_contents(\Constant::SW_lOG_FILE, date("Y-m-d H:i:s") . "\tCLIENT-CONNECT {$errorcode}, {$msg}");
            return false;
        }

        self::$_connectPool[$app_name] = $swc;
        return true;
    }

    public function isConnected($app_name = '')
    {
        if (empty($app_name) || empty(self::$_connectPool[$app_name])) {
            return false;
        }

        return self::$_connectPool[$app_name]->isConnected();
    }

    public function getConnectList($app_name = '')
    {
        if (empty($app_name) || empty(self::$_connectPool[$app_name])) {
            return false;
        }

        return self::$_connectPool[$app_name]->getsockname();
    }  
}
?>