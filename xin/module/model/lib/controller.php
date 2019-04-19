<?php
namespace Xin\Module\Model\Lib;

use Xin\Module\Model\Model\Model;
use Xin\Lib\Utils;
use Xin\Module\Model\Model\ModelField;
use Xin\Module\Model\Model\ModelT;
use Xin\Lib\SqlHelper;
use Xin\Module\Category\Model\Category;

class Controller extends \Phalcon\Mvc\Controller
{
	protected function _getModel()
	{
		$modelName = $this->request->get('model', 'string');
		//获取继承类的模型名
		if(!$modelName){
			$modelName=str_replace('Controller','',basename(str_replace('\\','/',get_class($this))));			
		}
		if ($modelName && $model = Model::findFirstByName($modelName)) {
			return $model;
		}
		if (!$model) {
			throw new \Exception('模型不存在');
		}
	}

	public function listAction()
	{
		$model = $this->_getModel();

		$model=$model->toArray();
		$modelt = new ModelT();
		$modelt->setModelId($model['id']);
		$modelfields=[];
		foreach($modelt->getModelFields() as $field){
			$modelfields[$field->field] =$field->toArray() ;
		}
		$from = $where = [];
		$from[] = 'from ' . $modelt->getSource() . ' as m';

		if ($model['extand_table']) {
			$modelt->switchExtandTable(1);
			$from[] = 'inner join ' . $modelt->getSource() . ' as s on m.id=s.'.$model['main_table'].'_id';
		}

		if ($model['settings']['searchField']) {
			$keyword = $this->request->get("keyword");
			$this->view->setVar('keyword', $keyword);
			if ($keyword) {
				foreach (explode(',', $model['settings']['searchField']) as $item) {
					$where[] = $item . ' like \'%' . SqlHelper::escapeLike($keyword) . '%\'';
				}
			}
		}
		foreach ($_GET as $k => $v) {			
			if (strpos($k, 'filter_') === 0 && ($item=substr($k, 7)) && $modelfields[$item]) {
				if(is_array($v)){
					$where[] = $item . ' in (\'' . implode("','",$v) . '\')';
				}else{
					$where[] = $item . '=\'' . SqlHelper::escapeLike($v) . '\'';
				}
			}
		}
		if($modelfields['status']){
			$where[]="status!='deleted'";
		}
		$sql = 'select count(*) as count ' . implode(' ', $from) . ($where ? ' where ' . implode(' and ', $where) : '');
		$query = $modelt->getReadConnection()->query($sql);
		if ($count = $query->fetch()) {
			$count = reset($count);
		}

		$pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
		$pagination->pageSize($model['settings']['listSize']);
		$pagination->recordCount($count);
		$this->view->setVar('pagination', $pagination);
		
		$result = [];
		list($start, $limit) = Utils::offset(null, $pagination->pageSize());
		if ($pagination->recordCount() && $start < $pagination->recordCount()) {

			$sql = 'select * ' . implode(' ', $from) . ($where ? ' where ' . implode(' and ', $where) : '') . ' order by m.id desc limit ' . $start . ',' . $limit;
			$query = $modelt->getReadConnection()->query($sql);
			while ($rs = $query->fetch()) {
				$result[] = $rs;
			}

			$listRole = [];
			if ($model['settings']['listRole']) {			
				/*
				id:操作:[EDIT]|编辑,[DELETE]|删除
				catid|category:分类:category/list?id=[catid]
				*/
				foreach (explode("\n", $model['settings']['listRole']) as $item) {
					$col = [];
					$arr = explode(":", $item);
					if (strpos($arr[0], '|') !== false) {
						list($name, $func) = explode('|', $arr[0]);
						$col['field'] = $name;
						$col['filter'] = $func;
					}else{
						$col['field'] = $arr[0];
					}
					
					$col['title'] = $arr[1];
					if ($arr[2]) {
						if (strpos($arr[2], '|') === false) {
							$col['link'] = $arr[2];
						} else {
							foreach (explode(',', $arr[2]) as $_item) {
								list($link, $txt) = explode("|", $_item);
								$col['links'][] = ['txt' => $txt, 'link' => $link];
							}
						}
					}
					$listRole[] = $col;
				}
			} else {
				if ($rs = reset($result)) {
					foreach ($rs as $k => $v) {
						$listRole[] = [
							'title' => $k,
							'field' => $k,
						];
					}
					$listRole[] = [
						'links' => [
							['link' => '[EDIT]', 'txt' => '编辑'],
							['link' => '[DELETE]', 'txt' => '删除']
						]
					];
				}
			}
			
		}
		$this->view->setVar('listRole', $listRole);
		$this->view->setVar('model', $model);
		$this->view->setVar('modelfield', $modelfields);
		$this->view->setVar('objectlist', $result);
		
		
	}
	
	public function deleteAction()
	{
		$idList = $this->request->getPost('id');
		$model=$this->_getModel();
		$objModel=new ModelT();
		$objModel->setModelId($model->id);
		if(!Utils::isNumericArray($idList)){
			return new \Xin\Lib\MessageResponse('错误的参数');
		}		
		try {
			foreach($idList as $id){
				$obj=$objModel->findFirstById($id);
				if($obj){
					if (!$obj->delete()) {
						throw new \Exception(implode(';', $obj->getMessages()));
					}
				}					
			}	
		} catch (\Exception $e) {
			$this->di->get('logger')->error($e->getMessage());
			return new \Xin\Lib\MessageResponse('数据保存失败');
		}	
		
		return new \Xin\Lib\MessageResponse('操作数据已保存', 'succ');
	}

	public function createAction()
	{
		$model=$this->_getModel();
		$objModel=new ModelT();
		$objModel->setModelId($model->id);
		
		if ($this->request->isPost()) {
			$datas=[];
			foreach($objModel->getModelFields() as $r){
				$f=	$r->toField();
				!$r->allow_create && $f && $f->disableFetchForm(true);	
				$datas[$r->field]=$f->getValue();
			}
			
			try {
				if($objModel->save($datas)){					
					return new \Xin\Lib\MessageResponse('数据已保存', 'succ');
				}
				$this->di->get('logger')->error(implode(';', $objModel->getMessages()));
			}catch (\Exception $e) {
				$this->di->get('logger')->error($e->getMessage());
			}
			return new \Xin\Lib\MessageResponse('数据保存失败');

		} else {
			$fields=new \Xin\Module\Model\Lib\Fields();
			foreach($objModel->getModelFields() as $r){
				if($r->allow_create){
					$fields->addField($r->toField());
				}
			}
			$this->view->setVars(array(
				'fields' => $fields
			));
		}
	}

	public function editAction()
	{
		$id = $this->request->get('id', 'int');
		if ($id < 1 || !$obj = Category::findFirstById($id)) {
			return new \Xin\Lib\MessageResponse('错误的参数');
		}

		if ($this->request->isPost()) {
			$datas = $_POST;
			if ($datas['parentid'] != $obj->parentid) {
				$parent = Category::findFirstById($datas['parentid']);
				if (!$parent) {
					return new \Xin\Lib\MessageResponse('无效的参数parentid');
				}
			}
			$datas['isshow'] = $datas['isshow'] ? 1 : 0;
			if (!$obj->save($datas)) {
				$this->di->get('logger')->error(implode(';', $obj->getMessages()));
				return new \Xin\Lib\MessageResponse('数据保存失败');
			} else {
				return new \Xin\Lib\MessageResponse('数据已保存', 'succ');
			}
		} else {
			$cat = $obj->toArray();
			$models = Model::find()->toArray();
			$cats = Category::find(array('order' => 'parentid asc ,listorder asc'))->toArray();
			$this->view->setVars(array(
				'models' => $models,
				'objData' => $cat,
				'catTree' => Utils::arrayToTree($cats, 0, 0, false),
				'type' => $cat['type']
			));
		}
	}

	public function sortAction(){
		$model=$this->_getModel();
		$objModel=new ModelT();
		$objModel->setModelId($model->id);
		$attrs=$objModel->getModelsMetaData()->getAttributes($objModel);
		if(!in_array('listorder',$attrs)){
			return new \Xin\Lib\MessageResponse('该模型不支持排序');
		} 

		if ($this->request->isPost()) {
			$listorders=$this->request->getPost('listorder');
			if($listorders && is_array($listorders)){
				foreach($listorders as $k=>$v){
					$objModel->getWriteConnection()->execute('update '.$objModel->getSource().' set listorder=:listorder where id=:id',['listorder'=>$v,'id'=>$k]);
				}
			}
			return new \Xin\Lib\MessageResponse('数据变更已保存','succ');
		}
		return new \Xin\Lib\MessageResponse('错误的排序参数');
	}

}