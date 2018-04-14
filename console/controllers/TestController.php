<?php
namespace console\controllers;
use yii\console\Controller;
/**
 * Test controller
 */

class TestController extends Controller {



    public function actionTestindex($uid){    
        echo "ok1";
        tools::hello($uid);
        //file_put_contents("/tmp/index.txt", "TestindexTestindex{$uid}");
    }    

    public function actionTestindex2(){        
        \Yii::$app->runAction('test/testindex', [1]);
    }         

}
?>
