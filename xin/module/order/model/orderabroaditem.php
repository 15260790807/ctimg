<?php

namespace Xin\Module\Order\Model;

use Xin\Lib\ModelBase;

class OrderAbroadItem extends ModelBase
{
    public $product_cache;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo(
            'order_id',
            "\Xin\Module\Order\Model\OrderAbroad",
            'id'
        );
    }

    public function beforeSave()
    {
        is_array($this->product_cache) && $this->product_cache = json_encode($this->product_cache, JSON_UNESCAPED_UNICODE);
    }
    public function afterFetch()
    {
        $this->product_cache = json_decode($this->product_cache, 1);
    }
}