<?php

namespace Xin\App\Admin\Controller;
use Xin\Model\Config;
use Xin\Module\Picture\Model\Picture;
use Xin\Module\Order\Model\Order;
use Xin\Module\Order\Model\OrderItem;
use OSS\OssClient;
use OSS\Core\OssException;
use Xin\lib\Utils;
class OutstockController extends \Phalcon\Mvc\Controller {
	
    public function indexAction() {
        $uid  = $this->di->get('auth')->getTicket()['uid'];   
        $this->view->setVar('uid',$uid);
    }
    public function byorderAction(){

    }
    //根据订单
    public function byorderitemAction(){

    }
    //上传的接口
    public function uploadapiAction(){
        $postData = $this->request->getPost();
        $tagUrl = $this->base64_image_content($postData['image']);
        if ($tagUrl){
            //TODO 将其写入数据库
            //return showMsg(1,$tagUrl);
            header('content-type:text/json;charset=utf-8');
            echo json_encode(array("code"=>200,"msg"=>"上传成功",'data'=>$tagUrl));exit;
        }else{
            //return showMsg(0,'图片上传失败！');
            header('content-type:text/json;charset=utf-8');
            echo json_encode(array("code"=>10001,"msg"=>"上传失败"));exit;
        }
    }
   	/*public function uploadapiAction(){
         $postData = $this->request->getPost();
            $uploadUrl =  "/";
            $tagUrl = $this->base64_image_content($postData['image'],$uploadUrl);
            if ($tagUrl){
                //TODO 将其写入数据库
                //return showMsg(1,$tagUrl);
                echo $tagUrl;
            }else{
                //return showMsg(0,'图片上传失败！');
                echo 0;
            }
    }*/
     /**
     * [将Base64图片转换为本地图片并保存]
     * @param $base64_image_content [要保存的Base64]
     * @param $path [要保存的路径]
     * @return bool|string
     */
    public function base64_image_content($base64_image_content){
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
            $type = $result[2];
            $ymdDir=date("Ymd",time());
            $basePutUrl =$this->config['module']['attachment']['uploadDir'].$ymdDir."/";

            if(!file_exists($basePutUrl)){
                //检查是否该文件夹，如果没有就创建，并给予最高权限
                mkdir($basePutUrl, 0700);
            }
            $filename='my'.time().rand(10000);
            $ping_url = $filename.".{$type}";
            $local_file_url = $basePutUrl.$ping_url;
            $network_url="/uploads/".$ymdDir."/".$ping_url;
            if (file_put_contents($local_file_url, base64_decode(str_replace($result[1], '', $base64_image_content)))){
            //TODO 个人业务的FTP 账号图片上传
           		return $network_url;
            }else{
               
                return false;
            }
        }else{
            
            return false;
        }
    }
    
    /*
    查询订单的接口,需要先查询到订单，才可以上传，
     */
    public function searchOrderAction(){
        $ordersn=$this->request->getPost("ordersn");
        //拼接网络请求接口
        //var_dump($this->config);
        $url= $this->config['curlapi']."cmsapi-searchOrder.html";
        $param['ordersn']=$ordersn;
        $result=Utils::curlPost($param,$url,true);
        header("content-type:text\json;charset=utf-8");
        return $result;
    }
    public function deleteimgAction(){
        /* try{
            $postData=$this->request->getPost();
            $item=OrderItem::findFirstById($postData['itemid']);
            //如果是空的话，需要异常抛出
            $shipment=$item->shipment;
            $shipment=explode(",",$shipment);
            if(in_array($postData['picid'],$shipment)){
                $key=array_search($postData['picid'],$shipment);
                unset($shipment[$key]);
            }
            $shipment=implode(",",$shipment);
            $item->shipment=$shipment;
            $item->update();
            header("content-type:text/json;charset=utf8");
            echo json_encode(array('code'=>200,'msg'=>"删除成功"));exit;
        }catch(\Exception $e){
            $this->di->get('logger')->error($e->getMessage());
            return new \Xin\Lib\MessageResponse('删除失败','error',[],500);
        } */
        $postData=$this->request->getPost();
        if(!isset($postData['itemid'])){
            $getData=$this->request->get();
            $postData['itemid']=$getData['itemid'];
        }
        $url= $this->config['curlapi']."cmsapi-deleteimg.html";
        //var_dump($postData);exit;
        $param=$postData;
        $result=Utils::curlPost($param,$url,true);
        header("content-type:text\json;charset=utf-8");
        if($result==false){
            return json_encode(array('code'=>500,'msg'=>"接口出错"));
        }
        return $result;
    }
    /*
    开始接受前端上传的图片，并且将图片和order的关系存放到表中：order_out
     */
    public function testUploadAction(){
        // 阿里云主账号AccessKey拥有所有API的访问权限，风险很高。强烈建议您创建并使用RAM账号进行API访问或日常运维，请登录 https://ram.console.aliyun.com 创建RAM账号。
        $accessKeyId = "LTAIjXjQKCDZVCu9";
        $accessKeySecret = "DyWPeToNGXknaN2u9aCpuajqcojzSs";
        // Endpoint以杭州为例，其它Region请按实际情况填写。
        $endpoint = "http://oss-us-east-1.aliyuncs.com";
        // 存储空间名称
        $bucket= "cfm-resources";
        // 文件名称
        $object = "uploads/20190422/rgaegaergaer.jpg";
        // <yourLocalFile>由本地文件路径加文件名包括后缀组成，例如/users/local/myfile.txt
        $filePath = "H:/newproject/ctimg/public/uploads/20190422/rgaegaergaer.jpg";
        try{
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->uploadFile($bucket, $object, $filePath);
            print(__FUNCTION__ . ": OK" . "\n");
        } catch(OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
        
    }
}

