<?php

namespace Xin\Module\Order\Controller;

use Xin\Module\Category\Model\Category;
use Xin\Module\express\Model\Express;
use Xin\Module\Model\Model\ModelT;
use Xin\Lib\Utils;
use Xin\Module\Order\Lib\Order as OrderLib;
use Xin\Module\Order\Model\Order;
use Xin\Module\Order\Model\OrderStatus;
use Xin\Module\Order\Model\OrderHistory;
use Xin\Lib\Mail;
use Xin\Module\Order\Model\OrderItem;
use Xin\Module\Gallery\Model\Gallery;
use Xin\Module\Product\Model\FlagAccessory;
use Xin\Module\Product\Model\Product;
use Xin\Module\Product\Model\ProductOptCust;
use Xin\Module\Product\Model\ProductOptCustItem;
use Xin\Module\Product\Model\ProductOptCustItemToUser;
use Xin\Module\Product\Model\ProductOptCustToFlagUser;
use Xin\Module\Product\Model\ProductType;
use Xin\Module\User\Model\UserMember;
use Xin\Module\Express\Model\ExpressService;
use Xin\lib\EXCELUtil\ExcelUtil;
use Xin\Module\Product\Model\ParameterMapping;
use Xin\Module\Order\Model\OrderSendInfo;
use Xin\App\Admin\Model\AccountToRole;
use Xin\App\Admin\Model\Role;
use Xin\Model\Config;
use Xin\Lib\SqlHelper;
use Xin\Module\Express\Lib\Warehouse;
use Xin\Module\bank\Model\Bank;
use Xin\Module\bank\Model\BankRecord;
use Xin\Module\bank\Model\BankRecordDetail;
use Xin\Module\Order\Lib\OrderAbroad;
use Xin\Module\Order\Model\OrderAbroad as OrderAbroadModel;
use Xin\Module\user\Model\UserCollect;
use Xin\Module\Picture\Model\Picture;
use Xin\Module\Order\Model\OrderItemShipment;
use \Xin\Module\Queue\Lib\CallbackParse;
use Xin\Module\User\Model\User;
use Xin\Module\User\Model\UserGroup;
use Xin\Module\Email\Model\EmailRecord;
use Xin\Module\Order\Model\OrderMergeSendInfo;
use Xin\Module\User\Model\UserSperialMake;
use Xin\Module\Order\Model\OrderItemHistory;
use Xin\Model\Address;

class OrderController extends \Xin\Module\Model\Lib\Controller
{

    /**
     * 订单详情
     * @return \Xin\Lib\MessageResponse
     * @throws \Exception
     */
    public function detailAction()
    {
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::find(['user_id=?0','bind'=>[$uid]]);
        $roleArr=[];
       
        foreach($accountToRole as $toRole){
            $roleArr[]=$toRole->role_id;
        }
        $isShowArr=['13','15','14'];
        /* 是否是管理员 和 客服 */
        $isShow=false;
        /* 是否 是制图部 */
        $isDepartment=false;
        /* 是否 是业务员 */
        $isSalesman=false;

        foreach($roleArr as $role){
            if( $uid=='1' || in_array($role,$isShowArr)){
                $isShow=true; 
                break;
            }elseif($role=='17'){
                $isDepartment=true;
            }elseif($role=='12') {
                $isSalesman=false;
            }
        }
       
        
        $model = $this->_getModel();
        $id = $this->request->getQuery('id', 'int', 0);
        
        if (!$order = Order::findFirstById($id)) {
            return new \Xin\Lib\MessageResponse('无效的记录');
        }
        $thumb_pre = $this->di->get('config')['module']['picture']['uploadUriPrefix'];
        
        $this->view->setVars([
	        "admin_uid"=>$uid,
            "thumb_pre"=> $thumb_pre,
            "isShow"=>$isShow,
            "isDepartment"=>$isDepartment,
            "isSalesman"=>$isSalesman
        ]);

        //获取vip信息
        $user = User::findFirstById($order->uid);
       
        $userGroup = UserGroup::findFirstById($user->group_id);
        $isVip = $userGroup->issystem;
        $vipDiscount = $userGroup->discount;
        $orderItem = OrderLib::getOrderDetailItem($order, $vipDiscount);
        
        //是否合并订单   $order->merge_status = 1 有可能是修改交期发货   只有merge_status=1 且merge_ids不为空才是合并订单
        $isMerge = ($order->merge_status && !empty($order->merge_ids))? true : false;
        $mergeData = [
            "isMerge" => $isMerge,
            "isAbnormal" => false,
            "data" => []
        ];
        if ($isMerge) {
            //获取合并的订单信息
            $m_ids = explode(',', $order->merge_ids);
            $offset = array_search($order->id, $m_ids);
            array_splice($m_ids, $offset, 1);
            $result = OrderLib::getMergeOrderInfo($m_ids);
            $mergeData['isAbnormal'] = $result['isAbnormal']; //合并异常     合并的订单中有订单发货或已与其他订单合并
            $mergeData['data'] = $result['data'];
        }
        $this->view->setVar("mergeData", $mergeData);
        
        $artwork = [];   //图片数组
        $amount = 0;
        foreach ($orderItem as &$value) {
            if($value['product_id']==='97'){
                $custToFlagUser=\Xin\Module\Product\Model\ProductOptCustToFlagUser::findFirstByUid($order->uid);
                if($custToFlagUser){
                    $value['custToFlagUser']=[
                        '匹配架子'=>$custToFlagUser->flag_pole?$custToFlagUser->flag_pole:'标准做法',
                        '配件'=>$custToFlagUser->accessorie?$custToFlagUser->accessorie:'标准做法',
                        '腰头做法'=>$custToFlagUser->waistSize
                    ];
                }else{
                    $value['custToFlagUser']=[
                        '匹配架子'=>'标准做法',
                        '配件'=>'标准做法',
                        '腰头做法'=>'标准做法'
                    ];
                }
            }

            $value["history"]=count(OrderItemHistory::find([
                "order_item_id=:order_item_id: and status=:status: and from_id!=:admin_uid:",
                "bind" => ["order_item_id" => $value["id"], "status" => "0","admin_uid"=>$uid]
            ])->toArray());
            $amount = Utils::countAdd($amount, $value['quantity'] * $value['unit_price']);
            foreach ($value['options'] as &$vopt) {
                if (is_array($vopt['value'])) {
                    if($vopt['dispose_user_id']){
                        $vopt['dispose_user']=User::findFirst($vopt['dispose_user_id'])->username;
                    }
                    if ($vopt['value'][0]['path']) {
                        foreach ($vopt['value'] as &$gallerId) {
                            if ($gallery = Gallery::findFirstById($gallerId['id'])) {
                                $gallerId['audit_state'] = $gallery->audit_state;
                                $gallerId['group_type'] = $gallery->group_type;
                            }
                        }
                        $artwork[] = $vopt['value'][0];
                    }
                }
            }
        }
        //物流额外服务
        if ($order->extra_express_info) {
            $extra_services = [];
            foreach ($order->extra_express_info['service'] as &$v) {
                $extra_services[] = ExpressService::findFirstById($v)->title;

            }
            $this->view->setVar("extra_services", implode(",", $extra_services));
            $this->view->setVar("extra_services_set", $order->extra_express_info['settings']['address']);
        }
        $detail = $order->toArray();
        $detail['amount'] =  $amount; //根据获取的订单商品计算得出  order表中的商品金额是实时的值
        $detail['total'] = Utils::countAdd($detail['amount'], $detail['freight']);
        $detail['total'] = Utils::countSub($detail['total'], $detail['discount']);
        $detail['total'] = Utils::countSub($detail['total'], $detail['integral_dedution']);
        $detail['total'] = Utils::countSub($detail['total'], $detail['balance_amount']);
        $detail['total'] = $detail['total'] > 0? $detail['total']:0;
        $detail['handling_amount'] = 0;
        if (!empty($detail['paypal_fee']) && $detail['paypal_fee'] > 0) {
            $detail['handling_amount'] = $detail['paypal_fee'];
        } else if ($detail['payment_id'] == 2) {
            $detail['handling_amount'] = round($detail['total'] * 0.029, 2); //手续费
        }
        $detail['total'] = Utils::countAdd($detail['total'], $detail['handling_amount']);

        /* 订单邮箱 */
        $email=$order->getEmailRecord();
        //未付款订单、  账号有财务和客服权限  才能修改金额
        $modify = intval($order->order_status_id) == 10 && ($this->checkAdminRole(15) || $this->checkAdminRole(14));
        $this->view->setVars([
            'modify' => $modify,
            'pics' => $artwork,
            'uid' => $order->uid,
            'orderid' => $id,
            'obj' => $detail,
            'order' => $order->toArray(),
            'objitem' => $orderItem,
            'm' => $m,
            'c' => $c,
            'emailList'=>$email->toArray()
        ]);
    }

    /**
     * 角色权限检测
     * @param type $role_id 要检测的角色id
     */
    public function checkAdminRole($role_id)
    {
        $ticket = $this->getDI()->get('auth')->getTicket();
        $admin_uid = $ticket['uid'];//操作人员的uid
        if (!empty($admin_uid)) {
            $account_role = AccountToRole::findFirst([
                "user_id=:user_id: and role_id=:role_id:",
                "bind" => [
                    "user_id" => $admin_uid,
                    "role_id" => $role_id,
                ],
            ]);
            if (!empty($account_role)) {
                return true;
            }
        }
        return false;
    }

    public function getIp()
    {
        //判断服务器是否允许$_SERVER
        if (isset($_SERVER)) {
            if (isset($_SERVER[HTTP_X_FORWARDED_FOR])) {
                $realip = $_SERVER[HTTP_X_FORWARDED_FOR];
            } elseif (isset($_SERVER[HTTP_CLIENT_IP])) {
                $realip = $_SERVER[HTTP_CLIENT_IP];
            } else {
                $realip = $_SERVER[REMOTE_ADDR];
            }
        } else {
            //不允许就使用getenv获取
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }

        return $realip;
    }

    /**
     * 修改订单 余额多扣的退款多扣部分的金额
     *
     * @param type $order 订单数据
     * @param type $diff 多扣的余额   (负数)
     * @throws \Exception
     */
    public function modifyOrderRefundBalance($order, $diff)
    {
        $user_bank = Bank::findFirstByUid($order->uid);
        $user_bank->balance = Utils::countSub($user_bank->balance, $diff);
        //支付流水记录修改
        $total_record = BankRecord::findFirst(["uid=:uid: and ordersn=:ordersn: and type = 0", "bind" => ["uid" => $order->uid, "ordersn" => $order->ordersn]]);
        if (empty($total_record)) {
            throw new \Exception("订单修改失败");
        }
        $total_record->amount = Utils::countAdd($total_record->amount, $diff);

        $detail_records = BankRecordDetail::find([
            "uid=:uid: and pid=:pid:  and ordersn=:ordersn: and  business_type= :business_type: and type = 0",
            "bind" => ["uid" => $order->uid, "pid" => $total_record->id, "ordersn" => $order->ordersn, "business_type" => BankRecord::COLUMN_TYPE_BALANCE]
        ]);
        if (empty($detail_records)) {
            throw new \Exception("订单修改失败");
        }
        foreach ($detail_records as $detail_record) {
            //处理余额支付记录
            $record_amount = $detail_record->amount;
            if ($diff <= $detail_record->amount) {
                $detail_record->delete();
            } else {
                $detail_record->amount = Utils::countSub($detail_record->amount, $diff);
                $detail_record->after_amount = Utils::countSub($detail_record->after_amount, $diff);
                if ($detail_record->save() == false) {
                    throw new \Exception("订单修改失败");
                }
            }
            $diff = Utils::countSub($diff, $record_amount);
            if ($diff >= 0) {
                break;
            }
        }

        if ($user_bank->save() == false || $total_record->save() == false) {
            throw new \Exception("订单修改失败");
        }
    }

    /**
     * 仅修改paypal手续费
     */
    public function modifyPaypalFeeAction() {
        $ticket = $this->getDI()->get('auth')->getTicket();
        $admin_uid = $ticket['uid'];//操作人员的uid
        if (empty($admin_uid)) {
            return new \Xin\Lib\MessageResponse("当前账号未登录", 'error');
        }
        if (!$this->checkAdminRole(15) && !$this->checkAdminRole(14)) {
            return new \Xin\Lib\MessageResponse("无修改权限", 'error');
        }

        if ($this->request->isPost()) {
            $this->db->begin();
            try {
                $id = $this->request->getPost('order_id');
                $paypal_fee = floatval($this->request->getPost('paypal_fee'));
                if ($paypal_fee < 0) {
                    throw new \Exception("输入的金额不能为负数");
                }
                $order = Order::findFirstById($id);
                if (empty($order)) {
                    throw new \Exception('未找到订单');
                }
                if (intval($order->order_status_id) != 10) {
                    throw new \Exception('未付款状态的订单才能修改金额');
                }
                $order->paypal_fee = $paypal_fee;


                //添加订单记录   记录某个操作员修改订单paypal手续费
                $orderHistory = new OrderHistory();
                $orderHistory->order_id = $id;
                $orderHistory->order_status_id = $order->order_status_id;
                $orderHistory->notify = 0;
                $orderHistory->uid = $admin_uid;
                $orderHistory->username = $ticket['username'];
                $orderHistory->comment = "修改paypal手续费为 " . $paypal_fee;

                if ($orderHistory->save() === false || $order->save() === false) {
                    throw new \Exception("订单更新失败");
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('数据保存成功!', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    /**
     * 修改订单金额
     */
    public function modifyAction()
    {
        $ticket = $this->getDI()->get('auth')->getTicket();
        $admin_uid = $ticket['uid'];//操作人员的uid
        if (empty($admin_uid)) {
            return new \Xin\Lib\MessageResponse("当前账号未登录", 'error');
        }
        if (!$this->checkAdminRole(15)) {
            return new \Xin\Lib\MessageResponse("无修改权限", 'error');
        }

        if ($this->request->isPost()) {
            $this->db->begin();
            try {
                $id = $this->request->getPost('order_id');
                $amount = floatval($this->request->getPost('amount'));
                $freight = floatval($this->request->getPost('freight'));
                $paypal_fee = floatval($this->request->getPost('paypal_fee'));
                $freight_abroad = $this->request->getPost('freight_abroad');
                $unit_prices = $this->request->getPost('unit_prices');
//            $freight = Utils::countAdd($freight, $paypal_fee);
                $order_items_amount = 0;
                $order_amount = 0;
                if ($amount < 0 || $freight < 0 || $paypal_fee < 0) {
                    throw new \Exception("输入的金额不能为负数");
                }
                $order = Order::findFirstById($id);
                if (empty($order)) {
                    throw new \Exception('未找到订单');
                }
                if (intval($order->order_status_id) != 10) {
                    throw new \Exception('未付款状态的订单才能修改金额');
                }
                //获取vip信息
                $user = User::findFirstById($order->uid);
                $userGroup = UserGroup::findFirstById($user->group_id);
                $isVip = $userGroup->issystem;
                $vipDiscount = $userGroup->discount? $userGroup->discount : 0;
                $vip_discount = Utils::countSub(1 - floatval($vipDiscount) / 100);
                $log_params = [
                    "before_change" => [
                        "amount" => floatval($order->amount),
                        "freight" => floatval($order->freight),
                        "balance_amount" => floatval($order->balance_amount),
                        "paypal_fee" => $order->paypal_fee ? floatval($order->paypal_fee) : 0,
                    ],
                    "after_change" => [
                        "amount" => $amount / $vip_discount,
                        "freight" => $freight,
                        "paypal_fee" => $paypal_fee ? $paypal_fee : 0,
                    ]
                ];
                //更新订单内商品单价
                $old_order_item = [];
                $new_order_item = [];
                foreach ($unit_prices as $k => $unit_price) {
                    $order_item = OrderItem::findFirstById($k);
                    $old_order_item[] = [
                        "unit_price" => floatval($order_item->unit_price),
                        "amount" => floatval($order_item->amount)
                    ];
                    $order_items_amount = Utils::countAdd($order_items_amount, sprintf("%.2f", (floatval($unit_price) * floatval($order_item->quantity))));

                    $offerPrice = sprintf("%.2f", (floatval($unit_price) / $vip_discount)); //单品原价
                    $order_item->unit_price = $offerPrice;
                    $order_item->vipDiscount = $vipDiscount;
                    $order_item->amount = sprintf("%.2f", (floatval($offerPrice) * floatval($order_item->quantity))); //单品原价 round(floatval($offerPrice) * floatval($order_item->quantity), 2);

                    $order_amount = Utils::countAdd($order_amount, $order_item->amount);

                    $new_order_item[] = [
                        "unit_price" => $order_item->unit_price,
                        "amount" => $order_item->amount
                    ];
                    if ($order_item->save() == false) {
                        throw new \Exception("订单中商品单价修改失败");
                    }
                }
                if (abs(Utils::countSub($order_items_amount, $amount)) > 0.5) {
                    throw new \Exception("修改后订单总金额与订单单品总金额不一致");
                }
                if (abs(Utils::countSub(sprintf("%.2f", $order_amount * $vip_discount), $amount)) > 0.5) {
                    throw new \Exception("修改后订单总金额与订单单品总金额不一致");
                }
                //更新订单及 支付流水记录
                $order_amount = Utils::countAdd($amount, $freight);
                $order_amount = Utils::countAdd($order_amount, $paypal_fee);
                $diff = Utils::countSub($order_amount, $order->balance_amount);
                if ($diff < 0) {
                    //余额多抵扣退还
                    $this->modifyOrderRefundBalance($order, $diff);
                    $order->balance_amount = $order_amount;
                }

                $log_params['before_change']['order_item'] = $old_order_item;
                $log_params['after_change']['order_item'] = $new_order_item;
                $log_params['after_change']['balance_amount'] = $order->balance_amount;

                $order->amount = $amount;
                $order->freight = $freight;
                $order->paypal_fee = $paypal_fee;
                if ($order->accessory_abroad == 1) {
                    if (($freight_abroad != '' && !empty($freight_abroad)) || $freight_abroad === 0) {
                        $order->freight_abroad = $freight_abroad;
                    }
                }

                $auditlog = new \Xin\App\Admin\Model\Auditlog(); //后台日志
                $auditlog->uid = $admin_uid;
                $auditlog->action = 'order' . '.' . 'modify';
                $auditlog->create_date = date('ymd');
                $auditlog->params = json_encode($log_params);
                $auditlog->ip = $this->getIp();
                if ($order->save() == false || $auditlog->save() == false) {
                    throw new \Exception("订单更新失败");
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('数据保存成功!', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    /**
     * 修改订单物流单号
     * @return \Xin\Lib\MessageResponse
     * @throws \Exception
     */
    public function updateexpresscodeAction()
    {
        $model = $this->_getModel();
        $id = $this->request->getQuery('id', 'int', 0);
        $code = $this->request->getQuery('code');
        if (!$id || !$obj = Order::findFirstById($id)) {
            return new \Xin\Lib\MessageResponse('无效的记录');
        }
        $obj->tracking_no = trim($code);
        if ($obj->save()) {
            $orderHistory = new OrderHistory();
            $orderHistory->order_id = $id;
            $orderHistory->order_status_id = $obj->order_status_id;
            $orderHistory->notify = 0;
            $orderHistory->comment = "修改物流单号为:" . $code;
            $orderHistory->uid = $this->auth->getTicket()['uid'];
            $orderHistory->username = $this->auth->getTicket()['username'];
            $orderHistory->save();
            return new \Xin\Lib\MessageResponse('更新成功');
        } else {
            return new \Xin\Lib\MessageResponse('保存信息失败');
        }

        return new \Xin\Lib\MessageResponse('保存信息失败');

    }



    /**
     * 订单图片状态修改
     * @return \Xin\Lib\MessageResponse
     * @throws \Exception
     */
    public function updateArtworkStateAction()
    {
        $data = $_POST;
        $login_uid=$this->getDI()->get('auth')->getTicket()['uid'];
        $gallery = Gallery::findFirstById($data['gallery_id']);
        $gallery->audit_state = $data['artwork_state'];

        $item = OrderItem::findFirst($data['item_id']);
        $item->options['artwork']['dispose_user_id'] =$login_uid;
        $artwork=$item->options['artwork']['value'][0];
        $artwork['artwork_state']=$data['artwork_state'];
        $item->options['artwork']['value'][0]=$artwork;
        if ($item->save() === false) {
            return new \Xin\Lib\MessageResponse('修改图片状态失败');
        } else {
            $gallery->save();
            return new \Xin\Lib\MessageResponse('修改图片状态成功', 'succ');
        }
    }

    /**
     * 订单状态修改，并发送邮件通知客户
     * @return \Xin\Lib\MessageResponse
     * @throws \Exception
     */
    public function operateAction()
    {

        $model = $this->_getModel();
        $id = $this->request->getQuery('order_id', 'int', 0);
        if (!$id || !$obj = Order::findFirstById($id)) {
            return new \Xin\Lib\MessageResponse('无效的记录');
        }

        $orderHistory = new OrderHistory();
        $orderHistory->order_id = $id;
        $orderHistory->order_status_id =$this->request->getPost('order_status_id', 'int');
        $orderHistory->notify = $this->request->getPost('notify') ? 1 : 0;
        $orderHistory->comment = trim($this->request->getPost('comment')) ?: " ";
        $orderHistory->uid = $this->auth->getTicket()['uid'];
        $orderHistory->username = $this->auth->getTicket()['username'];

        $this->db->begin();
        try {

            if ($orderHistory->save() === false) {
                $this->di->get('logger')->error(implode(';', $orderHistory->getMessages()));
            } else {
                $email = $obj->toArray()['username'];
                $uid = $obj->toArray()['uid'];
                $user = UserMember::findFirst($uid);
                $firstname = $user->firstname;
                $lastname = $user->lastname;
                $emailname = $firstname . ' ' . $lastname;
                $obj->order_status_id = $orderHistory->order_status_id;
                $notify = $this->request->getPost('notify');
                if ($obj->order_status_id == 60) {
                    $obj->delivery_time = time();//订单状态为发货，设置发货时间
                }
                if ($obj->save() === false) {
                    throw new \Exception('Integral deduction failed!');
                } else {
                    $this->db->commit();

                    /*
                    * 生产中调用工厂接口，下单通知工厂。
                    */
                    if ($obj->order_status_id == 50) {
                        //通知工厂
                        $this->di->get('logger')->debug('通知工厂');
                        $result = $this->sendPMSInterface($id);
                        $result[1] = json_decode($result[1]);

                        $orderSendInfo = OrderSendInfo::findFirstByOrder_id($id);
                        if (!$orderSendInfo) {
                            $orderSendInfo = new OrderSendInfo();
                            $orderSendInfo->order_id = $id;
                            $orderSendInfo->send_system = '工厂';
                        }

                        if ($result[1]->success == 'true') {
                            $orderSendInfo->status = 1;
                        } else {
                            $orderSendInfo->status = 2;
                        }

                        $orderSendInfo->sendJson = $result[0];
                        $orderSendInfo->message = $result[1]->message ? $result[1]->message : '接口连接失败';
                        if (!$orderSendInfo->save()) {
                            $this->di->get('logger')->error(implode(';', $orderSendInfo->getMessages()));
                        }
                        if($result[1]->success == 'true') {
                            $this->sendOrderMergeInfo($id);
                        }
                    }
                    $ticket = $this->getDI()->get('auth')->getTicket();
                    $login_uid = $ticket['uid'];//登录人员的id
                    $emailTitle = $this->request->getPost('emailTitle');
                    $emailTask=[
                        "uid"=>$uid,
                        "order_id" => $id,
                        "title"=>$emailTitle,
                        "name"=>$emailname,
                        "email"=>$email,
                        "operaId"=>$login_uid,
                        "type"=>$notify,
                        "options"=>"",
                        "content"=>""
                    ];
                    $reason = $this->request->getPost('reason');
                    if($reason){
                        $GalleryReasons =new \Xin\Module\Gallery\DataTag;
                        $reasonsArr=$GalleryReasons->reasons();
                        foreach($obj->getOrderItem() as $orderItem){
                            $reasons=$reasonsArr[$reason[$orderItem->id]];
                            if($reasons){
                                $emailSend=[
                                    "sendTitle"=>$reasons['reason'],
                                    "sendTitle_cn"=>$reasons['reason_cn'],
                                ];
                                $orderItem->options['artwork']['emailSend']=$emailSend;
                                $orderItem->save();
                            }
                        }
                    }

                    //图稿审核不通过，发送邮件
                    if ($notify) {

                        switch($notify){
                            case 31:
                                $emailTask['title']="Your Artwork Is Being Processed";
                                $content = $this->view->getPartial('email/attach_31',['order'=>$obj->toArray(),'username'=>$emailname]);
                                $emailTask['content']=$content;
                                $record= new EmailRecord();
                                $record->save($emailTask);
                                $emailTask['EmailRecord']=$record->id;
                                if (!$this->di->get('queue')->enqueue(CallbackParse::parseTask('EmailTask', 'send',$emailTask))) {
                                    $this->di->get('logger')->error("图稿处理中邮箱任务队列失败 订单ID:" . $id);
                                    return new \Xin\Lib\MessageResponse('图稿处理中邮箱没有进入队列请重新发送', 'succ');
                                }
                                return new \Xin\Lib\MessageResponse('图稿处理中邮箱已进入队列等待发送', 'succ');
                            break;
                            case 30:

                            if($reason){


                                $emailTask['title']="Artwork Proof for Order (".$obj->ordersn.")";

                                $content = $this->view->getPartial('email/attach_error', ['reason' => $reason, 'order'=>$obj->toArray(),'username'=>$emailname]);
                                $emailTask['content']=$content;
                                $record= new EmailRecord();
                                $record->save($emailTask);
                                $emailTask['EmailRecord']=$record->id;
                                if (!$this->di->get('queue')->enqueue(CallbackParse::parseTask('EmailTask', 'send', $emailTask))) {
                                    $this->di->get('logger')->error("插入审核失败邮箱任务队列失败 订单ID:" . $id);
                                    return new \Xin\Lib\MessageResponse('发送给客户审核失败邮箱没有进入队列请重新发送', 'succ');
                                }
                                $content = $this->view->getPartial('email/attach_error', ['reason' => $reason,'order'=>$obj->toArray(),'username'=>$emailname]);
                                $emailTask['content']=$content;
                                $emailTask['email']=Config::getValByKey('ADMIN_EMAIL');
                                $record= new EmailRecord();
                                $record->save($emailTask);
                                $emailTask['EmailRecord']=$record->id;
                                if (!$this->di->get('queue')->enqueue(CallbackParse::parseTask('EmailTask', 'send', $emailTask))) {

                                    $this->di->get('logger')->error("插入审核失败邮箱任务队列失败 订单ID:" . $id);
                                }
                                return new \Xin\Lib\MessageResponse('发送给客户和客服审核邮箱已进入队列等待发送', 'succ');
                            }
                            break;
                            case 50:
                            $content = $this->view->getPartial('email/attach_production');
                            $emailTask['content']=$content;
                            $emailTask['title']="Your Order (".$obj->ordersn.") is Under Production";
                            $record= new EmailRecord();
                            $record->save($emailTask);
                            $emailTask['EmailRecord']=$record->id;
                            if (!$this->di->get('queue')->enqueue(CallbackParse::parseTask('EmailTask', 'send', $emailTask))) {
                                $this->di->get('logger')->error("插入生产中邮箱任务队列失败 订单ID:" . $id);
                                return new \Xin\Lib\MessageResponse('生产中邮箱没有进入队列请重新发送', 'succ');
                            }
                            return new \Xin\Lib\MessageResponse('发送生产中邮箱已进入队列等待发送', 'succ');
                            break;
                            // case 60:
                            // $content = $this->view->getPartial('email/attach_shipped', ['tracking'=>$this->request->getPost('express_track'),'ordersn'=>$obj->ordersn]);
                            // $emailTask['content']=$content;
                            // $emailTask['title']="Your Order (".$obj->ordersn.") has been Shipped";
                            // if (!$this->di->get('queue')->enqueue(CallbackParse::parseTask('EmailTask', 'send', $emailTask))) {
                            //     $this->di->get('logger')->error("插入生产中邮箱任务队列失败 订单ID:" . $id);
                            //     return new \Xin\Lib\MessageResponse('生产中邮箱没有进入队列请重新发送', 'succ');
                            // }
                            // return new \Xin\Lib\MessageResponse('发送生产中邮箱已进入队列等待发送', 'succ');
                            // break;
                        }
                    }

                    return new \Xin\Lib\MessageResponse('信息已保存', 'succ', ['goback', Utils::url('admin/order/list')]);
                }
            }
        } catch (\Exception $e) {
            $this->di->get('logger')->error($e->getMessage());
        }

        $this->db->rollback();
        return new \Xin\Lib\MessageResponse('保存信息失败');
    }

    /**
     * 订单信息列表
     */
    public function listAction()
    {
        $keyword = $this->request->get("keyword");
        $belong = $this->request->get("belong");
        $status = $this->request->get("status");
        //订单状态列表
        $order_status = OrderStatus::find();

        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);
        $this->view->setVars([
            'keyword'=>$keyword,
            'belong' => $belong,
            'selected_status'=>$status,
            'statusList'=> $order_status->toArray(),
            'role'=>$role->toArray()
        ]);

        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['a' => 'order:order'])
        ->innerJoin('order:orderitem', 'a.id=oi.order_id', 'oi')
        ->innerJoin('user:user', 'a.uid=u.id', 'u')
        ->groupBy('a.id');

        if ($role->rolename == Config::getValByKey('BUSINESS_ROLE')
            || $role->rolename == Config::getValByKey('SUBORDINATE_ROLE')) {
            $build = $build->innerJoin('busine:BusineUser', 'a.uid=c.user_id', 'c')
//                ->leftJoin('order:OrderSendInfo', 'b.order_id=a.id', 'b')
                ->andwhere('c.busine_id=:busineid: ', ['busineid' => $uid]);
        }
        if ($keyword) {
            $build= $build->andWhere('u.username like \'%' . SqlHelper::escapeLike($keyword) . '%\' or '
                    . ' a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\' or '
                    . ' oi.po_code like \'%' . SqlHelper::escapeLike($keyword) . '%\' or '
                    . ' a.batch_no like \'%' . SqlHelper::escapeLike($keyword) . '%\' ');
        }
        if ($belong) {
            $build = $build->LeftJoin('busine:BusineUser', 'bu.user_id  = a.uid', 'bu')
                    ->LeftJoin('user:user', 'ub.id  = bu.busine_id', 'ub');
            $build = $build->andWhere('ub.username like \'%' . SqlHelper::escapeLike($belong) . '%\'');
        }
        if ($status) {
            $build = $build->andWhere(' a.order_status_id in (' . implode(",", $status) . ')');
        }else{
            $build=$build->andWhere('a.order_status_id!=90');
        }
        $start_time = $this->request->getQuery('start_time');
        $end_time = $this->request->getQuery('end_time');
        $this->view->setVars([
            "start_time"=> $start_time,
            "end_time"=> $end_time
        ]);
        if (!empty($start_time)) {
            $start_time = strtotime($start_time);
            $build = $build->andWhere(' a.create_time >= ' . $start_time);
        }
        if (!empty($end_time)) {
            $end_time = strtotime('+1 days', strtotime($end_time));
            $build = $build->andWhere(' a.create_time <= ' . $end_time);
        }
//        $count = $build->columns('count(a.id) as count')->getQuery()->execute()->getFirst()['count']; //用了count后不能用sql  count

        $orders = $build->columns("a.id,a.uid,a.ordersn,a.username,a.amount,a.freight,a.discount,a.integral_dedution,a.create_time,a.tracking_no,a.order_status_id,a.abroad_status,a.accessory_abroad, ifnull(a.paypal_fee,0) as paypal_fee, a.payment_id as payment_id")
        ->getQuery()
        ->execute();
        $count = count($orders->toArray());

        $this->view->setVar("count", $count);
        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }
        //累计销售额
        $total_amount = 0;
        foreach ($orders as $order) {
            $order_amount = Utils::countAdd($order->freight, $order->amount);
            $discount_amount = Utils::countAdd($order->integral_dedution, $order->discount);
            $order_amount = Utils::countSub($order_amount, $discount_amount);
            if ($order->paypal_fee > 0) {
                $order_amount = Utils::countAdd($order_amount, $order->paypal_fee);
            } else if ($order->payment_id == 2) {
                $paypal_fee = sprintf("%.2f", $order_amount * 0.029);
                $order_amount = Utils::countAdd($order_amount, $paypal_fee);
            }
            $total_amount = Utils::countAdd($total_amount, $order_amount);
        }
        $this->view->setvar("total_amount", $total_amount);

        //分页数据
        $rows = $build->limit($limit, $start)
            ->orderBy('a.create_time desc')
            ->getQuery()
            ->execute()->toArray();
        if (!empty($rows)) {
            //获取vip信息
            $ids = array_column($rows, 'id');
            $orderArray = Order::find(['order_status_id=:status: and id in ({ids:array})', 'bind' => ['status' => 10, 'ids' => $ids]]);
            foreach ($orderArray as $item) {
                $user = User::findFirstById($item->uid);
                $userGroup = UserGroup::findFirstById($user->group_id);
                $isVip = $userGroup->issystem;
                $vipDiscount = $userGroup->discount;
                $freightDiscount = OrderLib::getUserVipFreightDiscount($item->uid);
                OrderLib::computeVipOrder($item, $isVip, $vipDiscount);
                OrderLib::computeOrderVipFreight($item, $freightDiscount);
            }
            unset($orderArray);
            unset($item);
            //更新订单数据后重新获取
            $rows = Order::find(["id in ({ids:array})", "bind"=>["ids"=>$ids]])->toArray();
        }

        foreach($rows as $k => $row) {
            $belong_build = new \Phalcon\Mvc\Model\Query\Builder();
            $belong_build = $belong_build->from(['b' => 'busine:BusineUser'])
                ->leftJoin('user:User', 'su.id = b.busine_id', 'su')
                ->leftJoin('Xin\App\Admin\Model\AccountToRole', 'r.user_id = su.id', 'r')
                ->where("b.user_id = " . $row['uid'])
                ->columns("su.username,su.id,r.role_id")
                ->getQuery()
                ->execute();
            foreach ($belong_build->toArray() as $belong) {
                if ($belong['role_id'] == 14) {
                    //客服
                    if ($rows[$k]['server'] != '') {
                        $rows[$k]['server'] .= ',';
                    }
                    $rows[$k]['server'] .= $belong['username'];
                } else if ($belong['role_id'] == 12) {
                    //业务员
                    if ($rows[$k]['salesman'] != '') {
                        $rows[$k]['salesman'] .= ',';
                    }
                    $rows[$k]['salesman'] .= $belong['username'];
                }
            }
        }
        // 数组倒叙
        $rows = array_reverse($rows);
        $this->view->setVar('objectlist', $rows);
    }

    /**
     * 修改订单运费
     * @return \Xin\Lib\MessageResponse
     */
    public function editOrderFreightAction()
    {

        $id = $this->request->getQuery('id');
        $freight = $this->request->getQuery('freight');

        $obj = Order::findFirstById($id);

        if (!$obj) {
            return new \Xin\Lib\MessageResponse('无效的记录');
        }

        $obj->freight = $freight;
        $obj->save();
    }

    /**
     * 订单信息导出成EXCEL
     */
    public function exportOrderAction()
    {
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        $logger = \Phalcon\Di::getDefault()->get('logger');
        $logger->debug("=========test===============");

        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['a' => 'order:order'])
        ->innerJoin('order:orderitem', 'a.id=oi.order_id', 'oi')
        ->leftJoin('user:User', 'ou.id = a.uid', 'ou')
        ->leftJoin('order:OrderSendInfo', 'b.order_id=a.id', 'b')
        ->groupBy('a.id');

        if ($role->rolename == Config::getValByKey('BUSINESS_ROLE')
            || $role->rolename == Config::getValByKey('SUBORDINATE_ROLE')) {
            $build = $build->innerJoin('busine:BusineUser', 'a.uid=c.user_id', 'c')
                ->where('c.busine_id=:busineid:', ['busineid' => $uid]);
        }

        if ($this->request->getQuery('payment')) {
            $build = $build->andwhere('a.payment_id=:payment:', ['payment' => $this->request->getQuery('payment')]);
        }

        $keyword = $this->request->get("keyword");
        $belong = $this->request->get("belong");
        if ($keyword) {
            $build= $build->andWhere('ou.username like \'%' . SqlHelper::escapeLike($keyword) . '%\' or '
                    . ' a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\' or '
                    . ' oi.po_code like \'%' . SqlHelper::escapeLike($keyword) . '%\' or '
                    . ' a.batch_no like \'%' . SqlHelper::escapeLike($keyword) . '%\' ');
        }

        if ($belong) {
            if ($role->rolename != Config::getValByKey('BUSINESS_ROLE') && $role->rolename !=Config::getValByKey('SUBORDINATE_ROLE')) {
                $build = $build->innerJoin('busine:BusineUser', 'a.uid=c.user_id', 'c');
            }
            $build = $build->LeftJoin('user:user', 'ub.id  = c.busine_id', 'ub');
            $build = $build->andWhere('ub.username like \'%' . SqlHelper::escapeLike($belong) . '%\'');
        }

        $status = $this->request->getQuery("status");
        if (!empty($status)) {
            $build = $build->andWhere(' a.order_status_id in (' . $status . ')');
        }

        $start_time = $this->request->getQuery('start_time');
        $end_time = $this->request->getQuery('end_time');
        if (!empty($start_time)) {
            $start_time = strtotime($start_time);
            $build = $build->andWhere(' a.create_time >= ' . $start_time);
        }
        if (!empty($end_time)) {
            $end_time = strtotime('+1 days', strtotime($end_time));
            $build = $build->andWhere(' a.create_time <= ' . $end_time);
        }

        $result = $build->columns(" ifnull(ou.username,'') as username,a.ordersn,a.quantity, a.id,(a.amount+a.freight-a.discount-a.integral_dedution) as sum,a.amount,
        a.freight, ifnull(a.paypal_fee,0) as paypal_fee,a.discount,a.integral_dedution,ifnull(a.freight_abroad,0) as freight_abroad,ifnull(a.actual_freight_abroad,0) as actual_freight_abroad,a.abroad_status, a.weight,a.create_time,a.estimated_delivery_time,a.tracking_no, a.accessory_abroad,a.order_status_id,b.status, '' as server, '' as salesman, a.payment_id as payment_id,a.uid as uid")
            ->orderBy('a.create_time desc')
            ->getQuery()
            ->execute();
        $logger->debug("=========testorder数据库查询===============");
        $title = array( '用户', '订单编号','产品总数', 'PO','订单总金额', '产品总金额', '运费', 'paypal手续费','卡券优惠金额', '积分抵扣金额', '海外仓预估运费(客户实付)', '海外仓实际运费','海外仓订单状态', '重量',  '下单时间', '发货时间', 'v3物流单号',  '海外仓物流单号', '订单状态', '发送状态','所属客服', '所属业务员');

        $list = $result->toArray();

        if (!empty($list)) {
            //获取vip信息
            $ids = array_column($list, 'id');
            $orderArray = Order::find(['order_status_id=:status: and id in ({ids:array})', 'bind' => ['status' => 10, 'ids' => $ids]]);
            foreach ($orderArray as $item) {
                $user = User::findFirstById($item->uid);
                $userGroup = UserGroup::findFirstById($user->group_id);
                $isVip = $userGroup->issystem;
                $vipDiscount = $userGroup->discount;
                $freightDiscount = OrderLib::getUserVipFreightDiscount($item->uid);
                OrderLib::computeVipOrder($item, $isVip, $vipDiscount);
                OrderLib::computeOrderVipFreight($item, $freightDiscount);
            }
            unset($orderArray);
            unset($item);
            //更新订单数据后重新获取
            $build = $build->andWhere('a.id in ({ids:array})',['ids' => $ids]);
            $list = $build->getQuery()->execute()->toArray();
        }

        foreach ($list as $k => $v) {
            $belong_build = new \Phalcon\Mvc\Model\Query\Builder();
            $belong_build = $belong_build->from(['b' => 'busine:BusineUser'])
                ->leftJoin('user:User', 'su.id = b.busine_id', 'su')
                ->leftJoin('Xin\App\Admin\Model\AccountToRole', 'r.user_id = su.id', 'r')
                ->where("b.user_id = " . $v['uid'])
                ->columns("su.username,su.id,r.role_id")
                ->getQuery()
                ->execute();
            foreach ($belong_build->toArray() as $belong) {
                if ($belong['role_id'] == 14) {
                    //客服
                    if ($list[$k]['server'] != '') {
                        $list[$k]['server'] .= ',';
                    }
                    $list[$k]['server'] .= $belong['username'];
                } else if ($belong['role_id'] == 12) {
                    //业务员
                    if ($list[$k]['salesman'] != '') {
                        $list[$k]['salesman'] .= ',';
                    }
                    $list[$k]['salesman'] .= $belong['username'];
                }
            }
            //paypal手续费
            if ($v['paypal_fee'] > 0) {
                $list[$k]['sum'] = Utils::countAdd($v['sum'], $v['paypal_fee']);
            } else if ($v['payment_id'] == 2) {
                $list[$k]['paypal_fee'] = sprintf("%.2f", $v['sum'] * 0.029);
                $list[$k]['sum'] = Utils::countAdd($v['sum'], $list[$k]['paypal_fee']);
            }
            $tracking_number = '';//海外仓订单物流跟踪号
            //海外仓订单状态
            if (intval($list[$k]['accessory_abroad']) == 1) {
                switch (intval($list[$k]['abroad_status'])) {
                    case OrderAbroadModel::COLUMN_STATUS_DELIVERY_ERROR:
                        $list[$k]['abroad_status'] = '出库异常';
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_AUDIT_ERROR:
                        $list[$k]['abroad_status'] = '审核异常';
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_CREATE_ERROR:
                        $list[$k]['abroad_status'] = '创建异常';
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_CREATE:
                        $list[$k]['abroad_status'] = '待审核';
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_AUDIT:
                        $list[$k]['abroad_status'] = '待发货';
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_DELIVERY_PARTIAL:
                        $list[$k]['abroad_status'] = '部分出库';
                        $tracking_number = $this->getAbroadTrackingNo($v['id']);
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL:
                        $list[$k]['abroad_status'] = '全部出库';
                        $tracking_number = $this->getAbroadTrackingNo($v['id']);
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_CANCEL_PARTIAL:
                        $list[$k]['abroad_status'] = '部分取消';
                        break;
                    case OrderAbroadModel::COLUMN_STATUS_CANCEL_ALL:
                        $list[$k]['abroad_status'] = '全部取消';
                        break;
                    default :
                        $list[$k]['abroad_status'] = '--';
                        break;
                }
            }
            $list[$k]['accessory_abroad'] = $tracking_number;  //用于显示海外仓物流跟踪号
            $order_items = OrderItem::find(["order_id = :order_id:", "bind" => ["order_id"=> $v['id']]]);
            $po_code = '';
            foreach ($order_items as $order_item) {
                if (empty($order_item->po_code)) {
                    continue;
                }
                if (in_array($order_item->po_code, explode('、', $po_code))) {
                    continue;  //PO号重复的跳过
                }
                if ($po_code != '') {
                    $po_code .= '、';
                }
                $po_code .= $order_item->po_code;
            }
            $list[$k]['id'] = 'PO : '.$po_code;  //id 字段占位 用来显示订单中所有产品的po号
            $list[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            //订单中商品已发货后才显示发货时间
            if (!empty($v['estimated_delivery_time']) && ($v['order_status_id'] == 60 || $v['order_status_id'] == 70)) {
                $list[$k]['estimated_delivery_time'] = date('Y-m-d H:i:s', $v['estimated_delivery_time']);
            } else {
                $list[$k]['estimated_delivery_time'] = '--';
            }


            $list[$k]['tracking_no'] = $v['tracking_no'] == null ? '--' : $v['tracking_no'];
            $list[$k]['order_status_id'] = (OrderStatus::findFirstById($v['order_status_id']))->name_cn;
            $list[$k]['status'] = $v['status'] == 1 ? '成功' : ($v['status'] == 2 ? '失败' : '未发送');

            unset($list[$k]['payment_id']);
            unset($list[$k]['uid']);
        }

        $filename = '订单信息' . date('Y-m-d H:i:s', time());
        $logger->debug("=========testorder开始excel导出===============");
        ExcelUtil::export($list, $title, $filename);
    }

    /**
     * 订单传输管理页面
     */
    public function sendListAction()
    {

        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        $build = new \Phalcon\Mvc\Model\Query\Builder();

        if ($role->rolename == Config::getValByKey('BUSINESS_ROLE')
            || $role->rolename == Config::getValByKey('SUBORDINATE_ROLE')) {
            $build = $build->from(['a' => 'order:order'])
                ->innerJoin('busine:BusineUser', 'a.uid=c.user_id', 'c')
                ->leftJoin('order:OrderSendInfo', 'b.order_id=a.id', 'b')
                ->where('c.busine_id=:busineid:', ['busineid' => $uid])
                ->andWhere('a.order_status_id in ({orderStatusId:array})', ['orderStatusId' => \Xin\Module\Order\Lib\Order::ARTWORK_APPROVAL])
                ->columns('count(a.id) as count');

        } else {
            $build = $build->from(['a' => 'order:order'])
                ->leftJoin('order:OrderSendInfo', 'b.order_id=a.id', 'b')
                ->where('a.order_status_id in ({orderStatusId:array})', ['orderStatusId' => \Xin\Module\Order\Lib\Order::ARTWORK_APPROVAL])
                ->columns('count(a.id) as count');
        }
        $keyword = $this->request->get("keyword");
        if ($keyword) {
            $build = $build->andWhere('a.username like \'%' . SqlHelper::escapeLike($keyword) . '%\' or a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\'');
        }
        $count = $build->getQuery()->execute()->getFirst()['count'];

        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }


        // parent::listAction();
        $result = $build->columns("a.id,a.ordersn,a.username,a.amount,a.freight,a.weight,a.discount,a.create_time,a.tracking_no,a.order_status_id,b.send_system,b.status,b.message,b.update_time")
            ->orderBy('a.create_time desc')
            ->limit($limit, $start)
            ->getQuery()
            ->execute();
        $this->view->setVars([
            'objectlist'=>$result->toArray(),
            'keyword'=>$keyword
            ]);
    }

    /**
     * 发送订单到v3系统，并记录保存订单信息
     * @return \Xin\Lib\MessageResponse
     */
    public function sendOrderAction()
    {

        $auth = $this->getDI()->get('auth');
        if (!$auth->isAuthorized()) {
            return new \Xin\Lib\MessageResponse('请登陆系统');
        }

        $id = $this->request->getQuery('id');
        if ($id < 1 || (!$order = Order::findFirstById($id))) {
            return new \Xin\Lib\MessageResponse('未找到有效的记录');
        }

        $result = $this->sendPMSInterface($id);
        //$result = '{"success":,"message":65}';
        $result[1] = json_decode($result[1]);

        $orderSendInfo = OrderSendInfo::findFirstByOrder_id($id);
        $this->db->begin();
        try {
            if (!$orderSendInfo) {
                $orderSendInfo = new OrderSendInfo();
                $orderSendInfo->order_id = $id;
                $orderSendInfo->send_system = '工厂';
            }

            if ($result[1]->success == 'true') {
                $orderSendInfo->status = 1;
            } else {
                $orderSendInfo->status = 2;
            }

            $orderSendInfo->sendJson = $result[0];
            $orderSendInfo->message = $result[1]->message ? $result[1]->message : '接口连接失败';
            if (!$orderSendInfo->save()) {
                $this->di->get('logger')->error(implode(';', $orderSendInfo->getMessages()));
            } else {
                $this->db->commit();
            }
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->di->get('logger')->error($e->getMessage());
        }

        if($result[1]->success == 'true') {
            $this->sendOrderMergeInfo($id);
        }
    }

    /**
     * 订单传输到v3接口
     * @param $orderId
     * @return array
     */
    public function sendPMSInterface($orderId)
    {
        $order = Order::findFirstById($orderId);

        //快递服务商
        $express = Express::findFirstById($order->express_id);

        $ordersn = $order->ordersn;

        //获取订单最新状态变更生产中时间
        $confirmTime = OrderHistory::find(array(
            'columns' => 'max(create_time)',
            'conditions' => 'order_id = ?0 AND order_status_id = ?1',
            'bind' => [$orderId, '50']
        ));
        //获取订单中产品信息
        $orderItem = OrderItem::find(array(
            'conditions' => 'order_id = ?0',
            'bind' => [$orderId]
        ));
        /*
         * 在9点半前（包括9点半）订单状态变更为生产中的订单，发货时间为当天发货
         * 否则第二天发货
         * 当用户选择交期时间不为24小时，发货时间为第二天发货.
         */
        $orderDate = $confirmTime->toArray()[0]->toArray();
//        $orderDate = $confirmTime->toArray()[0];

        //获取产品交期
        foreach ($orderItem->toArray() as $tmpitem) {
            $options = $tmpitem['options'];
            $options = json_decode($options);
            $turnaround = $options->turnaround;
            if($turnaround) {
                $turnaround = $turnaround->value;
                break;
            }
        }
        if($order->merge_status != 1) {
            if ($tmp = strstr($turnaround, 'H', true)) {
                $estimatedDeliveryTime = $tmp*60*60;
            } else {
                $estimatedDeliveryTime = $turnaround * 24*60*60;
            }

            $estimatedDeliveryTime = date("Y-m-d H:i:s",($orderDate[0]+ $estimatedDeliveryTime));
            $this->di->get('logger')->info('计划发货时间' . $estimatedDeliveryTime);
            $order->estimated_delivery_time = strtotime($estimatedDeliveryTime);
            if (!$order->save()) {
                return array('','{"success":"error","message":"' . '计划送货(交付)时间保存出错"' . implode(';', $order->getMessages()) . '}');
            }
            $order = Order::findFirstById($order->id);
        }

        //美国时区转换为中国时区
        date_default_timezone_set('Asia/Shanghai'); //asia/shanghai  Asia/Shanghai
//        $beginDate = date("Y-m-d H:i:s", strtotime($orderDate->readAttribute(0) . '+' . 0 . ' hours'));
//        $this->di->get('logger')->debug('转换后时间' . $beginDate);
//
        //计算交付日期
//        $deliverDate = $date = date("Y-m-d H:i:s", strtotime($orderDate->readAttribute(0) . '+' . $turnaround . ' hours'));
//        $this->di->get('logger')->debug('交付时间' . $deliverDate);

        /*
         * 在9点半前（包括9点半）订单状态变更为生产中的订单，发货时间为当天发货
         * 否则第二天发货
         * 当用户选择交期时间不为24小时，发货时间为第二天发货
         */
        $beginDate = date("Y-m-d H:i:s", $orderDate[0]);
        if($order->merge_status == 1) {
            $deliverDate = date("Y-m-d H:i:s", $order->estimated_delivery_time);
            $this->di->get('logger')->debug('合并订单发货时间' . $deliverDate);
        } else {
            if($turnaround == '24H') {
                $nowDate = strtotime(date("Y-m-d",time())) +60*60*9+30*60; //获取当天9点半时间戳
                $nowDate2 = strtotime(date('Y-m-d', time())) + 60*60*15; //获取当天下午3点时间戳
                $this->di->get('logger')->debug('当天9点半'.date("Y-m-d H:i:s", $nowDate));
                $this->di->get('logger')->debug('当天15点'.date("Y-m-d H:i:s", $nowDate2));
                $this->di->get('logger')->debug('订单时间' . date("Y-m-d H:i:s", $orderDate[0]));
                if($order->quantity <= 10 && $orderDate[0] <= $nowDate2) {
                    //发货时间
                    $deliverDate = date("Y-m-d H:i:s", $orderDate[0]);
                    $this->di->get('logger')->debug('订单产品数量小于等于10，15点前发货时间' . $deliverDate);
                } else if($orderDate[0] <= $nowDate) {
                    //发货时间
                    $deliverDate = date("Y-m-d H:i:s", $orderDate[0]);
                    $this->di->get('logger')->debug('9点半前发货时间' . $deliverDate);
                } else {
                    //发货时间
                    $deliverDate = date("Y-m-d H:i:s", $orderDate[0]+60*60*24);
                    $this->di->get('logger')->debug('9点半后发货时间' . $deliverDate);
                }
            } else {
                //发货时间
                $deliverDate = date("Y-m-d H:i:s", $orderDate[0]+60*60*24);
                $this->di->get('logger')->debug('大于24小时发货时间' . $deliverDate);
            }
        }

        //客户名称、客户编码
        $uid = $order->uid;
        $userMember = UserMember::findFirstByUid($uid);
        $firsname = $userMember->firstname;
        $lastname = $userMember->lastname;
        $customerName = $firsname . $lastname;

        $pmsArray = array();
        $pmsArray['orderNo'] = $order->ordersn;
        $pmsArray['dispatchNo'] = $order->batch_no;
        $pmsArray['orderSource'] = 'CFM';
        $pmsArray['orderName'] = '';
        $pmsArray['orderDate'] = '' . $beginDate;
        $pmsArray['deliverDate'] = '' . $deliverDate;
        $pmsArray['agentName'] = '';
        $pmsArray['agentId'] = null;
        $pmsArray['customerName'] = $customerName;
        $pmsArray['customerId'] = null;
        //poNo号
        $pmsArray['poNo'] = '';
        $pmsArray['remark'] = '';
        $pmsArray['express'] = $express->title;

        $error = $this->getAgencyAddress($order, $express, $pmsArray);
        if($error) {
            return $error;
        }

        $pmsArray['customerAddress'] = $this->getCustomerAddress($order, $express);

        //新增ein号
        $pmsArray['ein'] = $order->ein_code;

        $pmsArray['products'] = array();

        //产品信息
        $num = 0;
        foreach ($orderItem->toArray() as $item) {
            $this->ArrayProduct($item, $pmsArray, $num, $uid);
        }

        $result = [];
        if (count($pmsArray['products']) < 1) {
            $result[0] = '{}';
            $result[1] = '{"success":"error","message":"没有产品或海外仓配件暂时不传工厂"}';
            return $result;
        }
//        $pmsArray['poNo'] = trim($pmsArray['poNo'], ',');
        $jsons = json_encode($pmsArray, JSON_UNESCAPED_UNICODE);
        $result[0] = $jsons;
        $pmsJson = json_encode($pmsArray);
        $this->di->get('logger')->debug($jsons);
        $this->di->get('logger')->debug($pmsJson);

        $url = Config::getValBykey('CFM_URL');
        $this->di->get('logger')->debug($url);
        $paramsArray = array(
            'http' => array(
                'method' => 'POST',
                'header' => array('Content-type:application/json', 'key:Accept-Charset', 'value:UTF-8'),
                'content' => $pmsJson,
                'timeout' => 60 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($paramsArray);
        $result[1] = file_get_contents($url, false, $context);

        $this->di->get('logger')->debug('' . $result[1]);
        //时区在转为美国时区
        date_default_timezone_set('America/Los_Angeles');
        return $result;
    }

    /**
     * 处理代理发货地址和税金账号
     * @param $order
     * @param $express
     * @param $array
     */
    public function getAgencyAddress($order, $express, &$array) {
        //快递服务
        $expressService = null;
        $expressServiceAddress = null;
        if (count($order->extra_express_info) != 0) {
            foreach ($order->extra_express_info['service'] as $expressServiceId) {
                $expressServicePojo = ExpressService::findFirstById($expressServiceId);
                if($expressServicePojo->title != 'Freight Colect') {
                    $expressService = $expressServicePojo->title;
                }
            }
            //代理发货地址
            $expressServiceAddress = $order->extra_express_info['settings']['address'];
        }
        /*
         * 新增海关税金
         * 规则：
         * 1、用户不选择到付场景下
         *    交易方式（payMethod）、交易账号（paymentAccount）为寄件人、寄件人账号
         *
         *    关税和税金支付（dutiesPayment）为收件人，TPC\Paper less Service\Drop shipping为第三方
         *    税金账号（dutiespaymentAccount）为账号为空, TPC\Paper less Service\Drop shipping为第三方账号
         *
         * 2、用户选择到付场景下
         *    交易方式（payMethod）、交易账号（paymentAccount）为收件人、收件人账号，
         *    快递方式为TPC/Dropship,Paper less Service为第三方、第三方账号（第三方账号和收件人账号一样）
         *
         *    关税和税金支付（dutiesPayment）为第三方
         *    税金账号（dutiespaymentAccount）为第三方账号
         */
        $userCollect = UserCollect::findFirst(['user_id=?0 and express=?1',
            'bind' => [$order->uid, $order->express_id]]);
        if ($order->frieght_collect == 'enable') {
            $array['freightCollect'] = 1;
            if ($expressService) {
                if ($expressService == 'Drop shipping'
                    || $expressService == 'TPC') {
                    $array['payMethod'] = '第三方';
                }
            } else {
                $array['payMethod'] = '收件人';
            }
            if (!$userCollect->collect_account) {
                return array('','{"success":"error","message":"到付场景下，收件人或第三方账号不能为空"}');
            }
            $array['paymentAccount'] = $userCollect->collect_account;
            $array['dutiesPayment'] = '第三方';
            $array['dutiespaymentAccount'] = $userCollect->collect_account;

        } else {
            $array['freightCollect'] = 0;
            $array['payMethod'] = '寄件人';
            if ($express->title == 'UPS') {
                if(($order->origin_weight/1000) >= 20) {
                    $array['paymentAccount'] = 'E45R03';
                } else {
                    $array['paymentAccount'] = 'E4A450';
                }
            } else if($express->title == 'DHL') {
                $array['paymentAccount'] = '601348167';
            } else if($express->title == 'TNT') {
                $array['paymentAccount'] = '3054275';
            }else {
                $array['paymentAccount'] = '466264903';
            }

            $array['dutiesPayment'] = '收件人';
            $array['dutiespaymentAccount'] = '';
        }

        if ($expressService) {
            $array['shipping'] = $expressService == 'Paper less Service' ? 'PIS' : $expressService;
            if (!$userCollect->collect_account) {
                return array('','{"success":"error","message":"第三方账号不能为空"}');
            }
            //关税
            $array['dutiesPayment'] = '第三方';
            $array['dutiespaymentAccount'] = $userCollect->collect_account;

            if ($express->title == 'UPS' && empty($expressServiceAddress['company'])) {
                $array['agencyAddress']['company'] = $expressServiceAddress['lastname'].$expressServiceAddress['firstname'];
            } else {
                $array['agencyAddress']['company'] = $expressServiceAddress['company'];
            }
            $array['agencyAddress']['familyName'] = $expressServiceAddress['lastname'];
            $array['agencyAddress']['givenName'] = $expressServiceAddress['firstname'];

            if ($array['shipping'] != 'Drop shipping') {
                $array['agencyAddress']['address1'] = $expressServiceAddress['address'];
                $array['agencyAddress']['address2'] = $expressServiceAddress['address1'];
                $array['agencyAddress']['city'] = $expressServiceAddress['city'];
                $array['agencyAddress']['state'] = $expressServiceAddress['state'];
                $array['agencyAddress']['country'] = $expressServiceAddress['country'];

                $zip = str_replace('-','',$expressServiceAddress['zip']);
                $zip = str_replace(' ','',$zip);
                $phone = str_replace('-','',$expressServiceAddress['phone']);
                $phone = str_replace(' ','',$phone);
                $array['agencyAddress']['postCode'] = $zip;
                $array['agencyAddress']['tel'] = $phone;
            }
        } else {
            $array['shipping'] = '';
            $array['agencyAddress'] = null;
        }

        if($express->title == 'UPS' || $expressService == 'Paper less Service') {
            if($order->frieght_collect == 'enable') {
                $array['payMethod'] = '收件人';    //因为UPS没有第三方概念，所以交易支付方式改为收件人
            }
            $array['dutiesPayment'] = '收件人';
            $array['dutiespaymentAccount'] = '';  //因为UPS没有第三方概念，税金支付方式改为收件人
        }
    }

    /**
     * 获取客户地址
     * @param $order
     * @param $express
     * @return array
     */
    public function getCustomerAddress($order, $express) {
        $customerAddress = array();
        $customerAddress['familyName'] = $order->shipping_lastname;
        $customerAddress['givenName'] = $order->shipping_firstname;
        if ($express->title == 'UPS' && empty($order->shipping_company)) {
            $customerAddress['company'] = $order->shipping_lastname.$order->shipping_firstname;
        } else {
            $customerAddress['company'] = $order->shipping_company;
        }
        $customerAddress['address1'] = $order->shipping_address_1;
        $customerAddress['address2'] = $order->shipping_address_2;
        $customerAddress['city'] = $order->shipping_city;
        $customerAddress['state'] = $order->shipping_state;
        $customerAddress['country'] = $order->shipping_country;

        $shipping_postcode = str_replace('-','',$order->shipping_postcode);
        $shipping_postcode = str_replace(' ','',$shipping_postcode);
        $shipping_telephone = str_replace('-','',$order->shipping_telephone);
        $shipping_telephone = str_replace(' ','',$shipping_telephone);
        $customerAddress['postCode'] = $shipping_postcode;
        $customerAddress['tel'] = $shipping_telephone;
        $customerAddress['email'] = $order->shipping_email;

        return $customerAddress;
    }

    /**
     * 工厂cfm接口对接-产品组
     * @param $item
     * @param $pmsArray
     * @param $num
     * @param $uid
     */
    public function ArrayProduct($item, &$pmsArray, &$num, $uid)
    {
        $this->di->get('logger')->debug('工厂cfm接口对接-产品组');
        $product_cache = json_decode($item['product_cache']);

        $product_id = $item['product_id'];
        $product = Product::findFirstById($product_id);
        $productType = ProductType::findFirstByProduct_id($product->id);

        //获取订单产品图稿路径
        $options = $item['options'];
        $options = json_decode($options);
        $artwork_id = $options->artwork->value[0]->id;
        $gallery = Gallery::findFirstById($artwork_id);

        //测试环境时获取固定图稿地址
        $runmode = Config::getValByKey("RUNMODE")? Config::getValByKey("RUNMODE"): 'prod';
        if (strtolower($runmode) == 'dev') {
            $imageSmallPath = 'https://www.china-flag-makers.com/public/uploads/thumb/b3f3e1cc-b233-4212-8df4-666ce274346b.jpg';
        } else {
            $imageOriginalPath = $this->config['module']['gallery']['uploadUriPrefix'] . $gallery->path;
            $imageSmallPath = $this->config['module']['gallery']['uploadUriPrefix'] . $gallery->thumb;
            $this->di->get('logger')->debug('地址xx' . $this->config['module']['gallery']['uploadUriPrefix']);
        }
        /*
         * 新增水洗标
         */
        if ($options->label) {
            $sewnInLabel_id = $options->label->value[0]->id;
            $sewnInLabel_gallery = Gallery::findFirstById($sewnInLabel_id);
            //测试环境时获取固定水洗标图稿地址
            $runmode = Config::getValByKey("RUNMODE")? Config::getValByKey("RUNMODE"): 'prod';
            if (strtolower($runmode) == 'dev') {
                $sewnInLabelPath = 'https://www.china-flag-makers.com/public/uploads/thumb/1065da53-e36b-4e28-ab21-74a0c36a284e.jpg';
            } else {
                $sewnInLabelPath = $this->config['module']['gallery']['uploadUriPrefix'] . $sewnInLabel_gallery->thumb;
            }
        }
        // $imageOriginalPath = '';
        $pmsProducts = array();
        $pmsProducts['productNo'] = '' . (++$num);
//        $pmsProducts['cfmProductID'] = $item['id'].'-'.$product_id;
        $pmsProducts['model'] = '';     //规格型号
        $pmsProducts['type'] = $productType->product_type;      //一级分类
//        $pmsProducts['subtype'] = $productType->product_child_type;  //二级分类
        $pmsProducts['subtype'] = $product->title_cn;  //二级分类
        $pmsProducts['name'] = $product->title_cn;
        $pmsProducts['imageOriginalPath'] = $imageOriginalPath;
        $pmsProducts['imageSmallPath'] = $imageSmallPath;
        $pmsProducts['imageOriginalSize'] = null;
        $pmsProducts['imageSmallSize'] = null;
        $pmsProducts['imageReuse'] = null;
        if($sewnInLabelPath) {
            $pmsProducts['sewnInLabelName'] = $sewnInLabelPath;
            $pmsProducts['packingLabel'] = '水洗标';
        } else {
            $pmsProducts['packingLabel'] = '';
        }

        //帐篷面料问题,帐篷暂定面料暂定位600D过PU长丝布
        if ($productType->product_type == '帐篷') {
            $pmsProducts['material'] = '600D过PU长丝布';
            $pmsProducts['tech'] = '热转印';   //印刷工艺
        } else {
            $pmsProducts['material'] = $options->material->attr_affect->title->val;
            $pmsProducts['tech'] = $options->material->attr_affect->title->tech;
        }

        //单双层，夹层面料
        if ($options->graphic->value == 'Double' || strpos($options->material->value, 'Double')) {
            $pmsProducts['side'] = 2;
            $pmsProducts['innerMaterial'] = '遮光布';
        } else {
            $pmsProducts['side'] = 1;

        }

        $pmsProducts['quantity'] = floatval($item['quantity']);
        $pmsProducts['leafletName'] = '';
        $pmsProducts['leafletType'] = '';
        $pmsProducts['manualImageName'] = '';
        $pmsProducts['boxedLogo'] = '';
        $pmsProducts['attachName'] = '';
        if($item['vipDiscount'] && $item['vipDiscount'] != 0) {
            $pmsProducts['price'] = floatval(sprintf('%.2f',$item['unit_price'] * (1-$item['vipDiscount']/100)));
        } else {
            $pmsProducts['price'] = floatval($item['unit_price']);
        }
        /*
         * 新增产品海关信息
         */
        $pmsProducts['hsCode'] = $productType->hsCode;
        $pmsProducts['hsName'] = $productType->hsName;
        $pmsProducts['declareName'] = $productType->hsEnglishName;
        $pmsProducts['comment'] = '';
        //产品poNo号
        if(!empty($item['po_code'])) {
            $pmsProducts['poNo'] = $item['po_code'];
        } else {
            $pmsProducts['poNo'] = '';
        }

        $user = User::findFirstById($uid);
        $sperialMakeStatus = $user->productMake_status;
        $sperialMakeArray = UserSperialMake::find(['user_id=?0' , 'bind' => [$uid]]);
        $this->buildCustomsMaterial($sperialMakeStatus, $sperialMakeArray, $pmsProducts);
        switch ($productType->product_child_type) {
            case '桌布':
            case '桌罩':
            case '弹力桌罩':
                $this->tableCoversProduct($product_cache, $options, $pmsProducts, $pmsArray, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '优质型铁板展架':
            case '标准型铁板展架':
            case '直形背景墙':
            case '弧形背景墙':
                $this->vimboDisplaysProduct($productType, $product_cache, $options, $pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '凹形':
            case '凸形':
            case '角形':
            case '直行':
            case '水滴形':
            case '矩形':
                $this->displayFlagsProduct($productType, $product_cache, $options, $pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '旗杆旗':
                $this->customFlagsProduct($product_cache, $options, $pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '大旗横幅':
                $this->customBannersProduct($product_cache, $options, $pmsProducts, $pmsArray, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '帐篷顶':
            case '帐篷半围':
            case '帐篷全围':
                $this->advertisingTentsProduct($productType, $product_cache, $options, $pmsProducts, $pmsArray, $num, $item, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '自定义帐篷':
                $this->customMakeTentsProduct($uid, $options, $pmsProducts, $pmsArray,$sperialMakeStatus, $sperialMakeArray, $num, $item);
                break;
            case '自定义沙滩旗':
                $this->customMakedisplayFlagsProduct($uid, $options, $pmsProducts, $pmsArray,$sperialMakeStatus, $sperialMakeArray, $num, $item);
                break;
            case '高尔夫旗':
                $this->customGolfFlagProduct($productType, $product_cache, $options,$pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '花园旗':
                $this->customGardenFlagProduct($productType, $product_cache, $options,$pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '手挥旗':
                $this->customHandwaveFlagProduct($product_cache, $options,$pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '汽车旗':
                $this->customCarFlagProduct($productType, $product_cache, $options,$pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '锦旗':
                $this->customPennantFlagProduct($productType, $product_cache, $options,$pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '沙滩旗单独配件':
                $this->customBeachFlagProduct($productType, $product_cache, $options, $pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            case '桌布沙滩旗':
                $this->customCoverFlagProduct($productType, $product_cache, $options,$pmsProducts, $pmsArray, $num, $sperialMakeStatus, $sperialMakeArray);
                break;
            default;

        }
    }

    /**
     * 产品特殊做法
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @param $pmsArray
     * @param $key
     */
    public function  buildProductMake($sperialMakeStatus, $sperialMakeArray, &$pmsProducts, $key) {
        if($sperialMakeStatus == 'enable') {
            if(is_array($sperialMakeArray->toArray()) && count($sperialMakeArray->toArray()) > 0) {
                foreach ($sperialMakeArray as $sperialMake) {
                    if($sperialMake->key == $key) {
                        if(!empty($sperialMake->value)) $pmsProducts['remark'] = $sperialMake->value;
                    }
                }
            }
        }
    }

    /**
     * 海关编码-旗杆/架子
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @param $parts
     */
    public function  buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, &$parts) {
        if($sperialMakeStatus == 'enable') {
            if(is_array($sperialMakeArray->toArray()) && count($sperialMakeArray->toArray()) > 0) {
                foreach ($sperialMakeArray as $sperialMake) {
                    if($sperialMake->key == 'customsFlag') {
                        if(!empty($sperialMake->value)) $parts['hsCode'] = $sperialMake->value;
                    }
                    if($sperialMake->key == 'customsFlagName') {
                        if(!empty($sperialMake->value)) $parts['hsName'] = $sperialMake->value;
                    }
                }
            }
        }
    }

    /**
     * 海关编码-面料
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @param $pmsProducts
     */
    public function  buildCustomsMaterial($sperialMakeStatus, $sperialMakeArray, &$pmsProducts) {
        if($sperialMakeStatus == 'enable') {
            if(is_array($sperialMakeArray->toArray()) && count($sperialMakeArray->toArray()) > 0) {
                foreach ($sperialMakeArray as $sperialMake) {
                    if($sperialMake->key == 'customsMaterial') {
                        if(!empty($sperialMake->value)) $pmsProducts['hsCode'] = $sperialMake->value;
                    }
                    if($sperialMake->key == 'customsMaterialName') {
                        if(!empty($sperialMake->value)) $pmsProducts['hsName'] = $sperialMake->value;
                    }
                }
            }
        }
    }

    /**
     * 工厂cfm接口对接-桌布产品
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @Param $pmsArray
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     */
    public function tableCoversProduct($product_cache, $options, &$pmsProducts, &$pmsArray, $sperialMakeStatus, $sperialMakeArray)
    {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'tableCover');
        $sku = $product_cache->sku;
        if($options->size) {
            $size = $options->size->value;
        } else {
            $size = $options->acreage->value;
        }
        $mapping = ParameterMapping::find(array(
            'conditions' => 'product_sku = ?0 AND size = ?1',
            'bind' => [$sku, $size]
        ));
        //产品长度宽度
        if($mapping && count($mapping->toArray()) > 0) {
            $pmsProducts['productLength'] = floatval($mapping->toArray()[0]['length']);
            $pmsProducts['productWidth'] = floatval($mapping->toArray()[0]['width']);
        } else {
//        $acreage = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        /*
         * 2018-12-11
         * 产品面积（喷印面积）为空，v3自动计算，不需要计算
         */
        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'pvc黑色拉链袋+彩色logo标签';
//        $pmsProducts['hemming'] = $mapping->toArray()[0]['hemming'];
        $pmsProducts['hemming'] = '双线';
//        $pmsProducts['sewingMethod'] = $mapping->toArray()[0]['sewingMethod'];   //缝纫配件
        $pmsProducts['sewingMethod'] = '';

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 工厂cfm接口对接-展架产品
     * @param $productType
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     */
    public function vimboDisplaysProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray)
    {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'vimboDisplay');
        $sku = $product_cache->sku;
        $size = $options->size->value;
        $mapping = ParameterMapping::find(array(
            'conditions' => 'product_sku = ?0 AND size = ?1',
            'bind' => [$sku, $size]
        ));
        $pmsProducts['side'] = 2; //展架默认双层，不需要夹层面料

        //展架默认一种面料
        $pmsProducts['material'] = '250g丝光平布';
        $pmsProducts['tech'] ='直喷';
        //产品长度宽度
        $pmsProducts['productLength'] = floatval($mapping->toArray()[0]['length']);
        $pmsProducts['productWidth'] = floatval($mapping->toArray()[0]['width']);
//        $pmsProducts['printSquare'] = floatval($mapping->toArray()[0]['area']); //产品面积
        /*
         * 2018-12-11
         * 产品面积（喷印面积）为空，v3自动计算，不需要计算
         */
        $pmsProducts['printSquare'] = null;

        $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];
        $pmsProducts['hemming'] = $mapping->toArray()[0]['hemming'];
        $pmsProducts['sewingMethod'] = $mapping->toArray()[0]['sewingMethod'];   //缝纫配件
        $pmsProducts['polesType'] = $options->size->attr_affect->flagPole->val;

        if(strtolower($options->frame->value) == 'yes') {
            //展架配件价格 按产品价格：6（画面）：4（配件）
            $productPrice = round(floatval($pmsProducts['price']) *0.6, 2);
            $partsPrice = round(floatval($pmsProducts['price']) *0.4, 2);
            $parts = array();
            $parts['model'] = '';
            $parts['type'] = $productType->product_type;
            $parts['subtype'] = '其他';
            $parts['imageOriginalPath'] = '';
            $parts['imageSmallPath'] = '';
            $parts['imageOriginalSize'] = null;
            $parts['imageSmallSize'] = null;
            $parts['imageReuse'] = null;
            $parts['material'] = '';
            $parts['tech'] = '配件订单';
            $parts['productLength'] = null;
            $parts['productWidth'] = null;
            $parts['printSquare'] = null;
            $parts['hemming'] = '';
            $parts['polesType'] = '';
            $parts['packingBag'] = '';
            $parts['quantity'] = $pmsProducts['quantity'] * 1;
            $parts['packingLabel'] = '';
            $parts['leafletName'] = '';
            $parts['leafletType'] = '';
            $parts['manualImageName'] = '';
            $parts['boxedLogo'] = '';
            $parts['attachName'] = '';
            $parts['sewnInLabelName'] = '';
            $parts['price'] = $partsPrice;
            $parts['comment'] = '';
            $parts['productNo'] = '' . (++$num);
            $parts['name'] = $options->size->attr_affect->flagPole->val;
            $parts['hsCode'] = $options->size->attr_affect->flagPole->hsCode;
            $parts['hsName'] = $options->size->attr_affect->flagPole->hsName;
            $parts['declareName'] = $options->size->attr_affect->flagPole->hsEnglishName;
            array_push($pmsArray['products'], $parts);

            $pmsProducts['price'] = $productPrice;
        }
        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 工厂cfm接口对接-沙滩旗产品
     * @param $productType
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     */
    public function displayFlagsProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray)
    {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'displayFlag');
        $title = $product_cache->title;
        $type = $options->type->value;
        $size = $options->size->value;
        $mapping = ParameterMapping::find(array(
            'conditions' => 'product_sku = ?0 AND size = ?1',
            'bind' => [$type . ' ' . $title, $size]
        ));

        $pmsProducts['productLength'] = floatval($mapping->toArray()[0]['length']);
        $pmsProducts['productWidth'] = floatval($mapping->toArray()[0]['width']);

//        $pmsProducts['printSquare'] = floatval($mapping->toArray()[0]['area']); //产品面积
        /*
         * 2018-12-11
         * 产品面积（喷印面积）为空，v3自动计算，不需要计算
         */
        $pmsProducts['printSquare'] = null;
        $pmsProducts['hemming'] = $mapping->toArray()[0]['hemming'];    //缝纫方式
//        $pmsProducts['sewingMethod'] = $mapping->toArray()[0]['sewingMethod'];
//        $pmsProducts['waistSize'] = '7厘米，装腰，上小下大'; //腰头尺寸
        $pmsProducts['waistHemming'] = $mapping->toArray()[0]['waistHemming'];  //腰头缝线

        if ($options->type->value == 'Standard') {
            //云剑沙滩旗腰头配置
            $pmsProducts['sewingMethod'] = '云剑2个1号铜扣+弹力绳绑在铜扣'; //缝纫配件
            $pmsProducts['waistSize'] = '云剑标准'; //腰头尺寸
        } else {
            //旺展沙滩旗腰头配置
            $pmsProducts['sewingMethod'] = '旺展1个3号铜扣+弹力绳绑在铜扣'; //缝纫配件
            $pmsProducts['waistSize'] = '旺展标准'; //腰头尺寸
        }

        $pmsProducts['waistMaterial'] = '450D加密牛津布';  //腰头面料

        if ($product_cache->title_cn != '矩形沙滩旗') {
            //除矩形沙滩旗外，其他沙滩旗腰头位置
            $pmsProducts['waistPosition'] = '常规—左侧';  //腰头位置;
        } else {
            //矩形沙滩旗腰头位置
            $pmsProducts['waistPosition'] = '矩形沙滩旗—上端+左侧';  //腰头位置;
        }

        //沙滩旗配件
        $parts = array();
        $parts['model'] = '';
//        $parts['cfmProductID'] = $pmsProducts['cfmProductID'];
        $parts['type'] = $productType->product_type;
        $parts['subtype'] = '其他';
        $parts['imageOriginalPath'] = '';
        $parts['imageSmallPath'] = '';
        $parts['imageOriginalSize'] = null;
        $parts['imageSmallSize'] = null;
        $parts['imageReuse'] = null;
        $parts['material'] = '';
        $parts['tech'] = '配件订单';
        $parts['productLength'] = null;
        $parts['productWidth'] = null;
        $parts['printSquare'] = null;
        $parts['hemming'] = '';
        $parts['polesType'] = '';
        $parts['packingBag'] = '';
        $parts['quantity'] = $pmsProducts['quantity'] * 1;
        $parts['packingLabel'] = '';
        $parts['leafletName'] = '';
        $parts['leafletType'] = '';
        $parts['manualImageName'] = '';
        $parts['boxedLogo'] = '';
        $parts['attachName'] = '';
        $parts['sewnInLabelName'] = '';
        $parts['price'] = null;
        $parts['comment'] = '';

        //云剑沙滩旗画面：配件金额比例（7：3）旺展沙滩旗画面：配件金额比例（8:2）
        if(strtolower($options->mountings->value) != 'no' && $options->mountings->abroad == false) {
            if(strtolower($options->type->value) == 'standard' ) {
                $productPrice = $pmsProducts['price']*0.7;
                $partsPrice = $pmsProducts['price']*0.3;
                if(strstr($options->mountings->attr_affect->Standard->val, '+')) {
                    $tmpArray = explode('+', $options->mountings->attr_affect->Standard->val);
                    $tempCount = count($tmpArray);
                    $parts['price'] = round(floatval($partsPrice) / $tempCount, 2);
                    $pmsProducts['price'] = $productPrice;
                } else {
                    $parts['price'] = round(floatval($partsPrice)/2, 2);
                    $pmsProducts['price'] = $productPrice;
                }
            } else {
                $productPrice = $pmsProducts['price']*0.8;
                $partsPrice = $pmsProducts['price']*0.2;
                if(strstr($options->mountings->attr_affect->Deluxe->val, '+')) {
                    $tmpArray = explode('+', $options->mountings->attr_affect->Deluxe->val);
                    $tempCount = count($tmpArray) + 1;
                    $parts['price'] = round(floatval($partsPrice) / $tempCount, 2);
                    $pmsProducts['price'] = $productPrice;
                } else {
                    $parts['price'] = round(floatval($partsPrice)/2, 2);
                    $pmsProducts['price'] = $productPrice;
                }
            }
        }
        //矩形沙滩旗配件特殊处理
        if ($product_cache->title_cn != '矩形沙滩旗') {
            //凸、凹、直、角、水滴形沙滩旗
            //旗杆配件
            if (strtolower($options->mountings->value) != 'no' && $options->mountings->abroad == false) {
                $parts['productNo'] = '' . (++$num);
                $parts['name'] = $options->type->value == 'Standard' ? $options->size->attr_affect->flagPole->val . '云剑' : $options->size->attr_affect->flagPole->val . '旺展';
                $parts['hsCode'] = $options->size->attr_affect->flagPole->hsCode;
                $parts['hsName'] = $options->size->attr_affect->flagPole->hsName;
                $parts['declareName'] = $options->size->attr_affect->flagPole->hsEnglishName;
                $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                array_push($pmsArray['products'], $parts);

                if ($options->type->value == 'Standard') {
                    /*
                     * 经济型配件
                     */
                    $mountingVal = $options->mountings->attr_affect->Standard->val;

                    if (strstr($mountingVal, '+')) {
                        $mountingArray = explode('+', $mountingVal);
                        $hsCodeArray = explode('+', $options->mountings->attr_affect->Standard->hsCode);
                        $hsNameArray = explode('+', $options->mountings->attr_affect->Standard->hsName);
                        $hsEnglishNameArray = explode('+', $options->mountings->attr_affect->Standard->hsEnglishName);
                        for ($i = 0; $i < count($mountingArray); $i++) {
                            if ($i == count($mountingArray) - 1) {
                                //经济型包装袋
                                $pmsProducts['packingBag'] = $mountingArray[$i];
                                break;
                            }
//                        $parts = array();
                            $parts['productNo'] = '' . (++$num);
                            /*
                             * 凸、凹、角、直形沙滩旗尺寸为15ft(旗杆是5M杆子)
                             * 时，地钉为MBS2,其他尺寸都是MBS1地钉
                             */
                            if ($product_cache->title_cn != '水滴形沙滩旗' && $options->size->value == '15ft'
                                && $mountingArray[$i] == 'MBS1') {
                                $parts['name'] = 'MBS2云剑';
                            } else {
                                $parts['name'] = $mountingArray[$i] . '云剑';
                            }
                            $parts['hsCode'] = $hsCodeArray[$i];
                            $parts['hsName'] = $hsNameArray[$i];
                            $parts['declareName'] = $hsEnglishNameArray[$i];
                            array_push($pmsArray['products'], $parts);
                        }
                    }

                } else {
                    $mountingVal = $options->mountings->attr_affect->Deluxe->val;
                    //旺展包装袋用旗杆名称
//                    $pmsProducts['packingBag'] = $options->size->attr_affect->flagPole->val;    //豪华型包装袋
                    $pmsProducts['packingBag'] = '配旺展' . $options->size->attr_affect->flagPole->val . '储藏袋'; //豪华型包装袋

                    if (strstr($mountingVal, '+')) {
                        /*
                         * 豪华型配件
                         */
                        $mountingArray = explode('+', $mountingVal);
                        $hsCodeArray = explode('+', $options->mountings->attr_affect->Deluxe->hsCode);
                        $hsNameArray = explode('+', $options->mountings->attr_affect->Deluxe->hsName);
                        $hsEnglishNameArray = explode('+', $options->mountings->attr_affect->Deluxe->hsEnglishName);
                        for ($i = 0; $i < count($mountingArray); $i++) {
//                            $parts = array();
                            $parts['productNo'] = '' . (++$num);
                            $parts['name'] = $mountingArray[$i] . '旺展';
                            $parts['hsCode'] = $hsCodeArray[$i];
                            $parts['hsName'] = $hsNameArray[$i];
                            $parts['declareName'] = $hsEnglishNameArray[$i];
//                            $parts['partNo'] = '旺展';
//                            $parts['quantity'] = 1;
//                            $parts['process'] = '';
//                            $parts['model'] = '';
//                            $parts['store'] = '';
                            array_push($pmsArray['products'], $parts);
                        }
                    } else {
                        /*
                         * 豪华型配件
                         */
                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = $mountingVal . '旺展';
                        $parts['hsCode'] = $options->mountings->attr_affect->Deluxe->hsCode;
                        $parts['hsName'] = $options->mountings->attr_affect->Deluxe->hsName;
                        $parts['declareName'] = $options->mountings->attr_affect->Deluxe->hsEnglishName;
                        array_push($pmsArray['products'], $parts);
                    }
                }
            } else {
                $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];
            }
        } else {
            if ($options->type->value == 'Standard') {
                if (strtolower($options->mountings->value) != 'no' && $options->mountings->abroad == false) {
                    //矩形沙滩旗
                    //矩形沙滩旗10ft尺寸的旗杆和包装袋比较特殊，需单独进行处理
                    $parts['productNo'] = '' . (++$num);
                    if ($options->size->value == '10ft') {
                        $parts['name'] = $options->size->attr_affect->standard->val . '云剑';
                        $parts['hsCode'] = $options->size->attr_affect->standard->hsCode;
                        $parts['hsName'] = $options->size->attr_affect->standard->hsName;
                        $parts['declareName'] = $options->size->attr_affect->standard->hsEnglishName;
                        $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                        array_push($pmsArray['products'], $parts);
//                    array_push($pmsProducts['parts'],
//                        array('name' => $options->size->attr_affect->standard->val,
//                            'partNo' => '云剑', 'quantity' => 1, 'process' => '', 'model' => '', 'store' => ''));
                        //所有沙滩旗如果没有选择配件，包装袋为默认包装袋
                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? $options->mountings->attr_affect->Standard->special : $mapping->toArray()[0]['packingBag'];

                    } else if ($options->size->value == '14ft') {
                        //矩形沙滩旗14ft尺寸的旗杆比较特殊，需单独进行处理
                        $parts['name'] = $options->size->attr_affect->standard->val . '云剑';
                        $parts['hsCode'] = $options->size->attr_affect->standard->hsCode;
                        $parts['hsName'] = $options->size->attr_affect->standard->hsName;
                        $parts['declareName'] = $options->size->attr_affect->standard->hsEnglishName;
                        $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                        array_push($pmsArray['products'], $parts);
//                    array_push($pmsProducts['parts'],
//                        array('name' => $options->size->attr_affect->standard->val,
//                            'partNo' => '云剑', 'quantity' => 1, 'process' => '', 'model' => '', 'store' => ''));
                        //所有沙滩旗如果没有选择配件，包装袋为默认包装袋
                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? $options->mountings->attr_affect->Standard->general : $mapping->toArray()[0]['packingBag'];
                    } else {
                        $parts['name'] = $options->size->attr_affect->flagPole->val . '云剑';
                        $parts['hsCode'] = $options->size->attr_affect->flagPole->hsCode;
                        $parts['hsName'] = $options->size->attr_affect->flagPole->hsName;
                        $parts['declareName'] = $options->size->attr_affect->flagPole->hsEnglishName;
                        $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                        array_push($pmsArray['products'], $parts);
//                    array_push($pmsProducts['parts'],
//                        array('name' => $options->size->attr_affect->flagPole->val,
//                            'partNo' => '云剑', 'quantity' => 1, 'process' => '', 'model' => '', 'store' => ''));
                        //所有沙滩旗如果没有选择配件，包装袋为默认包装袋
                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? $options->mountings->attr_affect->Standard->general : $mapping->toArray()[0]['packingBag'];
                    }

                    //经济型矩形沙滩旗配件
                    $mountingVal = $options->mountings->attr_affect->Standard->val;
                    if (strstr($mountingVal, '+')) {
                        $mountingArray = explode('+', $mountingVal);
                        $hsCodeArray = explode('+', $options->mountings->attr_affect->Standard->hsCode);
                        $hsNameArray = explode('+', $options->mountings->attr_affect->Standard->hsName);
                        $hsEnglishNameArray = explode('+', $options->mountings->attr_affect->Standard->hsEnglishName);
                        for ($i = 0; $i < count($mountingArray); $i++) {
//                            $parts = array();
                            $parts['productNo'] = '' . (++$num);
                            $parts['name'] = $mountingArray[$i] . '云剑';
                            $parts['hsCode'] = $hsCodeArray[$i];
                            $parts['hsName'] = $hsNameArray[$i];
                            $parts['declareName'] = $hsEnglishNameArray[$i];
//                            $parts['partNo'] = '云剑';
//                            $parts['quantity'] = 1;
//                            $parts['process'] = '';
//                            $parts['model'] = '';
//                            $parts['store'] = '';
//                            array_push($pmsProducts['parts'], $parts);
                            array_push($pmsArray['products'], $parts);
                        }
                    } else {
                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = $mountingVal;
                        $parts['hsCode'] = $options->mountings->attr_affect->Standard->hsCode;
                        $parts['hsName'] = $options->mountings->attr_affect->Standard->hsName;
                        $parts['declareName'] = $options->mountings->attr_affect->Standard->hsEnglishName;
                        array_push($pmsArray['products'], $parts);
//                        array_push($pmsProducts['parts'],
//                            array('name' => $mountingVal, 'partNo' => '', 'quantity' => 1,
//                                'process' => '', 'model' => '', 'store' => ''));
                    }
                }
            } else {
                if (strtolower($options->mountings->value) != 'no' && $options->mountings->abroad == false) {
                    //矩形沙滩旗10ft尺寸的旗杆和包装袋比较特殊，需单独进行处理
                    $parts['productNo'] = '' . (++$num);
                    if ($options->size->value == '10ft') {
                        $parts['name'] = $options->size->attr_affect->deluxe->val . '旺展';
                        $parts['hsCode'] = $options->size->attr_affect->deluxe->hsCode;
                        $parts['hsName'] = $options->size->attr_affect->deluxe->hsName;
                        $parts['declareName'] = $options->size->attr_affect->deluxe->hsEnglishName;
                        $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                        array_push($pmsArray['products'], $parts);
//                    array_push($pmsProducts['parts'],
//                        array('name' => $options->size->attr_affect->deluxe->val,
//                            'partNo' => '旺展', 'quantity' => 1, 'process' => '', 'model' => '', 'store' => ''));
                        //旺展包装袋用旗杆名称
//                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? $options->size->attr_affect->deluxe->val : $mapping->toArray()[0]['packingBag'];
                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? '配旺展' . $options->size->attr_affect->deluxe->val . '储藏袋' : $mapping->toArray()[0]['packingBag'];
                    } else if ($options->size->value == '14ft') {
                        //矩形沙滩旗14ft尺寸的旗杆比较特殊，需单独进行处理
                        $parts['name'] = $options->size->attr_affect->deluxe->val . '旺展';
                        $parts['hsCode'] = $options->size->attr_affect->deluxe->hsCode;
                        $parts['hsName'] = $options->size->attr_affect->deluxe->hsName;
                        $parts['declareName'] = $options->size->attr_affect->standard->hsEnglishName;
                        $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                        array_push($pmsArray['products'], $parts);
//                    array_push($pmsProducts['parts'],
//                        array('name' => $options->size->attr_affect->standard->val,
//                            'partNo' => '云剑', 'quantity' => 1, 'process' => '', 'model' => '', 'store' => ''));
                        //旺展包装袋用旗杆名称
//                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? $options->size->attr_affect->flagPole->val : $mapping->toArray()[0]['packingBag'];
                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? '配旺展' . $options->size->attr_affect->flagPole->val . '储藏袋' : $mapping->toArray()[0]['packingBag'];
                    } else {
                        $parts['name'] = $options->size->attr_affect->flagPole->val;
                        $parts['hsCode'] = $options->size->attr_affect->flagPole->hsCode;
                        $parts['hsName'] = $options->size->attr_affect->flagPole->hsName;
                        $parts['declareName'] = $options->size->attr_affect->flagPole->hsEnglishName;
                        $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                        array_push($pmsArray['products'], $parts);
//                    array_push($pmsProducts['parts'],
//                        array('name' => $options->size->attr_affect->flagPole->val,
//                            'partNo' => '旺展', 'quantity' => 1, 'process' => '', 'model' => '', 'store' => ''));
                        //旺展包装袋用旗杆名称
//                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? $options->size->attr_affect->flagPole->val : $mapping->toArray()[0]['packingBag'];
                        $pmsProducts['packingBag'] = strtolower($options->mountings->value) != 'no' ? '配旺展' . $options->size->attr_affect->flagPole->val . '储藏袋' : $mapping->toArray()[0]['packingBag'];
                    }

                    //豪华型 矩形沙滩旗配件
                    $mountingVal = $options->mountings->attr_affect->Deluxe->val;
                    if (strstr($mountingVal, '+')) {
                        $mountingArray = explode('+', $mountingVal);
                        $hsCodeArray = explode('+', $options->mountings->attr_affect->Deluxe->hsCode);
                        $hsNameArray = explode('+', $options->mountings->attr_affect->Deluxe->hsName);
                        $hsEnglishNameArray = explode('+', $options->mountings->attr_affect->Deluxe->hsEnglishName);
                        for ($i = 0; $i < count($mountingArray); $i++) {
//                            $parts = array();
                            $parts['productNo'] = '' . (++$num);
                            $parts['name'] = $mountingArray[$i] . '旺展';
                            $parts['hsCode'] = $hsCodeArray[$i];
                            $parts['hsName'] = $hsNameArray[$i];
                            $parts['declareName'] = $hsEnglishNameArray[$i];
//                            $parts['partNo'] = '旺展';
//                            $parts['quantity'] = 1;
//                            $parts['process'] = '';
//                            $parts['model'] = '';
//                            $parts['store'] = '';
//                            array_push($pmsProducts['parts'], $parts);
                            array_push($pmsArray['products'], $parts);
                        }
                    } else {
                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = $mountingVal;
                        $parts['hsCode'] = $options->mountings->attr_affect->Deluxe->hsCode;
                        $parts['hsName'] = $options->mountings->attr_affect->Deluxe->hsName;
                        $parts['declareName'] = $options->mountings->attr_affect->Deluxe->hsEnglishName;
                        array_push($pmsArray['products'], $parts);
//                        array_push($pmsProducts['parts'],
//                            array('name' => $mountingVal, 'partNo' => '', 'quantity' => 1,
//                                'process' => '旺展', 'model' => '', 'store' => ''));
                    }
                }
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 工厂cfm接口对接-大旗产品
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     */
    public function customFlagsProduct($product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray)
    {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $title = $product_cache->title;
        $mapping = ParameterMapping::find(array(
            'conditions' => 'product_sku = ?0',
            'bind' => [$title]
        ));

        //产品长度宽度
        if($mapping && count($mapping->toArray()) > 0) {
            $pmsProducts['productLength'] = floatval($mapping->toArray()[0]['length']);
            $pmsProducts['productWidth'] = floatval($mapping->toArray()[0]['width']);
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

//        $pmsProducts['printSquare'] = floatval($mapping->toArray()[0]['area']); //产品面积
        /*
         * 2018-12-11
         * 产品面积（喷印面积）为空，v3自动计算，不需要计算
         */
        $pmsProducts['printSquare'] = null;
//        $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];  //包装袋
        $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';
//        $pmsProducts['hemming'] = $mapping->toArray()[0]['hemming'];
        //横幅大旗，长宽只要一边超过180cm，缝边方式用四线。否则双线
        if($pmsProducts['productLength'] >= 180 || $pmsProducts['productWidth'] >= 180) {
            $pmsProducts['hemming'] = '四线';
        } else {
            $pmsProducts['hemming'] = '双线';
        }
//        $pmsProducts['waistHemming'] = $mapping->toArray()[0]['waistHemming'];
        $pmsProducts['waistHemming'] = '双线';

        //根据画面面料判断大旗使用什么缝纫面料
        switch ($options->material->attr_affect->title->val) {
            case '100D涤纶':
                $pmsProducts['waistMaterial'] = '白色2X2牛津布';
                break;
            default :
                $pmsProducts['waistMaterial'] = '白色600D涤纶';
                break;
        }

        $pmsProducts['waistSize'] = $options->pocketsize->value_cn;
//        if ($options->pocketsize->value == '1.6') {
//            $pmsProducts['waistSize'] = $options->pocketsize->value_cn;
//        } else if ($options->pocketsize->value == '2') {
//            $pmsProducts['waistSize'] = '装腰,5cm';
//        } else if ($options->pocketsize->value == '3.2') {
//            $pmsProducts['waistSize'] = '装腰,8cm';
//        }

//        $pmsProducts['waistSize'] = $options->pocketsize->value; //腰头尺寸
        $pmsProducts['waistPosition'] = $options->polepocket->attr_affect->title->val;  //腰头位置;
        if (strtolower($options->accessories->value) != 'no') {
            if ($options->polepocket->value == 'Left Only' || $options->polepocket->value == 'Right Only'
                || $options->polepocket->value == 'Top Only') {
                if ($options->polepocket->value == 'Left Only' && $options->accessories->value == 'Rope Sewn+Toggle') {
                    $pmsProducts['sewingMethod'] = $options->accessories->attr_affect->title->val;
                } else if ($options->accessories->value == 'Webbing') {
                    $pmsProducts['sewingMethod'] = '2条' . $options->accessories->attr_affect->title->val;
                } else {
                    $pmsProducts['sewingMethod'] = '2个' . $options->accessories->attr_affect->title->val;
                }
            } else if ($options->polepocket->value == 'Left Only' || $options->polepocket->value == 'Right Only') {
                if ($options->accessories->value == 'Webbing') {
                    $pmsProducts['sewingMethod'] = '4条' . $options->accessories->attr_affect->title->val;
                } else {
                    $pmsProducts['sewingMethod'] = '4个' . $options->accessories->attr_affect->title->val;
                }
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 工厂cfm接口对接-横幅产品
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     */
    public function customBannersProduct($product_cache, $options, &$pmsProducts, &$pmsArray, $sperialMakeStatus, $sperialMakeArray)
    {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $acreage = $options->acreage->value;
        $acreage = explode('x', $acreage);
//        $printSquare = ($acreage[0]*30.48 + 4*30.48) * ($acreage[1]*30.48 + 4*30.48);   //横幅面积要怎么算
        /*
         * 横幅
         */
        $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
        $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
//        $pmsProducts['printSquare'] = round($printSquare/10000, 2); //产品面积
        /*
         * 2018-12-11
         * 产品面积（喷印面积）为空，v3自动计算，不需要计算
         */
        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';  //包装袋？
        //横幅大旗，长宽只要一边超过180cm，缝边方式用四线。否则双线
        if($pmsProducts['productLength'] >= 180 ||  $pmsProducts['productWidth'] >= 180 ) {
            $pmsProducts['hemming'] = '四线';
        } else {
            $pmsProducts['hemming'] = '双线';
        }

//        $pmsProducts['sewingMethod'] = '内缝2.5CM宽白色厚织带';
        $grommets = $options->grommets->value;
        $pmsProducts['bucklePosition'] = $grommets;
        //横幅铜扣位置和个数。None铜扣0个，Corner Only铜扣4个，其他英寸比例要工厂计算铜扣个数
        if ($grommets != 'None,None,None,None') {
            if ($grommets == 'Corner Only,Corner Only,Corner Only,Corner Only') {
                $pmsProducts['sewingMethod'] = '四角打铜扣';
            } else {
                $grommetsArray = explode(',', $grommets);
                if ($grommetsArray[0] == 'None') {
                    $sewingMethod = '上边不打铜扣;';
                } else if ($grommetsArray[0] == 'Corner Only') {
                    $sewingMethod = '上边两角打铜扣;';
                } else {
                    $sewingMethod = '上边每' . round(floatval(substr($grommetsArray[0], 0, 1) * 30.48), 2) . '厘米打一铜扣;';
                }

                if ($grommetsArray[2] == 'None') {
                    $sewingMethod = $sewingMethod . '下边不打铜扣;';
                } else if ($grommetsArray[2] == 'Corner Only') {
                    $sewingMethod = $sewingMethod . '下边两角打铜扣;';
                } else {
                    $sewingMethod = $sewingMethod . '下边每' . round(floatval(substr($grommetsArray[2], 0, 1) * 30.48), 2) . '厘米打一铜扣;';
                }

                if ($grommetsArray[3] == 'None') {
                    $sewingMethod = $sewingMethod . '左边不打铜扣;';
                } else if ($grommetsArray[3] == 'Corner Only') {
                    $sewingMethod = $sewingMethod . '左边两角打铜扣;';
                } else {
                    $sewingMethod = $sewingMethod . '左边每' . round(floatval(substr($grommetsArray[3], 0, 1) * 30.48), 2) . '厘米打一铜扣;';
                }

                if ($grommetsArray[1] == 'None') {
                    $sewingMethod = $sewingMethod . '右边不打铜扣;';
                } else if ($grommetsArray[1] == 'Corner Only') {
                    $sewingMethod = $sewingMethod . '右边两角打铜扣;';
                } else {
                    $sewingMethod = $sewingMethod . '右边每' . round(floatval(substr($grommetsArray[1], 0, 1) * 30.48), 2) . '厘米打一铜扣;';
                }

                $pmsProducts['sewingMethod'] = $sewingMethod;
//                $pmsProducts['buckleNumber'] = null;
//                $pmsProducts['sewingMethod'] = '打铜扣：每' . substr($grommets, 0, 1) . '英寸打一铜扣';
            }
        } else {
            //$pmsProducts['buckleNumber'] = 0;
            $pmsProducts['sewingMethod'] = '';
        }

        array_push($pmsArray['products'], $pmsProducts);
//        $pmsProducts['parts'] = null;
    }

    /**
     * 工厂cfm接口对接-帐篷产品
     * @param $productType
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $item
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     */
    public function advertisingTentsProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $item, $sperialMakeStatus, $sperialMakeArray)
    {
        $tempArray = array();
        $partsCount = 0;  //统计帐篷配件
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'advertisingTent');
        $title = $product_cache->title;
        $typename = $options->type->value;
        if ($options->size) {
            $product_name = $title . ' ' . $options->size->value . ' ' . $typename;
        } else {
            $product_name = $title . ' ' . $typename;
        }
        $mapping = ParameterMapping::find(array(
            'conditions' => 'product_sku = ?0',
            'bind' => [$product_name]
        ));
        $pmsProducts['productLength'] = floatval($mapping->toArray()[0]['length']);
        $pmsProducts['productWidth'] = floatval($mapping->toArray()[0]['width']);
        /*
         * 2018-12-11
         * 产品面积（喷印面积）为空，v3自动计算，不需要计算
         */
        $pmsProducts['printSquare'] = null;
        $pmsProducts['hemming'] = $mapping->toArray()[0]['hemming'];
        $pmsProducts['side'] = 1;
        if ($options->type->value == 'Square') {
            $pmsProducts['polesType'] = '四角-松品31管';
        } else if ($options->type->value == 'Hex') {
            $pmsProducts['polesType'] = '六角-浩天40管';
        } else {
            $pmsProducts['polesType'] = '六角-浩天50管';
        }
        $pmsProducts['polesType'] = $pmsProducts['polesType'] . '(' . (floatval($mapping->toArray()[0]['length']) / 100) . '*' . (floatval($mapping->toArray()[0]['width']) / 100) . 'm)';

        $parts = array();
        $parts['model'] = '';
        $parts['type'] = $productType->product_type;
        $parts['subtype'] = '其他';
        $parts['imageOriginalPath'] = '';
        $parts['imageSmallPath'] = '';
        $parts['imageOriginalSize'] = null;
        $parts['imageSmallSize'] = null;
        $parts['imageReuse'] = null;
        $parts['material'] = '';
        $parts['tech'] = '配件订单';
        $parts['productLength'] = null;
        $parts['productWidth'] = null;
        $parts['printSquare'] = null;
        $parts['hemming'] = '';
        $parts['polesType'] = '';
        $parts['packingBag'] = '';
        $parts['packingLabel'] = '';
        $parts['leafletName'] = '';
        $parts['leafletType'] = '';
        $parts['manualImageName'] = '';
        $parts['boxedLogo'] = '';
        $parts['attachName'] = '';
        $parts['sewnInLabelName'] = '';
        $parts['price'] = null;
        $parts['comment'] = '';

        $parts['quantity'] = 1;
        if ($title == 'Full Wall' || $title == 'Half Wall') {
            //半围帐篷产品有架子配件，全围帐篷没有架子配件
            if (strtolower($options->graphic->value) == 'double' || strtolower($options->wallgraphic->value) == 'double') {
                $pmsProducts['side'] = 2;
                $pmsProducts['innerMaterial'] = '遮光布';
            } else {
                $pmsProducts['side'] = 1;
            }
            if (!$options->type->abroad || $options->type->abroad == false) {
                if ($title == 'Half Wall' && strtolower($options->pole->value) == 'yes') {
                    $parts['productNo'] = '' . (++$num);
                    if ($options->type->value == 'Hex') {
                        $parts['name'] = '六角半围铝杆40MM+夹具';
                    } else if ($options->type->value == '50Hex') {
                        $parts['name'] = '六角半围铝杆50MM+夹具';
                    } else {
                        $parts['name'] = '四角半围铝杆31MM+夹具';
                    }
                    $parts['hsCode'] = $options->type->attr_affect->tentFrame->hsCode;
                    $parts['hsName'] = $options->type->attr_affect->tentFrame->hsName;
                    $parts['declareName'] = $options->type->attr_affect->tentFrame->hsEnglishName;
                    $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                    $parts['quantity'] = $pmsProducts['quantity'] * 1;
//                    array_push($pmsArray['products'], $parts);
                    array_push($tempArray, $parts);
                    $partsCount++;
                }
            } /*else {
                    // 如果轮包走海外仓的话，帐篷顶传给工厂默认包装袋
                $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];  //包装袋
            }*/
            $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];  //包装袋

            //全围半围帐篷的单双面缝纫配件不同
            if (strtolower($options->graphic->value) == 'double' || strtolower($options->wallgraphic->value) == 'double') {
                $pmsProducts['sewingMethod'] = '双面缝魔术贴（logo朝里朝外皆可）';
            } else {
                $pmsProducts['sewingMethod'] = 'logo朝外';
            }
//            array_push($pmsArray['products'], $pmsProducts);
              array_push($tempArray, $pmsProducts);
            $partsCount++;
        } else {
            //其他帐篷顶产品配件
            if ($options->graphicoption->value == 'Tent Top+Frame') {
                //其他帐篷产品-选择架子和棚顶才有架子配件
                if ($options->graphicoption->abroad == false) {
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = $options->type->attr_affect->title->val;
                    $parts['hsCode'] = $options->type->attr_affect->title->hsCode;
                    $pmsjsons = json_encode($options->type->attr_affect->title);

                    $this->di->get('logger')->debug($pmsjsons);
                    $parts['hsName'] = $options->type->attr_affect->title->hsName;
                    $parts['declareName'] = $options->type->attr_affect->title->hsEnglishName;
                    $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                    $parts['quantity'] = $pmsProducts['quantity'] * 1;
//                    array_push($pmsArray['products'], $parts);
                    array_push($tempArray, $parts);
                    $partsCount++;
                }

            }
            if ($options->wheelbag->value == 'Yes') {
                //其他帐篷产品-选择轮包，轮包分为Square、Hex、50Hex三种类型
//                $wheelBag = array();
                if ($options->wheelbag->abroad == false) {
                    $parts['productNo'] = '' . (++$num);
                    switch ($options->type->value) {
                        case 'Square':
                            $parts['name'] = $options->wheelbag->attr_affect->Square->val;
                            $parts['hsCode'] = $options->wheelbag->attr_affect->Square->hsCode;
                            $parts['hsName'] = $options->wheelbag->attr_affect->Square->hsName;
                            $parts['declareName'] = $options->wheelbag->attr_affect->Square->hsEnglishName;
                            break;
                        case 'Hex':
                            $parts['name'] = $options->wheelbag->attr_affect->Hex->val;
                            $parts['hsCode'] = $options->wheelbag->attr_affect->Hex->hsCode;
                            $parts['hsName'] = $options->wheelbag->attr_affect->Hex->hsName;
                            $parts['declareName'] = $options->wheelbag->attr_affect->Hex->hsEnglishName;
                            break;
                        default:
                            $parts['name'] = $options->wheelbag->attr_affect->fiftyHex->val;
                            $parts['hsCode'] = $options->wheelbag->attr_affect->fiftyHex->hsCode;
                            $parts['hsName'] = $options->wheelbag->attr_affect->fiftyHex->hsName;
                            $parts['declareName'] = $options->wheelbag->attr_affect->fiftyHex->hsEnglishName;
                            break;
                    }
                    $parts['quantity'] = $pmsProducts['quantity'] * 1;
//                    array_push($pmsArray['products'], $parts);
                    array_push($tempArray, $parts);
                    $partsCount++;
                } else {
                    // 如果轮包走海外仓的话，帐篷顶传给工厂默认包装袋
                    $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];  //包装袋
                }
            } else {
                //其他帐篷产品-没有选择轮包，默认一种包装袋
                $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];  //包装袋
            }
            //其他帐篷产品-其他配件
            if ($options->weight->value != 'No') {
                if(strstr($options->weight->value, '+') && count(explode('+', $options->weight->value)) > 2) {
                    if ($options->type->value == 'Square') {
                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = '四角帐篷600DPVC牛津布沙袋';
                        $parts['hsCode'] = '4202920000102';
                        $parts['hsName'] = 'Sandbag';
                        $parts['declareName'] = '100% polyester';
                        $parts['quantity'] = $pmsProducts['quantity'];
//                        array_push($pmsArray['products'], $parts);
                        array_push($tempArray, $parts);
                        $partsCount++;

                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = '绑绳+四角帐篷SP-Base-5地钉';
                        $parts['hsCode'] = '7326909000999';
                        $parts['hsName'] = 'Flag Base';
                        $parts['declareName'] = 'Steel product';
                        $parts['quantity'] = $pmsProducts['quantity'];
//                        array_push($pmsArray['products'], $parts);
                        array_push($tempArray, $parts);
                        $partsCount++;
                    } else {
                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = '六角帐篷HT-09沙袋';
                        $parts['hsCode'] = '4202920000102';
                        $parts['hsName'] = 'Sandbag';
                        $parts['declareName'] = '100% polyester';
                        $parts['quantity'] = $pmsProducts['quantity'];
//                        array_push($pmsArray['products'], $parts);
                        array_push($tempArray, $parts);
                        $partsCount++;

                        $parts['productNo'] = '' . (++$num);
                        $parts['name'] = '绑绳+六角帐篷HT-06地钉';
                        $parts['hsCode'] = '7326909000999';
                        $parts['hsName'] = 'Flag Base';
                        $parts['declareName'] = 'Steel product';
                        $parts['quantity'] = $pmsProducts['quantity'];
//                        array_push($pmsArray['products'], $parts);
                        array_push($tempArray, $parts);
                        $partsCount++;
                    }
                } else {
                    $parts['productNo'] = '' . (++$num);
                    if ($options->type->value == 'Square') {
                        $parts['name'] = $options->weight->attr_affect->Square->val;
                        $parts['hsCode'] = $options->weight->attr_affect->Square->hsCode;
                        $parts['hsName'] = $options->weight->attr_affect->Square->hsName;
                        $parts['declareName'] = $options->weight->attr_affect->Square->hsEnglishName;
                    } else {
                        $parts['name'] = $options->weight->attr_affect->Hex->val;
                        $parts['hsCode'] = $options->weight->attr_affect->Hex->hsCode;
                        $parts['hsName'] = $options->weight->attr_affect->Hex->hsName;
                        $parts['declareName'] = $options->weight->attr_affect->Hex->hsEnglishName;
                    }
                    $parts['quantity'] = $pmsProducts['quantity'];
//                    array_push($pmsArray['products'], $parts);
                    array_push($tempArray, $parts);
                    $partsCount++;
                }
            }

            /*
             * 帐篷产品组合包
             * 全围半围单双面帐篷缝纫配件不同
             */
            if ($options->walloption->value && $options->walloption->value != 'No') {
                if (strtolower($options->graphic->value) == 'double' || strtolower($options->wallgraphic->value) == 'double') {
                    $sewingMethod = '双面相同logo/不同logo-根据图稿判断';
                } else {
                    $sewingMethod = 'logo朝外';
                }
                if (strtolower($options->graphic->value) == 'double' || strtolower($options->wallgraphic->value) == 'double') {
                    $side = 2;
                    $innerMaterial = '遮光布';
                } else {
                    $side = 1;
                }
                $quantity = $pmsProducts['quantity'];   //产品数量
                if ($options->walloption->value == '2 Half Side Walls+1 Full Back Wall') {
                    array_push($tempArray, $pmsProducts);
                    $partsCount++;

                    $pmsProducts['side'] = $side;
                    $pmsProducts['innerMaterial'] = $innerMaterial;
                    $pmsProducts['sewingMethod'] = $sewingMethod;
                    $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];
                    //半围组合
                    $pmsProducts['productNo'] = '' . (++$num);
                    $pmsProducts['subtype'] = '半围';  //二级分类
                    $pmsProducts['name'] = '半围';
                    $pmsProducts['quantity'] = $quantity * 2;
                    // 面积
                    if (strstr($title, '10x10')) {
                        $halfCondition = 'Half Wall 10*10 ' . $options->type->value;
                        $fullCondition = 'Full Wall 10*10 ' . $options->type->value;

                    } else if (strstr($title, '10x15')) {
                        $halfCondition = 'Half Wall 10*15 ' . $options->type->value;
                        $fullCondition = 'Full Wall 10*15 ' . $options->type->value;

                    } else if (strstr($title, '10x20')) {
                        $halfCondition = 'Half Wall 10*20 ' . $options->type->value;
                        $fullCondition = 'Full Wall 10*20 ' . $options->type->value;
                    }
                    $half = ParameterMapping::find(array(
                        'conditions' => 'product_sku = ?0',
                        'bind' => [$halfCondition]
                    ));
                    $pmsProducts['productLength'] = floatval($half->toArray()[0]['length']);
                    $pmsProducts['productWidth'] = floatval($half->toArray()[0]['width']);
//                    array_push($pmsArray['products'], $pmsProducts);
                    array_push($tempArray, $pmsProducts);
                    $partsCount = $partsCount+2;
                    //架子海外仓
                    $parts['productNo'] = '' . (++$num);
                    if ($options->type->value == 'Hex') {
                        $parts['name'] = '六角半围铝杆40MM+夹具';
                    } else if ($options->type->value == '50Hex') {
                        $parts['name'] = '六角半围铝杆50MM+夹具';
                    } else {
                        $parts['name'] = '四角半围铝杆31MM+夹具';
                    }

                    $parts['hsCode'] = $options->type->attr_affect->title->hsCode;
                    $parts['hsName'] = $options->type->attr_affect->title->hsName;
                    $parts['declareName'] = $options->type->attr_affect->title->hsEnglishName;
                    $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                    $parts['quantity'] = $quantity * 2;
//                    array_push($pmsArray['products'], $parts);
                    array_push($tempArray, $parts);
                    $partsCount = $partsCount+2;
                    //全围组合
                    $pmsProducts['productNo'] = '' . (++$num);
                    $pmsProducts['subtype'] = '全围';  //二级分类
                    $pmsProducts['name'] = '全围';
                    //全围面积
                    $full = ParameterMapping::find(array(
                        'conditions' => 'product_sku = ?0',
                        'bind' => [$fullCondition]
                    ));
                    $pmsProducts['productLength'] = floatval($full->toArray()[0]['length']);
                    $pmsProducts['productWidth'] = floatval($full->toArray()[0]['width']);
                    $pmsProducts['quantity'] = $quantity;
//                    array_push($pmsArray['products'], $pmsProducts);
                    array_push($tempArray, $pmsProducts);
                    $partsCount++;
                } else if ($options->walloption->value == '2 Half Side Walls') {
                    array_push($tempArray, $pmsProducts);
                    $partsCount++;

                    $pmsProducts['side'] = $side;
                    $pmsProducts['innerMaterial'] = $innerMaterial;
                    $pmsProducts['sewingMethod'] = $sewingMethod;
                    $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];
                    //半围组合
                    $pmsProducts['productNo'] = '' . (++$num);
                    $pmsProducts['subtype'] = '半围';  //二级分类
                    $pmsProducts['name'] = '半围';
                    $pmsProducts['quantity'] = $quantity * 2;
                    // 面积
                    if (strstr($title, '10x10')) {
                        $halfCondition = 'Half Wall 10*10 ' . $options->type->value;
                    } else if (strstr($title, '10x15')) {
                        $halfCondition = 'Half Wall 10*15 ' . $options->type->value;
                    } else if (strstr($title, '10x20')) {
                        $halfCondition = 'Half Wall 10*20 ' . $options->type->value;
                    }
                    $half = ParameterMapping::find(array(
                        'conditions' => 'product_sku = ?0',
                        'bind' => [$halfCondition]
                    ));
                    $pmsProducts['productLength'] = floatval($half->toArray()[0]['length']);
                    $pmsProducts['productWidth'] = floatval($half->toArray()[0]['width']);
//                    array_push($pmsArray['products'], $pmsProducts);
                    array_push($tempArray, $pmsProducts);
                    $partsCount = $partsCount+2;
                    //架子海外仓
                    $parts['productNo'] = '' . (++$num);
                    if ($options->type->value == 'Hex') {
                        $parts['name'] = '六角半围铝杆40MM+夹具';
                    } else if ($options->type->value == '50Hex') {
                        $parts['name'] = '六角半围铝杆50MM+夹具';
                    } else {
                        $parts['name'] = '四角半围铝杆31MM+夹具';
                    }
                    $parts['hsCode'] = $options->type->attr_affect->title->hsCode;
                    $parts['hsName'] = $options->type->attr_affect->title->hsName;
                    $parts['declareName'] = $options->type->attr_affect->title->hsEnglishName;
                    $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
                    $parts['quantity'] = $quantity * 2;
//                    array_push($pmsArray['products'], $parts);
                    array_push($tempArray, $parts);
                    $partsCount = $partsCount+2;

                } else if ($options->walloption->value == '1 Full Back Wall') {
                    array_push($tempArray, $pmsProducts);
                    $partsCount++;

                    $pmsProducts['side'] = $side;
                    $pmsProducts['innerMaterial'] = $innerMaterial;
                    $pmsProducts['sewingMethod'] = $sewingMethod;
                    $pmsProducts['packingBag'] = $mapping->toArray()[0]['packingBag'];
                    // 面积
                    if (strstr($title, '10x10')) {
                        $fullCondition = 'Full Wall 10*10 ' . $options->type->value;

                    } else if (strstr($title, '10x15')) {
                        $fullCondition = 'Full Wall 10*15 ' . $options->type->value;
                    } else if (strstr($title, '10x20')) {
                        $fullCondition = 'Full Wall 10*20 ' . $options->type->value;
                    }
                    //全围组合
                    $pmsProducts['productNo'] = '' . (++$num);
                    $pmsProducts['subtype'] = '全围';  //二级分类
                    $pmsProducts['name'] = '全围';
                    //全围面积
                    $full = ParameterMapping::find(array(
                        'conditions' => 'product_sku = ?0',
                        'bind' => [$fullCondition]
                    ));
                    $pmsProducts['productLength'] = floatval($full->toArray()[0]['length']);
                    $pmsProducts['productWidth'] = floatval($full->toArray()[0]['width']);
                    $pmsProducts['quantity'] = $quantity;
//                    array_push($pmsArray['products'], $pmsProducts);
                    array_push($tempArray, $pmsProducts);
                    $partsCount++;
                }
            } else {
//                array_push($pmsArray['products'], $pmsProducts);
                array_push($tempArray, $pmsProducts);
                $partsCount++;
            }
        }

        //帐篷画面：配件金额比例（5:5）
        if($partsCount > 0) {
            $productPrice = round(floatval($pmsProducts['price']) / $partsCount, 2);
            for($j=0; $j <count($tempArray); $j++) {
                $tempArray[$j]['price'] = $productPrice;
                array_push($pmsArray['products'], $tempArray[$j]);
            }
        } else {
            array_push($pmsArray['products'], $tempArray[0]);
        }
    }

    public function orderFormAction()
    {
        $keyword = $this->request->get("keyword");
        $this->view->setVar('keyword', $keyword);
        $status = $this->request->get("status");
        $this->view->setVar('selected_status', $status);

        //订单状态列表
        $order_status = OrderStatus::find();
        $this->view->setVar('statusList', $order_status->toArray());

        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['a' => 'order:order'])
            ->andWhere('a.payment_id=:num:', ['num' => '1']);
        if ($role->rolename == Config::getValByKey('BUSINESS_ROLE')
            || $role->rolename == Config::getValByKey('SUBORDINATE_ROLE')) {
            $build = $build
                ->innerJoin('busine:BusineUser', 'a.uid=c.user_id', 'c')
                ->where('c.busine_id=:busineid: and a.payment_id=:num:', ['busineid' => $uid, 'num' => '1'])
                ->columns('count(a.id) as count');

            if ($keyword) {
                $build = $build->andWhere('a.username like \'%' . SqlHelper::escapeLike($keyword) . '%\' or a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\'');
            }
            if ($status) {
                $build = $build->andWhere('a.order_status_id = ' . $status);
            }
        } else {
            $build = $build->columns('count(a.id) as count');

            if ($keyword) {
                $build = $build->where('a.username like \'%' . SqlHelper::escapeLike($keyword) . '%\' or a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\'');
            }
            if ($status) {
                $build = $build->andWhere('a.order_status_id = ' . $status);
            }
        }


        $result = $build->columns("a.id,a.ordersn,a.username,a.amount,a.freight,a.weight,a.discount,a.integral_dedution,a.create_time,a.tracking_no,a.order_status_id")
            ->orderBy('a.create_time desc')
            ->limit($limit, $start)
//            ->groupby('a.id')
            ->getQuery()
            ->execute();

        $orderItem = $build->columns('b.options,b.quantity,c.category_id,b.order_id')
            ->leftJoin('order:orderItem', 'b.order_id=a.id', 'b')
            ->leftJoin('product:product', 'c.id=b.product_id', 'c')
            ->getQuery()
            ->execute();
        $result = $result->toArray();
        $i = 0;
        foreach ($result as $key => $value) {
            foreach ($orderItem as $item) {
                if ($value['id'] == $item->order_id) {
                    $options = json_decode($item->options);
                    if ($item->category_id == 77 || $item->category_id == 80) {
                        if ((int)$item->quantity >= 10) {
                            $result[$key]['isShow'] = '帐篷大于9面';
                            $i++;
                        }
                    }  else {
                        if ((int)$item->quantity >= 100) {
                            $result[$key]['isShow'] = '数量大于99';
                            $i++;
                        }
                    }
                }
            }
        }
        $count = $i;
        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }
        $this->view->setVar('objectlist', $result);

    }

    /**
     * 审核海外仓订单
     * @return \Xin\Lib\MessageResponse
     * @throws \Exception
     */
    public function examineAbroadAction()
    {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $this->db->begin();
            try {
                $order = Order::findFirstById($id);
                if (empty($order)) {
                    throw new \Exception('未找到订单');
                }
                if (empty($order->accessory_abroad) || $order->accessory_abroad == 0) {
                    throw new \Exception('订单没有关联的海外仓订单');
                }
                $order_abroads = OrderAbroadModel::find([
                    "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                foreach ($order_abroads as $order_abroad) {
                    $warehouse_order = Warehouse::getOrderByCode($order_abroad->order_code);
                    if ($warehouse_order->ask == 'Success' && !empty($warehouse_order->data)) {
                        $status = $warehouse_order->data->order_status;
                        //C:待发货审核 W:待发货  D:已发货 H:暂存 N:异常订单 P:问题件 X:废弃
                        if ($status == 'C') {
                            $res = OrderAbroad::auditAbroadOrder($order_abroad);
                            if (!$res['status']) {
                                throw new \Exception('海外仓订单审核失败');
                            }
                        } else if ($status == 'W' || $status == 'D') {
                            continue;
                        } else if ($status == 'X') {
                            throw new \Exception('海外仓订单' . $order_abroad->order_code . '废弃');
                        } else {
                            throw new \Exception('海外仓订单' . $order_abroad->order_code . '异常');
                        }
                    } else {
                        throw new \Exception('未找到关联的海外仓订单' . $order_abroad->order_code);
                    }
                }
                //计算cfm订单所有关联海外仓订单的实际总运费
                $abroad_orders = OrderAbroadModel::find([
                    "conditions" => "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                $actual_freight = 0;
                foreach ($abroad_orders as $abroad_order) {
                    $item_freight = Utils::countAdd($abroad_order->actual_freight, $abroad_order->fixed_cost);
                    $actual_freight = Utils::countAdd($actual_freight, $item_freight);
                }
                if ($order->abroad_status == OrderAbroadModel::COLUMN_STATUS_CREATE || $order->abroad_status == OrderAbroadModel::COLUMN_STATUS_AUDIT_ERROR) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_AUDIT;
                }
                $order->actual_freight_abroad = $actual_freight;
                if ($order->save() === false) {
                    throw new \Exception('海外仓订单审核失败');
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('订单审核成功', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                $this->logger->error('examineAbroad-' . $e->getMessage());
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    /**
     * 海外仓订单信息列表
     */
    public function abroadListAction()
    {
        $keyword = $this->request->get("keyword");
        $this->view->setVar('keyword', $keyword);
        $status = $this->request->get("status");
        $this->view->setVar('status', $status);

        $uid = $this->getDI()->get('auth')->getTicket()['uid'];

        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['a' => 'order:orderabroad'])
//                ->leftJoin('order:OrderSendInfo', 'b.order_id=a.id', 'b')
            ->columns('count(a.id) as count');

        if ($keyword) {
            $build = $build->where('a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\' or a.reference_no like \'%' . SqlHelper::escapeLike($keyword) . '%\' '
                . ' or a.order_code like \'%' . SqlHelper::escapeLike($keyword) . '%\' or a.tracking_number like \'%' . SqlHelper::escapeLike($keyword) . '%\'');
        }
        if ($status != "") {
            $build = $build->andWhere('a.status = ' . $status);
        }
        $count = $build->getQuery()->execute()->getFirst()['count'];

        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }
        $result = $build->columns("a.id,a.ordersn,a.reference_no,a.tracking_number,a.order_code,a.status,ifnull(a.freight, 0) as freight,ifnull(a.actual_freight, 0) as actual_freight,ifnull(a.fixed_cost, 0) as fixed_cost,a.shipping_method,a.warehouse_code,a.abroad_data, a.create_time")
            ->orderBy('a.create_time desc')
            ->limit($limit, $start)
            ->getQuery()
            ->execute();
        $this->view->setVar('objectlist', $result->toArray());
    }

    /**
     * 海外仓订单详情
     */
    public function abroadorderAction()
    {

    }

    /**
     * 修复海外仓订单异常
     */
    public function repairAction()
    {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $this->db->begin();
            try {
                $order_abroad = OrderAbroadModel::findFirstById($id);
                if (intval($order_abroad->status) >= 0) {
                    return new \Xin\Lib\MessageResponse('订单' . $order_abroad->reference_no . '无异常,请刷新页面查看', 'error');
                }
                $res = OrderAbroad::repairAbroadOrder($order_abroad);
//                if (!$res) {
//                    return new \Xin\Lib\MessageResponse('订单修复失败', 'error');
//                }
                $order = Order::findFirstByOrdersn($order_abroad->ordersn);
                if (empty($order)) {
                    return new \Xin\Lib\MessageResponse('未找到cfm订单' . $order_abroad->ordersn, 'error');
                }
                //计算cfm订单所有关联海外仓订单的实际总运费
                $abroad_orders = OrderAbroadModel::find([
                    "conditions" => "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                $actual_freight = 0;
                foreach ($abroad_orders as $abroad_order) {
                    $item_freight = Utils::countAdd($abroad_order->actual_freight, $abroad_order->fixed_cost);
                    $actual_freight = Utils::countAdd($actual_freight, $item_freight);
                }
                $has_paid = (intval($order->order_status_id) == 10 || intval($order->order_status_id) == 90) ? false : true;
                $all_delivery = true;
                $status = 0;
                foreach ($abroad_orders as $abroad_order) {
                    if ($status < 0) {
                        break;
                    }
                    if (intval($abroad_order->status) < 0) {
                        $status = intval($abroad_order->status);
                    }
                    $status = intval($abroad_order->status) > intval($status) ? intval($abroad_order->status) : $status;
                    if (empty($abroad_order->order_code)) {
                        $status = OrderAbroadModel::COLUMN_STATUS_CREATE_ERROR;
                        $this->updateAbroadOrderStatus($abroad_order,$status);
                        break;
                    }
                    if ($has_paid && intval($abroad_order->status) == OrderAbroadModel::COLUMN_STATUS_CREATE) {
                        //支付提交审核， 订单审核异常
                        $status = OrderAbroadModel::COLUMN_STATUS_AUDIT_ERROR;
                        $this->updateAbroadOrderStatus($abroad_order,$status);
                        break;
                    }
                    if (!$has_paid && intval($abroad_order->status) == OrderAbroadModel::COLUMN_STATUS_AUDIT) {
                        //未支付却提交审核， 订单未支付审核异常
                        $status = OrderAbroadModel::COLUMN_STATUS_NOPAID_AUDIT_ERROR;
                        $this->updateAbroadOrderStatus($abroad_order,$status);
                        break;
                    }
                    if ($status == OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL) {
                        $status = 2;
                    } else {
                        $all_delivery = false;
                    }
                }
                if ($status == 2 && $all_delivery) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL;
                } else {
                    $order->abroad_status = $status;
                }
                $order->actual_freight_abroad = $actual_freight;
                if ($order->save() === false) {
                    throw new \Exception('海外仓订单修复失败');
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('订单修复成功', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                $this->logger->error('repairAction-' . $e->getMessage());
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    public function updateAbroadOrderStatus($abroad_order,$status) {
        $abroad_order->status = $status;
        if ($abroad_order->save() === false) {
            throw new \Exception('海外仓订单' . $abroad_order->reference_no . '状态更新失败');
        }
    }

    /**
     * 修复cfm关联的海外仓订单异常
     */
    public function repairAbnormalAction()
    {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $this->db->begin();
            try {
                $order = Order::findFirstById($id);
                if (empty($order)) {
                    throw new \Exception('未找到cfm订单' . $order->ordersn);
                }
                if (intval($order->abroad_status) >= 0) {
                    return new \Xin\Lib\MessageResponse('订单' . $order->ordersn . '无异常,请刷新页面查看', 'error');
                }
                //计算cfm订单所有关联海外仓订单的实际总运费
                $abroad_orders = OrderAbroadModel::find([
                    "conditions" => "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                foreach ($abroad_orders as $abroad_order) {
                    if (intval($abroad_order->status) >= 0) {
                        continue;
                    }
                    $res = OrderAbroad::repairAbroadOrder($abroad_order);
                    if (!$res) {
                        throw new \Exception('海外仓订单' . $abroad_order->reference_no . '修复失败');
                    }
                }
                //重新获取修复后订单数据更新cfm主订单
                $abroad_orders = OrderAbroadModel::find([
                    "conditions" => "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                $actual_freight = 0;
                foreach ($abroad_orders as $abroad_order) {
                    $item_freight = Utils::countAdd($abroad_order->actual_freight, $abroad_order->fixed_cost);
                    $actual_freight = Utils::countAdd($actual_freight, $item_freight);
                }
                $has_paid = (intval($order->order_status_id) == 10 || intval($order->order_status_id) == 90) ? false : true;
                $all_delivery = true;
                $status = 0;
                foreach ($abroad_orders as $abroad_order) {
                    if ($status < 0) {
                        break;
                    }
                    if (intval($abroad_order->status) < 0) {
                        $status = intval($abroad_order->status);
                    }
                    $status = intval($abroad_order->status) > intval($status) ? intval($abroad_order->status) : $status;
                    if (empty($abroad_order->order_code)) {
                        $status = OrderAbroadModel::COLUMN_STATUS_CREATE_ERROR;
                        break;
                    }
                    if ($has_paid && intval($abroad_order->status) == OrderAbroadModel::COLUMN_STATUS_CREATE) {
                        //支付提交审核， 订单审核异常
                        $status = OrderAbroadModel::COLUMN_STATUS_AUDIT_ERROR;
                        break;
                    }
                    if (!$has_paid && intval($abroad_order->status) == OrderAbroadModel::COLUMN_STATUS_AUDIT) {
                        //未支付却提交审核， 订单未支付审核异常
                        $status = OrderAbroadModel::COLUMN_STATUS_NOPAID_AUDIT_ERROR;
                        break;
                    }
                    if ($status == OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL) {
                        $status = 2;
                    } else {
                        $all_delivery = false;
                    }
                }
                if ($status == 2 && $all_delivery) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL;
                } else {
                    $order->abroad_status = $status;
                }
                $order->actual_freight_abroad = $actual_freight;
                if ($order->save() === false) {
                    throw new \Exception('海外仓订单修复失败');
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('订单修复成功', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                $this->logger->error('repairAbnormalAction-' . $e->getMessage());
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    /**
     *  出货图
     * */
    public function shipmentAction()
    {
        $keyword = $this->request->get("keyword");
        $this->view->setVar('keyword', $keyword);
        if($this->checkAdminRole(15)){
            $this->view->setVar('admin', 'ture');
        }
        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['a' => 'order:order'])
            ->columns('count(a.id) as count');
        if ($keyword) {
            $build = $build->andWhere('a.username like \'%' . SqlHelper::escapeLike($keyword) . '%\' or a.ordersn like \'%' . SqlHelper::escapeLike($keyword) . '%\'');
        } else {
            $t = time() - 86400 * 8;
            $build = $build->andWhere(' a.pay_time>=:time:', ['time' => $t]);
        }
        $count = $build->getQuery()->execute()->getFirst()['count'];
        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);
        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }
        $rs = $build->columns("a.*")
            ->orderBy('a.id desc')
            ->limit($limit, $start)
            ->getQuery()
            ->execute();
        $result = [];
        $item = [];
        foreach ($rs as $r) {
            $item = $r->toArray();
            $item['orderItem'] = [];
            foreach ($r->getOrderItem() as $key => $orderItem) {
                $item['orderItem'][] = $orderItem->toArray();
                $paths = explode(',', $item['orderItem'][$key]['shipment']);
                if ($paths !== false && $paths !== '') {
                    $picture = Picture::find([
                        'id in ({id:array})',
                        "bind" => [
                            "id" => $paths
                        ]
                    ]);
                    $item['orderItem'][$key]['shipment'] = $picture->toArray();
                }
            }
            $result[] = $item;
        }
        $this->view->setVar('objectlist', $result);
    }

    public function shipmentGalleryAction()
    {

        $dataGet = $_GET;
        $dataPost = $_POST;
        if ($dataGet['type']!=="FUTONG") {
            $type='CFM';
            $id=$dataGet['id']?$dataGet['id']:$dataPost['id'];
            $orderItem = OrderItem::findFirstById($id);
            $paths = explode(',', $orderItem->shipment);
            $save=$dataGet['save'];
            if ($save === 'true') {
                if($dataGet['header']=='true'){
                    header('Content-type: application/json; charset=UTF-8'); $jsonData = file_get_contents("php://input");
                    $jsonArray = json_decode($jsonData, TRUE);
                    foreach($jsonArray as $json){
                        $shipmentArr[] = $json['id'];
                    }
                    $shipment=join(',',$shipmentArr);
                }else{
                    $shipment=join(',',$dataPost['shipment']);
                }
                $orderItem->shipment = $shipment;
                if($orderItem->save()===false){

                }
                $paths = explode(',', $orderItem->shipment);
            }
        } else {
            $type='FUTONG';
            $ordersn=$dataGet['ordersn']?$dataGet['ordersn']:$dataPost['ordersn'];
            if ($ordersn) {
                if ($order = OrderItemShipment::findFirstByOrdersn($ordersn)) {
                    $paths = explode(',', $order->paths);
                    if ($dataGet['save'] === 'true') {
                        $shipment = join(',', $dataPost['shipment']);
                        if($dataGet['header']=='true'){
                            header('Content-type: application/json; charset=UTF-8');
                            $jsonData = file_get_contents("php://input");
                            $jsonArray = json_decode($jsonData, TRUE);
                            foreach($jsonArray as $json){
                                $shipmentArr[] = $json['id'];
                            }
                            $shipment=join(',',array_merge(array_filter($shipmentArr)));
                        }else{
                            $shipment=join(',',array_merge(array_filter($dataPost['shipment'])));
                        }
                        $order->paths = $shipment;
                        if($order->save()===false){

                        }
                        $paths = explode(',', $order->paths);
                    }

                } else {

                    $order = new OrderItemShipment();
                    $order->ordersn = $ordersn;
                    $order->save();
                }
            }
        }
        if ($paths && $paths !== false && $paths !== '') {
            $picture = Picture::find([
                'id in ({id:array})',
                "bind" => [
                    "id" => $paths
                ]
            ]);
            $picArr=[];
            foreach($picture->toArray() as $key=>$pic){
                $picArr[$key]['path']=Utils::thumb($pic['path']);
                $picArr[$key]['id']=$pic['id'];
            }
            $objectlist = $picArr;
        }

        $this->view->setVars([
            'id' => $id,
            'ordersn' => $ordersn,
            'objectlist' => $objectlist,
            'type'=>$type
        ]);
        $this->view->pick('order/field/shipment/shipmentgallery');
    }

    /**
     * 合并订单发送v3接口，并保存发送记录
     * @param $orderId
     */
    public function sendOrderMergeInfo($orderId) {
        $order = Order::findFirstById($orderId);

        //判断是否合并订单
        if($order->merge_status && $order->merge_status == 1) {
            $orderMergeArray = array();
            //获取合并编码
            $mergeOrdersn = $order->batch_no;
            $this->di->get('logger')->debug('合并订单编码===》' . $mergeOrdersn);
            //获取合并订单出货时间
            $estimated_delivery_time = $order->estimated_delivery_time;
            $this->di->get('logger')->debug('合并订单出货时间（美国时区）===》' . $estimated_delivery_time);
            //时间时区转换
            date_default_timezone_set('Asia/Shanghai');
            $delivery_time = date("Y-m-d H:i:s", $estimated_delivery_time);
            $this->di->get('logger')->debug('合并订单出货时间（国内时区）===》' . $delivery_time);

            $orderMergeArray['mergeOrdersn'] = $mergeOrdersn;
            $orderMergeArray['delivery_time'] = $delivery_time;
            $orderMergeArray['ordersnArray'] = array();

            array_push($orderMergeArray['ordersnArray'], array('ordersn'=> $order->ordersn));
            //查询其他相同合并编码的订单
            $merge_ids = $order->merge_ids;
            $orderIdArray = explode(",", $merge_ids);
            if($merge_ids) {
                foreach ($orderIdArray as $id) {
                    if($orderId != $id) {
                        $tmpOrder = Order::findFirstById($id);
                        array_push($orderMergeArray['ordersnArray'], array('ordersn'=> $tmpOrder->ordersn));
                    }
                }
            }
//            $orderMergeArray['customerAddress'] = null;
//            $orderMergeArray['agencyAddress'] = null;
//            $orderMergeArray['express'] = null;
//            $orderMergeArray['shipping'] = null;
//            $orderMergeArray['payMethod'] = null;
//            $orderMergeArray['paymentAccount'] = null;
//            $orderMergeArray['dutiesPayment'] = null;
//            $orderMergeArray['dutiespaymentAccount'] = null;
            //快递服务商
//            $express = Express::findFirstById($order->express_id);
//            $error = $this->getAgencyAddress($order, $express, $orderMergeArray);
            //发送v3
            $orderMergeResultArray = array();
            $orderMergeJson = json_encode($orderMergeArray);
            $this->di->get('logger')->debug($orderMergeJson);
            $orderMergeResultArray[0] = $orderMergeJson;

//            if(!$error) {
                $this->di->get('logger')->debug('合并订单开始准备发送给V3');
                $url = Config::getValBykey('CFM_ORDERMERGE_URL');
                $this->di->get('logger')->debug($url);
                $paramsArray = array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => array('Content-type:application/json', 'key:Accept-Charset', 'value:UTF-8'),
                        'content' => $orderMergeJson,
                        'timeout' => 60 * 60 // 超时时间（单位:s）
                    )
                );
                $context = stream_context_create($paramsArray);
                $orderMergeResultArray[1] = file_get_contents($url, false, $context);
//            } else {
//                $orderMergeResultArray[1] = $error;
//            }

            $this->di->get('logger')->debug('' . $orderMergeResultArray[1]);
            date_default_timezone_set('America/Los_Angeles');
            $orderMergeSendInfoArray = OrderMergeSendInfo::findFirstByOrderMerge_code($mergeOrdersn);
            $this->di->get('logger')->debug('合并订单发送结果保存中间表');
            if(!$orderMergeSendInfoArray && count($orderMergeSendInfoArray) <= 0) {
                $orderMergeSendInfo = new OrderMergeSendInfo();
                $orderMergeSendInfo->order_id = '';
                $orderMergeSendInfo->orderMerge_code = ''; //合并编码
                $orderMergeSendInfo->send_system = '工厂';
                if ($orderMergeResultArray[1]->code == 'true' || !$orderMergeResultArray[1]->success == 'error') {
                    $orderMergeSendInfo->status = 1;
                } else {
                    $orderMergeSendInfo->status = 2;
                }

                $orderMergeSendInfo->sendJson = $orderMergeResultArray[0];
                $orderMergeSendInfo->message = $orderMergeResultArray[1]->message ? $orderMergeResultArray[1]->message : '接口连接失败';
                if (!$orderMergeSendInfo->save()) {
                    $this->di->get('logger')->error(implode(';', $orderMergeSendInfo->getMessages()));
                }
            } else {
                foreach ($orderMergeSendInfoArray as $orderMergeSendInfo) {
                    if ($orderMergeResultArray[1]->code == 'true' || !$orderMergeResultArray[1]->success == 'error') {
                        $orderMergeSendInfo->status = 1;
                    } else {
                        $orderMergeSendInfo->status = 2;
                    }

                    $orderMergeSendInfo->sendJson = $orderMergeResultArray[0];
                    $orderMergeSendInfo->message = $orderMergeResultArray[1]->message ? $orderMergeResultArray[1]->message : '接口连接失败';
                    if (!$orderMergeSendInfo->save()) {
                        $this->di->get('logger')->error(implode(';', $orderMergeSendInfo->getMessages()));
                    }
                }
            }
        }
    }

    /**
     * 合并订单发送
     * @return \Xin\Lib\MessageResponse
     */
    public function sendOrderMergeAction() {
        $auth = $this->getDI()->get('auth');
        if (!$auth->isAuthorized()) {
            return new \Xin\Lib\MessageResponse('请登陆系统');
        }

        $orderId = $this->request->getQuery('orderId');
        if ($orderId < 1 || (!$order = Order::findFirstById($orderId))) {
            return new \Xin\Lib\MessageResponse('未找到有效的记录');
        }

        $this->sendOrderMergeInfo($orderId);
    }

    /*
     * 合并订单传输前台界面
     */
    public function sendOrderMergeListAction() {
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);

        $build = new \Phalcon\Mvc\Model\Query\Builder();

        if ($role->rolename == Config::getValByKey('BUSINESS_ROLE')
            || $role->rolename == Config::getValByKey('SUBORDINATE_ROLE')) {
            $build = $build->from(['a' => 'order:order'])
                ->innerJoin('busine:BusineUser', 'a.uid=c.user_id', 'c')
                ->leftJoin('order:OrderMergeSendInfo', 'b.order_id=a.id', 'b')
                ->where('c.busine_id=:busineid:', ['busineid' => $uid])
                ->andWhere('a.order_status_id in ({orderStatusId:array})', ['orderStatusId' => \Xin\Module\Order\Lib\Order::ARTWORK_APPROVAL])
                ->andWhere('a.merge_status=:merge_status:', ['merge_status' => 1])
                ->columns('count(a.id) as count');

        } else {
            $build = $build->from(['a' => 'order:order'])
                ->leftJoin('order:OrderMergeSendInfo', 'b.order_id=a.id', 'b')
                ->where('a.order_status_id in ({orderStatusId:array})', ['orderStatusId' => \Xin\Module\Order\Lib\Order::ARTWORK_APPROVAL])
                ->andWhere('a.merge_status=:merge_status:', ['merge_status' => 1])
                ->columns('count(a.id) as count');
        }

        $count = $build->getQuery()->execute()->getFirst()['count'];

        $pagination = Utils::loadUserControl('\Xin\Lib\Ctrl\Pagination');
        $pagination->recordCount($count);
        $this->view->setVar('pagination', $pagination);

        list($start, $limit) = Utils::offset(null, $pagination->pageSize());
        if (!$pagination->recordCount() || $start >= $pagination->recordCount()) {
            return;
        }

        $result = $build->columns("a.id,a.ordersn,b.orderMerge_code,a.username,a.create_time,a.order_status_id,b.send_system,b.status,b.message")
            ->orderBy('a.create_time desc')
            ->limit($limit, $start)
            ->getQuery()
            ->execute();
        $this->view->setVar('objectlist', $result->toArray());
    }

    /**
     * 更新cfm订单关联的海外仓订单
     * @return \Xin\Lib\MessageResponse
     * @throws \Exception
     */
    public function updateAllAbroadOrdersAction()
    {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $this->db->begin();
            try {
                $order = Order::findFirstById($id);
                if (empty($order)) {
                    throw new \Exception('未找到订单');
                }
                if (empty($order->accessory_abroad) || $order->accessory_abroad == 0) {
                    throw new \Exception('订单没有关联的海外仓订单');
                }
                //有异常的海外仓订单需先修复海外仓订单异常后 才能更新海外仓订单信息
                if (intval($order->abroad_status) < 0) {
                    throw new \Exception('请先修复海外仓订单异常!');
                }

                $order_abroads = OrderAbroadModel::find([
                    "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                foreach ($order_abroads as $order_abroad) {
                    if (intval($order_abroad->status) < 0) {
                        continue;
                    }
                    $res = OrderAbroad::updateAbroadOrder($order_abroad);
                    if (!$res) {
                        throw new \Exception('海外仓订单' . $order_abroad->reference_no . '更新失败');
                    }
                }
                //计算cfm订单所有关联海外仓订单的实际总运费
                $abroad_orders = OrderAbroadModel::find([
                    "conditions" => "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                $actual_freight = 0;
                $status = 0;
                $all_delivery = true;
                $all_cancel = true;
                foreach ($abroad_orders as $abroad_order) {
                    $item_freight = Utils::countAdd($abroad_order->actual_freight, $abroad_order->fixed_cost);
                    $actual_freight = Utils::countAdd($actual_freight, $item_freight);
                    if (intval($abroad_order->status) > $status) {
                        $status = $abroad_order->status;
                    }
                    if ($status != OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL) {
                        $all_delivery = false;
                    }
                    if ($status == OrderAbroadModel::COLUMN_STATUS_CANCEL_ALL && intval($abroad_order->status) != $status) {
                        $all_cancel = false;
                    }
                }
                if ($all_delivery) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL;
                } else {
                    $order->abroad_status = $status > OrderAbroadModel::COLUMN_STATUS_DELIVERY_PARTIAL? OrderAbroadModel::COLUMN_STATUS_DELIVERY_PARTIAL:$status;
                }
                if ($all_cancel) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_CANCEL_ALL;
                } else {
                    $order->abroad_status = $status > OrderAbroadModel::COLUMN_STATUS_CANCEL_PARTIAL? OrderAbroadModel::COLUMN_STATUS_CANCEL_PARTIAL:$status;
                }
                $order->actual_freight_abroad = $actual_freight;
                if ($order->save() === false) {
                    throw new \Exception('海外仓订单更新失败');
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('订单更新成功', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                $this->logger->error('updateAllAbroadOrdersAction-' . $e->getMessage());
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    /**
     * 更新海外仓记录信息
     */
    public function updateAbroadOrderAction() {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $this->db->begin();
            try {
                $order_abroad = OrderAbroadModel::findFirstById($id);
                //有异常的海外仓订单需先修复海外仓订单异常后 才能更新海外仓订单信息
                if (intval($order_abroad->status) < 0) {
                    throw new \Exception('请先修复海外仓订单异常!');
                }

                $res = OrderAbroad::updateAbroadOrder($order_abroad);
                if (!$res) {
                    throw new \Exception('海外仓订单' . $order_abroad->reference_no . '更新失败');
                }

                $order = Order::findFirstByOrdersn($order_abroad->ordersn);
                if (empty($order)) {
                    return new \Xin\Lib\MessageResponse('未找到cfm订单' . $order_abroad->ordersn, 'error');
                }

                //计算cfm订单所有关联海外仓订单的实际总运费
                $abroad_orders = OrderAbroadModel::find([
                    "conditions" => "uid = :uid: and ordersn=:ordersn:",
                    "bind" => [
                        "uid" => $order->uid,
                        "ordersn" => $order->ordersn
                    ]
                ]);
                $actual_freight = 0;
                $status = 0;
                $all_delivery = true;
                $all_cancel = true;
                foreach ($abroad_orders as $abroad_order) {
                    $item_freight = Utils::countAdd($abroad_order->actual_freight, $abroad_order->fixed_cost);
                    $actual_freight = Utils::countAdd($actual_freight, $item_freight);
                    if (intval($abroad_order->status) > intval($status)) {
                        $status = $abroad_order->status;
                    }
                    if ($status != OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL) {
                        $all_delivery = false;
                    }
                    if ($status == OrderAbroadModel::COLUMN_STATUS_CANCEL_ALL && intval($abroad_order->status) != $status) {
                        $all_cancel = false;
                    }
                }
                if ($all_delivery) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL;
                } else {
                    $order->abroad_status = $status > OrderAbroadModel::COLUMN_STATUS_DELIVERY_PARTIAL? OrderAbroadModel::COLUMN_STATUS_DELIVERY_PARTIAL:$status;
                }
                                
                if ($all_cancel) {
                    $order->abroad_status = OrderAbroadModel::COLUMN_STATUS_CANCEL_ALL;
                } else {
                    $order->abroad_status = $status > OrderAbroadModel::COLUMN_STATUS_CANCEL_PARTIAL? OrderAbroadModel::COLUMN_STATUS_CANCEL_PARTIAL:$status;
                }
                
                
                $order->actual_freight_abroad = $actual_freight;
                if ($order->save() === false) {
                    throw new \Exception('海外仓订单更新失败');
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('订单更新成功', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                $this->logger->error('updateAbroadOrderAction-' . $e->getMessage());
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
    }

    /**
     * 自定义帐篷
     * @param $uid
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @param $num
     * @param $item
     */
    public function customMakeTentsProduct($uid, $options, &$pmsProducts, &$pmsArray,$sperialMakeStatus, $sperialMakeArray, &$num, $item) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'advertisingTent');
        $optionItemId = $options->template->option_item_id;
        $optionItemIdValues = explode('-', $optionItemId);
        $custItemToUsers = ProductOptCustItemToUser::findFirstById($optionItemIdValues[1]);

        $acreage = explode('x', $custItemToUsers->description);
        if(!is_array($acreage) && count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

        $pmsProducts['productLength'] = $acreage[0];
        $pmsProducts['productWidth'] = $acreage[1];
        $pmsProducts['printSquare'] = null;
        $pmsProducts['hemming'] = '双线';

        $productOptCustItem = ProductOptCustItem::findFirstById($custItemToUsers->product_opt_cust_item_id);
        $productOptCust = ProductOptCust::findFirstById($productOptCustItem->product_opt_cust_id);
        $pmsProducts['subtype'] = $productOptCust->title_cn;  //二级分类
        $pmsProducts['name'] = $productOptCust->title_cn;  //产品名称

        $pmsProducts['polesType'] = $productOptCustItem->title_cn;

        $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';
        $pmsProducts['side'] = 1;
        /*
         * 帐篷产品组合包
        */
        if ($options->walloption->value && strtolower($options->walloption->value) != 'no') {
            if (strtolower($options->graphic->value) == 'double' || strtolower($options->wallgraphic->value) == 'double') {
                $sewingMethod = '双面缝魔术贴（logo朝里朝外皆可以）';
            } else {
                $sewingMethod = 'logo朝外';
            }
            if (strtolower($options->graphic->value) == 'double' || strtolower($options->wallgraphic->value) == 'double') {
                $side= 2;
                $innerMaterial = '遮光布';
            } else {
                $side = 1;
            }
            $quantity = $pmsProducts['quantity'];   //产品数量
            if ($options->walloption->value == '2 Half Side Walls + 1 Full Back Wall') {
                if(strtolower($options->tenttop->value) == 'yes') {
                    $pmsProducts['price'] = round(floatval($item['unit_price']) / 3, 2);
                    array_push($pmsArray['products'], $pmsProducts);
                } else {
                    $pmsProducts['price'] = round(floatval($item['unit_price']) / 2, 2);
                }


                $pmsProducts['side'] = $side;
                $pmsProducts['innerMaterial'] = $innerMaterial;
                $pmsProducts['sewingMethod'] = $sewingMethod;
                $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';
                //半围组合
                $pmsProducts['productNo'] = '' . (++$num);
                $pmsProducts['subtype'] = '半围';  //二级分类
                $pmsProducts['name'] = '半围';
                $pmsProducts['quantity'] = $quantity * 2;
                // 面积
                if (strstr($productOptCust->title_cn, '10x10')) {
                    $halfCondition = 'Half Wall 10*10 Hex';
                    $fullCondition = 'Full Wall 10*10 Hex';

                } else if (strstr($productOptCust->title_cn, '10x15')) {
                    $halfCondition = 'Half Wall 10*15 Hex';
                    $fullCondition = 'Full Wall 10*15 Hex';

                } else if (strstr($productOptCust->title_cn, '10x20')) {
                    $halfCondition = 'Half Wall 10*20 Hex';
                    $fullCondition = 'Full Wall 10*20 Hex';
                }
                $half = ParameterMapping::find(array(
                    'conditions' => 'product_sku = ?0',
                    'bind' => [$halfCondition]
                ));
                $pmsProducts['productLength'] = floatval($half->toArray()[0]['length']);
                $pmsProducts['productWidth'] = floatval($half->toArray()[0]['width']);
                array_push($pmsArray['products'], $pmsProducts);

                //全围组合
                $pmsProducts['productNo'] = '' . (++$num);
                $pmsProducts['subtype'] = '全围';  //二级分类
                $pmsProducts['name'] = '全围';
                //全围面积
                $full = ParameterMapping::find(array(
                    'conditions' => 'product_sku = ?0',
                    'bind' => [$fullCondition]
                ));
                $pmsProducts['productLength'] = floatval($full->toArray()[0]['length']);
                $pmsProducts['productWidth'] = floatval($full->toArray()[0]['width']);
                $pmsProducts['quantity'] = $quantity;
                array_push($pmsArray['products'], $pmsProducts);

            } else if ($options->walloption->value == '2 Half Side Walls') {
                if(strtolower($options->tenttop->value) == 'yes') {
                    $pmsProducts['price'] = round(floatval($item['unit_price']) / 2, 2);
                    array_push($pmsArray['products'], $pmsProducts);
                } else {
                    $pmsProducts['price'] = round(floatval($item['unit_price']) / 1, 2);
                }

                $pmsProducts['side'] = $side;
                $pmsProducts['innerMaterial'] = $innerMaterial;
                $pmsProducts['sewingMethod'] = $sewingMethod;
                $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';;
                //半围组合
                $pmsProducts['productNo'] = '' . (++$num);
                $pmsProducts['subtype'] = '半围';  //二级分类
                $pmsProducts['name'] = '半围';
                $pmsProducts['quantity'] = $quantity * 2;
                // 面积
                if (strstr($productOptCust->title_cn, '10x10')) {
                    $halfCondition = 'Half Wall 10*10 Hex';
                } else if (strstr($productOptCust->title_cn, '10x15')) {
                    $halfCondition = 'Half Wall 10*15 Hex';
                } else if (strstr($productOptCust->title_cn, '10x20')) {
                    $halfCondition = 'Half Wall 10*20 Hex';
                }
                $half = ParameterMapping::find(array(
                    'conditions' => 'product_sku = ?0',
                    'bind' => [$halfCondition]
                ));
                $pmsProducts['productLength'] = floatval($half->toArray()[0]['length']);
                $pmsProducts['productWidth'] = floatval($half->toArray()[0]['width']);
                array_push($pmsArray['products'], $pmsProducts);

            } else if ($options->walloption->value == '1 Full Back Wall') {
                if(strtolower($options->tenttop->value) == 'yes') {
                    $pmsProducts['price'] = round(floatval($item['unit_price']) / 2, 2);
                    array_push($pmsArray['products'], $pmsProducts);
                } else {
                    $pmsProducts['price'] = round(floatval($item['unit_price']) / 1, 2);
                }

                $pmsProducts['side'] = $side;
                $pmsProducts['innerMaterial'] = $innerMaterial;
                $pmsProducts['sewingMethod'] = $sewingMethod;
                $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';
                // 面积
                if (strstr($productOptCust->title_cn, '10x10')) {
                    $fullCondition = 'Full Wall 10*10 Hex';

                } else if (strstr($productOptCust->title_cn, '10x15')) {
                    $fullCondition = 'Full Wall 10*15 Hex';
                } else if (strstr($productOptCust->title_cn, '10x20')) {
                    $fullCondition = 'Full Wall 10*20 Hex';
                }
                //全围组合
                $pmsProducts['productNo'] = '' . (++$num);
                $pmsProducts['subtype'] = '全围';  //二级分类
                $pmsProducts['name'] = '全围';
                //全围面积
                $full = ParameterMapping::find(array(
                    'conditions' => 'product_sku = ?0',
                    'bind' => [$fullCondition]
                ));
                $pmsProducts['productLength'] = floatval($full->toArray()[0]['length']);
                $pmsProducts['productWidth'] = floatval($full->toArray()[0]['width']);
                $pmsProducts['quantity'] = $quantity;
                array_push($pmsArray['products'], $pmsProducts);
            }
        } else {
            array_push($pmsArray['products'], $pmsProducts);
        }
    }

    /**
     * 自定义沙滩旗
     * @param $uid
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @param $num
     * @param $item
     */
    public function customMakedisplayFlagsProduct($uid, $options, &$pmsProducts, &$pmsArray,$sperialMakeStatus, $sperialMakeArray, &$num, $item) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'displayFlag');

        $acreage = explode('x', $options->acreage->value);
        if(!is_array($acreage) && count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

        $productOptCustToFlagUser = ProductOptCustToFlagUser::findFirstByUid($uid);

        $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
        $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        $pmsProducts['printSquare'] = null;
        $pmsProducts['hemming'] = '双线';
        $pmsProducts['waistHemming'] = '窄四线';

        $pmsProducts['waistMaterial'] = '450D加密牛津布';

        $pmsProducts['sewingMethod'] = "";   //缝纫配件

        $pmsProducts['waistPosition'] = '常规-左侧  '; //腰头位置

        $pmsProducts['packingBag'] = 'EVA磨砂袋+彩色LOGO标签';

        if($productOptCustToFlagUser) {
            if(empty($productOptCustToFlagUser->accessorie)) {
                $pmsProducts['sewingMethod'] = '2个1号铜扣+弹力绳';
            } else {
                $pmsProducts['sewingMethod'] = $productOptCustToFlagUser->accessorie;
            }
            $pmsProducts['polesType'] = $productOptCustToFlagUser->flag_pole;
            $pmsProducts['waistSize'] = $productOptCustToFlagUser->waistSize; //腰头尺寸
        } else {
            $pmsProducts['sewingMethod'] = '2个1号铜扣+弹力绳';
            $pmsProducts['polesType'] = '客户自己的杆子';
            $pmsProducts['waistSize'] = "标准做法"; //腰头尺寸
        }


        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 高尔夫旗标准
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customGolfFlagProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $size = $options->acreage->value_cn;
        //产品长度宽度
        if($size) {
            $acreage = explode('x', $size);
            $pmsProducts['productLength'] = $acreage[0];
            $pmsProducts['productWidth'] = $acreage[1];
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'opp袋';
        $pmsProducts['hemming'] = '双线';
        $pmsProducts['waistHemming'] = '双线';

        $pmsProducts['waistMaterial'] = '根据图稿呈现形式来缝纫';
        $pmsProducts['waistSize'] = '根据图稿呈现形式来缝纫';

        $pmsProducts['waistPosition'] = '旗帜左侧';  //腰头位置;
        $pmsProducts['sewingMethod'] = '';

        if(strtolower($options->accessories->value) != 'no') {
            //高尔夫旗画面：配件金额比例（5:5）
            $price = round(floatval($pmsProducts['price']) /2, 2);
            $parts = array();
            $parts['model'] = '';
            $parts['type'] = $productType->product_type;
            $parts['subtype'] = '其他';
            $parts['imageOriginalPath'] = '';
            $parts['imageSmallPath'] = '';
            $parts['imageOriginalSize'] = null;
            $parts['imageSmallSize'] = null;
            $parts['imageReuse'] = null;
            $parts['material'] = '';
            $parts['tech'] = '配件订单';
            $parts['productLength'] = null;
            $parts['productWidth'] = null;
            $parts['printSquare'] = null;
            $parts['hemming'] = '';
            $parts['polesType'] = '';
            $parts['packingBag'] = '';
            $parts['quantity'] = $pmsProducts['quantity'] * 1;
            $parts['packingLabel'] = '';
            $parts['leafletName'] = '';
            $parts['leafletType'] = '';
            $parts['manualImageName'] = '';
            $parts['boxedLogo'] = '';
            $parts['attachName'] = '';
            $parts['sewnInLabelName'] = '';
            $parts['price'] = $price;
            $parts['comment'] = '';

            $parts['hsCode'] = '3926909090999';
            $parts['hsName'] = 'Flag Pole';
            $parts['declareName'] = 'Materail:PVC';
            switch (strtolower($options->accessories->value)) {
                case 'flag tube insert':
                    $pmsProducts['sewingMethod'] = '白色塑料套管FP02';
                    break;
                case 'flag tube insert+flag pole':
                    $pmsProducts['price'] = $price;
                    $pmsProducts['sewingMethod'] = '白色塑料套管FP02';
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '纯白色高尔夫球杆FP04-1';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'flag tube insert+flag pole+aluminium alloy cup':
                    $pmsProducts['price'] = $price;
                    $parts['price'] =  round(floatval($parts['price']) /2, 2);
                    $pmsProducts['sewingMethod'] = '白色塑料套管FP02';
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '纯白色高尔夫球杆FP04-1';
                    array_push($pmsArray['products'], $parts);

                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '铝合金杯洞2813';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'flag swivel+grommet':
                    $pmsProducts['price'] = $price;
                    $pmsProducts['sewingMethod'] = '3个2号铜扣+白色圆形垫片加固';
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '旗面夹子2691';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'grommet':
                    $pmsProducts['sewingMethod'] = '3个2号铜扣+白色圆形垫片加固';
                    break;
                default: break;
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 花园旗标准
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customGardenFlagProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $title = $product_cache->title;
        $size = $options->acreage->value_cn;
        //产品长度宽度
        if($size) {
            $acreage = explode('x', $size);
            $pmsProducts['productLength'] = $acreage[0];
            $pmsProducts['productWidth'] = $acreage[1];
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'EVA磨砂袋';
        $pmsProducts['hemming'] = '双线';
        $pmsProducts['waistHemming'] = '双线';

        $pmsProducts['waistMaterial'] = '根据图稿呈现形式来缝纫';
        $pmsProducts['waistSize'] = '2.5cm,根据图稿呈现形式来缝纫';
        $pmsProducts['sewingMethod'] = '';

        $pmsProducts['waistPosition'] = '旗帜上端';  //腰头位置;
        if(strtolower($options->accessories->value) != 'no') {
            //花园旗画面：配件金额比例（5:5）
            $price = round(floatval($pmsProducts['price']) /2, 2);
            $parts = array();
            $parts['model'] = '';
            $parts['type'] = $productType->product_type;
            $parts['subtype'] = '其他';
            $parts['imageOriginalPath'] = '';
            $parts['imageSmallPath'] = '';
            $parts['imageOriginalSize'] = null;
            $parts['imageSmallSize'] = null;
            $parts['imageReuse'] = null;
            $parts['material'] = '';
            $parts['tech'] = '配件订单';
            $parts['productLength'] = null;
            $parts['productWidth'] = null;
            $parts['printSquare'] = null;
            $parts['hemming'] = '';
            $parts['polesType'] = '';
            $parts['packingBag'] = '';
            $parts['quantity'] = $pmsProducts['quantity'] * 1;
            $parts['packingLabel'] = '';
            $parts['leafletName'] = '';
            $parts['leafletType'] = '';
            $parts['manualImageName'] = '';
            $parts['boxedLogo'] = '';
            $parts['attachName'] = '';
            $parts['sewnInLabelName'] = '';
            $parts['price'] = $price;
            $parts['comment'] = '';

            $parts['hsCode'] = '3926909090999';
            $parts['hsName'] = 'Flag Pole';
            $parts['declareName'] = 'Materail:PVC';
            switch (strtolower($options->accessories->value)) {
                case 'pole':
                    $pmsProducts['price'] = $price;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '长1m铁质花园旗杆（GF-I01）';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'suction cup':
                    $pmsProducts['price'] = $price;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '2个吸盘+透明塑料杆';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'pole+suction cup':
                    $pmsProducts['price'] = $price;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '长1m铁质花园旗杆（GF-I01）+2个吸盘+透明塑料杆';
                    array_push($pmsArray['products'], $parts);
                default: break;
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 手挥旗标准
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customHandwaveFlagProduct($product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $size = $options->acreage->value_cn;
        //产品长度宽度
        if($size) {
            $acreage = explode('x', $size);
            $pmsProducts['productLength'] = $acreage[0];
            $pmsProducts['productWidth'] = $acreage[1];
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'EVA磨砂袋';
        $pmsProducts['hemming'] = '30*45cm以下 单层电割边，双层单线；30*45cm或者以上 双线（待确认）';
        $pmsProducts['waistHemming'] = '30*45cm以下 单线；30*45cm或者以上 双线';

        //根据画面面料判断大旗使用什么缝纫面料
        switch ($options->material->attr_affect->title->val) {
            case '100D涤纶':
                $pmsProducts['waistMaterial'] = '白色2X2牛津布';
                break;
            default :
                $pmsProducts['waistMaterial'] = '白色600D涤纶';
                break;
        }
        $pmsProducts['waistSize'] = '根据旗杆的大小做腰头尺寸,图稿呈现形式来缝纫';

        $pmsProducts['waistPosition'] = '旗帜左侧';  //腰头位置;
        $pmsProducts['sewingMethod'] = '需标注旗杆的大小、型号';

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 汽车旗标准
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customCarFlagProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $size = $options->acreage->value_cn;
        //产品长度宽度
        if($size) {
            $acreage = explode('x', $size);
            $pmsProducts['productLength'] = $acreage[0];
            $pmsProducts['productWidth'] = $acreage[1];
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'EVA磨砂袋';
        $pmsProducts['hemming'] = '双线';
        $pmsProducts['waistHemming'] = '双线';

        $pmsProducts['waistSize'] = '根据图稿呈现形式来缝纫';

        $pmsProducts['waistPosition'] = '旗帜左侧';  //腰头位置;
        $pmsProducts['sewingMethod'] = '';

        if(strtolower($options->accessories->value) != 'no') {
            //花园旗画面：配件金额比例（5:5）
            $price = round(floatval($pmsProducts['price']) /2, 2);
            $parts = array();
            $parts['model'] = '';
            $parts['type'] = $productType->product_type;
            $parts['subtype'] = '其他';
            $parts['imageOriginalPath'] = '';
            $parts['imageSmallPath'] = '';
            $parts['imageOriginalSize'] = null;
            $parts['imageSmallSize'] = null;
            $parts['imageReuse'] = null;
            $parts['material'] = '';
            $parts['tech'] = '配件订单';
            $parts['productLength'] = null;
            $parts['productWidth'] = null;
            $parts['printSquare'] = null;
            $parts['hemming'] = '';
            $parts['polesType'] = '';
            $parts['packingBag'] = '';
            $parts['quantity'] = $pmsProducts['quantity'] * 1;
            $parts['packingLabel'] = '';
            $parts['leafletName'] = '';
            $parts['leafletType'] = '';
            $parts['manualImageName'] = '';
            $parts['boxedLogo'] = '';
            $parts['attachName'] = '';
            $parts['sewnInLabelName'] = '';
            $parts['price'] = $price;
            $parts['comment'] = '';

            $parts['hsCode'] = '3926909090999';
            $parts['hsName'] = 'Flag Pole';
            $parts['declareName'] = 'Materail:PVC';
            switch (strtolower(trim($options->accessories->value))) {
                case 'economic':
                    $pmsProducts['price'] = $price;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '长36CM白色小汽车旗杆带环扣（CF-P08）';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'premium':
                    $pmsProducts['price'] = $price;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '长50CM白色大汽车旗杆带盖帽带环扣（CF-P02）';
                    array_push($pmsArray['products'], $parts);
                    break;
                default: break;
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 锦旗
     * @param $productType
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customPennantFlagProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $title = $product_cache->title;
        $size = $options->acreage->value_cn;
        //产品长度宽度
        if($size) {
            $acreage = explode('x', $size);
            $pmsProducts['productLength'] = $acreage[0];
            $pmsProducts['productWidth'] = $acreage[1];
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'EVA磨砂袋';
        $pmsProducts['hemming'] = '电割边';
        $pmsProducts['waistHemming'] = '单线';

        $pmsProducts['waistMaterial'] = '根据图稿呈现形式来缝纫（本色反腰调图需要加出血）';
        $pmsProducts['waistSize'] = '根据图稿呈现形式来缝纫（本色反腰调图需要加出血）';
        $pmsProducts['sewingMethod'] = '根据图稿呈现形式来缝纫（4cm边穗，颜色款式根据图稿呈现形式来缝纫）';

        if (strtolower($options->graphic->value) == 'triple') {
            $side = 3;
            $innerMaterial = '遮光布';
        } else {
            $side = 1;
            $innerMaterial = '';
        }

        $pmsProducts['waistPosition'] = '旗帜上端';  //腰头位置;

        //锦旗画面：配件金额比例（5:5）
        $price = round(floatval($pmsProducts['price']) /2, 2);
        if(strtolower($options->pole->value) == 'no' && strtolower($options->accessories->value) == 'no') {
            $partsPrice = round(floatval($price) /2, 2);
        } else {
            $partsPrice = $price;
        }
        $parts = array();
        $parts['model'] = '';
        $parts['type'] = $productType->product_type;
        $parts['subtype'] = '其他';
        $parts['imageOriginalPath'] = '';
        $parts['imageSmallPath'] = '';
        $parts['imageOriginalSize'] = null;
        $parts['imageSmallSize'] = null;
        $parts['imageReuse'] = null;
        $parts['material'] = '';
        $parts['tech'] = '配件订单';
        $parts['productLength'] = null;
        $parts['productWidth'] = null;
        $parts['printSquare'] = null;
        $parts['hemming'] = '';
        $parts['polesType'] = '';
        $parts['packingBag'] = '';
        $parts['quantity'] = $pmsProducts['quantity'] * 1;
        $parts['packingLabel'] = '';
        $parts['leafletName'] = '';
        $parts['leafletType'] = '';
        $parts['manualImageName'] = '';
        $parts['boxedLogo'] = '';
        $parts['attachName'] = '';
        $parts['sewnInLabelName'] = '';
        $parts['price'] = null;
        $parts['comment'] = '';

        $parts['hsCode'] = '3926909090999';
        $parts['hsName'] = 'Flag Pole';
        $parts['declareName'] = 'Materail:PVC';
        if(strtolower($options->pole->value) != 'no') {
            switch (strtolower($options->pole->value)) {
                case 'wooden round head':
                    $pmsProducts['price'] = $price;
                    $parts['price'] = $partsPrice;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '实心木杆配木质圆头';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'golden sharp corner':
                    $pmsProducts['price'] = $price;
                    $parts['price'] = $partsPrice;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '实心木杆配金色尖头';
                    array_push($pmsArray['products'], $parts);
                    break;
                default: break;
            }
        }

        if(strtolower($options->accessories->value) != 'no') {
            switch (strtolower($options->accessories->value)) {
                case 'tassels':
                    $pmsProducts['price'] = $price;
                    $parts['price'] = $partsPrice;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '挂穗(颜色根据图稿确认)';
                    array_push($pmsArray['products'], $parts);
                    break;
                case 'hanging Cord':
                    $pmsProducts['price'] = $price;
                    $parts['price'] = $partsPrice;
                    $parts['productNo'] = '' . (++$num);
                    $parts['name'] = '挂绳(颜色根据图稿确认)';
                    array_push($pmsArray['products'], $parts);
                    break;
                default: break;
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

    /**
     * 沙滩旗单独配件
     * @param $productType
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customBeachFlagProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        //沙滩旗配件
        $parts = array();
        $parts['productNo'] = $pmsProducts['productNo'];
        $parts['model'] = '';
        $parts['type'] = $productType->product_type;
        $parts['subtype'] = '其他';
        $parts['imageOriginalPath'] = '';
        $parts['imageSmallPath'] = '';
        $parts['imageOriginalSize'] = null;
        $parts['imageSmallSize'] = null;
        $parts['imageReuse'] = null;
        $parts['material'] = '';
        $parts['tech'] = '配件订单';
        $parts['productLength'] = null;
        $parts['productWidth'] = null;
        $parts['printSquare'] = null;
        $parts['hemming'] = '';
        $parts['polesType'] = '';
        $parts['packingBag'] = '';
        $parts['quantity'] = $pmsProducts['quantity'] * 1;
        $parts['packingLabel'] = '';
        $parts['leafletName'] = '';
        $parts['leafletType'] = '';
        $parts['manualImageName'] = '';
        $parts['boxedLogo'] = '';
        $parts['attachName'] = '';
        $parts['sewnInLabelName'] = '';
        $parts['price'] = $pmsProducts['price'];
        $parts['comment'] = '';
        if($product_cache->title_cn == '沙滩旗配件-旗杆') {
            $flag_size = strtolower($options->flag_size->value);
            $flag_type = strtolower($options->flag_type->value);
            $type = strtolower($options->type->value);
            $flagAccessory = FlagAccessory::find(['type=:type: and flag_type=:flagType: and flag_size=:flagSize:',
                'bind'=>['type'=>$type, 'flagType'=>$flag_type, 'flagSize'=>$flag_size]]);

            $flagAccessory = $flagAccessory->toArray();
            if(!$flagAccessory && count($flagAccessory) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应单独配件信息"}');

            $parts['name'] = $flagAccessory[0]['model'] . $flagAccessory[0]['type_cn'];
            $parts['hsCode'] = $flagAccessory[0]['hsCode'];
            $parts['hsName'] = $flagAccessory[0]['hsName'];
            $parts['declareName'] = $flagAccessory[0]['hsEnglishName'];
            $this->buildCustomsFlag($sperialMakeStatus, $sperialMakeArray, $parts);
        } else {
            $parts['name'] = $options->model->value_cn;
            $parts['hsCode'] = $options->model->attr_affect->hsCode;
            $parts['hsName'] = $options->model->attr_affect->hsName;
            $parts['declareName'] = $options->model->attr_affect->hsEnglishName;
        }
        $parts['poNo'] = $pmsProducts['poNo'];
        array_push($pmsArray['products'], $parts);
    }

    public function exchangeAction(){
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $type=$this->request->get('type');
        $item_id=$this->request->get('item_id');
        $from_id=$this->request->get('user_id');
        $user=User::findFirst($uid);
        $item_id=$this->request->get('item_id');

        if($type!=='text'){
            $this->view->pick('order/field/exchange/exchange');
            if($this->request->isPost()){
                $orderItem=OrderItem::findFirst($item_id);
                $OrderItemHistory=new \Xin\Module\Order\Model\OrderItemHistory();
                $OrderItemHistory->from_id=$user->id;
                $OrderItemHistory->order_id=$orderItem->order_id;
                $OrderItemHistory->user_id=$from_id;
                $OrderItemHistory->create_time=time();
                $OrderItemHistory->order_item_id=$item_id;
                $OrderItemHistory->comment=trim($this->request->getPost('comment'));
                $OrderItemHistory->save();
            }
        }
        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $OrderItemHistory = $build->from(['o' => 'order:orderitemhistory'])
        ->innerJoin('user:user', 'u.id=o.from_id', 'u')
        ->where('o.order_item_id=:item: and o.status!=:status:', ['item' => $item_id,'status'=>'2'])
        ->columns('u.username,o.create_time,o.status,o.id,o.comment,o.from_id,o.user_id,o.order_item_id')
        ->getQuery()
        ->execute();

        foreach($OrderItemHistory as $itemHistory){
            if($itemHistory->status==='0' && $itemHistory->from_id!=$uid){
                $history=\Xin\Module\Order\Model\OrderItemHistory::findFirst($itemHistory->id);
                $history->status='1';
                $history->update();
            }
        }

        $this->view->setVar('objectlist',$OrderItemHistory->toArray());
        $this->view->setVars([
            'item_id'=>$item_id,
            'user'=>$user->toArray()
        ]);
    }

    public function galleryHistoryAction(){
        $this->view->pick('order/field/galleryHistory/galleryHistory');
        $item_id=$this->request->get('item_id');
        $OrderItemGalleryHistory=\Xin\Module\Order\Model\OrderItemGalleryHistory::find(['order_item_id=:order_item_id:','bind'=>['order_item_id'=>$item_id]])->toArray();
        foreach ($OrderItemGalleryHistory as $key=>&$History){
            $gallery=Gallery::findFirst($History['gallery_id']);
            // $galleryArr[$key]['thumb']=$gallery->thumb;
            $History['path']=$gallery->path;
            $History['thumb']=$gallery->thumb;
            $History['ext']=$gallery->ext;

        }
        $this->view->setVar('galleryHistory',$OrderItemGalleryHistory);
    }

    /**
     * 获取订单 关联的海外仓订单物流跟踪号
     * @param type $order_id  订单id
     * @return string
     */
    public function getAbroadTrackingNo($order_id) {
        $tracking_number = '';
        $abroad_orders = OrderAbroadModel::find([
            "pid = :pid: and status = :status:",
            "bind" => [
                "pid" => $order_id,
                "status" => OrderAbroadModel::COLUMN_STATUS_DELIVERY_ALL
            ]
        ]);
        foreach ($abroad_orders as $abroad_order) {
            if (empty($abroad_order->tracking_number)) {
                continue;
            }
            if ($tracking_number != '') {
                $tracking_number .= '、';
            }
            $tracking_number .= $abroad_order->tracking_number;
        }
        return $tracking_number;
    }

    /**
     * 后台修改订单地址
     * 替换的地址中国家改变 需要判断变更后的国家是否也支持订单的物流方式及物流服务
     */
    public function editOrderAddressAction() {
        if ($this->request->isPost()) {
            $order_id = intval($this->request->getPost('id'));
            $address_id = intval($this->request->getPost('address_id'));
            $this->db->begin();
            try {
                $address = Address::findFirstById($address_id);
                $order = Order::findFirstById($order_id);
                if (empty($address_id) || empty($order_id) || empty($address) || empty($order) || ($address->uid != $order->uid)) {
                    return new \Xin\Lib\MessageResponse('订单地址修改失败', 'error');
                }
                $order_status = $order->order_status_id;
                if ($order_status != 20 && $order_status != 21 && $order_status != 30 && $order_status != 31) {
                    throw new \Exception("订单当前状态不支持修改地址!");
                }
                if ($address->country != $order->shipping_country) {
                    throw new \Exception("替换地址的国家需和原地址的国家一致!");
                }
                $order->shipping_fullname = $address->firstname . " " . $address->lastname;
                $order->shipping_firstname = $address->firstname;
                $order->shipping_lastname = $address->lastname;
                $order->shipping_telephone = $address->phone;
                $order->shipping_company = $address->company;
                $order->shipping_address_1 = $address->address;
                $order->shipping_address_2 = $address->address1;
                $order->shipping_country = $address->country;
                $order->shipping_state = $address->state;
                $order->shipping_city = $address->city;
                $order->shipping_postcode = $address->zip;
                $order->shipping_email = $address->email;

                //添加订单记录   记录某个操作员修改订单地址
                $orderHistory = new OrderHistory();
                $orderHistory->order_id = $order_id;
                $orderHistory->order_status_id = $order_status;
                $orderHistory->notify = 0;
                $orderHistory->uid = $this->auth->getTicket()['uid'];
                $orderHistory->username = $this->auth->getTicket()['username'];
                $orderHistory->comment = "修改收货地址";

                if ($orderHistory->save() === false || $order->save() === false) {
                    $this->di->get('logger')->error("editOrderAddress: " . implode(';', $obj->getMessages()));
                    throw new \Exception("订单地址保存失败!");
                }
                $this->db->commit();
                return new \Xin\Lib\MessageResponse('订单地址修改成功!', 'succ');
            } catch (\Exception $e) {
                $this->db->rollback();
                return new \Xin\Lib\MessageResponse($e->getMessage(), 'error');
            }
        }
        $order_id = $this->request->getQuery("id");
        $this->view->setVar('id', $order_id);
        $this->view->pick('address/address_list');
    }

    public function loadDataAction() {
        $order_id = $this->request->getQuery("id");
        $keyword = $this->request->getQuery("keyword");
        $this->view->setVar('keyword', $keyword);

        $order = Order::findFirstById($order_id);
        if (empty($order_id) || empty($order->toArray())) {
            return new \Xin\Lib\MessageResponse('未找到订单', 'error');
        }

        $uid = $order->uid;
        $country = $order->shipping_country;
//        $country = $this->request->getQuery("country");
//        $this->view->setVar('country', $country);

        $build = new \Phalcon\Mvc\Model\Query\Builder();
        $build = $build->from(['a' => 'Xin\Model\Address'])
                ->columns('count(*) as count')
                ->Where('a.uid=:uid: and country =:country:', ['uid' => $uid, "country" => $country]);

        if ($keyword) {
            $build = $build->andWhere('a.zip like \'%' . SqlHelper::escapeLike($keyword) . '%\''
                    . ' or a.address like \'%' . SqlHelper::escapeLike($keyword) . '%\''
                    . ' or a.address1 like \'%' . SqlHelper::escapeLike($keyword) . '%\''
                    . ' or a.firstname like \'%' . SqlHelper::escapeLike($keyword) . '%\''
                    . ' or a.lastname like \'%' . SqlHelper::escapeLike($keyword) . '%\'');
        }
        $count = $build->getQuery()->execute()->getFirst()['count'];

        $sort = $this->request->getQuery("sort");
        $offset = intval($this->request->getQuery("offset"));
        $limit = intval($this->request->getQuery("limit"));
        $offset = $offset > 0? $offset:0;
        $limit = $limit > 0? $limit:10;
        $build = $build->columns('a.*')->orderBy('a.create_time desc')->limit($limit, $offset);
        if (!empty($sort)) {
            $row_order = $this->request->getQuery("order");
            $row_order = $row_order? $row_order : 'asc';
            $build = $build->orderBy("a." . $sort . ' ' . $row_order);
        }
        $addrs = $build->getQuery()->execute();

        return json_encode([
            "total" => $count,
            "rows" => $addrs->toArray()
        ]);
    }

    /**
     * 桌面沙滩旗
     * @param $productType
     * @param $product_cache
     * @param $options
     * @param $pmsProducts
     * @param $pmsArray
     * @param $num
     * @param $sperialMakeStatus
     * @param $sperialMakeArray
     * @return array
     */
    public function customCoverFlagProduct($productType, $product_cache, $options, &$pmsProducts, &$pmsArray, &$num, $sperialMakeStatus, $sperialMakeArray) {
        $this->buildProductMake($sperialMakeStatus, $sperialMakeArray, $pmsProducts, 'customFlag');
        $title = $product_cache->title;
        $size = $options->acreage->value_cn;
        //产品长度宽度
        if($size) {
            $acreage = explode('x', $size);
            $pmsProducts['productLength'] = $acreage[0];
            $pmsProducts['productWidth'] = $acreage[1];
        } else {
            $size = $options->acreage->value;
            $acreage = explode('x', $size);
            if(!$acreage || count($acreage) <= 0) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽信息"}');

            $pmsProducts['productLength'] = round(floatval($acreage[0] * 30.48), 3);
            $pmsProducts['productWidth'] = round(floatval($acreage[1] * 30.48), 3);
        }
        if(empty($pmsProducts['productLength'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长度"}');
        if(empty($pmsProducts['productWidth'])) return array('','{"success":"error","message":"'.$pmsProducts['name'].'找不到对应长宽"}');

        $pmsProducts['printSquare'] = null;
        $pmsProducts['packingBag'] = 'EVA磨砂袋';
        $pmsProducts['hemming'] = '单线';
        $pmsProducts['waistHemming'] = '单线';

        $pmsProducts['waistMaterial'] = '黑色450D牛津布(默认标准)或者根据图稿呈现形式来缝纫）';
        $pmsProducts['waistSize'] = '马尾标准';
        $pmsProducts['sewingMethod'] = '黑色弹力绳';

        $pmsProducts['waistPosition'] = '旗帜左侧';  //腰头位置;

        if(strtolower($options->accessories->value) != 'no') {
            //锦旗画面：配件金额比例（5:5）
            $price = round(floatval($pmsProducts['price']) /2, 2);
            $parts = array();
            $parts['model'] = '';
            $parts['type'] = $productType->product_type;
            $parts['subtype'] = '其他';
            $parts['imageOriginalPath'] = '';
            $parts['imageSmallPath'] = '';
            $parts['imageOriginalSize'] = null;
            $parts['imageSmallSize'] = null;
            $parts['imageReuse'] = null;
            $parts['material'] = '';
            $parts['tech'] = '配件订单';
            $parts['productLength'] = null;
            $parts['productWidth'] = null;
            $parts['printSquare'] = null;
            $parts['hemming'] = '';
            $parts['polesType'] = '';
            $parts['packingBag'] = '';
            $parts['quantity'] = $pmsProducts['quantity'] * 1;
            $parts['packingLabel'] = '';
            $parts['leafletName'] = '';
            $parts['leafletType'] = '';
            $parts['manualImageName'] = '';
            $parts['boxedLogo'] = '';
            $parts['attachName'] = '';
            $parts['sewnInLabelName'] = '';
            $parts['price'] = $price;
            $parts['comment'] = '';

            $parts['hsCode'] = '3926909090999';
            $parts['hsName'] = 'Flag Pole';
            $parts['declareName'] = 'Materail:PVC';
            if(strtolower($options->accessories->value) != 'no') {
                $pmsProducts['price'] = $price;
                $parts['productNo'] = '' . (++$num);
                $parts['name'] = '直径8cm长61.7cm桌面沙滩旗旗杆（TF-BP01）、牛津布袋（TF-B）';
                array_push($pmsArray['products'], $parts);
            }
        }

        array_push($pmsArray['products'], $pmsProducts);
    }

}
