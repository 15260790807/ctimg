<?php

namespace Xin\Module\Order;

use Xin\Module\Order\Model\Order;
use Xin\Module\Order\Model\OrderStatus;
use Xin\Module\Order\Model\OrderHistory;
use Xin\App\Admin\Model\AccountToRole;
use Xin\App\Admin\Model\Role;
use Xin\Module\Order\Model\OrderItemHistory;
use Xin\Module\Order\Model\OrderItem;
use Xin\Lib\Utils;

class DataTag extends \Xin\Lib\DataTag
{

    public function orderStatusList()
    {
        $id=[21,40,80];
        $uid = $this->getDI()->get('auth')->getTicket()['uid'];
        $accountToRole = AccountToRole::findFirstByUser_id($uid);
        $role = Role::findFirstById($accountToRole->role_id);
        if($role->rolename!=="财务"){
            array_push($id,'20','90','10');
        }
        return OrderStatus::find(['id NOT IN ({id:array})','bind'=>[
            'id'=>$id
        ],'orderby' => 'steps asc'])->toArray();
    }

    public function orderHistoryList($params)
    {
        return OrderHistory::find(['order_id=:order_id:', 'bind' => ['order_id' => intval($params['order_id'])], 'orderby' => 'id asc'])->toArray();
    }

    public function orderItemHistoryList($params)
    {
        /*if($params['order_id']){
            $OrderItemHistory=OrderItemHistory::find(['order_id=:order_id: and status=:status: and from_id!=:from_id:', 'bind' => ['order_id' => $params['order_id'],'status'=>'0','from_id'=>$params['from_id']]])->toArray();
        }else{
            $OrderItemHistory=OrderItemHistory::find(['user_id=:user_id: and status=:status: and from_id!=:from_id:', 'bind' => ['user_id' =>$params['user_id'],'status'=>'0','from_id'=>$params['from_id']]])->toArray();
        }
        
        $data['order']=OrderItem::findFirst($OrderItemHistory[0]['order_item_id'])->toArray();
        $data['count']=count($OrderItemHistory)?count($OrderItemHistory):'';
        $data['url']=Utils::url('order/detail',['id'=>$data['order']['id']]);
        return $data;*/
    }


    public function orderItemIsApprove($params){
        $order=Order::findFirst($params['order_id']);
        if($order->order_status_id>30) return false;
        foreach($order->getOrderitem() as $item){
            if($item->options['artwork']['approve']==='notApprove'){
                return true;
            }
        }
    }
} 