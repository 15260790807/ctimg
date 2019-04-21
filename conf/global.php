<?php
/* error_reporting(E_ALL);
echo "<pre />";
var_dump($_SERVER);
echo $_SERVER['REQUEST_SCHEME']."<br/>";
echo $_SERVER['REQUEST_SCHEME']."<br/>";
echo $_SERVER['SERVER_PROTOCOL']."<br/>";
echo $_SERVER['HTTP_HOST']."<br/>";
echo $_SERVER['SERVER_PORT']."<br/>";
echo $_SERVER['SERVER_PORT']."<br/>";
echo $_SERVER['SCRIPT_NAME']."<br/>"; */
$http_s=isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:$_SERVER['SERVER_PROTOCOL'];
//$str=$http_s.'://'.$_SERVER['HTTP_HOST']..dirname($_SERVER['SCRIPT_NAME']).'/uploads/';
$port_s=($_SERVER['SERVER_PORT']=='80'?'':':'.$_SERVER['SERVER_PORT']);
/* echo $http_s."--".$port_s; */
$myMakeUrl=$http_s."://".$_SERVER['HTTP_HOST'].$port_s;
/* echo $myMakeUrl; */
return new \Phalcon\Config([
    'base' => [
        'database' => [
            'default' => [
                'adapter' => 'Mysql',
                /*'host' => '127.0.0.1',
                'username' => 'root',
                'password' => 'root',*/
//                'dbname' => 'testbug',
//                'dbname' => 'dcat',
//                'dbname' => 'cfm_test',//本地测试
//                'dbname' => 'cfm',//本地测试
//                'dbname' => 'formal',//本地正式
                'charset' => 'utf8',

                //本地服务器
                /*'dbname' => 'centerimg',
                'prefix' => 'ct_',
                'host' => '127.0.0.1',
                'username' => 'root',
                'password' => 'root',*/
                //测试服务器
                
                'dbname' => 'bannerpr_cfm',
                'prefix' => 'dc_',
                'host' => '101.132.112.217',
                'username' => 'root',
                'password' => 'd12f41c162cb158c',
            ],
        ],
        'localdb' => [
            'default' => [
                'adapter' => 'Mysql',
                /*'host' => '127.0.0.1',
                'username' => 'root',
                'password' => 'root',*/
//                'dbname' => 'testbug',
//                'dbname' => 'dcat',
//                'dbname' => 'cfm_test',//本地测试
//                'dbname' => 'cfm',//本地测试
//                'dbname' => 'formal',//本地正式
                'charset' => 'utf8',

                //本地服务器
                /*'dbname' => 'centerimg',
                'prefix' => 'ct_',
                'host' => '127.0.0.1',
                'username' => 'root',
                'password' => 'root',*/
                //测试服务器
                
                'dbname' => 'ctimg',
                'prefix' => 'ct_',
                'host' => '127.0.0.1',
                'username' => 'root',
                'password' => 'lamp1234',
            ],
        ],
        'env'=>'prod',
        'visualUri' => 'index.php?_url=', //配合route的handle工作,如果采用重写，这里就设置基准目录就好.同时用于生成url
        'staticUri' => '/home/', //静态文件路径，可以是绝对地址或相对地址
        'defaultRouter' => 'admin/index/index',
        'cacheDir' => RUNTIME_DIR . '/data/',
        'log' => [
            'level' => Phalcon\Logger::ERROR,
            'path' => RUNTIME_DIR . "logs/debug.log",
        ],
        'security'=>[
            'salt'=>'eEAfR[N@DyaIP_2My|:+.u>/6m,$D'
        ],
        'webUrl'=>'',
        'module'=>[
            'accessory'=>[],
            'user'=>['disabled'=>false],
            'coupon'=>[],
            'bank' => [],
            'crontab' => [],
            'log' => [],
            'order'=>[],
            'model'=>[],
            'category'=>[],
            'document'=>[],
            'busine'=>[],
            'queue'=>[],
            'comment'=>[],
            'creditline'=>[],
            'express'=>[],
            'linkage'=>[],
            'product'=>[],
            'gallery'=>[
                'uploadUriPrefix'=>isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:$_SERVER['SERVER_PROTOCOL'].'://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT']=='80'?'':':'.$_SERVER['SERVER_PORT']).dirname($_SERVER['SCRIPT_NAME']).'/uploads/',
                'uploadDir'=>BASE_DIR.'/public/uploads/',
                'maxSize'=>2000 * 1024 * 1024,
                'preview'=>['org'=>'1024*1024','thumb'=>'320*320']
            ],
            'attachment'=>[
                'uploadUriPrefix'=>isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:$_SERVER['SERVER_PROTOCOL'].'://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT']=='80'?'':':'.$_SERVER['SERVER_PORT']).dirname($_SERVER['SCRIPT_NAME']).'/uploads/',
                'uploadDir'=>BASE_DIR.'/public/uploads/'
            ],
            'picture'=>[
               'uploadUriPrefix'=>$myMakeUrl.'/uploads/',
               //'uploadUriPrefix'=>'http://bd.ctimg.bd/uploads/',
                'uploadDir'=> BASE_DIR.'/public/uploads/',
                'extensions'=>'gif,jpg,jpeg,bmp,png',
                "cfmImgUrl"=>"http://www.china-flag-makers.com/",
            ],
            'operation'=>[],
            'auditlog'=>[],
            'integral'=>[],
            'config'=>[]
        ],
        'vendor'=>[
            'PHPMailer'=>'PHPMailer',
            'TCPDF'=>'TCPDF',
            'PhpOffice'=>'PhpOffice',
            'Psr'=>'Psr',
            'aliyun'=>'aliyun',
            'OSS'=>'oss'
        ]
    ],
    'application' => [
        'home' => [ 
        ],
        'admin' =>[
            'staticUri' => 'admin/',
            'defaultRouter' => 'admin/outstock/byorderitem',
        ]
    ]
]);