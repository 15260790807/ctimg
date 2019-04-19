<?php
namespace Xin\Module\Model\Model;
use Xin\Lib\ModelBase;
use Xin\Module\Model\Lib\MetaData;

class ModelT extends ModelBase
{
    protected $_modelId,$_useExtandTable=false;

    public function setModelId($modelId){
        $this->_modelId=$modelId;
        parent::setSource($this->getSource());
    }

    public function switchExtandTable($use){
        $this->_useExtandTable=$use;
        return $this;
    }

    public function getSource()
    {
        if($this->_modelId && $m=$this->_getModels($this->_modelId)){
            return  $this->getTablePrefix().(!$this->_useExtandTable ? $m['main_table']: $m['extand_table']);
        }
        throw new \Exception('未设置有效的模型信息，无法匹配数据表');
    }

    
    public function getModelFields(){
        if (!$this->_modelId || !$model = $this->_getModels($this->_modelId)) {
            throw new \Exception('未设置有效的模型信息，无法匹配数据表');
        }
        switch($model['kind']){
            case Model::COLUMN_KIND_DOC:
                $where='model_id=1 or model_id=?0';
                break;
            case Model::COLUMN_KIND_USER:
                $where='model_id=2 or model_id=?0';
                break;
            case Model::COLUMN_KIND_INDEPEND:
                $where=' model_id=?0';
                break;
        }
        return ModelField::find([$where, 'bind' => [$this->_modelId],'order'=>'listorder asc']);
    }

    public function save($data=null, $whiteList=null){        
        if ($this->_useExtandTable) {
            return parent::save($data,$whiteList);
        }

        if (!$this->_modelId || !$model = $this->_getModels($this->_modelId)) {
            throw new \Exception('未设置有效的模型信息，无法匹配数据表');
        }
        $extData=$mainData=[];
        $fields = $this->getModelFields();
        if (is_array($data)) {
            $data['model_id'] = $this->_modelId;
        } else {
            $data = ['model_id' => $this->_modelId];
        }

        foreach ($fields as $item) {
            if ($item->is_main) {
                $mainData[$item->field] = $data[$item->field] ? $data[$item->field] : $this->{$item->field};
            } else {
                $extData[$item->field] = $data[$item->field] ? $data[$item->field] : $this->{$item->field};
            }
        }
        
        $nesting=false;
        $this->getWriteConnection()->begin($nesting);
        if(parent::save($mainData,$whiteList?array_intersect($whiteList,array_keys($mainData)):array_keys($mainData))){
            if($model['extand_table']){
                $extModel=new ModelT();
                $extModel->setModelId($this->_modelId);
                $extModel->switchExtandTable(true);
                $pk=$model['main_table'].'_id';
                $extModel->{$pk}=$this->id;
                if($extModel->save($extData,$whiteList?array_intersect($whiteList,array_keys($extData)):array_keys($extData))){          
                    $this->getWriteConnection()->commit($nesting);
                    return true;
                }
            }else{
                $this->getWriteConnection()->commit($nesting);
                return true;
            }
        }
        $this->getWriteConnection()->rollback($nesting);
        return false;
    }


    
    protected function _getModels($modelId=null){
        static $models;
        if(!isset($models)){
            $models=Model::findFillWithKey('id');
        }
        return $modelId ? $models[$modelId] : $models;
    }

}