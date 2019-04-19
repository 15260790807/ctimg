<?php

namespace Xin\Lib;

use \Phalcon\Http\Request\File;

class Uploader
{
    /**
     * 默认上传配置
     * @var array
     */
    private $config = array(
        'mimes' => array(), //允许上传的文件MiMe类型
        'maxSize' => 0, //上传的文件大小限制 (0-不做限制)
        'exts' => array(), //允许上传的文件后缀
        'autoSub' => true, //自动子目录保存文件
        'subName' => array('date', 'Ymd'), //子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
        'rootPath' => BASE_DIR . '/public/uploads/', //保存根路径
        'savePath' => '', //保存路径
        'saveName' => array('uuid', ''), //上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
        'saveExt' => '', //文件保存后缀，空则使用原后缀
        'replace' => false, //存在同名是否覆盖
        'hash' => true, //是否生成hash编码
        'callback' => false, //检测文件是否存在回调，如果存在返回文件信息数组
        'driver' => '', // 文件上传驱动
        'driverConfig' => array(), // 上传驱动配置
    );

    /**
     * 上传驱动实例
     * @var Object
     */
    private $uploader;

    /**
     * 构造方法，用于构造上传实例
     * @param array  $config 配置
     * @param string $driver 要使用的上传驱动 LOCAL-本地上传驱动，FTP-FTP上传驱动
     */
    public function __construct($config = array(), $driver = 'local')
    {
    	/* 获取配置 */
        $this->config = array_merge($this->config, $config);
        /* 设置上传驱动 */
        $class = strpos($driver, '\\') ? $driver : '\\Xin\\Lib\\Uploader\\Driver\\' . strtolower($driver);
        $this->setDriver($class, []);
        
        /* 调整配置，把字符串配置参数转换为数组 */
        if (!empty($this->config['mimes'])) {
            if (is_string($this->mimes)) {
                $this->config['mimes'] = explode(',', $this->mimes);
            }
            $this->config['mimes'] = array_map('strtolower', $this->mimes);
        }
        if (!empty($this->config['exts'])) {
            if (is_string($this->exts)) {
                $this->config['exts'] = explode(',', $this->exts);
            }
            $this->config['exts'] = array_map('strtolower', $this->exts);
        }
    }

    /**
     * 使用 $this->name 获取配置
     * @param  string $name 配置名称
     * @return multitype    配置值
     */
    public function __get($name)
    {
        return $this->config[$name];
    }

    public function __set($name, $value)
    {
        if (array_key_exists($name,$this->config)) {
            $this->config[$name] = $value;
        }
    }

    public function __isset($name)
    {
        return isset($this->config[$name]);
    }

    /**
     * 上传单个文件
     * @param  \Phalcon\Http\Request\File  $file 文件
     * @return array        上传成功后的文件信息
     */
    public function upload(File $file)
    {
        /* 检测上传根目录 */
        if (!$this->uploader->checkRootPath()) {
            throw new \Exception($this->uploader->getError());
        }

        /* 检查上传目录 */
        if (!$this->uploader->checkSavePath($this->savePath)) {
            throw new \Exception($this->uploader->getError());
        }
        
        $info = [
            'key' => $file->getKey(),
            'type' => 0&&function_exists('finfo_open') ? $file->getRealType():$file->getType(),
            'ext' => $file->getExtension(),
            'name' =>$file->getName(),
            'tmp_name'=>$file->getTempName(),
            'size'=>$file->getSize(),            
        ];
        
        /* 文件上传失败，捕获错误代码 */
        if ($file->getError()) {
            throw new \Exception($file->getError());
        }

        /* 无效上传 */
        if (empty($file->getName())) {
            throw new \Exception('Unknown upload error!');
        }
       
        /* 检查是否合法上传 */
        if (!$file->isUploadedFile()) {
            throw new \Exception('Illegal file upload!');
        }
        
        /* 检查文件大小 */
        if (!$this->checkSize($info['size'])) {
            throw new \Exception('Upload file size inconsistent!');
        }

        /* 检查文件Mime类型 */
        if (!$this->checkMime($info['type'])) {
            throw new \Exception('Upload file MIME type not allowed!');
        }

        /* 检查文件后缀 */
        if (!$this->checkExt($info['ext'])) {
            throw new \Exception('Upload file suffixes are not allowed');
        }

        if ($this->hash) {
            $info['md5'] = $this->getHash($file);
            //$info['sha1'] = sha1_file($file->getTempName());
        }

        /* 调用回调函数检测文件是否存在 */
        if($this->callback){
            $data = call_user_func($this->callback, $info);
            if ($this->callback && $data) {
                if (file_exists('.' . $data['path'])) {
                    return $data;
                } elseif ($this->removeTrash) {
                    call_user_func($this->removeTrash, $data);//删除垃圾据
                }
            }
        }

        /* 生成保存文件名 */
        $info['savename'] = $this->getSaveName($info);

        /* 检测并创建子目录 */
        $info['savepath'] = $this->savePath . $this->getSubPath($info['name']);

        /* 对图像文件进行严格检测 */
        $ext = strtolower($info['ext']);
        if (!$_POST['chunks'] && in_array($ext, array('gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'))) {
            $imginfo = getimagesize($file->getTempName());
            if (empty($imginfo) || ($ext == 'gif' && empty($imginfo['bits']))) {
                throw new \Exception('Illegal image file!');
            }
        }

        /* 保存文件 并记录保存成功的文件 */
        if ($this->uploader->save($info, $this->replace)) {
            return $info;
        } else {
            throw new \Exception($this->uploader->getError());
        }

    }

    public function getHash(File $file)
    {
        static $hashs = [];
        if (!$hash = $hashs[$file->getTempName()]) {
            $hash = md5_file($file->getTempName());
            $hashs[$file->getTempName()] = $hash;
        }
        return $hash;
    }

    /**
     * 设置上传驱动
     * @param string $class 驱动类名称
     */
    private function setDriver($class, $config)
    {
        $this->uploader = new $class($this->rootPath, $config);
        if (!$this->uploader) {
            throw new \Exception("There is no upload driver:{$name}");
        }
    }

    
    /**
     * 检查文件大小是否合法
     * @param integer $size 数据
     */
    private function checkSize($size)
    {
        return !($size > $this->maxSize) || (0 == $this->maxSize);
    }

    /**
     * 检查上传的文件MIME类型是否合法
     * @param string $mime 数据
     */
    private function checkMime($mime)
    {
        return empty($this->config['mimes']) ? true : in_array(strtolower($mime), $this->mimes);
    }

    /**
     * 检查上传的文件后缀是否合法
     * @param string $ext 后缀
     */
    private function checkExt($ext)
    {
        return empty($this->config['exts']) ? true : in_array(strtolower($ext), $this->exts);
    }

    /**
     * 根据上传文件命名规则取得保存文件名
     * @param string $file 文件信息
     */
    private function getSaveName($file)
    {
        $rule = $this->saveName;
        if (empty($rule)) { //保持文件名不变
            /* 解决pathinfo中文文件名BUG */
            $filename = substr(pathinfo("_{$file['name']}", PATHINFO_FILENAME), 1);
            $savename = $filename;
        } else {
            $savename = $this->getName($rule, $file['name']);
            if (empty($savename)) {
                throw new \Exception('File name rule error!');
            }
        }

        /* 文件保存后缀，支持强制更改文件后缀 */
        $ext = empty($this->config['saveExt']) ? $file['ext'] : $this->saveExt;

        return $savename . '.' . $ext;
    }

    /**
     * 获取子目录的名称
     * @param array $file  上传的文件信息
     */
    private function getSubPath($filename)
    {
        $subpath = '';
        $rule = $this->subName;
        if ($this->autoSub && !empty($rule)) {
            $subpath = $this->getName($rule, $filename) . '/';
            if (!empty($subpath) && !$this->uploader->mkdir($this->savePath . $subpath)) {
                throw new \Exception($this->uploader->getError());
            }
        }
        return $subpath;
    }

    /**
     * 根据指定的规则获取文件或目录名称
     * @param  array  $rule     规则
     * @param  string $filename 原文件名
     * @return string           文件或目录名称
     */
    private function getName($rule, $filename)
    {
        $name = '';
        if (is_array($rule)) { //数组规则
            $func = $rule[0];
            $param = (array)$rule[1];
            foreach ($param as &$value) {
                $value = str_replace('__FILE__', $filename, $value);
            }
            if($func=='uuid'){
                $random=new \Phalcon\Security\Random();
                $func=[$random,'uuid'];
            }
            $name = call_user_func_array($func, $param);            
        } elseif (is_string($rule)) { //字符串规则
            if (function_exists($rule)) {
                $name = call_user_func($rule);
            } else {
                $name = $rule;
            }
        }
        return $name;
    }

}
