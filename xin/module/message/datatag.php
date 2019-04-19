<?php

namespace Xin\Module\Message;

class DataTag extends \Xin\Lib\DataTag{

    public function unreadSlice($params){
        return parent::_slice($params);
    }
} 