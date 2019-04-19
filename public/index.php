<?php
//phpinfo();exit;
use Phalcon\Logger;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Events\Manager;
use Phalcon\Session\Adapter\Files as Session;
use Phalcon\Http\Response\Cookies;
use Phalcon\Http\Request;



define('BASE_DIR', dirname(__DIR__) . '/');

$di = new \Phalcon\DI\FactoryDefault();
require BASE_DIR . 'xin/boot.php';

//默认路由设置
$di->set('router', function () use ($di) {
    $router = new \Phalcon\Mvc\Router(false);
    $router->removeExtraSlashes(true);
    $config = $di->get('config');
    
    list($m, $c, $a) = explode('/', $config->defaultRouter);
    $router->setDefaults([
        'namespace' => "Xin\\App\\{$m}\\Controller\\",
        'module' => $m,
        'controller' => $c,
        'action' => $a
    ]);
    $router->add("#^([\\w_]+)/([\\w_]+)/([\\w_]+)$#", [
        'namespace'=>1,
        'module' => 1,
        "controller" => 2,
        "action" => 3,
    ])->convert('namespace', function ($ns) {
        return 'Xin\\App\\' . $ns . '\Controller\\';
    });
    $router->add("#^([^/]+)/([^/]+)$#", [
        "controller" => 1,
        "action" => 2,
    ]);
    return $router;
}, true);


$di->setShared('viewCache', function () {
    $cache = new \Phalcon\Cache\Backend\File(
        new Phalcon\Cache\Frontend\Output(["lifetime" => 86400]),
        ["cacheDir" => RUNTIME_DIR . "/cache/"]
    );
    return $cache;
});

$di->setShared('session', function () {
    $session = new Session();
    $session->start();
    return $session;
});

$di->set('cookies', function () {
    $cookies = new Cookies();
    //$cookies->useEncryption(false); //禁用加密
    return $cookies;
});

$di->setShared('volt', function () use ($di) {
    $volt = new VoltEngine($di->get('view'), $di);
    $config = $di->get('config');
    $volt->setOptions([
        'compiledPath' => $config->cacheDir . 'volt/',
        'compiledSeparator' => '_',
        'compileAlways' => APP_LEVEL == Logger::DEBUG,
        'stat' => true, //开启文件变更判断，需在compileAlways=false时生效
    ]);
    $volt->getCompiler()->addExtension(new \Xin\Lib\ViewExtension($volt->getCompiler()));
    return $volt;
});

$di->set('view', function () use ($di) {
    $view = new View();
    $view->setDI($di);
    $eventsManager = new Manager();
    $eventsManager->attach("view:notFoundView", function ($event, $_view) {
        $_view->getDI()->getLogger()->error($event->getType() . "\t" . var_export($_view->getActiveRenderPath(),1));
    });
    $view->setEventsManager($eventsManager);
    $view->disableLevel(array(
        View::LEVEL_LAYOUT => true,
        View::LEVEL_MAIN_LAYOUT => true,
    ));
    $view->registerEngines(['.html' => 'volt']);
    return $view;
}, true);

$di->setShared('queue',function(){
    return new \Xin\Module\Queue\Service\QueueMysql();
});


$di->setShared('application', function() use ($di,$config){
    $application = new \Phalcon\Mvc\Application();
    $application->setDI($di);
    $eventsManager = new Phalcon\Events\Manager();
    $eventsManager->attach('application:beforeStartModule', function ($event, $app, $moduleName) use ($di, $config) {
        $moduleConfig = $config->application[$moduleName];
        if ($moduleConfig && count($moduleConfig) > 0) {
            $_config = $di->get("config");
            $_config = $_config->merge($moduleConfig);
            $di->setShared('config', $_config);
        }

        if (APP_LEVEL == Logger::DEBUG) include CONFIG_DIR . 'debug.php';
        $modelManager=$di->getModelsManager();
        $mergedConfig = $_config ? $_config : $config->base;
        foreach($mergedConfig->module as $k=>$v){
            if($v['disabled']) continue;
            $modelManager->registerNamespaceAlias($k, '\Xin\Module\\'.$k.'\Model');
        }
        //设置链接基础路径及静态资源
        $di->get('url')->setBaseUri($mergedConfig->visualUri);
        if ($mergedConfig->staticUri) {
            $di->get('url')->setStaticBaseUri(rtrim($mergedConfig->staticUri, '/') . '/');
        }
    });
    $eventsManager->attach('application:beforeHandleRequest', function ($event, $app,$dispatcher) use ($di, $config) {
        $module = $dispatcher->getModuleName();
        $controller=$dispatcher->getControllerName();
        //本地应用中不存在时尝试调用模块中控制器
        $handlerClass = $dispatcher->getHandlerClass();
        $skin=$di->get('config')['skin'].'/';
        if (!$di->has($handlerClass) && !class_exists($handlerClass)) {
            $dispatcher->setNamespaceName('Xin\Module\\' . $controller . '\Controller');
            //$dispatcher->setControllerName();
            $di->get('view')->setViewsDir([
                XIN_DIR . 'module/' . $controller . '/view/',
                XIN_DIR . 'app/' .$module.'/view/'.$skin,
            ]);            
        } else {
            $di->get('view')->setViewsDir([
                XIN_DIR . 'app/' . $module . '/view/'.$skin,
            ]);
        }
    });
    
    //拦截请求响应为json的，自动组装为json格式返回
    $eventsManager->attach('application:viewRender', function ($event, $application,$view) use ($di) {
        if($_REQUEST['_format']=='json'){
            $vars=$view->getParamsToView();
            $data=['status'=>'ok','data'=>$vars];
            $view->setContent(json_encode($data,JSON_UNESCAPED_UNICODE));       
            return false;
        }
    });
    $application->setEventsManager($eventsManager);
    $apps = [];
    foreach ($config->application as $k => $v) {
        $apps[$k] = [
            'className' => '\\Xin\\App\\' . $k . '\App',
        ];
    }
    $application->registerModules($apps);
    return $application;
});
try {
    echo $di->getApplication()->handle()->getContent();
} catch (\Exception $e) {
    $di->getLogger()->critical($e->getMessage() . ":" . $e->getTraceAsString());
    echo $e->getMessage();
}
