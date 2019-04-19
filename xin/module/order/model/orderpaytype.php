<?php

namespace Xin\Module\Order\Model;

use Xin\Lib\ModelBase;

class OrderPayType extends ModelBase
{
    const COLUMN_TYPE_PAYPAL = 1;
    const COLUMN_TYPE_CREDIT_LIMITS = 2;
    const COLUMN_TYPE_BALANCE = 3;
    const COLUMN_TYPE_COUPON = 4;
    const COLUMN_TYPE_INTEGRAL = 5;
    
    public function initialize()
    {
        parent::initialize();
    }
}