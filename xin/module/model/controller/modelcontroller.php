<?php

namespace Xin\Module\Model\Controller;

use Xin\Module\Model\Model\Model,
    Xin\Module\Model\Model\ModelField,
    Xin\Module\Model\Model\ModelT,
    Xin\Module\Model\Lib\Fields,
    Xin\Lib\Utils;
use Xin\Module\Linkage\Model\Linkage;
use Xin\Module\Model\Model\Datasource;


class ModelController extends \Phalcon\Mvc\Controller
{

    public function listAction()
    {
        $count = Model::count();

        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }

        $rs = Model::query()
            ->limit($limit, $start)
            ->execute();
        
        $this->view->setVar('objectlist', $rs->toArray());
    }

    public function createAction()
    {
        if ($this->request->isPost()) {
            $model = new Model();
            $model->useext=$this->request->getPost('useext');
            $datas = array(
                'title' => $this->request->getPost('title', 'string', '', 1),
                'name' => $this->request->getPost('name', 'string', '', 1),
                'description' => $this->request->getPost('description', 'string'),
                'settings' => $this->request->getPost('settings'),
                'kind'=>$this->request->getPost('kind'),
            );
            
            try {
                if ($model->create($datas)) {
                    return new \Xin\Lib\MessageResponse('数据已保存', 'succ',['列表'=>Utils::url('admin/model/list')]);
                } else {
                    $this->di->get('logger')->error(implode(';', $model->getMessages()));
                }
            } catch (\Exception $e) {
                $this->di->get('logger')->error($e->getMessage());
            }
            return new \Xin\Lib\MessageResponse('数据保存失败');
        }
    }

    public function editAction()
    {
        $modelId = $this->request->get('modelid', 'int', 0);
        if (!$modelId || !$model = Model::findFirstById($modelId)) {
            return new \Xin\Lib\MessageResponse('无效的模型id');
        }

        if ($this->request->isPost()) {
            $datas = array(
                'title' => $this->request->getPost('title', 'string', '', 1),
                'description' => $this->request->getPost('description', 'string'),
                'settings' => $this->request->getPost('settings')
            );

            try {
                if (!$model->save($datas)) {
                    $this->di->get('logger')->error(implode(';', $model->getMessages()));
                } else {
                    return new \Xin\Lib\MessageResponse('数据已保存', 'success');
                }
            } catch (\Exception $e) {
                $this->di->get('logger')->error($e->getMessage());
            }
            return new \Xin\Lib\MessageResponse('数据保存失败');
        }

        $this->view->setVar('model', $model->toArray());
    }

    public function deleteAction()
    {
        $id = $this->request->getPost('id', 'int');
        if ($id < 1 || !$obj = Model::findFirstById($id)) {
            return new \Xin\Lib\MessageResponse('错误的参数');
        }

        try {
            if (!$obj->delete()) {
                $this->di->get('logger')->error(implode(';', $role->getMessages()));
            } else {
                return new \Xin\Lib\MessageResponse("信息已删除", 'succ');
            }

        } catch (\Exception $e) {
            $this->di->get('logger')->error($e->getMessage());
        }
        return new \Xin\Lib\MessageResponse("保存失败");
    }


    //模型字段列表
    public function fieldListAction()
    {
        $modelId = $this->request->get('modelid', 'int', 0);
        if (!$modelId || !$model = Model::findFirstById($modelId)) {
            return new \Xin\Lib\MessageResponse('无效的模型id');
        }

        $count = ModelField::count(['model_id=?0', 'bind' => [$modelId]]);

        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }
        
        $rs = ModelField::query()
            ->where('model_id=?0', [$modelId])
            ->limit($limit, $start)
            ->order('listorder asc')
            ->execute();
        $this->view->setVar('objectlist', $rs->toArray());
        $this->view->setVar('model' , $model->toArray());

    }

    //模型创建字段
    public function fieldCreateAction()
    {
        $modelId = $this->request->get('modelid', 'int', 0);
        if (!$modelId || !$model = Model::findFirstById($modelId)) {
            return new \Xin\Lib\MessageResponse('无效的模型id');
        }
        if ($this->request->getPost('dosubmit')) {
            $datas = $this->request->getPost();
            $datas['model_id'] = $model->id;

            if (!$field = Fields::loadField($datas['formtype'], $datas['field'])) {
                return new \Xin\Lib\MessageResponse('加载指定的字段类型错误');
            }     
            $field->setSettings($datas['settings']);
            $modelField = new ModelField();
            $modelT = new ModelT();
            $modelT->setModelId($model->id); 

            $this->db->begin();
            $flag=(function() use ($modelField,$modelT,$datas,$field){              
                try {
                    if (!$modelField->save($datas)) {
                        $this->di->get('logger')->error(implode(';',$modelField->getMessages()));
                        return false;
                    }
                    if (!$field->applyTo($modelT)) {
                        $this->di->get('logger')->error('模型变更应用失败');
                        return false;
                    }
                } catch (\Exception $e) {
                    $this->di->get('logger')->error($e->getMessage());
                    return false;
                }
                return true;
            })();
            if($flag && $this->db->commit()){
                return new \Xin\Lib\MessageResponse('数据已保存', 'succ',['列表'=>Utils::url('admin/model/list')]);
            }else{
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse('保存结果失败');
            }            
            
        }
        
        $this->view->setVars(array(
            'fields' => Fields::getFieldTypes()
        ));
    }


    

    //模型创建字段
    public function fieldEditAction()
    {
        $id = $this->request->get('id', 'int', 0);
        if (!$id || !$fieldModel =ModelField::findFirstById($id)) {
            return new \Xin\Lib\MessageResponse('无效的模型字段id');
        }
        if (!$field = Fields::loadField($fieldModel->formtype, $fieldModel->field)) {
            return new \Xin\Lib\MessageResponse('加载指定的字段类型错误');
        }  
        $field->setTitle($fieldModel->title);
        $field->setSettings($fieldModel->settings);
        if ($this->request->getPost('dosubmit')) {
            $datas = $this->request->getPost();
            unset($datas['model_id']);

               
            $modelT = new ModelT();
            $modelT->setModelId($model->id); 
            $this->db->begin();
            $flag=(function() use ($fieldModel,$modelT,$datas,$field){              
                try {
                    if (!$fieldModel->save($datas)) {
                        $this->di->get('logger')->error(implode(';',$fieldModel->getMessages()));
                        return false;
                    }
                    if (!$field->applyTo($modelT)) {
                        $this->di->get('logger')->error('模型变更应用失败');
                        return false;
                    }
                } catch (\Exception $e) {
                    $this->di->get('logger')->error($e->getMessage());
                    return false;
                }
                return true;
            })();
            if($flag && $this->db->commit()){
                return new \Xin\Lib\MessageResponse('数据已保存', 'succ');
            }else{
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse('保存结果失败');
            }            
            
        }
                
        $this->view->setVars(array(
            'objData'=>$fieldModel->toArray(),
            'fields' => Fields::getFieldTypes(),
            'field' => $field
        ));
    }

    //模型字段详情表单
    public function fieldSettingAction()
    {
        $name = $this->request->getPost('field', 'string');
        if (!$name || !$field = Fields::loadField($name)) {
            return new \Xin\Lib\MessageResponse('加载指定的字段类型错误');
        }
        $this->view->setVar('field',$field);
    }

    //数据源
    public function dataSourceAction()
    {
        $this->view->setVars(array(
            'linkages' => Linkage::find('parentid=0')->toArray(),
            'dataSources' =>  Datasource::find()->toArray(),
        ));
    }

}
