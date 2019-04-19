<?php

namespace Xin\Module\Model;

use Phalcon\Cache\Backend\Factory;

class DataTag extends \Xin\Lib\DataTag{


    public function fieldDict(){        
        return Lib\Field::getFieldTypes();
    }

} 