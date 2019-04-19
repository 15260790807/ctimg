<?php

namespace Xin\Module\Attachment\Controller;

use Xin\Lib\Uploader;
use Xin\Module\Attachment\Model\Attachment;

class AttachmentController extends \Phalcon\Mvc\Controller
{
    public function uploadAction()
    {
        if ($this->request->hasFiles()) {
            $upload = new Uploader([
                //'exts'=>'jpg,png,gif',
                'rootPath'=>$this->config['module']['attachment']['uploadDir']
            ]);
            foreach ($this->request->getUploadedFiles() as $file) {
                try {
                    $data = $upload->upload($file);
                    $attach=new Attachment();
                    $attach->title=$data['name'];
                    $attach->type=$this->request->getQuery('type');
                    $attach->size=$data['size'];
                    $attach->ext=$data['ext'];
                    $attach->group_type=$this->request->getQuery('group');
                    $attach->path=$data['savepath'].$data['savename'];
                    if($attach->save()===false){
                        throw new \Exception(implode(';',$attach->getMessages()));
                    }      
                    $data=[
                        'hash'=>$attach->getHash(),
                        'url'=>$this->config['module']['attachment']['uploadUriPrefix'].$attach->path,
                    ];
                    //echo json_encode($data);exit;  
                    $this->view->setVar('file',$data);                 
                } catch (\Exception $e) {
                    $this->di->get('logger')->error($e->getMessage());
                    return new \Xin\Lib\MessageResponse('上传附件失败','error',[],500);
                }
            }
        } else {
            return new \Xin\Lib\MessageResponse("暂无上传文件",'error',[],500);
        }
    }
}