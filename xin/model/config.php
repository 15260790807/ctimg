<?php

namespace Xin\Model;
use Xin\Model\User;
use Xin\Model\ContainerUser;

class Config extends \Phalcon\Mvc\Model
{
    CONST CACHEKEY='configlist.php';
    public $settings;
    public static function getValByKey($key, $defaultVal=null){
        static $list;
        if(!isset($list)){
            $cache =\Phalcon\Di::getDefault()->getModelsCache();
            $list=$cache->get(self::CACHEKEY);
            if(!$list){
                $list=[];
                if($rs=self::find(['status=1 and group_type=1','column'=>'name,val,type'])){
                    foreach($rs as $item){
                        $list[$item->name]=self::_getVal($item->val,$item->type);
                    }
                }
                $list && $cache->save($cacheKey,$list);
            }
        }
        if(!array_key_exists($key,$list)){
            if($r=self::findFirst(['status=1 and name=?0','bind'=>[$key],'column'=>'name,val,type'])){
                return self::_getVal($r->val,$r->type);
            }
            return $defaultVal;
        }else{
            return $list[$key];
        }
    }
    
    public function beforeSave()
    { 
        is_array($this->settings) && $this->settings = json_encode($this->settings,JSON_UNESCAPED_UNICODE);
    }

    public function fireEvent($event)
    {
        switch($event){
            case 'afterFetch':
                $this->settings = json_decode($this->settings, 1);
                break;
            case 'afterSave':
                $this->di->get('logger')->info("fireEvent");
                $this->di->get('logger')->info(json_encode($event));
                intval($this->group_type)===1 && $this->di->getModelsCache()->delete(self::CACHEKEY);
                break;
        }
    }

    protected static function _getVal($val,$type){
        switch($type){
            case 'box':
            case 'editbox':
                return \json_decode($val,1);
            case 'bool':
                return $val?true:false;
            default:
                return $val;
        }
    }
}
