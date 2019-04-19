<?php

namespace Xin\Module\Model\Model;
use Xin\Lib\ModelBase;

class Datasource extends ModelBase
{

    public function beforeSave()
    {
        $this->settings = json_encode($this->settings,JSON_UNESCAPED_UNICODE);
    }

    public function afterFetch()
    {
        $this->settings = json_decode($this->settings,true);
    }
}
