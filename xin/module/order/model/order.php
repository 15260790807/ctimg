<?php

namespace Xin\Module\Order\Model;

use Xin\Lib\ModelBase;

class Order extends ModelBase
{
    public $extra_express_info;

    public function initialize()
    {
        parent::initialize();
        $this->hasMany("id", "\Xin\Module\Order\Model\OrderItem", "order_id", array('alias' => 'OrderItem'));
        $this->hasMany("id", "\Xin\Module\Email\Model\EmailRecord", "order_id", array('alias' => 'EmailRecord'));
    }

    public function beforeSave()
    {
        is_array($this->extra_express_info) && $this->extra_express_info = json_encode($this->extra_express_info,JSON_UNESCAPED_UNICODE);
    }
 
    public function fireEvent($event)
    {
        switch($event){
            case 'afterFetch':
                $this->extra_express_info = $this->extra_express_info ? json_decode($this->extra_express_info, 1) :[];
                break;
        }
    }


}