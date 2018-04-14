<?php
namespace frontend\controllers\h5;
use Yii;
use app\Base\Functions;
use app\Base\SwooleClientMain;
use app\Base\UmengApi;
use app\Base\Jssdk;
use app\Base\RongcloudApi;
use frontend\models\Share;
use frontend\models\City;
use frontend\models\User;
use frontend\models\ActCategory;
use frontend\models\ActSignUp;
use frontend\models\Appeal;
use frontend\models\CourseLike;
use frontend\models\CoursePrice;
use yii\web\Controller;
use yii\log\Logger;
use yii\caching\DbDependency;
use yii\caching\ChainedDependency;
use yii\web\UploadedFile;
use common\aliyunOss\AliyunOss;
use char0n\ffmpeg\test;
/**
 * 主页 controller
 */
class SiteController extends Controller
{
    public $enableCsrfValidation = false;            
    public $layout = false;

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }


    /*
     * 首页     
     * */
    public function actionError(){                        
               
        return $this->render('error',
            [                               
            ]
        );               
    }


    /*
     * 测试swoole   
     * */
    public function actionTestswoole(){           
        //带参数,'app'=>\Yii::$app
        //serialize(array('one'=>array('a'=>'test/testindex','p' => array(3181374),'app'=>\Yii::$app)));        
        $rs = SwooleClientMain::invokeAPI(array('one'=>array('a'=>'test/testindex','p' => array(3181374),'apptype'=>'h5')));
        //不带参数
        //$rs = SwooleClientMain::invokeAPI(array('one'=>array('a'=>'test/testindex','p' => array())));
        var_dump($rs);
    }                          
}