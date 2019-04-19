<?php

namespace Xin\Module\Order\Lib;

use Xin\Model\Config;
use Xin\Module\Product\Model\Product;
use Xin\Module\Product\Model\ProductOptionItem;
use Xin\Module\Product\Lib\Fields;
use Xin\Module\User\Model\User;
use Xin\Module\User\Model\UserGroup;
use Xin\Lib\Utils;
use Xin\Module\express\Model\Express;
use Xin\Module\Order\Model\Order as OrderModel;
use Xin\App\Admin\Model\Auditlog;
use Xin\Module\Order\Lib\OrderAbroad;
use Xin\Module\Order\Model\OrderItem;
use Xin\Module\Bank\Lib\Bank as BankLib;
use Xin\Module\Order\Model\OrderPayType;
use Xin\Module\Order\Model\OrderPayDetail;

class Order
{
    /*
     * 图稿确认
     * 生产中
     * 已发货
     * 已完成
     */
    CONST ARTWORK_APPROVAL = [80, 50, 60, 61, 70];
    CONST SHIPPED = 60; //已发货
    CONST IN_PRODUCTION = 50; //生产中
    CONST IN_SHIPPED = 61; //发货中

    public static function computeItem(Product $product, $optionsReq, $uid, $quantity = 1, $skipRequiredValid = false)
    {
        $optionPrices = [];
        $optionWeight = [];
        $result = [
            'price' => 0,
            'weight' => 0
        ];
        if ($uid) {
            if ($user = User::findFirstById($uid)) {
                $userGroup = UserGroup::findFirstById($user->group_id);
            }
        }

        if (!is_array($optionsReq)) {
            throw new \Exception('选择参数不合法');
        }
        $options = [];
        $optionVals = [];
        $boxOptVal = [];
        $optTypes = [];
        foreach ($product->getProductOption() as $opt) {
            //不是VIP的跳过VIP选项
            if ($userGroup && $userGroup->level != 10 && $opt->settings['isvip']) {
                continue;
            }
            $optType = Fields::loadField($opt->formtype, $opt->name);
            $optTypes[] = $optType;
            if (!$optType->hasPrice()) continue;
            $options[$opt->id] = $opt;
            $optionVals[$opt->name] = [];
            if (!$skipRequiredValid && $opt->settings['required'] && (!array_key_exists($opt->name, $optionsReq) || $optionsReq[$opt->name] == '')) {
                throw new \Exception('有必选项未选');
            }
            if (in_array($opt->formtype, ['box', 'turnaround', 'accessory','cust'])) {
               (array_key_exists($opt->name, $optionsReq) && $optionsReq[$opt->name] != '') && $boxOptVal[] =strpos($optionsReq[$opt->name],'-')?explode('-',$optionsReq[$opt->name])[0]:$optionsReq[$opt->name];
            }
        }

        $optionItems = ProductOptionItem::findFillWithKey('id', ['id in ({id:array})', 'bind' => ['id'=>$boxOptVal]]);
        if (count($optionItems) != count($boxOptVal)) {
            throw new \Exception('Some products has expired,please reselect the product!');
            // throw new \Exception('传入和查询不符');
        }
        foreach ($optionItems as $item) {
            if (!array_key_exists($item['product_option_id'], $options)) {
                throw new \Exception('不存在的选项组');
            }
            $optname = $options[$item['product_option_id']]->name;
            $optionVals[$optname][] = $item['id'];
        }
        //TODO 没做排序，因为需要面积,先把铜扣放到最后
        foreach ($options as $k => $opt) {
            if ($opt->formtype == 'grommet') {
                unset($options[$k]);
                $options[$opt->id] = $opt;
                break;
            }elseif ($opt->formtype == 'cust') {
                list($a,$b)=explode('-',$optionsReq[$opt->name]);
                $a && $optionsReq[$opt->name]=$a;
            }
        }

        foreach ($options as $opt) {
            $price_affect = $weight_affect = 0;
            switch ($opt->formtype) {
                case 'box':
                case 'cust':
                case 'turnaround':
                    $choosenOpts = $optionVals[$options[$opt->id]->name];
                    if ($choosenOpts == '') continue;
                    foreach ($choosenOpts as $choosenOpt) {
                        $price_affect += $optionItems[$choosenOpt]['price_affect'];
                        $weight_affect += $optionItems[$choosenOpt]['weight_affect'];
                    }
                    break;
                case 'acreage':
                    $matched=false;
                    if(is_numeric($optionsReq[$opt->name])){
                        if($r=ProductOptionItem::findFirst(['product_option_id=?0 and id=?1','bind'=>[$opt->id,$optionsReq[$opt->name]]])){                            
                            $price_affect+=$r->price_affect;
                            $weight_affect+=$r->weight_affect;
                            $matched=true;
                        }
                    }else{
                        list($w,$h)=explode('x',$optionsReq[$opt->name]);
                        $w>=0 && $h>=0 && $area=$w*$h*0.3048*0.3048; //平方英寸转平方米
                        if($area<=0){ 
                            throw new \Exception($opt->title);
                        }
                        foreach($opt->getProductOptionItem() as $item){
                            if($item->settings['valueLower']<$area && $item->settings['valueUpper']>=$area ){                            
                                $price_affect+= $item->settings['priceType']=='1' ? $item->price_affect : $item->price_affect*$area;
                                $weight_affect+=$item->settings['weightType']=='1' ? $item->weight_affect : $item->weight_affect*$area;
                                $optionsReq[$opt->name]=$item->id;//使用itemid供接下来虚拟参数匹配
                                $matched=true;
                                break;
                            }
                        }
                    }                    
                    if(!$matched){
                        throw new \Exception($opt->title);
                    }
                    $optionVals[$opt->name][]=$optionsReq[$opt->name];
                    break;
                case 'grommet':
                    $arr = explode(',', $optionsReq[$opt->name]);

                    if (count($arr) != 4) {
                        throw new \Exception($opt->title);
                    }
                    $n = 0;

                    foreach ($arr as $k => $a) {
                        if ($a == 'Corner Only') {
                            $n += 1;
                        } elseif ($a == '0/inch' || $a == 'None') {
                        } else {
                            $a = str_replace("/inch", "", $a);

                            if (!preg_match('/^\d+(?:\.\d+)?$/', $a) || $a < 0) {
                                throw new \Exception($opt->title);
                            }

                            $n += ceil(($k % 2 == 0 ? $w : $h) / $a);
                        }
                    }
                    if ($n > 0) {
                        $matched = false;
                        foreach ($opt->getProductOptionItem() as $item) {
                            if ($item->settings['valueLower'] < $n && $item->settings['valueUpper'] >= $n) {
                                $price_affect += $item->settings['priceType'] == '1' ? $item->price_affect : $item->price_affect * $n;
                                $weight_affect += $item->weight_affect;
                                $optionsReq[$opt->name] = $item->id;//使用itemid供接下来虚拟参数匹配
                                $matched = true;
                                break;
                            }
                        }

                        if (!$matched) {
                            throw new \Exception($opt->title);
                        }
                    }
                    break;
            }
            $optionPrices[$opt->name] = $price_affect;
            $optionWeight[$opt->name] = $weight_affect;
        }
        $optionPrices['price_base'] = $product->price_base;
        $optionWeight['weight_base'] = $product->weight_base;
        $price_role = $product->price_role;
        //虚拟选项
        foreach ($product->getProductOptionRel() as $optrel) {
            foreach ($optrel->settings as $item) {
                $matched = true;
                foreach ($item['opts'] as $v) {
                    if (!in_array($v, $optionsReq)) {
                        $matched = false;
                    }
                }
                if ($matched) {
                    $optionPrices[$optrel->name] = $item['price_affect'];
                    $optionWeight[$optrel->name] = $item['weight_affect'];
                    break;
                }
            }
        }

        $uksortFunc = function ($a, $b) {
            $f = strlen($b) - strlen($a);
            if ($f == 0) return strcmp($a, $b);//如果 str1 小于 str2 返回 < 0； 如果 str1 大于 str2 返回 > 0；如果两者相等，返回 0。
            else return $f;
        };

        uksort($optionPrices, $uksortFunc);

        $logger = \Phalcon\Di::getDefault()->get('logger');
        $logger->info('$price=' . $price_role . ' $optionPrices=' . json_encode($optionPrices));

        foreach ($optionPrices as $k => $v) {
            $price_role = str_replace($k, $v, $price_role);
        }
        $price_role = preg_replace('/((?:[a-zA-z]\w)+)/', '0', $price_role);
        if ($price_role == '') {
            throw new \Exception('无效的价格配置');
        }

        if ($product->combine_type == 0) { //自动价格计算
            if (eval('$price=' . $price_role . ';') === false) {
                $logger->critical('$price=' . $price_role);
            }
        } elseif ($product->combine_type == 1) { //价格矩阵
            $matrix = $product->getProductOptionMatrix();
            foreach ($matrix as $item) {
                $matched = true;
                foreach ($optionVals as $k => $v) {
                    //FIXME 先不支持多选
                    if (reset($v) != $item->opts[$k]) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    if ($item->price_affect) {
                        $price = $item->price_affect;
                    } else {
                        if (eval('$price=' . $price_role . ';') === false) {
                            $logger->critical('$price=' . $price_role);
                        }
                    }
                    break;
                }
            }
            if (!$matched) {
                throw new \Exception('无对应的价格配置');
            }
        } elseif ($product->combine_type == 2) { //黑名单
            $matrix = $product->getProductOptionMatrix();
            foreach ($matrix as $item) {
                $matched = true;
                foreach ($item->opts as $k => $v) {
                    if (!in_array($v, $optionVals[$k])) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    throw new \Exception('对应组合不支持');
                }
            }
            if (eval('$price=' . $price_role . ';') === false) {
                $logger->critical('$price=' . $price_role);
            }
        } elseif ($product->combine_type == 4) { //白名单
            $matrix = $product->getProductOptionMatrix();
            $matched = false;
            foreach ($matrix as $item) {
                $matched = true;
                foreach ($item->opts as $k => $v) {
                    if (!in_array($v, $optionVals[$k])) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    break;
                }
            }
            if (!$matched) {
                throw new \Exception('对应组合不支持');
            }
            if (eval('$price=' . $price_role . ';') === false) {
                $logger->critical('$price=' . $price_role);
            }
        }
        $category_id = $product->category_id;
        if ($category_id != 82 && $category_id != 83 && $category_id != 86 && $category_id != 87 && $category_id != 161) {
            if (strstr($price, '.')) {
                $price_arry = explode('.', $price);
                $price_arry[1] = '0.' . $price_arry[1];
                if ($price_arry[1] <= 0.5) {
                    $price = $price_arry[0] + 0.69;
                } else {
                    $price = $price_arry[0] + 0.99;
                }
            }
        }

//        $result['price']=sprintf("%.2f", $price/Config::getValByKey('EXCHANGE'));
        /*
         * vip等级优惠价格
         */
        if ($userGroup && $userGroup->issystem == 2) {
            $discounted = $userGroup->discount;
            $result['price'] = sprintf("%.2f", $price);
            $result['offerPrice'] = sprintf("%.2f", ($result['price'] * (1 - $discounted / 100)));
        } else {
            $result['price'] = sprintf("%.2f", $price); //产品已经是美元价
            $result['offerPrice'] = sprintf("%.2f", $price);
        }

        $weight_role = $product->weight_role;
        uksort($optionWeight, $uksortFunc);

        $logger->info('$weight_role=' . $weight_role . ' $optionWeights=' . json_encode($optionWeight));

        foreach ($optionWeight as $k => $v) {
            $weight_role = str_replace($k, $v, $weight_role);
        }
        $logger->info('$weight_role=' . $weight_role . ' $optionWeights=' . json_encode($optionWeight));
        $weight_role = preg_replace('/((?:[a-zA-z]\w)+)/', '0', $weight_role);
        !$weight_role && $weight_role = 0;
        eval('$weight=' . $weight_role . ';');
        $result['weight'] = sprintf("%.2f", $weight);

        //箱重，调试使用
        /*
        $compute = new \Xin\Module\Express\Lib\ExpressCompute($uid, $expressId);
        $_optionVals=[];
        foreach($optionVals as $k=>$v){
            $_optionVals[$k]=reset($v);
        }
		$compute->addItem($product->id, 0, $quantity, $_optionVals);
        $expressInfo = $compute->computeWeight(0, 0, null);
        $result['boxweight']=$expressInfo['boxWeight'];
        */
        return $result;
    }
    
    
    public static function getOrderDetailItem($order, $vipDiscount = 100) {
        $express = Express::findFirst($order->express_id);
        $orderItems =[];
        $accessories = OrderAbroad::getOrderAbroadAccessories($order->id);
        $vip_discount = 1;
        foreach ($order->getOrderItem() as $item) {
            if($vipDiscount > 0 && $order->order_status_id == 10) {
                $vip_discount = (1 - floatval($vipDiscount) / 100);
            } else if (!empty ($item->vipDiscount) && $order->order_status_id != 10 && $order->order_status_id != 90) {
                $vip_discount = (1 - floatval($item->vipDiscount) / 100);
            }
            $item->unit_price = sprintf("%.2f", ($item->unit_price * floatval($vip_discount)));
            $item->amount = round(($item->unit_price * $item->quantity),2);
            $order_items = [
                "id" => $item->id,
                "product_id" => $item->product_id,
                "amount" => $item->amount,
                "quantity" => $item->quantity,
                "unit_price" => $item->unit_price,
                "unit_weight" => $item->unit_weight, 
                "po_code" => $item->po_code,
                "options" => $item->options,
                "product_cache" => $item->product_cache,
                "showDetail" => false,
                "items" => []
            ];
            $unit_price = $item->unit_price? $item->unit_price : 0; //商品单价
            $unit_weight = $item->unit_weight? $item->unit_weight : 0;//商品单位重量
            $title_en = ''; //非配件部分商品名称
            foreach ($item->options as $opt) {
                //有走海外仓的配件才拆出来显示
                if ($opt['abroad'] && !empty($opt['abroad_affect']['accessory_id'])) {
                    $accessory = $accessories[$opt['abroad_affect']['accessory_id']];
                    $accessory['unit_price'] = sprintf("%.2f", ($accessory['unit_price'] * floatval($vip_discount)));
                    $accessory_item = [
                        "accessory_abroad" => true,
                        "title" => $accessory['title_en'],
                        "unit_price" => $accessory['unit_price'],
                        "unit_weight" => $accessory['unit_weight'],
                        "amount" => sprintf("%.2f", ($accessory['unit_price'] * floatval($item->quantity))),
                        "quantity" => $item->quantity, //商品中配件数
                        "thumb" => $accessory['thumb'], //配件图片
                        "tracks" => []//配件数量多的可能存在 多个订单跟踪号  该数组用于记录对应跟踪号中配件数  用跟踪号作为数组key
                    ];
                    foreach ($accessories[$opt['abroad_affect']['accessory_id']]['rels'] as $k => $rel) {
                        $num = intval($item->quantity) > intval($rel['quantity'])? intval($rel['quantity']): intval($item->quantity);
                        if (empty($accessory_item['tracks'][$rel['tracking_number']])) {
                            $accessory_item['tracks'][$rel['tracking_number']] = [
                                "quantity" => $num,
                                "tracking_number" => $rel['tracking_number']
                            ];
                        } else {
                            $accessory_item['tracks'][$rel['tracking_number']]['quantity'] += $num;
                        }
                        if (empty($accessory_item['shipping_method'])) {
                            $accessory_item['shipping_method'] = $rel['shipping_method'];
                        }
                        if (empty($accessory_item['tracking_number'])) {
                            $accessory_item['tracking_number'] = $rel['tracking_number'];
                        }
                        if (empty($accessory_item['status'])) {
                            $accessory_item['status'] = $rel['status'];
                        } else if (intval($rel['status']) > intval($accessory_item['status'])) {
                            $accessory_item['status'] = $rel['status'];
                        }
                        $accessories[$opt['abroad_affect']['accessory_id']]['rels'][$k]['quantity'] -= $num;
                        if ($accessories[$opt['abroad_affect']['accessory_id']]['rels'][$k]['quantity'] == 0) {
                            unset($accessories[$opt['abroad_affect']['accessory_id']]['rels'][$k]);
                        }
                    }
                    
                    $order_items['items'][] = $accessory_item;
                    $unit_price = Utils::countSub($unit_price, $accessory['unit_price']);
                    $unit_weight = Utils::countSub($unit_weight, $accessory['unit_weight']);
//                    if (strtoupper($opt['value']) != "YES") {
//                        //选项是配件加其他商品或配件
//                        //非配件部分 商品名称
//                        $titles = explode('+', $opt['value']);
//                        foreach ($titles as $k => $val) {
//                            if (strtolower($val) == 'frame' || strtolower($val) == 'wheelbag'){
//                                unset($titles[$k]);
//                            }
//                        }
//                        if (!empty($titles)) {
//                            if ($title_en != '') {
//                                $title_en .= '+';
//                            } else {
//                                $title_en .= substr($accessory['title_en'], 0, strripos($accessory['title_en'], '-') + 1);
//                            }
//                            $title_en .= implode('+', $titles);
//                        }
//                    }
                }
            }
            if ($unit_weight > 0){
                $order_items['items'][] = [
                    "accessory_abroad" => false,
                    "status" => $order->order_status_id,
                    "title" => $title_en ? $title_en : $item->product_cache['title'],
                    "unit_price" => $unit_price > 0 ? $unit_price : 0,
                    "unit_weight" => $unit_weight > 0 ? $unit_weight : 0,
                    "amount" => $unit_weight > 0 ? sprintf("%.2f", ($unit_price * floatval($item->quantity))) : 0,
                    "thumb" => $item->options['artwork']['value'][0]['thumb'],
                    "path" => $item->options['artwork']['value'][0]['path'],
                    "tracking_number" => $order->tracking_no ? $order->tracking_no : '', //订单跟踪号
                    "shipping_method" => $express->title ? $express->title : '',
                    "quantity" => $item->quantity, //商品中配件数
                    "tracks" => []
                ];
            }
            $orderItems[] = $order_items;
        }
        return $orderItems;
    }

    /**
     * 计算订单产品Vip价格
     * @param $r
     * @param $isVip
     * @param $vipDiscount
     */
    public static function computeVipOrder(&$r, $isVip, $vipDiscount) {
        $logger = \Phalcon\Di::getDefault()->get('logger');
        $logger->debug('['.$r->ordersn.']订单产品Vip价格');
        $amount = 0;
        $isSave = false;
        foreach(OrderItem::findByOrderId($r->id) as $oitem){
            if($r->order_status_id == 10) {
                if($isVip == 2 && $oitem->vipDiscount != $vipDiscount) {
                    $r->amount = 0;
                    $offerPrice = sprintf("%.2f", ($oitem->unit_price * (1 - $vipDiscount / 100)));
                    $oitem->vipDiscount = $vipDiscount;
                    $oitem->amount = $offerPrice * $oitem->quantity;
                    $amount = $amount + $oitem->amount;
                    $isSave = true;
                } else if($isVip != 2 && $oitem->vipDiscount != 0) {
                    $r->amount = 0;
                    $oitem->vipDiscount = 0;
                    $oitem->amount = $oitem->unit_price * $oitem->quantity;
                    $amount = $amount + $oitem->amount;
                    $isSave = true;
                }
            }

            if($isSave) {
                if(!$oitem->save()) {
                    $logger->error('计算订单产品Vip价格子订单保存失败');
                    $logger->error(implode(';', $oitem->getMessages()));
                }
            }
        }
        if($isSave) {
            $r->amount = $amount;
            if(!$r->save()) {
                $logger->error('计算订单产品Vip价格订单保存失败');
                $logger->error(implode(';', $r->getMessages()));
            }
        }
    }
    
    
    /**
     * 获取可以合并的订单数据
     * @param type $aid  用户地址id
     * @param type $uid  用户id
     * @return array 可以合并的订单的数据
     * @param type $toAddress 收货地址
     * @param int $expressid  物流方式id
     * @param array $svc  第三方服务id数组
     * @param int $eid 发货地址id数组
     * @return type
     */
    public static function getMergeOrders($aid, $uid, $toAddress, $expressid, $svc=[] , $eid=0) {
        $merge_orders = null;
        if ($toAddress && $toAddress->uid == $uid) {
            $merge_time = self::getMergeLimitTime();
            $orders = OrderModel::find([
                        "uid = :uid: and shipping_fullname=:fullname: "
                        . " and express_id=:express_id: "
                        . " and shipping_address_1=:address: "
                        . " and shipping_country=:country: and shipping_company=:company: "
                        . " and shipping_state=:state: and shipping_city=:city: "
                        . " and shipping_postcode=:zip: "
//                        . "and shipping_telephone=:phone: "
                        . " and estimated_delivery_time > :merge_time:"
                        . " and (order_status_id = 20 or order_status_id = 30 or order_status_id = 50)",// 已付款  图稿审核中 生产中
                        "bind" => [
                            "uid" => $uid,
                            "merge_time" => $merge_time,
                            "fullname" => $toAddress->firstname . " " . $toAddress->lastname,
                            "address" => $toAddress->address,
                            "country" => $toAddress->country,
                            "company" => $toAddress->company,
                            "state" => $toAddress->state,
                            "city" => $toAddress->city,
                            "zip" => $toAddress->zip,
//                            "phone" => $toAddress->phone,
                            "express_id" => $expressid
                        ],
                        "order" => "create_time desc",
            ]);
            foreach ($orders as $k => $order) {
                if (!self::checkMergeOrderAddress($order, $svc, $eid)) {
                    //不同的三方服务或者发货地址跳过 不允许合并
                    continue;
                }
                $merge_order =  $order->toArray();
                $merge_item = (array) json_decode(json_encode($merge_order));
                $order_item = OrderItem::findByOrderId($merge_order['id']); //获取订单商品
//                $key = empty($merge_order['merge_ids']) ? strval($merge_order['id']) : strval($merge_order['merge_ids']);
                $key = $merge_order['batch_no'];
                $merge_item['orderItems'] = $order_item->toArray();
                if (empty($merge_orders[$key])) {
                    $express = Express::findFirstById($merge_order['express_id']);
                    $merge_orders[$key] = [
                        "batch_no" => $merge_order['batch_no'], //发货批号
                        "delivery_time" => $merge_order['estimated_delivery_time'],//date('m/d/Y', $merge_order['estimated_delivery_time']), //预计发货时间
                        "orders" => [],
                        "express" => $express->title //物流方式
                    ];
                }
                $merge_orders[$key]['orders'][$merge_order['id']] = $merge_item;
            }
        }
        return $merge_orders;
    }
        
    /**
     * 检验将要合并的订单是否可以进行合并
     * @param type $uid  用户id
     * @param array $m_ids 将要合并的订单id
     * @param type $toAddress 收货地址    验证要合并的订单地址一致
     * @param int $expressid  物流方式id
     * @param array $svc 第三方服务id
     * @param int $eid 发货地址id
     * @return boolean
     * @throws \Exception
     */
    public static function checkMergeOrder($uid, $m_ids, $toAddress, $expressid, $svc=[], $eid=0) {
        $logger = \Phalcon\Di::getDefault()->get('logger');
        $bath_no = '';
        if ($toAddress) {
            $merge_time = self::getMergeLimitTime();
            $orders = OrderModel::find([
                        "uid = :uid: and shipping_fullname=:fullname: "
                        . " and express_id=:express_id: "
                        . " and shipping_address_1=:address:"
                        . " and shipping_country=:country: and shipping_company=:company: "
                        . " and shipping_state=:state: and shipping_city=:city: "
                        . " and shipping_postcode=:zip: "
//                        . "and shipping_telephone=:phone: "
                        . " and estimated_delivery_time > :merge_time:"
                        . " and (order_status_id = 20 or order_status_id = 30 or order_status_id = 50)"// 已付款  图稿审核中 生产中
                        . " and id in ({ids:array}) ",
                        "bind" => [
                            "uid" => $uid,
                            "ids" => $m_ids,
                            "merge_time" => $merge_time,
                            "fullname" => $toAddress->firstname . " " . $toAddress->lastname,
                            "address" => $toAddress->address,
                            "country" => $toAddress->country,
                            "company" => $toAddress->company,
                            "state" => $toAddress->state,
                            "city" => $toAddress->city,
                            "zip" => $toAddress->zip,
//                            "phone" => $toAddress->phone,
                            "express_id" => $expressid
                        ],
                        'orderby' => 'create_time asc'
            ]);
            foreach ($orders as $order) {
                if ($bath_no == '') {
                    $bath_no = $order->batch_no;
                }
//                elseif ($bath_no != $order->batch_no) {
//                    throw new \Exception("Batch number error!");
//                }
                if (!self::checkMergeOrderAddress($order, $svc, $eid)) {
                    throw new \Exception("some order's address is different");
                }
            }
            
            $batchs = array_column($orders->toArray(), "batch_no");
            if (!empty($batchs) && !in_array($bath_no, $batchs)) {
                $logger->error('batch_no=' . $bath_no . '  batchs=' . json_encode($batchs));
                throw new \Exception("Batch number error!");
            }
            if (!self::checkMergeIds($m_ids, $orders)) {
                throw new \Exception("Orders could not be merged!");
            }
        } else {
            throw new \Exception('Address infomation could not be found!');
        }
        return $bath_no;
    }
    
    /**
     * 检测要合并的订单
     * 要合并的中是否和其他订单合并过 如果已合并过且其合并的订单中存在订单不在m_ids中 则不允许合并
     * @param array $m_ids   为合并订单的ids  
     * @param array $orders  根据$m_ids及订单状态获取的订单数组   传入的$orders必须为允许合并的订单即允许和其他订单合并或再次合并
     * @throws \Exception
     */
    public static function checkMergeIds($m_ids, $orders) {
        $logger = \Phalcon\Di::getDefault()->get('logger');
        if (count($m_ids) != count($orders->toArray())) {
                $logger->error('Action-checkMergeIds: some orders could not be found!');
//            throw new \Exception('some orders could not be found!');
            return false;
        }
        //要合并的订单是否已合并过    如果已合并过 合并的订单id也需在m_ids中 否则错误
        foreach ($orders as $order) {
            if (intval($order->merge_status) == 1 && !empty($order->merge_ids)) {
                $merge_ids = explode(",", $order->merge_ids);
                //如果merge_id 不是 m_ids的子集 说明合并有问题
                //比较两个合并订单id数组（只比较键值），计算交集的个数是否和$order订单合并的id个数一致
                if (count(array_intersect($merge_ids, $m_ids)) != count($merge_ids)) {
//                    throw new \Exception('some orders has merged other order!');
                    $logger->error('Action-checkMergeIds: some orders has merged other order!');
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * 更新合并的订单的 发货批号、预计发货时间， 是否合并标志
     * @param type $order
     */
    public static function updateMergeOrders($order) {
        $logger = \Phalcon\Di::getDefault()->get('logger');
        $m_ids = explode(",", $order->merge_ids);
        if (empty($m_ids)) {
            throw new \Exception("Merger order failed!");
        }
        $merge_orders = OrderModel::find([
           "uid = :uid: and id in ({ids:array}) and (order_status_id = 20 or order_status_id = 30 or order_status_id = 50)",
            "bind" =>[
                "uid" => $order->uid,
                "ids" => $m_ids
            ]
        ]);
         //$order的 merge_ids中包含$order的id在调用checkMergeIds验证时需要去除 否则与获取的要合并的订单个数对不上
        $offset = array_search($order->id, $m_ids);
        array_splice($m_ids, $offset, 1);
        //验证订单是否可以与 merge_ids中的订单合并
        if (!self::checkMergeIds($m_ids, $merge_orders)) {
            throw new \Exception("Orders could not be merged!");
        }

        //更新合并的旧订单信息      *新订单批号必须再 合并订单的批号数组中
        $batchs = array_column($merge_orders->toArray(), "batch_no");
        if (!empty($batchs) && !in_array($order->batch_no, $batchs)) {
            $logger->error('batch_no=' . $order->batch_no . '  batchs=' . json_encode($batchs));
            throw new \Exception("Batch number error!");
        }
        foreach ($merge_orders as $merge_order) {
//            if ($merge_order->batch_no != $order->batch_no) {
//                throw new \Exception("Batch number error!");
//            }
            $merge_order->merge_ids = $order->merge_ids;
            $merge_order->batch_no = $order->batch_no;
            $merge_order->estimated_delivery_time = $order->estimated_delivery_time;
            $merge_order->merge_status = 1;
            if ($merge_order->save() === false) {
                throw new \Exception(implode(";", $merge_order->getMessages()));
            }
        }
        if (!empty($order->merge_return) && $order->merge_return > 0) {
            //合并订单退用户还多付的运费
            $extra_params = [
                "ordersn" => $order->ordersn   //与旧订单合并的新订单编号
            ];
            $res = BankLib::refund($order->uid, $order->merge_return, 'Refund of freight', $extra_params);
            if (!$res) {
                throw new \Exception("Redundant freight refund failed!");
            }
        }
        
        //记录合并订单操作日志
        if (!empty($m_ids)) {
            $auditlog = new Auditlog();
            $request = new \Phalcon\Http\Request();
            $auditlog->uid = $order->uid;
            $auditlog->action = 'order.merge';
            $auditlog->params = json_encode($order->toArray());
            $auditlog->create_date = date('ymd');
            $auditlog->ip = $request->getClientAddress();
            if ($auditlog->save() === false) {
                throw new \Exception(implode(";", $auditlog->getMessages()));
            }
        }
    }
    
    /**
     * 合并订单时检测订单的第三方服务是否与新单一致
     * @param type $order
     * @param array $svc  第三方服务id
     * @param type $eid 发货地址id
     */
    public static function checkMergeOrderAddress($order, $svc = [], $eid = 0) {
        $svc = is_array($svc)? $svc : (array)$svc;//所选服务数组
        $eid = intval($eid);//所选地址id
        $extra_express_info = $order->extra_express_info;
        if (!is_array($order->extra_express_info)) {
            $extra_express_info = Utils::object_array($extra_express_info);
        }
        if (empty($extra_express_info)) {
            if (!empty($svc) || $eid > 0) {
                return false;
            }
        } else {
            $services = $extra_express_info['service']? $extra_express_info['service'] : [];
            if (!is_array($services)) {
                $services = (array)$services;
            }
//            过滤与所选服务不符的订单
            if (empty($svc) || count(array_intersect($svc, $services)) != count($svc) || count($services) != count($svc)) {
                return false;
            }
            $address_id = !empty($extra_express_info['settings']['address']['id'])? intval($extra_express_info['settings']['address']['id']): -1;
//            过滤与所选发货地址不符的订单
            if ($eid != $address_id) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 获取合并的订单信息
     * @param type $ordersn  合并的订单id数组
     * @return array
     */
    public static function getMergeOrderInfo($ids) {
        $result = [
            "isAbnormal" => false,
            "data" => []
        ];
        $mergeOrders = OrderModel::find(['id in ({ids:array})', 'bind' => ['ids' => $ids]]);
        
        //检测合并订单中是否和其他订单合并
        $result['isAbnormal'] = !self::checkMergeIds($ids, $mergeOrders);
        $express = Express::find()->toArray();
        foreach ($mergeOrders as $mergeOrder) {
            $orderData = $mergeOrder->toArray();
            $orderData['orderItems'] = self::getOrderDetailItem($mergeOrder);
            $orderData['showDetail'] = true;
            $orderData['shipping_method'] = $express[array_search($mergeOrder->express_id, array_column($express, 'id'))]['title'];
            $orderData['tracking_number'] = $orderData['tracking_no'];
            $orderData['create_time'] = date('Y-m-d H:i:s', $mergeOrder->create_time);
            if (floatval($orderData['paypal_fee']) > 0) {
                $orderData['paypal_fee'] = $orderData['paypal_fee'];
            } else if (intval($orderData['payment_id']) == 2) {
                $total = Utils::countSub( Utils::countAdd($orderData['amount'], $orderData['freight']) , Utils::countAdd($orderData['integral_dedution'], $orderData['discount']));
                $orderData['paypal_fee'] = round($total*0.029,2);
            } else {
                $orderData['paypal_fee'] = 0;
            }
            unset($orderData['tracking_no']);
            $result['data'][] = $orderData;
            //已付款  图稿审核中 生产中
            if (intval($mergeOrder->order_status_id) != 20 && intval($mergeOrder->order_status_id) != 30 && intval($mergeOrder->order_status_id) != 50) { 
                $result['isAbnormal'] = true;
            }
        }
        return $result;
    }

    /**
     * 场景：用户的运费vip折扣变动，重新计算订单运费价格
     * @param $order
     * @param $freightDiscount
     */
    public static function computeOrderVipFreight(&$order, $freightDiscount) {
        $logger = \Phalcon\Di::getDefault()->get('logger');
        $logger->debug('订单['.$order->ordersn.']运费vip折扣值：' . $order->freight_discount);
        $logger->debug('订单['.$order->ordersn.']所属用户当前运费vip折扣值：' . $freightDiscount);
        if($order->freight_discount != $freightDiscount && $order->order_status_id == 10) {
            $logger->debug('订单['.$order->ordersn.']开始重新计算运费折扣');
            $freight = $order->freight;
            $freight_abroad = $order->freight_abroad;
            $extraLongPrice = $order->extraLong_price;
            $tmpFreight = ($freight-$freight_abroad-$extraLongPrice)/(1-$order->freight_discount/100);

            $compute = new \Xin\Module\Express\Lib\ExpressCompute(null, null);

            $tmpFreight = $compute->computeVipFreight($freightDiscount, $tmpFreight);
            $tmpFreight = round($tmpFreight + $extraLongPrice, 2);
            $tmpFreight = round($tmpFreight + $freight_abroad,2);
            $order->freight_discount = $freightDiscount;
            $order->freight = $tmpFreight;
            if(!$order->save()) {
                $logger->error('计算订单运费Vip价格订单保存失败');
                $logger->error(implode(';', $order->getMessages()));
            }
        }
    }

    /**
     * 获取用户运费VIP折扣
     * @param $uid
     * @return mixed
     */
    public static function getUserVipFreightDiscount($uid) {
        $user = User::findFirstById($uid);
        $userGroup = UserGroup::findFirstById($user->group_id);
        $freightDiscount = $userGroup->freight_discount;
        return $freightDiscount;
    }

    public static function exchange($item_id){

    }
    /**
     * 获取合并订单不允许合并的时间点 的时间戳
     * 
     */
    public function getMergeLimitTime() {
        //当前时间戳
        $now_time = time(); 
        //当前中国日期 输出有效时间限制到天
        $ch_date = strtotime(date("Y-m-d", strtotime('+15 Hours', strtotime(date('Y-m-d H:0:0')))));
        //当天早上9:00的时间戳
        $limit_time  =  strtotime('-6 Hours', $ch_date);
        
        if ($now_time > $limit_time) {
            //当前时间过9点 只能合并 今天之后的订单
            $merge_time = strtotime('-15 Hours', strtotime('+1 days', $ch_date));
        } else {
            //预计当天及0点之后发货的订单都可以拿来合并
            $merge_time = strtotime('-15 Hours', $ch_date);
        }
        return $merge_time;
    }
    
    /**
     * 获取合并订单的合并信息
     * $ids中订单是要合并的订单 
     * @param array $ids
     */
    public static function getMergeOrderCostInfo($uid, $ids) {
        $mergeOrders = OrderModel::find(["uid=:uid: and id in ({ids:array})", "bind" => ["uid" => $uid, "ids" => $ids]]);
        if (empty($mergeOrders) || empty($mergeOrders->toArray())) {
//            throw new \Exection();
            return [];
        }
        $paypalFee = 0;
        $amountPaid = 0;
        $shippingRefunded = 0;
        $taxRefunded = 0; //退还的税金
        $mergeWeight = 0;
        $mergeAmount = 0; //合并的所有订单产品金额
        $mergeFreight = 0; //合并的所有订单运费金额
        foreach ($mergeOrders as $mergeOrder) {
            $discount = Utils::countAdd(floatval($mergeOrder->integral_dedution), floatval($mergeOrder->discount));
            $orderAmount = Utils::countSub(Utils::countAdd(floatval($mergeOrder->amount), floatval($mergeOrder->freight)), $discount);
            if (floatval($mergeOrder->paypal_fee) > 0) {
                $paypalFee = Utils::countAdd($paypalFee, floatval($mergeOrder->paypal_fee));
            } else if (intval($mergeOrder->payment_id) == 2) {
                $paypalFee = Utils::countAdd($paypalFee, round($orderAmount*0.029,2));
            }
            
            $amountPaid = Utils::countAdd($amountPaid, $orderAmount);
            $shippingRefunded = Utils::countAdd($shippingRefunded, floatval($mergeOrder->merge_return));
//            $taxRefunded = Utils::countAdd($shippingRefunded, floatval($mergeOrder->merge_tax_refund));
            
            $orderWeight = Utils::countAdd(floatval($mergeOrder->weight), floatval($mergeOrder->box_weight));
            $mergeWeight = Utils::countAdd($mergeWeight, floatval($orderWeight));
            $mergeAmount = Utils::countAdd($mergeAmount, floatval($mergeOrder->amount));
            $mergeFreight = Utils::countAdd($mergeFreight, floatval(floatval($mergeOrder->freight)));
        }
        $total = Utils::countSub($amountPaid, $shippingRefunded);
        $total = Utils::countSub($total, $taxRefunded);
        return [
            "amountPaid" => $amountPaid,
            "shippingRefunded" => $shippingRefunded,
//            "taxRefunded" => $taxRefunded,
            "mergeWeight" => $mergeWeight,
            "mergeAmount" => $mergeAmount,
            "mergeFreight" => $mergeFreight,
            "paypalFee" => $paypalFee,
            "total" => $total
        ];
    }
    public function saveOrderPayRecordAction($data) {
        //paypal
        if (!empty($data['paypal_amount']) && $data['paypal_amount'] > 0) {
            $order_detail = [
                "uid" => $data['uid'],
                "order_id" =>  $data['order_id'],
                "amount" => $data['paypal_amount'],
                "type" => OrderPayType::COLUMN_TYPE_PAYPAL
            ];
            $res = self::saveRecordAction($order_detail);
            if (!$res) {
                return false;
            }
        }
        //授信额度
        if (!empty($data['credit_amount']) && $data['credit_amount'] > 0) {
            $order_detail = [
                "uid" => $data['uid'],
                "order_id" =>  $data['order_id'],
                "amount" => $data['credit_amount'],
                "type" => OrderPayType::COLUMN_TYPE_CREDIT_LIMITS
            ];
            $res = self::saveRecordAction($order_detail);
            if (!$res) {
                return false;
            }
        }
        //余额
        if (!empty($data['balance_amount']) && $data['balance_amount'] > 0) {
            $order_detail = [
                "uid" => $data['uid'],
                "order_id" =>  $data['order_id'],
                "amount" => $data['balance_amount'],
                "type" => OrderPayType::COLUMN_TYPE_BALANCE
            ];
            $res = self::saveRecordAction($order_detail);
            if (!$res) {
                return false;
            }
        }
        //卡券
        if (!empty($data['coupon_amount']) && $data['coupon_amount'] > 0) {
            $order_detail = [
                "uid" => $data['uid'],
                "order_id" =>  $data['order_id'],
                "amount" => $data['coupon_amount'],
                "type" => OrderPayType::COLUMN_TYPE_COUPON
            ];
            $res = self::saveRecordAction($order_detail);
            if (!$res) {
                return false;
            }
        }
        //积分
        if (!empty($data['integral_amount']) && $data['integral_amount'] > 0) {
            $order_detail = [
                "uid" => $data['uid'],
                "order_id" =>  $data['order_id'],
                "amount" => $data['integral_amount'],
                "type" => OrderPayType::COLUMN_TYPE_INTEGRAL
            ];
            $res = self::saveRecordAction($order_detail);
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    /**
     * 生成订单支付明细记录
     */
    public static function saveRecordAction($data) {
        $order_detail = new OrderPayDetail();
        $order_detail->uid = $data['uid'];
        $order_detail->order_id = $data['order_id'];
        $order_detail->amount = $data['amount'];
        $order_detail->type = $data['type'];
        $order_detail->create_time = time();
        if ($order_detail->save() === false) {
            return 0;
        }
        return $order_detail->id;
    }
}