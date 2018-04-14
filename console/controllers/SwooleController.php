<?php
namespace console\controllers;
use yii\console\Controller;
use app\Base\SwooleServer;
/**
 * SwooleController
 */

class SwooleController extends Controller {
    private $sw = null;    
    //启动swoole服务端
    public function actionStart(){    
        $this->sw = new SwooleServer();                               
        $this->sw->startServer();
    }  

    //启动swoole服务端
    public function actionShutdown(){                                   
        $this->sw->__ShutDown();
    }                                               
}
?>
