<?php

namespace Xin\Module\Order\Model;

use Xin\Lib\ModelBase;

class UploadTask extends ModelBase
{
    public $extra_express_info;

    public function initialize()
    {
        parent::initialize();
        $this->setConnectionService('localdb');
    }


}