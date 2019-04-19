<?php

namespace Xin\Module\User\Controller;

use Xin\Lib\SqlHelper;
use Xin\Lib\Utils;
use Xin\Module\User\Model\User;
use Phalcon\Db\Column;
use Xin\Lib\HttpClient;

class UserController extends \Phalcon\Mvc\Controller
{
    public function listAction()
    {
        $keyword = $this->request->get("keyword");
        $groupid = $this->request->getQuery("groupid");
        $this->view->setVar('keyword', $keyword);
        
        $build= new \Phalcon\Mvc\Model\Query\Builder();
        $build=$build->from(['user:User'])
            ->columns('count(*) as count');            
        if($keyword){
            $build=$build->where('username like \'%'.SqlHelper::escapeLike($keyword).'%\'');
        }             
        //if($groupid){
        //    $build=$build->andWhere('group_id=:groupid:',['groupid'=>$groupid]);
        //}
        $count=$build->getQuery()->execute()->getFirst()['count'];
            
        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start,$limit)=Utils::offset(null,$pagination->pageSize());
        if (!$pagination->recordCount() || $start>=$pagination->recordCount()) {
            return;
        }

        //$this->view->setVar('rolelist',Role::findFillWithKey('id'));

        $rs=$build->columns('*')
            ->orderBy('id desc')
            ->limit($limit,$start)
            ->getQuery()
            ->execute();        
        $this->view->setVar('objectlist', $rs->toArray());
    }

    /**
     * @todo 创建前台用户
     */
    public function createAction()
    {
        if ($this->request->isPost()) {
            $data = $_POST;
            //判断用户是否存在
            $user = User::findFirstByUsername($data['username']);
            if ($user) {
                return new \Xin\Lib\MessageResponse("对不起，用户已存在！");
            }
            $user = new User();
            $auth = $this->getDI()->get('auth');
            $salt = $auth->security->getSaltBytes(1);
            $user_data = array(
                'user_type' => User::TYPE_FRONT,
                'nickname' => $data['nickname'],
                'username' => $data['username'],
                'salt' => $salt,
                'reg_ip' => $this->request->getServer('REMOTE_ADDR'),
                'create_time' => time(),
                'status' => $data['status'],
                'mobile' => $data['mobile']
            );
            if ($data['password'] == $data['repassword']) {
                $user_data['password'] = md5($data['password'] . $salt);
            }
            if ($user->create($user_data)) {
                $insertId = $user -> getWriteConnection() -> lastInsertId($user -> getSource());
                $account = new Account();
                $account->status=Account::STATUS_AVAILABLE;
                $account->password=$this->security->hash($data['password']);
                $account->uid=$insertId;

                try {
                    if (!$flag=$account->create()) {
                        $this->logger->error(implode(';', $account->getMessages()));
                    }
                }catch(Exception $e){
                    $this->logger->error($e->getMessage());
                }
                return new \Xin\Lib\MessageResponse("创建成功！", 'succ');
            } else {
                return new \Xin\Lib\MessageResponse("创建失败！");
            }

        }
    }

    /**
     * 编辑前台用户信息
     */
    public function editAction()
    {
        $id = $this->request->getQuery('id', 'int', 0);
        $user = User::findFirst(array(
            "id =:id:",
            "bind" => array('id' => $id),
            "bindTypes" => array('id' => Column::BIND_PARAM_INT)
        ));
        if ($id < 1 || !$user) {
            return new \Xin\Lib\MessageResponse('未找到有效的记录');
        }
        $user = User::findFirst(array(
            "id =:id:",
            "bind" => array('id' => $id),
            "bindTypes" => array('id' => Column::BIND_PARAM_INT)
        ));
        if ($this->request->isPost()) {
            $data = $_POST;
            $user_data = $data;
            $user_save_data = array('nickname', 'status', 'mobile');
            if (!empty($data['password'])) {
                if ($data['password'] == $data['repassword']) {
                    $auth = $this->getDI()->get('auth');
                    $salt = $auth->security->getSaltBytes(1);
                    $user_data['salt'] = $salt;
                    $user_save_data[] = 'salt';
                    $user_save_data[] = 'password';
                    $user_data['password'] = md5($data['password'] . $salt);
                } else {
                    return new \Xin\Lib\MessageResponse('两次密码不一致！');
                }
            }
            if ($user->save($user_data, $user_save_data)) {
                return new \Xin\Lib\MessageResponse('修改成功！', 'succ');
                $this->view->setVar('isSaved', 1);
            } else {
                return new \Xin\Lib\MessageResponse($user->getMessages());
            }
        }
        $this->view->setVar('user', $user->toArray());
    }

    /**
     * 查看前台用户信息
     */
    public function detailAction()
    {
        $id = $this->request->getQuery('id', 'int', 0);
        if ($id > 0) {
            $user = User::findFirst(array(
                "id =:id:",
                "bind" => array('id' => $id),
                "bindTypes" => array('id' => Column::BIND_PARAM_INT)
            ));
            if ($id < 1 || !$user) {
                $this->flash->error('未找到有效的记录！');
            }
            $this->view->setVar('user', $user->toArray());
        } else {
            $this->flash->error('参数不正确！');
        }
    }

    /**
     * 删除管理员 --软删除，status 置为1即为删除
     */
    public function changeStatusAction()
    {
        $id = $_POST['id'];
        $user = User::findFirst(array(
            "id =:id:",
            "bind" => array('id' => $id),
            "bindTypes" => array('id' => Column::BIND_PARAM_INT)
        ));
        if ($id < 1 || !$user) {
            echo json_encode(array('code' => -2, 'message' => '未找到有效的记录！'));
        } else {
            if ($user->save(array('status' => $this->request->getPost('status', 'int', 0)))) {
                echo json_encode(array('code' => 1, 'message' => '操作成功！'));
            } else {
                echo json_encode(array('code' => -1, 'message' => '操作失败！'));
            }
        }
    }
}