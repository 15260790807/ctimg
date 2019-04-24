<?php

namespace Xin\Module\Picture\Controller;

use Xin\Lib\Uploader;
use Xin\Module\Picture\Model\Picture;
use Xin\Lib\Utils;
use Xin\Module\Order\Model\OrderItem;
use Xin\Module\Uploadoss\Model\UploadTask;
class PictureController extends \Phalcon\Mvc\Controller
{
    public function uploadsPicAction()
    {
        if ($this->request->hasFiles()) {
            $upload = new Uploader([
                'exts'=>'gif,jpg,jpeg,bmp,png',
                'hash'=>true,
                'rootPath'=>$this->config['module']['picture']['uploadDir']
            ]);
            foreach ($this->request->getUploadedFiles() as $file) {
                try {
                    if(!$hash=$upload->getHash($file)){                        
                        throw new \Exception('无效文件');
                    }
                    if(!$pic=Picture::findFirstByHash($hash)){
                        $data = $upload->upload($file);
                        $pic=new Picture();
                        $pic->hash=$data['md5'];
                        $pic->title=$data['name'];
                        $pic->size=$data['size'];
                        $pic->path=$data['savepath'].$data['savename'];
                        if($pic->save()===false){
                            throw new \Exception(implode(';',$pic->getMessages()));
                        }  
                         //加入任务其中
                         $UploadTask=new UploadTask();
                         $UploadTask->path=$data['savepath'].$data['savename'];
                         $UploadTask->name=$data['savename'];
                         $UploadTask->hash=$data['md5'];
                         $UploadTask->createtime=time();
                         if($UploadTask->create()===false){
                             throw new \Exception(implode(';',$UploadTask->getMessages()));
                         }     
                    } 
                    //  Orientation 属性判断上传图片是否需要旋转(转)
                    // https://www.zhangshengrong.com/p/LKa4Dlx0aQ/
                    $rootPath = $this->config['module']['picture']['uploadDir'];
                    if($data['ext']=="jpeg"){
                        $path=$this->config['module']['picture']['uploadDir'].$pic->path; 
                        $image = imagecreatefromstring(file_get_contents($path));
                        $exif = exif_read_data($path);
                        if(!empty($exif['Orientation'])) {
                        switch($exif['Orientation']) 
                            {
                                case 8:
                                $image = imagerotate($image,90,0);
                                break;
                                case 3:
                                $image = imagerotate($image,180,0);
                                break;
                                case 6:
                                $image = imagerotate($image,-90,0);
                                break;
                            }
                        }
                        imagejpeg($image,$path);
                    }
                    $data=[
                        'hash'=>$this->di->get('crypt')->encryptBase64(str_pad($pic->id,11,'0',STR_PAD_LEFT)),
                        'url'=>$this->config['module']['picture']['uploadUriPrefix'].$pic->path,
                        'id'=>$pic->id,
                        'path'=>\Xin\Lib\Utils::url($pic->path)
                    ];      
                    $this->view->setVar('file',$data);                 
                } catch (\Exception $e) {
                    $this->di->get('logger')->error($e->getMessage());
                    return new \Xin\Lib\MessageResponse('上传图片失败','error',[],500);
                }
            }
        } else {
            return new \Xin\Lib\MessageResponse("暂无上传图片",'error',[],500);
        }
    }

    public function uploadsPicByitemAction()
    {
        //需要先请求查询订单商品，图片的
        $dataPost = $_POST;
        if ($this->request->hasFiles()) {
            $upload = new Uploader([
                'exts'=>'gif,jpg,jpeg,bmp,png',
                'hash'=>true,
                'rootPath'=>$this->config['module']['picture']['uploadDir']
            ]);
            //先获取该订单商品的id，
            /* $id=$dataPost['itemid'];
            $orderItem = OrderItem::findFirstById($id);
            if(!empty($orderItem->shipment)){
                $shipmentId = explode(',', $orderItem->shipment);
            }else{
                $shipmentId=array();
            }   */          
            foreach ($this->request->getUploadedFiles() as $file) {
                
               // try {
                    if(!$hash=$upload->getHash($file)){                        
                        throw new \Exception('无效文件');
                    }
                // if(!$pic=Picture::findFirstByHash($hash)){
                    //没找到该照片，就存进去
                    $data = $upload->upload($file);
                    //将图片的hash值放到接口中更新
                    //加入任务其中
                    $UploadTask=new UploadTask();
                    $UploadTask->path=$data['savepath'].$data['savename'];
                    $UploadTask->name=$data['savename'];
                    $UploadTask->hash=$data['md5'];
                    $UploadTask->createtime=time();
                    if($UploadTask->create()===false){
                        throw new \Exception(implode(';',$UploadTask->getMessages()));
                    } 
                    var_dump($data);exit;
                    //} 
                    /* if(!in_array($pic->id,$shipmentId)){
                        $shipmentId[]=$pic->id;
                        $shipmentid_s=implode(',',$shipmentId);
                        $orderItem->shipment=$shipmentid_s;  
                        $orderItem->update();  
                    } */
                    //  Orientation 属性判断上传图片是否需要旋转(转)
                    // https://www.zhangshengrong.com/p/LKa4Dlx0aQ/
                    $noprefix_path=$data['savepath'].$data['savename'];
                  //  $rootPath = $this->config['module']['picture']['uploadDir'];
                    if($data['ext']=="jpeg"){
                        $path=$this->config['module']['picture']['uploadDir'].$noprefix_path; 
                        $image = imagecreatefromstring(file_get_contents($path));
                        $exif = exif_read_data($path);
                        if(!empty($exif['Orientation'])) {
                        switch($exif['Orientation']) 
                            {
                                case 8:
                                $image = imagerotate($image,90,0);
                                break;
                                case 3:
                                $image = imagerotate($image,180,0);
                                break;
                                case 6:
                                $image = imagerotate($image,-90,0);
                                break;
                            }
                        }
                        imagejpeg($image,$path);
                    }
                    $param=[
                        "itemid"=>$dataPost['itemid'],
                        'md5'=>$data['md5'],
                        "name"=>$data['name'],
                        "size"=>$data['size'],
                        "savepath"=>$data['savepath'],
                        "savename"=>$data['savename'],
                    ];
                    $url=$this->config['curlapi']."cmsapi-savepic.html";
                    $result=Utils::curlPost($param,$url,true);
                    if($result!=false){
                        $result=json_decode($result,true);
                    }
                    if($result['code']==200){
                        $data=[
                            'hash'=>$this->di->get('crypt')->encryptBase64(str_pad($result['data']['picid'],11,'0',STR_PAD_LEFT)),
                            'url'=>$this->config['module']['picture']['uploadUriPrefix'].$noprefix_path,
                            'id'=>$result['data']['picid'],
                            'path'=>\Xin\Lib\Utils::url($noprefix_path)
                        ];      
                        $this->view->setVar('file',$data);
                    }else{
                        return new \Xin\Lib\MessageResponse($result['msg'],'error',[],500);
                    }                 
               /*  } catch (\Exception $e) {
                    $this->di->get('logger')->error($e->getMessage());
                    return new \Xin\Lib\MessageResponse('上传图片失败','error',[],500);
                } */
            }
        } else {
            return new \Xin\Lib\MessageResponse("暂无上传图片",'error',[],500);
        }
    }
}