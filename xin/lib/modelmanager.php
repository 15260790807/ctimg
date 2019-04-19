<?php
namespace Xin\Lib;


class ModelManager extends \Phalcon\Mvc\Model\Manager
{
    public function getModelSource(\Phalcon\Mvc\ModelInterface $model)
	{
	    $entityName=get_class($model);
	    $key=strtolower($entityName);
	    if(!isset($this->_sources[$key])){
	        $val=strtolower(substr(preg_replace('/([A-Z])/','_$1',substr($entityName,strrpos($entityName,'\\')+1)),1));
	        //加入应用名作为表前缀,如oc_api__xxx
	        $i=strpos($entityName,'Xin\\App\\');
	        if ($i!==false){
	            $i+=strlen('Xin\\App\\');
	            $val=strtolower(substr($entityName,$i,strpos($entityName,'\\',$i)-$i)).'__'.$val;
            }
            $this->_sources[$key]=$val;
        }
        return $this->_prefix.$this->_sources[$key];
	}
}