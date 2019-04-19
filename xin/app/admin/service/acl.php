<?php
namespace Xin\App\Admin\Service;

use Xin\Lib\SqlHelper;
use Xin\App\Admin\Service\Auth as auth;
use Xin\App\Admin\Model\Menu;
use Xin\App\Admin\Model\Access;
use Xin\App\Admin\Model\Privilege;
use Xin\Lib\Utils;

class Acl extends \Phalcon\Di\Injectable implements \Phalcon\Acl\AdapterInterface
{
    private $_rootUserId = 1;
    private $_publicAccessKeys = array();
    protected $_defaultAccess = 0;
    protected $_accessList;

    public function setDefaultAction($defaultAccess)
    {
        $this->_defaultAccess = $defaultAccess;
    }

    /**
     * Returns the default ACL access level
     */
    public function getDefaultAction()
    {
        return $this->_defaultAccess;
    }

    /**
     * Adds a role to the ACL list. Second parameter lets to inherit access data from other existing role
     * @return bool
     */
    public function addRole($role, $accessInherits = null)
    {
    }

    /**
     * Do a role inherit from another existing role
     * @return bool
     */
    public function addInherit($roleName, $roleToInherit)
    {
    }

    /**
     * Check whether role exist in the roles list
     * @return bool
     */
    public function isRole($roleName)
    {
    }

    /**
     * Check whether resource exist in the resources list
     * @return bool
     */
    public function isResource($resourceName)
    {
    }

    /**
     * Adds a resource to the ACL list
     *
     * Access names can be a particular action, by example
     * search, update, delete, etc or a list of them
     * @return bool
     */
    public function addResource($resourceObject, $accessList)
    {
    }

    /**
     * Adds access to resources
     * @return bool
     */
    public function addResourceAccess($resourceName, $accessList)
    {
    }

    /**
     * Removes an access from a resource
     */
    public function dropResourceAccess($resourceName, $accessList)
    {
    }

    /**
     * Allow access to a role on a resource
     */
    public function allow($roleName, $resourceName, $access, $func = null)
    {
        if ($roleName == '*') $this->_publicAccessKeys[] = strtolower($resourceName . '.' . $access);
    }

    /**
     * Deny access to a role on a resource
     */
    public function deny($roleName, $resourceName, $access, $func = null)
    {;
    }

    /**
     * 检查当前用户是否有权限
     * @param string $roleName 角色id，多个用逗号分隔
     * @param string $resourceName 应用/[模块/]控制器
     * @param string $access 动作名
     * @return bool
     */
    public function isAllowed($roleName, $resourceName, $access, array $parameters = null)
    {
        return true;
        $accessKey = strtolower($resourceName . "/" . $access);

        if (in_array($accessKey, $this->_publicAccessKeys)
            || in_array(strtolower($resourceName . "/*"), $this->_publicAccessKeys)) return true;

        if ($roleName) {
            $roleIds = is_array($roleName) ? $roleName : explode(',', $roleName);
            $accessList = $this->_getActiveAccess($roleIds, 0);
        } else {
            $ticket = $this->getDI()->get('auth')->getTicket();
            if (!$ticket) return false;
            if ($this->_isRoot()) return true;

            $accessList = $this->getActiveAccess();
        }

        return Utils::hasAccess($accessKey, $accessList);
    }

    /**
     * Returns the role which the list is checking if it's allowed to certain resource/access
     * @return string
     */
    public function getActiveRole()
    {;
    }

    /**
     * Returns the resource which the list is checking if some role can access it
     * @return array
     */
    public function getActiveResource()
    {
        //获取所有可用菜单
        $rs = Menu::findFillWithKey('id',['isshow=1']);
        $menus=[];
        $accessList = $this->getActiveAccess();
        if($this->_isRoot()){
            $menus=$rs;
        }else{
            foreach($rs as $r){
                if( Utils::hasAccess($r['url'],$accessList)){
                    $menus[$r['id']]=$r;
                }
            }
            $fun=function($pid) use($rs,&$menus){
                if($pid==0) return;
                if(!$menus[$pid]){
                    $menus[$pid]=$rs[$pid];
                }            
            };            
            foreach($menus as $m){
                $fun($m['parentid']);
            }
        }
        foreach ($menus as &$v) { 
            if($v['url']!='' && strpos($v['url'],":")===false){    
                $i=strpos($v['url'],'?');
                if($i===false){
                    $v['link'] = Utils::url($v['url']);
                }else{
                    $v['link'] = Utils::url(substr($v['url'],0,$i)).'&'.substr($v['url'],$i+1);
                }           
            }else{
                $v['link'] =$v['url'];
            }
        }
        return $menus;
    }

    /**
     * Returns the access which the list is checking if some role can access it
     * @return array
     */
    public function getActiveAccess()
    {
        if (!isset($this->_accessList)) {
            if (!$ticket = $this->_getTicket()) return array();
            $roleids = array();
            foreach ($ticket['roles'] as $role) {
                $roleids[] = $role['id'];
            }
            $this->_accessList = $this->_getActiveAccess($roleids, $ticket['uid']);
        }
        return $this->_accessList;
    }

    protected function _getActiveAccess($roleids, $uid)
    {
        $accessList = [];
        //读取角色权限,仅读取有权限的部分
        if ($roleids) {
            $rs = Access::find(["isallow=1 and object_type={object_type} AND object_value in ({object_value:array})", 'bind'=>['object_type'=>Access::COLUMN_OBJECTTYPE_ROLE ,'object_value'=>$roleids]]);
            foreach ($rs as $r) {
                $accessList[$r->id] = $r->access_key.($r->access_data?'?'.$r->access_data:'');
            }
        }
        //读取个人权限，并覆盖角色权限
        if ($uid) {
            $rs = Access::find(["object_type=?0 AND object_value=?1",'bind'=>[Access::COLUMN_OBJECTTYPE_USER,$uid]]);            
            foreach ($rs as $r) {
                if ($r->isallow) {
                    $accessList[$r->id] = $r->access_key.($r->access_data?'?'.$r->access_data:'');
                } else {
                    unset($accessList[$r->id]);
                }
            }
        }
        return $accessList;
    }

    /**
     * Return an array with every role registered in the list
     * @return RoleInterface[]
     */
    public function getRoles()
    {
    }

    /**
     * Return an array with every resource registered in the list
     * @return  ResourceInterface[] Description
     */
    public function getResources()
    {
    }

    /**
     * 判断当前用户是否用root用户
     * @return bool
     */
    protected function _isRoot()
    {
        $ticket = $this->_getTicket();
        return $ticket['uid'] == $this->_rootUserId;
    }

    protected function _getTicket()
    {
        return $this->getDI()->get('auth')->getTicket();
    }

    public function setNoArgumentsDefaultAction($defaultAccess)
    {
    }

    public function getNoArgumentsDefaultAction()
    {

    }
}
