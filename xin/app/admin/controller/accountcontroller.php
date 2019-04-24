<?php
namespace Xin\App\Admin\Controller;

use Phalcon\Db\Column;  
use Xin\App\Admin\Model\Account;
use Xin\App\Admin\Model\AccountToRole;
use Xin\App\Admin\Model\Role;
use Xin\Lib\SqlHelper;
use Xin\Lib\Utils;
use Xin\Model\Config;
use Xin\Model\ContainerCluster;
use Xin\Model\ContainerUser;
use Xin\Model\ServiceStorage;
use Phalcon\Mvc\Model\Criteria;
use Xin\Module\User\Model\User;
use Xin\Module\busine\model\Busine;

/**
 * 管理员账号管理
 * Class AccountController
 * @package Xin\App\Admin\Controller
 */
class AccountController extends \Phalcon\Mvc\Controller
{
    public function loginAction()
    {

        $auth = $this->getDI()->get('auth');
        $forward = $this->di->get('config')->defaultRouter;
        if ($this->request->isPost()) {
            if (!$this->security->checkToken()) {
                return new \Xin\Lib\MessageResponse("重复提交表单,请返回重试");
            }
            $username = $this->request->getPost('username');
            $password = $this->request->getPost('password');
            $param=[
                "username"=>$username,
                "password"=>$password,
            ];
            try {
               // $auth->signInWithKey($username, $password);
               $url=$this->config['curlapi']."cmsapi-checkuser.html";
               $result=Utils::curlPost($param,$url,true);
               header("content-type:text\json;charset=utf-8");
                if($result==false){
                    return json_encode(array('code'=>500,'msg'=>"接口出错"));
                }
                $result=json_decode($result,true);
                if($result['code']!=200){
                    return new \Xin\Lib\MessageResponse($result['msg'], 'error');
                }
                //TODO 这里判断url前缀是否属于本站
                $_forward = $this->request->getPost('forward');
                return $this->response->redirect($_forward?$_forward:$forward);
            } catch (\Exception $e) {
                return new \Xin\Lib\MessageResponse($e, 'error');
            }
        }
    }

    public function logoutAction()
    {
        $auth = $this->getDI()->get('auth');
        $auth->removeTicket();
        $forward = $this->di->get('config')->defaultRouter;
        return $this->response->redirect($forward);
    }

    public function listAction()
    {
        $keyword = $this->request->get("keyword");
        $roleid = $this->request->getQuery("roleid");
        $this->view->setVar('keyword', $keyword);

        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        $build= new \Phalcon\Mvc\Model\Query\Builder();

        if($role->rolename == Config::getValByKey('BUSINESS_ROLE')) {
            //列出当前业务员底下客服人员
            $build=$build->from(['a' => 'admin:Account'])
                ->innerJoin('user:User','a.uid=u.id','u')
                ->innerJoin('busine:Busine','u.id=bs.subordinate_uid','bs')
                ->leftJoin('admin:AccountToRole','ar.user_id=a.uid','ar')
                ->where('bs.busine_uid=:busineuid:',['busineuid'=>$uid]);
            
        } else {
            $build=$build->from(['a' => 'admin:Account'])
                ->innerJoin('user:User','a.uid=u.id','u')
                ->leftJoin('admin:AccountToRole','ar.user_id=a.uid','ar');
        }
        if($keyword){
            $build=$build->andWhere('u.username like \'%'.SqlHelper::escapeLike($keyword).'%\'');
        }
        if($roleid){
            $build=$build->andWhere('ar.role_id=:roleid:',['roleid'=>$roleid]);
        }
        $count=$build->columns('count(*) as count')->getQuery()->execute()->getFirst()['count'];

        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start,$limit)=Utils::offset(null,$pagination->pageSize());
        if (!$pagination->recordCount() || $start>=$pagination->recordCount()) {
            return;
        }

        $this->view->setVar('rolelist',Role::findFillWithKey('id'));

        $rs=$build->columns('u.id,u.username,u.email,u.status as user_status,a.create_time,a.update_time,a.status,a.lastloginip,a.lastlogintime,group_concat(ar.role_id) as roleids')
            ->orderBy('u.id desc')
            ->groupBy('a.uid')
            ->limit($limit,$start)
            ->getQuery()
            ->execute();

        $this->view->setVar('business_role_flag', $role->rolename == Config::getValByKey('BUSINESS_ROLE'));
        $this->view->setVar('objectlist', $rs->toArray());
    }

    public function createAction()
    {
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        if ($this->request->isPost()) {
            $data = $_POST;
            if(!$data['roleid'] || !is_array($data['roleid'])){
                return new \Xin\Lib\MessageResponse('请选择角色');
            }
            $roleidArr=$data['roleid'];

            if(Role::count(['id in ({id:array})','bind'=>['id'=>$roleidArr]])!=count($roleidArr)){
                return new \Xin\Lib\MessageResponse('选择了无效的角色');
            }
            //判断用户是否存在
            //如果用户不存在就创建一个新用户只能在后台登录,前台登录不了
            $user=User::findFirstByUsername($data['username']);
            if($user){
                return new \Xin\Lib\MessageResponse('用户名已存在');
            }
        
            $user = new User();
            $salt = $this->security->getSaltBytes(1);
            $user->nickname=$user->username=$data['username'];
            $user->salt=$salt;
            $user->reg_ip=$this->request->getServer('REMOTE_ADDR');
            $user->status=$data['status'];
          
            $user->email=$data['email'].'(后台)';
            $user->password='';//md5(md5($data['password'] ). $salt);
            $user->avatar=$user->score=$user->money=0;
            $account=new Account();
            $account->mobile=$data['mobile'];
            $account->password=$this->security->hash($data['password']);
            $account->settings=$data['settings'];
            $account->status=$data['status'];

            $this->db->begin();
            // 执行一些操作
            $flag=(function() use ($user,$account,$roleidArr){    
                try{
                    if($user->save()===false){
                        $this->di->get('logger')->error(implode(";",$user->getMessages()));
                        return;
                    }
                    
                    $account->uid=$user->id;
                    if($account->save()===false){
                        $this->di->get('logger')->error(implode(";",$account->getMessages()));
                        return;
                    }

                    foreach($roleidArr as $roleid){
                        $rel=new AccountToRole();
                        $rel->role_id=$roleid;
                        $rel->user_id=$user->id;
                        if($rel->save()===false){
                            $this->di->get('logger')->error(implode(";",$rel->getMessages()));
                            return;
                        }
                    }

                }catch(\Exception $e){
                    $this->di->get('logger')->error($e->getMessage());
                    return false;
                }
                return true;
            })();

            //业务员与客服人员关联起来
            if($role->rolename == Config::getValByKey('BUSINESS_ROLE')) {
                $busine = new Busine();
                $busine->busine_uid = $uid;
                $busine->subordinate_uid = $user->id;
                if($busine->save()===false){
                    $this->di->get('logger')->error(implode(";",$busine->getMessages()));
                    return;
                }
            }
            if ($flag && $this->db->commit()) {
                return new \Xin\Lib\MessageResponse("信息已保存",'succ');
            } else {
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse("创建失败！");
            }
        }

        if($role->rolename != Config::getValByKey('BUSINESS_ROLE')) {
            $roles = Role::find(["status!=?0", 'bind' => [Role::COLUMN_STATUS_DELETE]]);
        } else {
            //当前业务员只能创建客服人员角色的用户
            $roles = Role::find(["rolename=?0 and status!=?1", 'bind' => [Config::getValByKey('SUBORDINATE_ROLE'),Role::COLUMN_STATUS_DELETE]]);
        }

        $this->view->setVar('roles', $roles->toArray());
    }

    public function editAction()
    {
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        $id = $this->request->getQuery('id', 'int', 0);
        if ($id < 1 || (!$user = User::findFirstById($id)) || (!$account = Account::findFirstByUid($id))) {
            return new \Xin\Lib\MessageResponse('未找到有效的记录');
        }

        if ($this->request->isPost()) {
            $data = $_POST; 

            $accountdata=[
                'status'=>$data['status'],
                'mobile'=>$data['mobile']
            ];
            $userdata=[
                'email'=>$data['email']
            ];
            
            if(!$data['roleid'] || !is_array($data['roleid'])){
                return new \Xin\Lib\MessageResponse('请选择角色');
            }
            $roleidArr=$data['roleid'];
            if(Role::count(['id in ({id:array})','bind'=>['id'=>$roleidArr]])!=count($roleidArr)){
                return new \Xin\Lib\MessageResponse('选择了无效的角色');
            }

            if (!empty($data['password']) && $data['password']!=$data['oldpassword']) {
                 //验证旧的密码
                if (!empty($data['oldpassword'])) {
                    if (!$this->di->get('security')->checkHash($data['oldpassword'], $account->password)) {
                        return new \Xin\Lib\MessageResponse('输入了错误的旧密码！');
                    }
                } else {
                    return new \Xin\Lib\MessageResponse('请输入旧的密码！');
                }
                $accountdata['password']=$this->security->hash($data['password']);
            }

            // $this->db->begin();
            // 执行一些操作
            // $flag=(function() use ($id,$user,$account,$accountdata,$userdata,$roleidArr){    
                try{
                    if(!$user->save($userdata)){
                        $this->di->get('logger')->error(implode(";",$user->getMessages()));
                        return;
                    }
                    $account->status=$accountdata['status'];
                    $account->mobile=$accountdata['mobile'];
                    if($account->save()===false){
                        $this->di->get('logger')->error(implode(";",$account->getMessages()));
                        return;
                    }  
                    
                    foreach(AccountToRole::find(['user_id=?0','bind'=>[$id]]) as $r){
                        if(!$r->delete()){
                            $this->di->get('logger')->error('删除角色关系失败');
                            return;
                        }  
                    }
                    
                    foreach($roleidArr as $roleid){
                        $rel=new AccountToRole();
                        $rel->role_id=$roleid;
                        $rel->user_id=$user->id;
                        if($rel->save()===false){
                            $this->di->get('logger')->error(implode(";",$rel->getMessages()));
                            return;
                        }
                    }
                    
                }catch(\Exception $e){
                    $this->di->get('logger')->error($e->getMessage());
                    return false;
                }
                // return true;
            // })();
            

            // if ($flag && $this->db->commit()) {
                return new \Xin\Lib\MessageResponse("信息已保存",'succ');
            // } else {
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse("保存失败！");
            // }
            
        }

        if($role->rolename != Config::getValByKey('BUSINESS_ROLE')) {
            $roles = Role::find(["status!=?0", 'bind' => [Role::COLUMN_STATUS_DELETE]]);
        } else {
            //当前业务员只能创建客服人员角色的用户
            $roles = Role::find(["rolename=?0 and status!=?1", 'bind' => [Config::getValByKey('SUBORDINATE_ROLE'),Role::COLUMN_STATUS_DELETE]]);
        }

        $this->view->setVar('roles', $roles->toArray());
        $accountdata=$account->toArray();
        $accountdata['roles']=$account->getRole()->toArray();
        $this->view->setVar('user', $user->toArray());
        $this->view->setVar('account', $accountdata);
    }

    public function deleteAction()
    {
        $id = $this->request->getPost('id');
        $idList= is_array($id)? $id :[$id];
        if(!Utils::isNumericArray($idList)){            
            return new \Xin\Lib\MessageResponse('无效的参数');
        }
        if(in_array(1,$idList)){
            return new \Xin\Lib\MessageResponse('内置管理员不允许删除');
        }
        $batchflag=true;
        foreach($idList as $id){
            if ($id < 1 || !($user = User::findFirstById($id)) || !($account = Account::findFirstByUid($id))) {
                $this->di->get('logger')->warning('未找到有效的记录User.id='.$user.' ,account.id='.$id);
                $batchflag=false;
                continue;
            }
            
            $this->db->begin();
            // 执行一些操作
            $flag = (function () use ($user, $account) {
                try {
                    if (!$user->delete()) {
                        $this->di->get('logger')->error(implode(";", $user->getMessages()));
                        return false;
                    }
                    foreach($account->getAccountToRole() as $rel){
                        if (!$rel->delete()) {
                            $this->di->get('logger')->error(implode(";", $rel->getMessages()));
                            return false;
                        }
                    }
                    if (!$account->delete()) {
                        $this->di->get('logger')->error(implode(";", $account->getMessages()));
                        return false;
                    }
                } catch (\Exception $e) {
                    $this->di->get('logger')->error($e->getMessage());
                    return false;
                }
                return true;
            })();

            /*
             * 如果删除用户角色属于客服人员，
             * 同时删除业务员与客服人员的关联
             */
            $busine = Busine::findFirstBySubordinate_uid($id);
            if($busine) {
                if (!$busine->delete()) {
                    $this->di->get('logger')->error(implode(";", $account->getMessages()));
                    return false;
                }
            }

            if (!$flag || !$this->db->commit()) {
                $this->db->rollback();
                $batchflag=false;
            }
        }

        if ($batchflag) {
            return new \Xin\Lib\MessageResponse("选定信息已删除",'succ');
        } else {
            return new \Xin\Lib\MessageResponse("部分信息删除失败");
        }

    }

}
