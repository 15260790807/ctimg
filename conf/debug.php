<?php
error_reporting(E_ALL & ~E_NOTICE);
$logger = $di->get('logger');

$di->setShared('modelsMetadata', function () use ($di) {
    return new Phalcon\Mvc\Model\Metadata\Memory();
});

//数据库
$eventsManager = new Phalcon\Events\Manager();
$eventsManager->attach('db', function ($event, $connection) use ($logger) {
    switch ($event->getType()) {
        case 'beforeQuery':
            $logger->log($connection->getSQLStatement(), \Phalcon\Logger::DEBUG);
            break;
    }
});
$di->get('db')->setEventsManager($eventsManager);
/*
$eventsManager=$di->get('view')->getEventsManager();
$eventsManager->attach("view:beforeRenderView", function ($event, $_view) {
    $_view->getDI()->getLogger()->debug($event->getType() . "\t" . var_export($_view->getActiveRenderPath(),1));
});*/
//$di->get('view')->setEventsManager($eventsManager);


//加载路径

// $eventsManager->attach("loader:beforeCheckPath", function ($event, $obj,$path) use ($di) {
//     $di->getLogger()->log("autoload finding === ".$obj->getCheckedPath(), \Phalcon\Logger::DEBUG);
// });
// $eventsManager->attach("loader:pathFound", function ($event, $obj,$path) use ($di) {
//     $di->getLogger()->log("autoload === ".$path, \Phalcon\Logger::DEBUG);
// });
// $di->get('loader')->setEventsManager($eventsManager);


//性能监听
$eventsManager->attach('application:boot',function(){
    global $__profiling;
    $__profiling=[];
    $__profiling['starttime']=microtime(true);
});
$app->setEventsManager($eventsManager);
