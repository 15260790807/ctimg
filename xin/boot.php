<?php
use Phalcon\Mvc\Model\Metadata\Files as MetaDataAdapter;
use Phalcon\Events\Manager;
use Phalcon\Http\Request;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as LoggerAdapter;

define('XIN_DIR', BASE_DIR . 'xin/');
define('CONFIG_DIR', BASE_DIR . 'conf/');
define('RUNTIME_DIR', BASE_DIR . 'var/');
define('VENDOR_DIR', BASE_DIR . 'vendor/');

$config = require CONFIG_DIR . 'global.php';
$request = new Request();
define('APP_LEVEL', $config->base->log->level);
define('WEB_URL',rtrim($config->base['webUrl']? $config->base['webUrl']:(isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:$_SERVER['SERVER_PROTOCOL'].'://'.$_SERVER['HTTP_HOST'].($_SERVER['SERVER_PORT']=='80'?'':':'.$_SERVER['SERVER_PORT']).dirname($_SERVER['SCRIPT_NAME'])),'/').'/');

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($di) {
    if (!(error_reporting() & $errno)) {
        return;
    }
    if (in_array($errno, array(1, 4, 16, 64))) {
        $level = Logger::CRITICAL;
    } elseif (in_array($errno, array(32, 128))) {
        $level = Logger::WARNING;
    } elseif ($errno == 8) {
        $level = Logger::NOTICE;
    } else {
        $level = Logger::ERROR;
    }
    $di->getLogger()->log($errstr . ' ' . $errfile . ':' . $errline, $level);
    return true;
});

require XIN_DIR . 'lib/loader.php';
//使用自定义加载器，强制对类文件名做小写转换
//并注册插件的命名空间
$loader = new \Xin\Lib\Loader();
$defaultNS=['xin' => XIN_DIR];
if($config->base['vendor']){
    foreach($config->base->vendor as $k=>$v){
        $defaultNS[$k]=VENDOR_DIR.$v;
    }
}
$loader->registerNamespaces($defaultNS);
$loader->register();

$di->setShared('config', $config->base);//设置默认的配置
$di->setShared('loader', $loader);

$di->setShared('logger', function () use ($di) {
    $config = $di->get('config');
    $logger = new LoggerAdapter($config->log->path);
    $logger->setLogLevel(APP_LEVEL);
    return $logger;
});

$di->setShared('db', function () use ($di) {
    $config = $di->get('config');
    $dbconfig = $config->database->default;
    $dbclass = '\Xin\Lib\\' . $dbconfig->adapter;
    $connection = new $dbclass(array(
        'host' => $dbconfig->host,
        'username' => $dbconfig->username,
        'password' => $dbconfig->password,
        'dbname' => $dbconfig->dbname,
        "options" => array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $dbconfig->charset,
        )
    ));
    return $connection;
});
/* $di->setShared('localdb', function () use ($di) {
    $config = $di->get('config');
    $dbconfig = $config->localdb->default;
    $dbclass = '\Xin\Lib\\' . $dbconfig->adapter;
    $connection = new $dbclass(array(
        'host' => $dbconfig->host,
        'username' => $dbconfig->username,
        'password' => $dbconfig->password,
        'dbname' => $dbconfig->dbname,
        "options" => array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $dbconfig->charset,
        )
    ));
    return $connection;
}); */
$di->setShared('modelsMetadata', function () use ($di) {
    $config = $di->get('config');
    return new MetaDataAdapter(array(
        'metaDataDir' => $config->cacheDir . 'meta/'
    ));
});

$di->setShared('modelsManager', function () use ($di) {
    $config = $di->get('config');
    $modelsManager = new \Xin\Lib\ModelManager();
    $modelsManager->registerNamespaceAlias('_', '\Xin\Model');
    $modelsManager->setModelPrefix($config->database->default->prefix);
    $eventsManager = new Phalcon\Events\Manager();
    $eventsManager->attach('model:beforeValidationOnCreate', function ($event, $model) {
        $attrs=$model->getModelsMetaData()->getAttributes($model);
        in_array('create_time',$attrs) && $model->create_time=time();
        in_array('update_time',$attrs) && $model->update_time=time();
    });
    $eventsManager->attach('model:beforeValidationOnUpdate', function ($event, $model) {
        $attrs=$model->getModelsMetaData()->getAttributes($model);
        in_array('update_time',$attrs) && $model->update_time=time();
    });
    $modelsManager->setEventsManager($eventsManager);
    return $modelsManager;
});

$di->setShared('crypt', function () use ($di) {
    $config = $di->get('config');
    $crypt = new Phalcon\Crypt();
    $crypt->setKey($config->security->salt);
    return $crypt;
});

$di->setShared('modelsCache', function () use ($di) {
    $config = $di->get('config');
    $frontCache = new Phalcon\Cache\Frontend\Data([
        'lifetime' => 3600
    ]);
    $cache = new Phalcon\Cache\Backend\File($frontCache, [
        "cacheDir" => $config->cacheDir . "/cache/",
    ]);
    return $cache;
});

