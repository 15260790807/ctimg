<?php

namespace Xin\App\Admin\Controller;

use Phalcon\Db;
use Xin\Lib\Utils;
use Xin\App\Admin\Model\Menu,
    Xin\App\Admin\Model\Privilege;
use Phalcon\Mvc\Model\Exception;

class MenuController extends \Phalcon\Mvc\Controller
{
    /**
     * 菜单列表
     */
    public function listAction()
    {
        $menus = [];
        $rs = Menu::find(['order' => '  listorder asc']);
        foreach ($rs as $r) {
            $menus[] = $r->toArray();
        }
        $this->view->setVars(['datalist' => $menus]);
    }

    /**
     * 创建菜单
     */
    public function createAction()
    {
        if ($this->request->isPost()) {
            $menu = new Menu();
            $datas = $_POST;
            $datas['parentid']=intval($datas['parentid']);
            if ($datas['parentid']>0) {
                $parent = Menu::findFirstById($datas['parentid'])->toArray();
                if (!$parent) {
                    return new \Xin\Lib\MessageResponse('无效的参数parentid');
                }
            }
            $datas['isshow'] = $datas['isshow'] ? 1 : 0;
            $datas['listorder'] = intval($datas['listorder']);
            $datas['settings'] && $datas['settings'] = json_encode($datas['settings']);            
            $datas['url']=trim($datas['url']);

            try{
                if (!$menu->save($datas)) {
                    throw new \Exception(implode(";",$menu->getMessages()));
                } else {
                    return new \Xin\Lib\MessageResponse('数据已保存', 'succ');
                }
            }catch(\Exception $e){
                $this->di->get('logger')->error($e->getMessage());
                return new \Xin\Lib\MessageResponse("保存数据失败");                
            }
        }
    }

    /**
     * 编辑菜单
     */
    public function editAction()
    {
        $id = $this->request->get('id', 'int');
        $menu = Menu::findFirstById($id);
        if (!$menu) {
            return new \Xin\Lib\MessageResponse('无效的记录');
        }
        if ($this->request->isPost()) {
            $datas = $_POST;
            $datas['isshow'] = $datas['isshow'] ? 1 : 0;
            $datas['parentid']=intval($datas['parentid']);

            if ($datas['parentid']!=$menu->parentid && $datas['parentid']>0 
                && Menu::count(['id=?0','bind'=>$datas['parentid']])!=1){
                return new \Xin\Lib\MessageResponse('无效的参数parentid');
            }

            try{               
                if (!$menu->save($datas)) {
                    throw new \Exception(implode(";",$menu->getMessages()));
                }
            }catch(\Exception $e){
                $this->di->get('logger')->error($e->getMessage());
                return new \Xin\Lib\MessageResponse("保存数据失败");                
            }           

            return new \Xin\Lib\MessageResponse('数据已保存', 'succ');
        } else {
            $menu = $menu->toArray();
            $this->view->setVar('objectdata', $menu);
        }
    }

    /**
     * 删除菜单
     */
    public function deleteAction()
    {
        $id = intval($this->request->getPost('id'));
        if ($id < 1 || !$menu = Menu::findFirstById($id)) {
            return new \Xin\Lib\MessageResponse('错误的参数');
        }
        try {
            if (!$menu->delete()) {
                return new \Xin\Lib\MessageResponse('数据保存失败:' . $menu->getMessages());
            }
            return new \Xin\Lib\MessageResponse('数据已删除', 'succ');
        } catch (\Exception $e) {
            return new \Xin\Lib\MessageResponse('数据保存失败:' . $e->getMessages());
        }
    }

    //全部保存
    public function saveAllAction()
    {
        $data=$this->request->getJsonRawBody(true);
        if(!$data || !is_array($data)){
            return new \Xin\Lib\MessageResponse('错误的参数格式');
        }
        $oldMenus=Menu::findFillWithKey('id',null,true);
        $newItems=[];
        try {
            $this->db->begin();
            $flag = true;
            foreach ($data as $item) {
                $newid=false;
                if($item['id']{0}=='T'){
                    $menu=new Menu(); 
                    $newid=$item['id'];
                    unset($item['id']);
                }else{
                    if(!isset($oldMenus[$item['id']])){
                        return new \Xin\Lib\MessageResponse('数据版本发生变化，请刷新重试');
                    }else{
                        $menu=$oldMenus[$item['id']];
                        unset($oldMenus[$item['id']]);
                    }
                }
                if($item['parentid']{0}=='T'){
                    $item['parentid']= $newItems[$item['parentid']]?$newItems[$item['parentid']] :0;
                }
                if(!$menu->save($item)){
                    $this->di->get('logger')->error( var_export($item,1));
                    $this->di->get('logger')->error( implode(';',$menu->getMessages()));
                    $flag=false;
                    break;
                }
                if($newid){
                    $newItems[$newid]=$menu->id;
                }
            }
            if($flag){
                foreach($oldMenus as $menu){                    
                    if(!$menu->delete()){
                        $this->di->get('logger')->error( implode(';',$menu->getMessages()));
                        $flag=false;
                        break;
                    }
                }
            }
            if ($flag && $this->db->commit()) {
                return new \Xin\Lib\MessageResponse('批量操作已保存','succ');
            } else {
                $this->db->rollback();
            }
        } catch (\Exception $e) {
            $this->di->logger->error(implode(";", $e->getMessage()));
        }

        return new \Xin\Lib\MessageResponse('保存结果失败');
    }
}
