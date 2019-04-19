<?php

namespace Xin\Lib;
use Phalcon\Mvc\User\Module;

class DataTag extends Module {
    /**
     * 返回结果集中的一个片段，需返回分页相关信息
     * @return array('items'=>[],'pagesize'=>int,'pageindex'=>'','count'=>'')
     */
    function _slice($params){
        $result=[
            'items'=>[],
            'pagesize'=>10,
            'pageindex'=>1,
            'count'=>0
        ];
        return $result;
    }
    /**
     * 返回所有结果
     */
    function _list(){

    }
    /**
     * 返回kv结果
     */
    function _dict(){

    }
    /**
     * 返回单一结果
     */
    function _one(){

    }

}