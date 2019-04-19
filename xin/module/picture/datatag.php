<?php

namespace Xin\Module\Picture;

use Xin\Module\Picture\Model\Picture;
use Xin\Lib\Utils;

class DataTag extends \Xin\Lib\DataTag{

    
    public function id2Infos($params){
        $ids=$params['ids'];
        if(trim($ids)==''){
            return [];
        }
        if(strpos($ids,',')){
            $ids=explode(',',$ids);
        }
        if(!is_array($ids)){$ids=[$ids];}
        $queryid=[];
        foreach($ids as $id){
            if($id==intval($id) && $id>0) $queryid[]=$id;
        }
        if(!$queryid) return [];
        $rs=Picture::find(['id in ({id:array})','bind'=>['id'=>$queryid]]);
        $result=[];
        foreach($rs as $r){
            $item=$r->toArray();
            $item['url']=$r->getUrl();
            $item['hash']=$r->getHash();
            $result[]=$item;
        }
        return $result;
    }
}