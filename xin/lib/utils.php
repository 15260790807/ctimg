<?php

namespace Xin\Lib;

use Phalcon\Di;
use Xin\Module\Product\Model\Product;


class Utils
{
    /**
     * @param $url
     * @param array $params
     * @return string
     */
    public static function url($url, $params = null,$fullpath=false)
    {
        static $urlObj;
        if (!isset($urlObj)) {
            $urlObj = Di::getDefault()->get('url');
        }
        $i=strpos($url,'?');
        if($i!==false){
            $p=substr($url,$i+1);
            $url=substr($url,0,$i);
            foreach(explode('&',$p) as $item){
                list($k,$v)=explode('=',$item);
                (!isset($params[$k]) || strpos($k,'[')) && $params[$k]=$v;
            }
        }
        if(strpos($url,'admin')!==0){
            if( $params['url']){
                return WEB_URL. $params['url'];
            }
            if(strpos($url,'home/')===0){
                $url=substr($url,5);
            }
            if($url=='index/index'){
                return WEB_URL.($params?('?'.http_build_query($params)):'');
            }
            $url=str_replace('/','-',$url);
            return WEB_URL. ($url==''?'':"{$url}.html").($params?('?'.http_build_query($params)):'');
        }

        return ($fullpath?WEB_URL:'').$urlObj->get($url, $params);
    }

    /**
     * 根据页码和页大小返回limit和offset
     * @param $page
     * @param $pagesize
     * @return array [offset,limit]
     */
    public static function offset($page=null,$pagesize=null){
        !$page && $page=Di::getDefault()->getRequest()->getQuery('page', 'absint', 1, true);
        !$pagesize && $pagesize=Di::getDefault()->getRequest()->getQuery('pagesize', 'absint', 10, true);

        $page=intval($page);
        $page=$page<1 ? 1:$page;
        $pagesize=intval($pagesize);
        ($pageSize > 100 || $pageSize < 1) && $pageSize = 10;
        return [($page-1)*$pagesize,$pagesize];
    }

    /**
     * 字符串截取
     * @param str 要截取的字符串
     * @param start=0 开始位置，默认从0开始
     * @param length 截取长度
     * @param charset=”utf-8″ 字符编码，默认UTF－8
     * @param suffix='...' 是否在截取后的字符后面显示指定符号，默认显示'...'，false为不显示
     * */
    public static function subString($str, $start = 0, $length, $charset = "utf-8", $suffix = '') {

        if (mb_strlen($str, $charset) <= $length)
            $suffix = false;

        if (function_exists("mb_substr")) {
            $slice= mb_substr($str, $start, $length, $charset);
        }elseif (function_exists('iconv_substr')) {
            $slice=iconv_substr($str, $start, $length, $charset);
        }else{
            $re['utf-8'] = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";
            $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";
            $re['gbk'] = "/[x01-x7f]|[x81-xfe][x40-xfe]/";
            $re['big5'] = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
        }
        if ($suffix)
            return $slice . $suffix;

        return $slice;
    }

    public static function loadUserControl($ctrlName, $params = null)
    {
        $di=Di::getDefault();
        if($ctrlName{0}!=='\\'){
            $ctrlName=$di->get('dispatcher')->getNamespaceName().'Ctrl\\'.$ctrlName;
        }
        $ctrl = new $ctrlName($params['id']);
        if($params) $ctrl->setAttribs($params);
        return $ctrl;
    }

    /**
     * 扁平数组转为树
     * @param array $lists
     * @param int $parentid
     * @param int $depth
     * @return array
     */
    public static function arrayToTree($lists,$parentid=0,$depth=0,$ignoreShow=true){
        $newarr=array();
        foreach($lists as $k=>$item){
            if($ignoreShow && array_key_exists('isshow',$item) && !$item['isshow']) continue;
            if($item['parentid'] == $parentid){
                $item['depth']=$depth;
                unset($lists[$k]);
                if($child=self::arrayToTree($lists,$item['id'],$depth+1,$ignoreShow)){
                    $item['childs']=$child;
                }
                $newarr[]=$item;
            }
        }
        return $newarr;
    }

    public static function findChildrenInList($list,$pid){
        $result=[];
        foreach($list as $item){
            if($item['parentid']==$pid){
                $result[]=$item;
                $result=array_merge($result,self::findChildrenInList($list,$item['id']));
            }
        }
        return $result;
    }

    /**
     * 是否是全数字的数组
     */
    public static function isNumericArray($arr,$gtZero=true){
        if(!is_array($arr) || count($arr)==0) return false;
        foreach ($arr as $p) {
            if (!is_numeric($p) || ($gtZero && $p<=0))  return false;
        }
        return true;
    }

	/**
	 * 比较源是否被目标兼容，满足前缀一致且参数中没有不一样的值为兼容
	 * 如role/index/index?id=5相对于/role/index/index?id=5&t=1不兼容
	 * 如role/index/index?id=5&t=1相对于/role/index/index?id=5兼容
	 * 如role/index/index?id=5相对于/role/index/index?id=4&t=1不兼容
	 * @param string $source
	 * @param string $target
	 * @return boolean
	 */
    public static function compatibleAccess($source,$target){
		$source=trim(strtolower($source));
        $target=trim(strtolower($target));

        ($k=strrpos($source,'#'))!==false && $source=substr($source,0,$k-1);
        ($k=strrpos($target,'#'))!==false && $target=substr($target,0,$k-1);
        if($source==$target) return true;

		$i=strpos($source,'?');
        $j=strpos($target,'?');
        $s_acc=$i===false? $source : substr($source,0,$i);
        $t_acc=$j===false? $target : substr($target,0,$j);
        if($s_acc!=$t_acc) return false; //accesskey不一致直接算不匹配
        if($j===false) return true; //目标没有筛选条件限定,即为宽松，算匹配
        if($i===false && $j!==false) return false; //目标有筛选条件限定而源没有，算不匹配

        $s_ps=explode('&',substr($source,$i+1));
        $t_ps=explode('&',substr($target,$i+1));
        if(count($s_ps)<count($t_ps)) return false; //目标筛选条件多于源，算不匹配

        foreach($t_ps as $v){
            if(!in_array($v,$s_ps)) return false; //目标筛选条件在源不存在，算不匹配
        }
        return true;
    }

    public static function hasAccess($source,$accessList){
        foreach($accessList as $acc){
            if(self::compatibleAccess($source,$acc)) return true;
        }
        return false;
    }

    public static function hashcode($mix) {
        if (is_object($mix) && function_exists('spl_object_hash')) {
            return spl_object_hash($mix);
        } elseif (is_resource($mix)) {
            $mix = get_resource_type($mix) . strval($mix);
        } else {
            $mix = serialize($mix);
        }
        return crc32($mix);
    }

    public static function callColumnFunc($val,$str){
        if(!$str) return $val;
        $i=strpos($str,'(');
        $fun=$i===false?$str:substr($str,0,$i);
        if(method_exists('\Xin\Lib\Utils',$fun)){
            if($i===false) return call_user_func_array(['\Xin\Lib\Utils', $fun], [$val]);
            eval('$res=\Xin\Lib\Utils::'.$fun.'($val,'.substr($str,$i+1).';');
            return $res;
        }else{
            if($i===false) return call_user_func_array($fun, [$val]);
            eval('$res='.$fun.'($val,'.substr($str,$i+1).';');
            return $res;
        }
    }
    public static function statusText($val){
        $list=['enable'=>'enable','disable'=>'disable','deleted'=>'deleted'];
        return $list[$val]?$list[$val]:'unknown';
    }
    public static function categoryTitle($catid){
        $obj= \Xin\Module\Category\Model\Category::findFirstById($catid);
        return $obj ? $obj->title: '';
    }

    public static function img($path,$width=0,$height=0){
        return '<img src="'.Di::getDefault()->get('config')['module']['attachment']['uploadUriPrefix'].$path.'" style="'.($width?"width:{$width}px;":'').($height?"height:{$height}px;":'').'"/>';
    }
    public static function thumb($path,$width=0,$height=0){
        return Di::getDefault()->get('config')['module']['picture']['uploadUriPrefix'].$path.'?w='.$width.'&h='.$height;
    }
    public static function productType($is_package){
        return $is_package?'产品包':'单产品';
    }

    public static function date($val){
        return $val ? date('Y-m-d',$val):'1970-01-01';
    }
    public static function orderStatus($val,$type='cn'){
        static $status;
        if(!isset($status)){
            $status=\Xin\Module\Order\Model\OrderStatus::findFillWithKey('id');
        }
        return $status[$val]?$status[$val]['name_'.$type]:'unknow';
    }
    public static function payment($val,$type='cn'){
        static $status;
        if(!isset($status)){
            $status=\Xin\Module\Order\Model\Payment::findFillWithKey('id');
        }
        return $status[$val]?$status[$val]['title_'.$type]:'';
    }
    public static function express($val=0){
        static $status;
        if(!isset($status)){
            $status=\Xin\Module\Express\Model\Express::findFillWithKey('id');
        }
        return $status[$val]?$status[$val]['title']:'';
    }
    public static function strToarray($str){
        return json_decode($str,true);
    }
    public static function modellink($link,$object,$modelid){
        $m=Di::getDefault()->get('dispatcher')->getModuleName();
        $c=Di::getDefault()->get('dispatcher')->getControllerName();
        $ps=['id'=>$object['id']];
        $_GET['modelid'] && $ps['modelid']=$_GET['modelid'];
        $_GET['catid'] && $ps['catid']=$_GET['catid'];
        $_GET['filter_category_id'] && $ps['filter_category_id']=$_GET['filter_category_id'];
        $link=str_replace(['[EDIT]','[DELETE]'],[Utils::url("$m/$c/edit",$ps),Utils::url("$m/$c/delete",$ps)],$link);
        return $link;
    }
    //检查的图片类型  
    public static function checkIsImage($filename){    
        $alltypes = '.gif|.jpeg|.png|.bmp';//定义检查的图片类型  
        if(file_exists($filename)){        
            $result= getimagesize($filename);       
            $ext = image_type_to_extension($result);      
            return stripos($alltypes,$ext);   
        }else{      
            return false;  
        }
    }
    public function ll(){
        
    }
    public static function buildThumb($source_path, $thumb_dir, $thumb_filename, $to_width, $to_height) {
        ini_set('memory_limit','1024M');
        $logger = \Phalcon\Di::getDefault()->get('logger');
        $logger->debug('开始图片转换');
        //by william 2018/10/16 11:10 增加 gs .eps文件的处理
        if((substr($source_path,-4)=='.pdf' || substr($source_path,-3)=='.ai' || substr($source_path,-4)=='.eps') && shell_exec('ls /usr/bin/gs')){
        	$cmd="/usr/bin/gs  -r72 -dFirstPage=1 -dLastPage=1 -q -dBATCH -dNOPAUSE -sDEVICE=jpeg  -sOutputFile={$thumb_dir}/{$thumb_filename}.jpg {$source_path}";
        	$logger->info($cmd);
            shell_exec($cmd);
        	$source_path= "{$thumb_dir}/{$thumb_filename}.jpg";
        }
        if(0&&extension_loaded('imagick')){
            $logger->debug('初始化Imagick');
            $im = new \Imagick();
            $logger->debug('设定缩略图大小');
            $im->setResolution($to_width,$to_height);   //设置图像分辨率
            $im->setCompressionQuality(100); //压缩比
            $im->readImage($source_path); //设置读取pdf的第一页
            foreach ($im as $k => $v){
                $logger->debug('转换-写入压缩目录');
                $v->setImageFormat('jpg');
                $v->scaleImage($to_width,$to_height,true);
                $filename = "{$thumb_dir}/{$thumb_filename}.jpg";
                $logger->debug('转换-压缩jpg文件名：'.$filename);
                if($v->writeImage($filename) == true){
                    $logger->debug('转换完成：'."{$thumb_filename}.jpg");
                    return  "{$thumb_filename}.jpg";
                }
            }
            $logger->debug('转换完成：'."test");
            return ;
        }
        $cmd=BASE_DIR.'vendor/thumb/thumb';
        if(DIRECTORY_SEPARATOR=='\\'){
            $cmd.='.exe';
        }
        if(self::checkIsImage($source_path)){
            $cmd.=" -s $source_path -t {$thumb_dir}/{$thumb_filename}.jpg -w $to_width -h $to_height";
            $res=shell_exec($cmd);
            if(trim($res)=='ok'){
                return $thumb_filename . '.jpg';
            }else{
                \Phalcon\Di::getDefault()->get('logger')->error($cmd."=>".$res);
                return false;
            }
        }
    
        $img = file_get_contents($source_path);
        if (!$img) {
            return false;
        }
        //  Orientation 属性判断上传图片是否需要旋转(转)
        // https://www.zhangshengrong.com/p/LKa4Dlx0aQ/
        $handle = ImageCreateFromString($img);
        $exif = exif_read_data($source_path);
        if(!empty($exif['Orientation'])) {
        switch($exif['Orientation']) 
            {
                case 8:
                $handle = imagerotate($handle,90,0);
                break;
                case 3:
                $handle = imagerotate($handle,180,0);
                break;
                case 6:
                $handle = imagerotate($handle,-90,0);
                break;
            }
        }
        imagejpeg($handle,$source_path);
        $w = imagesx($handle);
        $h = imagesy($handle);
        $w1 = $to_width;
        $h1 = $to_height;
        $tt = $h / $w;
        $dim = imagecreatetruecolor($w1, $h1);
        //设置背景色
        imagefill($dim, 0, 0, imagecolorallocate($dim, 255, 255, 255));

        // if ($tt < $h1 / $w1) {
        //     //较扁
        //     $x = (int)($h * $w1 / $h1);
        //     imagecopyresized($dim, $handle, 0, 0, (int)(($w - $x) / 2), 0, $w1, $h1, $x, $h);
        // } elseif ($tt > $h1 / $w1) {
        //     //较窄
        //     $y = (int)($w * $h1 / $w1);
        //     imagecopyresized($dim, $handle, 0, 0, 0, (int)(($h - $y) / 2), $w1, $h1, $w, $y);
        // } else {
        //     imagecopyresized($dim, $handle, 0, 0, 0, 0, $w1, $h1, $w, $h);
        // }
        imagecopyresized($dim, $handle, 0, 0, 0, 0, $w1, $h1, $w, $h);

        $thumb_filename = strpos($thumb_filename, '.') === false ? $thumb_filename : substr($thumb_filename, 0, strrpos($thumb_filename, '.'));
        $path = rtrim($thumb_dir, '/\\') . '/' . $thumb_filename . '.jpg';
        imagejpeg($dim, $path, 100);
        unset($dim);
        unset($handle);
        return $thumb_filename . '.jpg';
    }

    public static function getThumb($path, $w=null, $h=null) {
        static $upload_pre;
        if (!isset($upload_pre)) {
            $upload_pre = nuts_core::load_config('system', 'upload_url');
        }
        $path = trim($path);
        if(strpos($path,$upload_pre)===0) $path=substr($path,strlen($upload_pre));
        if (empty($path)) {
            return IMG_PATH . 'nopic.gif';
        } elseif (strpos($path, 'http://') === 0) {
            return $path; //已经是上传的地址，直接返回，临时实现！
        } else {
            if(!$w && !$h){
                return $upload_pre . $path;
            }
            return thumb($upload_pre . $path, $w, $h);
        }
        return $path;
    }
    public static function mkdir($dir, $mode = 0777)
    {
        if (is_dir($dir)) return TRUE;
        if (!self::mkdir(dirname($dir), $mode)) return FALSE;

        return @mkdir($dir, $mode);
    }
    //数组按照特定字段分组
    public static function arraytogroup($array,$fieldname){
        $newA = [];
        $keys = array_filter(array_unique(array_column($array,$fieldname)));
        foreach ($array as $key => $value) {
            if ($value[$fieldname]==NUll ) {
                $newA[][] = $value;
            }
            else{
                foreach ($keys as $k => $v) {
                    if ($v == $value[$fieldname]) {
                        $newA[$v][] = $value;
                    }
                }
            }
        }
        return $newA;
    }
    //加法  解决float运算溢出
    public static function countAdd($arg1, $arg2) {
        try {
            $r1 = strlen(explode(".", strval($arg1))[1]);
        } catch (\Exception $e) {
            $r1 = 0;
        }
        try {
            $r2 = strlen(explode(".", strval($arg2))[1]);
        } catch (\Exception $e) {
            $r2 = 0;
        }
        $m = pow(10, max($r1, $r2));
        $n = ($r1 >= $r2) ? $r1 : $r2;
//        return ($arg1 * $m + $arg2 * $m) / $m;
        return round((($arg1 * $m + $arg2 * $m) / $m), $n);
    }

    //减法  解决float运算溢出
    public static function countSub($arg1, $arg2) {
      try {
        $r1 = strlen(explode(".", strval($arg1))[1]);
      } catch (\Exception $e) {
        $r1 = 0;
      }
      try {
        $r2 = strlen(explode(".", strval($arg2))[1]);
      } catch (\Exception $e) {
        $r2 = 0;
      }
      $m = pow(10, max($r1, $r2));
      $n = ($r1 >= $r2) ? $r1 : $r2;
      return round((($arg1 * $m - $arg2 * $m) / $m),$n);
    }
// 超过批量单数量时，弹出相对应的表单，客户填写好表达，
//发邮箱到sales@china-flag-makers.com。
//帐篷：10面以上
//其他产品：100面以上
//$arr :数组  ,  $num : 数量
//返回 标题和内容
    public static function orderform($arr){
        $arr['quantity']=(int)$arr['quantity'];
        if(!is_array($arr['options'])){
            $options= json_decode($arr['options'],true);
        }else{
            $options=$arr['options'];
        }
        if(!$arr['category_id']){
            $product=Product::findFirstById($arr['product_id']);
            $arr['category_id']=$product->category_id;
        }
        $new['title']='We have received your order. Thank you for your order. ';
        if($arr['category_id']=='77' || $arr['category_id']=='80'){
            if ($arr['category_id']=='77' && $arr['quantity'] > 10 ) {
                $new['body']='But www.china-flag-makers.com  is only available to the small order(for tent, the maximum qty is 10). If you have large order need, our sales will estimate the order and contact you at the quickest possible time for the offline order.';
                return json_encode($new);
            }
            if ($arr['category_id']=='80' && $arr['quantity'] > 100 ) {
                $new['body']='But www.china-flag-makers.com  is only available to the small order(for tent, the maximum qty is 100). If you have large order need, our sales will estimate the order and contact you at the quickest possible time for the offline order.';
                return json_encode($new);
            }
            
        }else{
            if ($arr['quantity'] >= 201) {
                $new['body']='But www.china-flag-makers.com  is only available to the small order(for table cover and flag, the maximum qty is 200). If you have large order need, our sales will estimate the order and contact you at the quickest possible time for the offline order';
                return json_encode($new);
            }
        }

        return false;
    }
    //PHP stdClass Object转array  
    public static function object_array($array) {
        if (is_object($array)) {
            $array = (array) $array;
        } if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = self::object_array($value);
            }
        }
        return $array;
    }
}
