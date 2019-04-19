<?php

namespace Xin\Module\Order\Model;

use Xin\Lib\ModelBase;

class OrderAbroad extends ModelBase
{

    CONST COLUMN_STATUS_CANCEL_ERROR = -5;     //取消海外仓订单异常
    CONST COLUMN_STATUS_NOPAID_AUDIT_ERROR = -4;     //未支付却提交海外仓订单审核异常
    CONST COLUMN_STATUS_DELIVERY_ERROR = -3;     //海外仓订单出库异常
    CONST COLUMN_STATUS_AUDIT_ERROR = -2;        //海外仓订单审核异常
    CONST COLUMN_STATUS_CREATE_ERROR = -1;       //海外仓订单创建异常
    CONST COLUMN_STATUS_CREATE = 0;              //海外仓新创建订单 (草稿)
    CONST COLUMN_STATUS_AUDIT = 1;               //海外仓提交并审核的订单 (待发货) 
    CONST COLUMN_STATUS_DELIVERY_PARTIAL = 2;    //部分出库
    CONST COLUMN_STATUS_DELIVERY_ALL = 3;        //全部出库
    CONST COLUMN_STATUS_CANCEL_PARTIAL = 4;        //海外仓订单部分取消
    CONST COLUMN_STATUS_CANCEL_ALL = 5;        //海外仓订单全部取消
    
    public function initialize()
    {
        parent::initialize();
        $this->hasMany("id", "\Xin\Module\Order\Model\OrderAbroadItem", "order_id", array('alias' => 'OrderAbroadItem'));
    }
}