<?php

namespace Xin\App\Admin\Controller;
use Xin\Model\Config;
use Xin\Module\Picture\Model\Picture;
class IndexController extends \Phalcon\Mvc\Controller {

    public function indexAction() {
        $uid  = $this->di->get('auth')->getTicket()['uid'];   
        $this->view->setVar('uid',$uid);
    }

    public function bannerAction(){
        if($this->request->isPost()){ 
            if (is_array($this->request->getPost('productimage'))) {
            //    $url = $this->request->getPost('productimageUrl');
                foreach ($this->request->getPost('productimage') as $item) {
                    if ($pic = Picture::findHash($item)) {
                        $picIdList[] = $pic->id;
                    }
                }
                if ($picIdList) {
                    $data['picture_ids'] = implode(",", $picIdList);
                }
            }
            $config = Config::findFirstByName('HOME_BANNER');
            $config->val=$data['picture_ids'];
            $config->save();
        }
        $banner=Config::getValByKey('HOME_BANNER');
        $pics = $_pics = [];
        $rs = Picture::find(['id in ({id:array})', 'bind' => ['id' => explode(",", $banner)]]);
        foreach ($rs as $r) {
            $_pics[$r->id] = [
                'name' => $r->title ? $r->title : basename($r->path),
                'src' => $r->getUrl(),
                'hash' => $r->getHash()
            ];
        }
        foreach (explode(",", $banner) as $r) {
            $_pics[$r] && $pics[] = $_pics[$r];
        }
        $this->view->setVar('productPics',$pics);
    }
}

