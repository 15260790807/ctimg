<?php
namespace Xin\Module\Order\Lib;

use Xin\Module\Express\Lib\Warehouse;
use Xin\Model\Config;
use Xin\Lib\Utils;
use Xin\Module\Order\Model\Order;
use Xin\Module\Order\Model\OrderAbroad as OrderAbroadModel;
use Xin\Module\Order\Model\OrderAbroadItem;
use Xin\Module\Accessory\Model\Accessory;
use Xin\Module\Email\Lib\Email;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as LoggerAdapter;

class OrderAbroad {
    
    public static function getLogger(){
        $config = require CONFIG_DIR . 'global.php';
        $log_file_path = $config->base->log->path;
        return new LoggerAdapter($log_file_path);
    }
    
    /**
     * 根据cfm平台订单中的配件商品信息生成对应的海外仓订单
     * @param array $accessoryOrders  走海外仓配件信息
     * @param object $order cfm订单信息
     * @param string $countryCode 目的国家代码
     * @param string $shippingMethod 海外仓物流方式
     * @param string $warehouseCode 海外仓代码
     * @throws \Exception
     */
    public static function createAbroadOrder($accessoryOrders, $order, $countryCode, $shippingMethod, $warehouseCode) {
        $has_banormal = false;//是否有异常  
        //根据拆分的走海外仓配件商品 生成海外仓订单
        $order_desc = '关联cfm订单' . $order->ordersn; //订单描述
        $name = $order->shipping_fullname;
        $phone = $order->shipping_telephone;
        $province = $order->shipping_state;
        $city = $order->shipping_city;
        $zipcode = $order->shipping_postcode;
        $options = [
            "company" => $order->shipping_company? $order->shipping_company : ''
        ];
        //物流地址长度限制30个字符    "FedEx每个地址长度必须小于30个字符，请将超出部分放至别的地址"
        if (strlen($order->shipping_address_1) > 30) {
            throw new \Exception("address can't more than 30 english letters");
//            $index = strrpos(substr($order->shipping_address_1, 0, 30), ' ');
//            $address1 = substr($order->shipping_address_1, 0, $index);
//            $options['address2'] = substr($order->shipping_address_1, $index, strlen($order->shipping_address_1));
        } else if (strlen($order->shipping_address_2) > 30) {
            throw new \Exception("address1 can't more than 30 english letters");
        } else {
            $address1 = $order->shipping_address_1;
            $options['address2'] = $order->shipping_address_2;
        }
        if (strlen($phone) > 15) {
            $phone = substr($order->shipping_telephone, 0, 15);
        }
        $child_count = 0;
        foreach ($accessoryOrders as $accessoryOrder) {
            for($i = 0; $i < intval($accessoryOrder['quantity']); $i++) {
                $reference_no = $order->ordersn . '-' . sprintf('%03s', ++$child_count); //订单参考号
                //构造要生成的海外仓子订单商品
                $order_abroad = new OrderAbroadModel();
                $order_abroad->uid = $order->uid;
                $order_abroad->pid = $order->id;
                $order_abroad->ordersn = $order->ordersn;
                $order_abroad->reference_no = $reference_no;
                $order_abroad->freight = $accessoryOrder['freight'];//不含商品固定费
                $order_abroad->fixed_cost = $accessoryOrder['fixed_cost'];
                $order_abroad->country_code = $countryCode;
                $order_abroad->warehouse_code = $warehouseCode;
                $order_abroad->shipping_method = $shippingMethod;
                $order_abroad->create_time = time();
//                $order_abroad->exchange = $accessoryOrder['exchange']? floatval($accessoryOrder['exchange']) : floatval(Config::getValByKey('EXCHANGE'));
                $order_abroad->abroad_data = json_encode($accessoryOrder, JSON_UNESCAPED_UNICODE);
                if ($order_abroad->save() === false) {
                    throw new \Exception(implode(";",$order_abroad->getMessages()));
                }
                $accessory = Accessory::findFirstById($accessoryOrder['accessory_id']);
                //记录海外仓订单商品到order_abroad_item
//              $accessoryOrder 目前是单个配件一个订单
                $order_abroad_item = new OrderAbroadItem();
                $order_abroad_item->order_id = $order_abroad->id;
                $order_abroad_item->accessory_id = $accessoryOrder['accessory_id'];
                $order_abroad_item->sku = $accessoryOrder['sku'];
                $order_abroad_item->quantity = $accessoryOrder['num'];
                $order_abroad_item->accessory_cache = json_encode($accessory->toArray(), JSON_UNESCAPED_UNICODE);//配件快照
                if ($order_abroad_item->save() === false) {
                    throw new \Exception(implode(";",$order_abroad_item->getMessages()));
                }
                $items = [];
                $items[] = [
                    "product_sku" => $accessoryOrder['sku'],
                    "quantity" => $accessoryOrder['num']
                ];
                $warehouseOrder = Warehouse::createOrder($reference_no, $shippingMethod, (object) $items, $order_desc, $warehouseCode, $countryCode, $name, $phone, $province, $city, $address1, $zipcode, $options);
                if ($warehouseOrder->ask == 'Success' && !empty($warehouseOrder->order_code)) {
                    $order_abroad->order_code = $warehouseOrder->order_code; //海外仓系统订单号
                    $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CREATE; //海外仓订单状态
                } else {
                    $has_banormal = true;
                    $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CREATE_ERROR; //海外仓订单创建异常
                }
                $order_abroad->update_time = time();
                if ($order_abroad->save() === false) {
                    throw new \Exception(implode(";",$order_abroad->getMessages()));
                }
            }
        }
        //海外仓订单创建异常 发送邮件通知管理员处理
        if ($has_banormal) {
            $title = '创建海外仓订单异常';
            $content = '您有一个cfm订单在创建海外仓订单时出现异常请及时处理：<br>'
                    . 'cfm平台订单编号:' . $order->ordersn;
            Email::notifyAdminUser($title, $content);
        }
        return !$has_banormal;
    }
    
    /**
     * 审核cfm订单关联的海外仓订单  存在审核多个订单情况
     * 
     * 审核订单异常时邮件通知管理员处理   
     * @param int $uid 客户id
     * @param string $ordersn cfm订单编号
     * @return float cmf订单配件所有关联海外仓订单的实际总运费
     */
    public static function auditAbroadOrders($uid, $ordersn){
        $has_banormal = false;//海外仓操作是否有异常
        $order_abroads = OrderAbroadModel::find([
            "uid=:uid: and ordersn = :ordersn:",
            "bind" => [
                "uid" => $uid,
                "ordersn" => $ordersn
            ]
        ]);
        $actual_freight = 0;
        foreach ($order_abroads as $order_abroad) {
            $res = self::auditAbroadOrder($order_abroad);
            if (!$res['status']) {
                $has_banormal = true;
            } else {
                $actual_freight = Utils::countAdd($actual_freight, $res['actual_freight']);
            }
        }
        //海外仓订单创建异常 发送邮件通知管理员处理
        if ($has_banormal) {
            $actual_freight = 0;//异常时不存实际运费
            $title = '审核海外仓订单异常';
            $content = '您有一个cfm订单在审核海外仓订单时出现异常请及时处理：<br>'
                    . 'cfm平台订单编号:' . $ordersn;
            Email::notifyAdminUser($title, $content);
        }
        return ["status" => !$has_banormal, "actual_freight" => $actual_freight];
    }
    
    /**
     * 审核海外仓订单，成功后更新所关联的cfm平台海外仓订单中实际海外仓总运费等信息
     * 
     * @param object $order_abroad 海外仓订单模型
     * @return array $data
     * $data['actual_freight']  海外仓订单实际运费
     */
    public static function auditAbroadOrder($order_abroad) {
        $data = ["status"=> false, "actual_freight" => 0];
        $update_abroad = Warehouse::updateAbroadOrderByCode($order_abroad->order_code);
        if ($update_abroad) {
            $warehouse_order = Warehouse::getOrderByCode($order_abroad->order_code);
            if ($warehouse_order->ask == 'Success' && !empty($warehouse_order->data->fee_details->totalFee)) {
                $abroad_data = (array) json_decode($order_abroad->abroad_data);
                $exchange = $abroad_data['exchange'] ? floatval($abroad_data['exchange']) : floatval(Config::getValByKey('EXCHANGE'));
                $abroad_data['actual_freight'] = $warehouse_order->data->fee_details->totalFee; //实际运费 不含海外仓平台商品固定费 RMB
                
                $order_abroad->abroad_data = json_encode($abroad_data);
                $order_abroad->actual_freight = round(floatval($warehouse_order->data->fee_details->totalFee) / $exchange, 4); //海外仓订单提交审核
                $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_AUDIT; //海外仓订单提交审核
                $data['status'] = true;
                $data['actual_freight'] = Utils::countAdd($order_abroad->actual_freight, $order_abroad->fixed_cost);
            }
        } else {
            $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_AUDIT_ERROR; //海外仓订单审核异常
        }
        $order_abroad->update_time = time();
        if ($order_abroad->save() == false) {
            $data['status'] = false;
            $data['actual_freight'] = 0;
        }
        return $data;
    }
    
    /**
     * 取消cfm订单关联的海外仓订单  存在取消多个订单情况
     * 
     * 取消订单异常时邮件通知管理员处理   
     * @param int $uid 客户id
     * @param string $ordersn cfm订单编号
     * @return bool
     */
    public static function cancelAbroadOrders($uid, $ordersn){
        $has_banormal = false;//海外仓操作是否有异常
        $order_abroads = OrderAbroadModel::find([
            "uid=:uid: and ordersn = :ordersn:",
            "bind" => [
                "uid" => $uid,
                "ordersn" => $ordersn
            ]
        ]);
        foreach ($order_abroads as $order_abroad) {
            $res = self::cancelOrder($order_abroad);
            if (!$res) {
                $has_banormal = true;
            }
        }
        //海外仓订单取消异常 发送邮件通知管理员处理
        if ($has_banormal) {
            $title = '取消海外仓订单异常';
            $content = '您有一个cfm订单在取消海外仓订单时出现异常请及时处理：<br>'
                    . 'cfm平台订单编号:' . $ordersn;
            Email::notifyAdminUser($title, $content);
        }
        return ["status" => !$has_banormal];
    }
    
    /**
     * 取消 海外仓订单
     * @param object $order_abroad
     * @return boolean
     */
    public static function cancelOrder($order_abroad) {
        if (!empty($order_abroad->order_code)) {
            throw new \Exception('Some orders has not found!');
        } else {
            $warehouse_order = Warehouse::getOrderByRefCode($order_abroad->order_code);
            if ($warehouse_order->ask == 'Success' && !empty($warehouse_order->data)) {
                $status = $warehouse_order->data->order_status;
                $order_abroad->status = $order_abroad->status >= 0 ? $order_abroad->status : OrderAbroadModel::COLUMN_STATUS_CANCEL;
                if ($order_abroad->save() === false) {
                    throw new \Exception('Order cancellation failed');
                }
                //C:待发货审核 W:待发货  D:已发货 H:暂存 N:异常订单 P:问题件 X:废弃
                if ($status == 'C') { 
                    $res = Warehouse::cancelOrder($order_abroad->order_code);
                    if ($res->ask != 'Success') {
                        $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CANCEL_ERROR;
                    }
                } else {
                    $order_abroad->status = $order_abroad->status >= 0? $order_abroad->status : OrderAbroadModel::COLUMN_STATUS_CANCEL_ERROR;
                }
                if ($order_abroad->save() === false) {
                    throw new \Exception('Order cancellation failed');
                }
            } else {
                throw new \Exception('Some orders has not found!');
            }
        }
    }

    /**
     * 修复某个海外仓订单的异常
     * @param object $order_abroad   cfm存储的海外仓订单记录
     * @return boolean
     * @throws \Exception
     */
    public static function repairAbroadOrder($order_abroad) {
        if ($order_abroad->status == OrderAbroadModel::COLUMN_STATUS_CREATE_ERROR) {
            if (!empty($order_abroad->order_code)) {
                throw new \Exception('已生成关联的海外仓订单，请登录海外仓平台查看');
            } else {
                //修复创建海外仓订单异常
                //先判断参考号是否已经有创建了海外仓
                $warehouse_order = Warehouse::getOrderByRefCode($order_abroad->reference_no);
                if ($warehouse_order->ask == 'Success' && !empty($warehouse_order->data)) {
                    $status = $warehouse_order->data->order_status;
                    //C:待发货审核 W:待发货  D:已发货 H:暂存 N:异常订单 P:问题件 X:废弃
                    if ($status == 'C') {
                        $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CREATE;
                    } else if ($status == 'W') {
                        $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_AUDIT;
                    } else if ($status == 'D') {
                        $order_abroad->tracking_number = $warehouse_order->data->tracking_no;
                        $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL;
                    } else {
                        throw new \Exception('问题件，需至海外仓平台处理');
                    }
                    if (!empty($warehouse_order->data->fee_details->totalFee)) {
                        $abroad_data = (array) json_decode($order_abroad->abroad_data);
                        $exchange = $abroad_data['exchange'] ? floatval($abroad_data['exchange']) : floatval(Config::getValByKey('EXCHANGE'));
                        $abroad_data['actual_freight'] = $warehouse_order->data->fee_details->totalFee; //实际运费 不含海外仓平台商品固定费 RMB
                        $order_abroad->abroad_data = json_encode($abroad_data);
                        
                        $order_abroad->actual_freight = round(floatval($warehouse_order->data->fee_details->totalFee) / $exchange, 4); //海外仓订单提交审核
                    }
                    $order_abroad->country_code = $warehouse_order->data->consignee_country_code;
                    $order_abroad->warehouse_code = $warehouse_order->data->warehouse_code;
                    $order_abroad->shipping_method = $warehouse_order->data->shipping_method;
                    $order_abroad->order_code = $warehouse_order->data->order_code;
                    if ($order_abroad->save() === false) {
                        throw new \Exception('订单修复失败');
                    }
                } else {
                    $res = self::createNewOrder($order_abroad);
                }
            }
        } else if ($order_abroad->status == OrderAbroadModel::COLUMN_STATUS_AUDIT_ERROR) {
            //修复审核订单异常
            if (empty($order_abroad->order_code)) {
                $warehouse_order = Warehouse::getOrderByRefCode($order_abroad->reference_no);
                if ($warehouse_order->ask == 'Success' && !empty($warehouse_order->data)) {
                    //海外仓平台根据cfm平台的订单参考号创建草稿成功， 但cfm没有成功记录海外仓平台创建的订单号
                    $order_abroad->order_code = $warehouse_order->data->order_code;
                } else {
                    $res = self::createNewOrder($order_abroad);
                }
            }
            $res = self::auditAbroadOrder($order_abroad);
            if (!$res['status']) {
                throw new \Exception('订单审核异常修复失败');
            }
        } else if ($order_abroad->status == OrderAbroadModel::COLUMN_STATUS_DELIVERY_ERROR) {
            throw new \Exception('发货异常需联系海外仓平台解决');
        } else {
            throw new \Exception('订单修复失败');
        }
        return true;
    }
    
    /**
     * 根据cfm平台记录的海外仓订单信息创建海外仓订单
     * @param type $order_abroad
     * @return boolean
     * @throws \Exception
     */
    public static function createNewOrder($order_abroad) {
        $order = Order::findFirstByOrdersn($order_abroad->ordersn);
        //根据拆分的走海外仓配件商品 生成海外仓订单
        $order_desc = '关联cfm订单' . $order->ordersn; //订单描述
        $name = $order->shipping_fullname;
        $phone = $order->shipping_telephone;
        $province = $order->shipping_state;
        $city = $order->shipping_city;
        $zipcode = $order->shipping_postcode;
        $options = [];
        //物流地址长度限制30个字符    "FedEx每个地址长度必须小于30个字符，请将超出部分放至别的地址"
        if (strlen($order->shipping_address_1) > 30) {
            $index = strrpos(substr($order->shipping_address_1, 0, 30), ' ');
            $address1 = substr($order->shipping_address_1, 0, $index);
            $options['address2'] = substr($order->shipping_address_1, $index, strlen($order->shipping_address_1));
        } else {
            $address1 = $order->shipping_address_1;
            $options['address2'] = $order->shipping_address_2;
        }
        if (strlen($phone) > 15) {
            $phone = substr($order->shipping_telephone, 0, 15);
        }
        //获取一个海外仓订单中的所有商品
        $order_abroad_items = OrderAbroadItem::find([
            "order_id=:order_id:",
            "bind" => [
                "order_id" => $order_abroad->id
            ]
        ]);
        $items = [];
        foreach ($order_abroad_items->toArray() as $order_abroad_item) {
            $item = [
                "product_sku" => $order_abroad_item['sku'],
                "quantity" => $order_abroad_item['quantity']
            ];
            $items[] = $item;
        }
        $reference_no = $order_abroad->reference_no;
        $shippingMethod = $order_abroad->shipping_method;
        $warehouseCode = $order_abroad->warehouse_code;
        $countryCode = $order_abroad->country_code;
        $warehouseOrder = Warehouse::createOrder($reference_no, $shippingMethod, (object) $items, $order_desc, $warehouseCode, $countryCode, $name, $phone, $province, $city, $address1, $zipcode, $options);
        if ($warehouseOrder->ask == 'Success' && !empty($warehouseOrder->order_code)) {
            $order_abroad->order_code = $warehouseOrder->order_code; //海外仓系统订单号
            $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CREATE; //海外仓订单状态
            $order_abroad->update_time = time();
            if ($order_abroad->save() === false) {
                throw new \Exception(implode(";", $order_abroad->getMessages()));
            }
        } else {
            throw new \Exception($warehouseOrder->message);
        }
        return true;
    }
    
    /**
     * 获取订单中走海外仓的商品配件
     * @param int $id cfm订单id
     * @return type
     */
    public static function getOrderAbroadAccessories($id) {
        $accessory_imgs = []; //配件缓存图片
        $accessories = []; // 海外仓配件
        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['o' => 'order:OrderAbroad'])
                ->leftjoin('order:OrderAbroadItem', 'i.order_id = o.id', 'i')
                ->leftjoin('accessory:accessory', 'i.accessory_id = a.id', 'a')
                ->where("o.pid = '" . $id . "' and o.id > 0")
                ->columns(" o.id, o.ordersn, o.reference_no, o.tracking_number, o.order_code, o.status, o.create_time,"
                . " o.freight, o.fixed_cost, o.shipping_method, o.id, o.warehouse_code, o.country_code, "
                . " i.sku, i.quantity, i.accessory_id, i.accessory_cache, a.price, a.weight, a.pic_ids");

        $order_abroad_items = $build->getQuery()->execute();
        foreach ($order_abroad_items as $order_abroad_item) {
            $key = $order_abroad_item->accessory_id;
            if (empty($accessories[$key])) {
                $accessories[$key]['sku'] = $order_abroad_item->sku;
                $accessories[$key]['quantity'] = intval($order_abroad_item->quantity);
                $accessories[$key]['accessory_id'] = $order_abroad_item->accessory_id;
                $accessories[$key]['rels'] = [];
            } else {
                $accessories[$key]['quantity'] += intval($order_abroad_item->quantity);
            }
            if (empty($accessories[$key]['accessory_cache'])) {
                $accessories[$key]['accessory_cache'] = (array) json_decode($order_abroad_item->accessory_cache);
                if (empty($accessories[$key]['title_en'])) {
                    $accessories[$key]['title_en'] = empty($accessories[$key]['accessory_cache']['title_en']) ? '' : $accessories[$key]['accessory_cache']['title_en'];
                }
                if (empty($accessories[$key]['unit_price']) || floatval($accessories[$key]['unit_price']) == 0) {
                    $accessories[$key]['unit_price'] = empty($order_abroad_item->price) ? 0 : $order_abroad_item->price;
                }
                if (empty($accessories[$key]['unit_weight']) || floatval($accessories[$key]['unit_weight']) == 0) {
                    $accessories[$key]['unit_weight'] = empty($order_abroad_item->weight) ? 0 : $order_abroad_item->weight;
                }
                if (empty($accessories[$key]['accessory_cache']['thumb'])) {
                    //获取配件图片
                    if (!empty($accessory_imgs[$order_abroad_item->accessory_id]) && !empty($accessory_imgs[$order_abroad_item->accessory_id]['thumb'])) {
                        $accessories[$key]['thumb'] = $accessory_imgs[$order_abroad_item->accessory_id]['thumb'];
                    } else {
                        $picture_id = explode(',', $order_abroad_item->pic_ids)[0];
                        $picture = \Xin\Module\Picture\Model\Picture::findFirstById($picture_id);
                        $accessories[$key]['thumb'] = $picture->path;
                    }
                }
            }
            if (empty($accessories[$key]['rels'][$order_abroad_item->reference_no])) {
                $accessories[$key]['rels'][$order_abroad_item->reference_no] = [];
                $accessories[$key]['rels'][$order_abroad_item->reference_no]['quantity'] = intval($order_abroad_item->quantity);
                $accessories[$key]['rels'][$order_abroad_item->reference_no]['status'] = $order_abroad_item->status;
                $accessories[$key]['rels'][$order_abroad_item->reference_no]['tracking_number'] = $order_abroad_item->tracking_number ? $order_abroad_item->tracking_number : '';
                $accessories[$key]['rels'][$order_abroad_item->reference_no]['shipping_method'] = $order_abroad_item->shipping_method;
            }
        }
        return $accessories;
    }
    
    /**
     * 更新cfm平台海外仓订单记录
     * @param object $warehouse_order   海外仓平台订单信息
     * @param object $order_abroad   cfm存储的海外仓订单记录
     * @throws \Exception
     */
    public static function updateOrder($warehouse_order, $order_abroad) {
        if ($warehouse_order->ask == 'Success' && !empty($warehouse_order->data)) {
            //更新海外仓订单记录
            $status = $warehouse_order->data->order_status;
            //C:待发货审核 W:待发货  D:已发货 H:暂存 N:异常订单 P:问题件 X:废弃
            if ($status == 'C') {
                $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CREATE;
            } else if ($status == 'W') {
                $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_AUDIT;
            } else if ($status == 'D') {
                $order_abroad->tracking_number = $warehouse_order->data->tracking_no;
                $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL;
            } else if ($status == 'X') {
                $order_abroad->status = OrderAbroadModel::COLUMN_STATUS_CANCEL_ALL;
            } else {
                throw new \Exception($order_abroad->reference_no . '关联的海外仓订单是问题件，需至海外仓平台处理');
            }
            if (!empty($warehouse_order->data->fee_details->totalFee)) {
                $abroad_data = (array) json_decode($order_abroad->abroad_data);
                $exchange = $abroad_data['exchange'] ? floatval($abroad_data['exchange']) : floatval(Config::getValByKey('EXCHANGE'));
                $abroad_data['actual_freight'] = $warehouse_order->data->fee_details->totalFee; //实际运费 不含海外仓平台商品固定费 RMB
                $order_abroad->abroad_data = json_encode($abroad_data);
                $order_abroad->actual_freight = round(floatval($warehouse_order->data->fee_details->totalFee) / $exchange, 4);
            }
            $order_abroad->country_code = $warehouse_order->data->consignee_country_code;
            $order_abroad->warehouse_code = $warehouse_order->data->warehouse_code;
            $order_abroad->shipping_method = $warehouse_order->data->shipping_method;
            $order_abroad->order_code = $warehouse_order->data->order_code;
            if ($order_abroad->save() === false) {
                throw new \Exception('海外仓订单记录更新失败');
            }
        } else {
            throw new \Exception("未找到" . $order_abroad->reference_no . "关联的海外仓订单");
        }
        return true;
    }
    
    /**
     * 更新cfm存储的海外仓订单记录
     * @param object $order_abroad   cfm存储的海外仓订单记录
     */
    public static function updateAbroadOrder($order_abroad){
        if (empty($order_abroad->order_code)) {
            $warehouse_order = Warehouse::getOrderByRefCode($order_abroad->reference_no);
            self::updateOrder($warehouse_order, $order_abroad);
        } else {
            $warehouse_order = Warehouse::getOrderByCode($order_abroad->order_code);
            self::updateOrder($warehouse_order, $order_abroad);
        }
        return true;
    }

}