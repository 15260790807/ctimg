<?php
namespace Xin\Lib;

class Loader extends \Phalcon\Loader{
    public function autoLoad($className){
        if(stripos($className,'xin')===0){
            return parent::autoLoad(strtolower($className));
        }
        parent::autoLoad($className);
    }
}