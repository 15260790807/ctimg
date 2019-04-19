<?php

namespace Xin\App\Admin;

use Phalcon\Events\Manager;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Logger;
use Xin\Lib\Utils;
use Xin\Module\User\Model\User;
use Phalcon\Http\Request;
class App
{
    public function registerAutoloaders($di)
    {
    }

    public function registerServices($di)
    {
        $di->getModelsManager()->registerNamespaceAlias('admin', '\Xin\App\Admin\Model');

        $di->set('acl', function () {
            return new Service\Acl();
        }, true);
        $di->set('auth', function () {
            return new Service\Auth();
        }, true);
        
        $eventsManager = new Manager();
        $eventsManager->attach('dispatch:beforeDispatch', function ($event, $dispatcher) use ($di) {
            if ($dispatcher->wasForwarded()) {
                return;
            }

            //转向不鉴权
            $c = $dispatcher->getControllerName();
            $a = $dispatcher->getActionName();
            $m = $dispatcher->getModuleName();
            $auth = $di->get('auth');

            //审计
            if($di->get('config')['env']!='dev'){
                $d=[];
                $_POST && $d['POST']=$_POST;
                $d['GET']=$_GET;
                unset($d['GET']['_url']);
                if(!$d['GET']){unset($d['GET']);}
                if($d['POST']){ //目前只记录保存
                    $request=new Request();
                    $auditlog=new Model\Auditlog();
                    if($auth->getTicket()['uid']){
                        $auditlog->uid=$auth->getTicket()['uid'];
                    }else{
                        if ($c == 'account' && in_array($a,['login'])) {
                            $username=$d['POST']['username'];
                            $user=User::findFirstByUsername($username);
                            $auditlog->uid=$user->id;
                        }
                    }
                    $auditlog->action= $c.'.'.$a;
                    $auditlog->params=json_encode($d);
                    $auditlog->create_date=date('ymd');
                    $auditlog->ip=$request->getClientAddress();

                   // $auditlog->save();
                }
            }
            if ($c == 'account' && in_array($a,['login','logout'])) {
                return;
            }

            if (!$auth->isAuthorized()) {
                $resp=new \Xin\Lib\MessageResponse('请先登陆系统', 'error', ['登录'=>Utils::url('admin/account/login')]);
                echo $resp->getContent();
                exit;
            }
            //判断用户权限
            if (!$di->get('acl')->isAllowed(null, $m.'/'. $c, $a)) {
                $resp= new \Xin\Lib\MessageResponse('您无权限访问该页面', 'error');
                echo $resp->getContent();
                exit;
            }

        });

        $di->getShared('dispatcher')->setEventsManager($eventsManager);
        
        $di->getShared('config')['skin']='';
      
    }

}
