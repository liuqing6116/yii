<?php
/**
    --------------------------------------------------
    swoole服务
    --------------------------------------------------
   
    --------------------------------------------------
    Author: 刘青
    --------------------------------------------------
*/

namespace app\Base;

use app\Base\SwooleServerUtil;
class SwooleServer{        
    private $taskout_count = 0;
    private $server = null;
    private $taskInfo = array();    
    private $config = array(
        'open_length_check' => 1,
        'dispatch_mode' => 2,
        'package_length_type' => 'N',
        'package_length_offset' => 0,
        'package_body_offset' => 4,
        // 'package_max_length' => 1024 * 1024 * 4,
        // 'buffer_output_size' => 1024 * 1024 * 4,
        // 'pipe_buffer_size' => 1024 * 1024 * 32,
        'open_tcp_nodelay' => 1,
        'heartbeat_check_interval' => 5,
        'heartbeat_idle_time' => 10,

        //'max_conn' => 10000,
        'reactor_num' => 4,
        'worker_num' => 4,
        'task_worker_num' => 10,

        'max_request' => 0,
        'task_max_request' => 20,

        'backlog' => 2000,
        'log_file' => '/tmp/swoole_server.log',
        'task_tmpdir' => '/tmp/swtasktmp',
        'pid_file' => '/var/run/swoole_server.pid',
        // 'user' => 'www',
        // 'group' => 'www',

        'daemonize' => 1,
    );
    private $table = null;

    function __construct()
    {               
        $host = \Constant::SERVER_HOST;         
        $port = \Constant::SERVER_PORT;
        $this->server = new \swoole_server($host, $port);
        $this->server->set($this->config);        

        $this->server->on('connect', array($this, 'onConnect'));
        $this->server->on('workerstart', array($this, 'onWorkerStart'));
        $this->server->on('receive', array($this, 'onReceive'));
        $this->server->on('workererror', array($this, 'onWorkerError'));
        $this->server->on('task', array($this, 'onTask'));
        $this->server->on('close', array($this, 'onClose'));
        $this->server->on('finish', array($this, 'onFinish'));
        $this->server->on('start', array($this, 'onStart'));

        $this->initTable();
        $this->initCounTable();
    }

    function initTable()
    {
        $this->table = new \swoole_table(\Constant::TABLE_MAX_LINE);
        $this->table->column('content', \swoole_table::TYPE_STRING, \Constant::PROC_MAX_SHAIR);
        $this->table->create();
    }

    function initCounTable()
    {
        $this->counTable = new \swoole_table(\Constant::TABLE_MAX_LINE);
        $this->counTable->column('recv_count', \swoole_table::TYPE_INT);
        $this->counTable->column('fin_count', \swoole_table::TYPE_INT);
        $this->counTable->column('close_count', \swoole_table::TYPE_INT);
        $this->counTable->create();
        $this->countKey = 'countKey';
    }

    function startServer()
    {
        $this->server->start();
    }

    function onConnect($serv, $fd, $from_id)
    {
        $serv->heartbeat();

        $this->taskInfo[$fd] = array();
    }

    function onStart($server)
    {
        echo date("Y-m-d H:i:s")." #{$this->server->master_pid}>> swoole server starting now...!\n";
        file_put_contents($this->server->setting['pid_file'], $this->server->master_pid);
    }

    function onWorkerStart( $server, $worker_id)
    {
        swoole_set_process_name("phpworker|{$worker_id}");
    }

    function sendInfo($serv, $fd, $data)
    {
        if(!isset($data['msg'])){
            $data['msg'] = 'error';
        }
        if(!isset($data['code'])){
            $data['msg'] = 700009;
        }
        $len = strlen(serialize($data['info']));
        $pack = SwooleServerUtil::packFormat($data['msg'], $data['code'], $data['info'], $len);
        $pack = SwooleServerUtil::packEncode($pack);

        $result = $serv->send($fd, $pack);
        return $result;
    }

    function onReceive(\swoole_server $serv, $fd, $from_id, $data)
    {
        $this->counTable->incr($this->countKey,'recv_count');

        // $this->fd = $fd;

        $request_info = SwooleServerUtil::packDecode($data);        
        $infoMsg = array('msg'=>'未知错误', 'code'=>700000, 'info'=>$request_info['data']);

        if ($request_info["status"] != 0) {
            $infoMsg['msg'] = '请求数据发送或接收异常';
            $infoMsg['code'] = 700005;
            return $this->sendInfo($serv, $fd, $infoMsg);
        }

        // echo "REC {$request_info['u']} - $fd\n";

        $request = $request_info["data"];        

        if ($request['t'] == \Constant::SW_CTRL_CMD) 
        {
            $this->counTable->decr($this->countKey,'recv_count');
            return $this->adminCtrlCMD($request['cmd'], $serv, $fd);
        }

        if (!is_array($request["api"]) && count($request["api"])) 
        {
            $infoMsg['msg'] = '未指定api';
            $infoMsg['code'] = 700003;
            return $this->sendInfo($serv, $fd, $infoMsg);
        }

        $this->taskInfo[$fd] = $request;
        unset($request);
        $task = array(
            "t" => $this->taskInfo[$fd]["t"],
            "u" => $this->taskInfo[$fd]["u"],
            "o" => $this->taskInfo[$fd]["o"],
            "fd" => $fd,
        );

        switch ($this->taskInfo[$fd]["t"]) 
        {
            //单接口同步
            case \Constant::SW_SYNC_SINGLE:
                $task["api"] = $this->taskInfo[$fd]["api"]["one"];
                $taskid = $serv->task($task);

                $this->taskInfo[$fd]["task"][$taskid] = "one";
                return true;
                break;
            //单接口异步
            case \Constant::SW_RSYNC_SINGLE:
                $task["api"] = $this->taskInfo[$fd]["api"]["one"];
                $serv->task($task);

                $infoMsg['msg'] = '异步请求已经成功投递';
                $infoMsg['code'] = 700001;
                unset($this->taskInfo[$fd]);

                return $this->sendInfo($serv, $fd, $infoMsg);
                break;
            //多接口同步
            case \Constant::SW_SYNC_MULTI:
                foreach ($this->taskInfo[$fd]["api"] as $k => $v) {
                    $task["api"] = $v;
                    $taskid = $serv->task($task);
                    $this->taskInfo[$fd]["task"][$taskid] = $k;
                }

                return true;
                break;
            //多接口异步
            case \Constant::SW_RSYNC_MULTI:
                foreach ($this->taskInfo[$fd]["api"] as $k => $v) {
                    $task["api"] = $this->taskInfo[$fd]["api"][$k];
                    $serv->task($task);
                    $pack[$k] = SwooleServerUtil::packFormat("异步请求已经成功投递", 700001);
                }
                $pack = SwooleServerUtil::packFormat('OK', 0, $pack);
                $pack = SwooleServerUtil::packEncode($pack);

                $serv->send($fd, $pack);
                unset($this->taskInfo[$fd]);

                return true;
                break;

            default:
                $infoMsg['msg'] = '未知类型任务';
                $infoMsg['code'] = 700002;
                unset($this->taskInfo[$fd]);

                return $this->sendInfo($serv, $fd, $infoMsg);
                break;
        }

        return $this->sendInfo($serv, $fd, $infoMsg);
    }

    function onTask(\swoole_server $serv, $task_id, $from_id, $data)
    {
        // echo "TASK {$data['fd']}\n";
        swoole_set_process_name("phptask|{$task_id}|{$from_id}" . $data["api"]["a"]);

        try {
            $ret = $this->doWork($data);
            $this->taskout_count ++;
            $data["result"] = json_decode($ret, true);
        } catch (Exception $e) {
            $err_msg = '后端接口执行报错'.($e->getMessage()?',抛出：'.$e->getMessage():'');
            $err_code = $e->getCode()?:700500;

            $data["result"] = SwooleServerUtil::packFormat($err_msg,$err_code);
            unset($err_msg);
            unset($err_code);
        }

        if (\Constant::USE_SWOOLE_TALBE) 
        {
            $ser_data = serialize($data);
            if (strlen($ser_data) > 8000) {
                $splitData = SwooleServerUtil::splitData($data['result']);

                $tb_keys = array();
                foreach ($splitData as $key => $value) {
                    $tb_keys[$key] = $value['key'];
                    $this->table->set($value['key'], array('content'=>$value['str']));
                }
                $data['tb_keys'] = $tb_keys;

                unset($data['result']);
            }
        }

        if ($data['t'] == \Constant::SW_RSYNC_SINGLE || $data['t'] == \Constant::SW_RSYNC_MULTI) {
            if ($data['result']['status'] == 0) {
                $data['result']['data'] = '异步请求已执行完成！';
            }
        }

        return $data;
    }

    public function doWork($param)
    {   

        $params = [];
        $action = isset($param['api']["a"])?$param['api']["a"]:'';
        if($action == ''){
            return;
        }                            
        if(isset($param['api']['p'])){
            $params = $param['api']['p'];
            if(!is_array($params)){
                $params = [strval($params)];
            }
        }       
        try{            
            $parts = \Yii::$app->createController($action);
            if (is_array($parts)) {
                $res = \Yii::$app->runAction($action,$params);
                // file_put_contents($this->config['log_file'], date("Y-m-d H:i:s") ." [task result] = ".var_export($res,true). "\r\n", FILE_APPEND);                
            }
        }catch(Exception $e){            
            file_put_contents($this->config['log_file'], date("Y-m-d H:i:s") ." doWorkerr = ".$e. "\r\n", FILE_APPEND);
        }
        // unset($proxy);
        // return $result;
    }    

    function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {
        $content = array($this->taskInfo, $serv, $worker_id, $worker_pid, $exit_code);
        $info = date("Y-m-d H:i:s") . " |WorkerError| " . json_encode( $content ) . "\r\n";
        echo $info;
        //QYF_Swoole_Server_Log::log($info);
    }

    function onFinish(\swoole_server $serv, $task_id, $data)
    {
        $this->counTable->incr($this->countKey,'fin_count');
        if (\Constant::USE_SWOOLE_TALBE) 
        {
            if ($data['tb_keys']) {
                $fullstr = '';
                foreach ($data['tb_keys'] as $key) {
                    $tbrow = $this->table->get($key);
                    $this->table->del($key);
                    $fullstr .= $tbrow['content'];
                }

                $result = unserialize(base64_decode($fullstr));
                $data['result'] = $result;
            }
        } else {
            if (is_string($data)) 
            {
                $data = trim($data);
                $task_tmpfile_ary = explode('/', $data);
                $task_tmpfile = $serv->setting['task_tmpdir'].'/'.end($task_tmpfile_ary);
                if (file_exists($task_tmpfile)) {
                    $data = file_get_contents($task_tmpfile);
                    $data = unserialize($data);

                    unlink($task_tmpfile);
                    unset($task_tmpfile_ary);
                    unset($task_tmpfile);
                }
            }
        }

        $infoMsg['msg'] = '未知结果';
        $infoMsg['code'] = '700011';
        $infoMsg['info'] = $data;

        $fd = $data["fd"];
        if(!isset($this->taskInfo[$fd]))
        {
            //server 已经close了tcp连接
            return;
        }

        if (empty($data['result'])) {
            $infoMsg['msg'] = '后端接口请求失败';
            $infoMsg['code'] = '700500';
            return $this->sendInfo($serv, $fd, $infoMsg);
        }

        $key = $this->taskInfo[$fd]["task"][$task_id];
        $this->taskInfo[$fd]["result"][$key] = $data["result"];
        unset($this->taskInfo[$fd]["task"][$task_id]);

        switch ($data["t"]) 
        {
            case \Constant::SW_SYNC_SINGLE:
                $ret_data = $data['result'];
                if (!isset($ret_data['status']) || !isset($ret_data['data'])) {
                    $infoMsg['msg'] = '后端提供接口返回数据异常';
                    $infoMsg['code'] = '700502';
                    unset($this->taskInfo[$fd]);
                    return $this->sendInfo($serv, $fd, $infoMsg);
                }
                break;

            case \Constant::SW_SYNC_MULTI:
                if (count($this->taskInfo[$fd]["task"]) == 0) {
                    $ret_data = $this->taskInfo[$fd]["result"];
                } else {
                    if (!isset($data["result"]['status']) || !isset($data["result"]['data'])) {
                        $infoMsg['msg'] = '后端提供接口返回数据异常';
                        $infoMsg['code'] = '700502';
                        unset($this->taskInfo[$fd]);
                        return $this->sendInfo($serv, $fd, $infoMsg);
                    }
                    return true; //继续接收数据
                }
                break;

            default:
                unset($this->taskInfo[$fd]);
                return $this->sendInfo($serv, $fd, $infoMsg);
                break;
        }

        $infoMsg['code'] = 0;
        $infoMsg['msg']  = 'OK';
        $infoMsg['info'] = $ret_data;
        unset($this->taskInfo[$fd]);
        return $this->sendInfo($serv, $fd, $infoMsg);
    }

    function onClose(\swoole_server $server, $fd, $from_id)
    {
        $this->counTable->incr($this->countKey,'close_count');
        unset($this->taskInfo[$fd]);
    }

    function __ShutDown()
    {
        $info = date("Y-m-d H:i:s") . " |ShutDown_Server| " . " #{$this->server->worker_id}>> received order shutdown now...!\n";
        echo $info;
        //QYF_Swoole_Server_Log::log($info);
        return $this->server->shutdown();
    }

    private function __Reload($fd)
    {
        if ($this->config['task_tmpdir'] && !file_exists($this->config['task_tmpdir'])) {
            mkdir($this->config['task_tmpdir'], 0777);
        }
        
        $info = date("Y-m-d H:i:s") . " |Reload_Server| " . " #{$this->server->worker_id}>> received order reload now ...!\n";
        echo $info;

        $infoMsg = array();
        $reloadrs = $this->server->reload();
        if ($reloadrs) {
            $infoMsg['msg'] = '重启成功';
            $infoMsg['code'] = 0;
        } else {
            $errcode = swoole_errno();
            $errstr = swoole_strerror($errcode);

            $info = date("Y-m-d H:i:s") . " |Reload_Server| " . " #{$this->server->worker_id}>> reload faild, error code : {$errcode}, error msg : {$errstr}";
            $infoMsg['msg'] = '重启失败';
            $infoMsg['code'] = 700101;
        }
        $infoMsg['info'] = $info;

        //QYF_Swoole_Server_Log::log($info);
        return $this->sendInfo($this->server, $fd, $infoMsg);
    }

    /**
     * 管理命令
     * @param  [type] $cmd [description]
     * @return [type]      [description]
     */
    function adminCtrlCMD($cmd, $serv, $fd)
    {
        $ret = array();
        switch ($cmd) 
        {
            case 'shutdown':
            case 'close':
                $this->__ShutDown();
                $ret['msg'] = 'shutdown success';
                $ret['code'] = 0;
                $ret['info'] = '';
                break;

            case 'reload':
                return $this->__Reload($fd);
                break;

            case 'ping':
            case 'info':
                $ret['msg'] = '连接正常';
                $ret['code'] = 0;
                $ret['info'] = $this->config;
                $ret['info']['__HOST'] = \Constant::SERVER_HOST;
                $ret['info']['__PORT'] = \Constant::SERVER_PORT;
                $ret['info']['__HOSTNAME'] = gethostname();
                $ret['info']['__OX'] = php_uname();
                $ret['info']['connections-count'] = count($serv->connections);

                $countdata = $this->counTable->get($this->countKey);
                $ret['info']['finish-count'] = $countdata['fin_count'];
                $ret['info']['receive-count'] = $countdata['recv_count'];
                $ret['info']['close-count'] = $countdata['close_count'];
                break;
            
            default:
                $ret['msg'] = '命令错误参数错误';
                $ret['code'] = 700010;
                break;
        }
        return $this->sendInfo($serv, $fd, $ret);
    }

    final function __destruct()
    {
        $this->server->shutdown();
    }
}
?>