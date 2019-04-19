<?php

namespace Xin\Module\Order\Model;

use Xin\Lib\ModelBase;
use Xin\Model\Config;

class OrderItem extends ModelBase
{
    public $product_cache;
    public $options;

    public function initialize()
    {
        parent::initialize();
        $this->hasMany("ordersn", "\Xin\Module\Order\Model\OrderItemShipment", "ordersn", array('alias' => 'OrderItemShipment'));
        $this->belongsTo(
            'order_id',
            "\Xin\Module\Order\Model\Order",
            'id'
        );
        $this->hasMany("id","\Xin\Module\Order\Model\OrderItemGalleryHistory", "order_item_id", ['alias' => 'OrderItemGalleryHistory']);
    }

    public function beforeSave()
    {
        is_array($this->product_cache) && $this->product_cache = json_encode($this->product_cache, JSON_UNESCAPED_UNICODE);
        is_array($this->options) && $this->options = json_encode($this->options, JSON_UNESCAPED_UNICODE);
    }
    public function afterFetch()
    {
        $this->product_cache = json_decode($this->product_cache, 1);
        $this->options = json_decode($this->options, 1);
    }
}