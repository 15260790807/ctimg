<?php
namespace Xin\Lib;
use Phalcon\Http\Response;

class MessageResponse extends Response{
    private $data;
    /**
     * @param string $msg
     * @param string $level waring,info,error,succ
     * @param array $url_forward 可选择goback,home,referer,close,url(指定url时需穿数组,文字做key，链接做value),多个连接时，第一个为默认项
     */
    public function __construct($msg,$level = 'error', $url_forward = ['goback','home'],$breadcrumb='Hint',$title='Hint', $code=null, $status=null)
	{
        parent::__construct(null,$code,$status);
        if ($msg instanceof \Exception) {
            $msg = $msg->getMessage();
        }
        if(!$url_forward){
            $url_forward=['goback','home'];
        }
        $msg = is_array($msg) ? $msg : array($msg);
        $this->data=[
            'message' => $msg,
            'forwards' => $url_forward,
            'level' => $level,
            'breadcrumb'=>$breadcrumb,
            'title'=>$title,
        ];
    }
    public function getContent(){
        //响应为json的，自动组装为json格式返回
        if($this->getDI()->get('request')->isAjax() || $_REQUEST['_format']=='json'){   
            $this->setContentType('application/json');      
            $vars=$this->data;
            $vars['status']=$vars['level']=='error'?'error':'ok'; 
            return json_encode($vars,JSON_UNESCAPED_UNICODE);            
        }
        return $this->getDI()->get('view')->getPartial('index/showmessage',$this->data);
    }
}