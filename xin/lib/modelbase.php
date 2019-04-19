<?php

namespace Xin\Lib;

class ModelBase extends \Phalcon\Mvc\Model
{
    const COLUMN_STATUS_ENABLE="enable";
    const COLUMN_STATUS_DISABLE="disable";
    const COLUMN_STATUS_DELETE="deleted";

    public function initialize()
    {
        $this->useDynamicUpdate(true);
    }

    public static function findFillWithKey($key, $parameters = null,$useObject=false)
    {
        $rs = array();
        foreach (self::find($parameters) as $item) {
            if (is_object($key) && $key instanceof \Closure) {
                $rs[$key($item)] = $useObject ? $item: $item->toArray();
            } else {
                $rs[$item->$key] = $useObject ? $item: $item->toArray();
            }
        }
        return $rs;
    }

    protected function getTablePrefix($prefix='default')
    {
        static $prefixs=array();
        if(!isset($prefixs[$prefix])){
        	$t=$this->getDI()->get('config')->database[$prefix];
        	$t && $prefixs[$prefix]=$t->prefix;
        	return $t->prefix;
        }
        return $prefixs[$prefix];
    }
}
