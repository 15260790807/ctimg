<?php

namespace Xin\App\Admin\Service;

use Phalcon\Db\Column;
use Phalcon\Mvc\User\Module;
use Xin\App\Admin\Model\Account;
use Xin\App\Admin\Model\UserToken;
use Xin\Module\User\Model\User;

class Auth extends Module
{
    const SESSION_KEY='auth-identity';
    protected $_ticket;
    protected $_autologin;

    public function isAuthorized()
    {
        $ticket=$this->getTicket();
        return $ticket && $ticket['uid'];
    }

    /**
     * 返回当前用户信息
     *
     * @return array (uid,nickname,username,settings,roles)
     */
    public function getTicket()
    {
        if($this->_ticket == null){
            $this->_ticket= $this->session->get(self::SESSION_KEY);
            !$this->_ticket && $this->_ticket=array();
        }
        return $this->_ticket;
    }

    protected function saveTicket($user) {

        $this->_ticket=$user;
        $this->session->set(self::SESSION_KEY,$this->_ticket );
    }

    public function removeTicket() {
        if ($this->cookies->has('RMT')) {
            $this->cookies->get('RMT')->delete();
        }
        $this->session->remove(self::SESSION_KEY);
        $this->session->destroy();
    }

    public function signInWithKey($username,$pwd){        
        $user = User::findFirstByUsername($username);

        if (!$user) {
            throw new \Exception('错误的用户名');
        }
        
        $adminUser = Account::findFirstByUid($user->id);
        if (!$adminUser || $adminUser->status!=Account::COLUMN_STATUS_ENABLE) {
            throw new \Exception('该管理账户不存在或被禁用');
        }

        if(!$this->security->checkHash($pwd, $adminUser->password)){
            throw new \Exception('错误的密码');
        }

        $adminUser->lastloginip = $this->request->getClientAddress();
        $adminUser->lastlogintime = time();
        if ($adminUser->save()===false) {
            $this->logger->error(implode(';',$adminUser->getMessages()));
        }

        $roles = array();
        foreach ($adminUser->getRole()->toArray() as $item) {
            $roles[$item['id']] = $item;
        }

        $this->saveTicket([
            'uid' => $user->id,
            'username' => $user->username,
            'settings' => $adminUser->settings,
            'roles'=>$roles
        ]);

        return true;
    }

    /**
     * 返回当前用户所属的用户组
     * @return array
     */
    public function getRoles() {
        $ident = $this->getTicket();
        return $ident && $ident['roles'] ? $ident['roles'] : array();
    }

    public function encodePwd($pwd,$salt){
        return md5(md5($pwd).$salt);
    }

    /**
     * @todo 返回当前用户拥有权限
     */
    public function getAccess($conditions, $bind) {
        $phql = "SELECT p.accesskey FROM admin:Privilege p
                 LEFT JOIN admin:Access a  ON p.id = a.privilege_id 
                 WHERE " . $conditions;
        $rs = $this->getDI()->get('modelsManager')->createQuery($phql)->execute($bind)->toArray();
        $access = [];
        if (!empty($rs)) {
            foreach ($rs as $item) {
                $access[] = implode('/', explode('.', $item['accesskey']));
            }
        }
        return $access;
    }

}