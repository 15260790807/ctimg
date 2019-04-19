<?php
namespace Xin\Lib;

class ViewExtension
{
    protected $_compiler;
    public function __construct($compiler)
    {
        $this->_compiler = $compiler;
    }
    public function compileFunction($name, $arguments, $funcArguments)
    {
        if (substr($name, 0, 4) == 'php_' && ($name = substr($name, 4)) && function_exists($name)) {
            return $name . '(' . $arguments . ')';
        }
        if (method_exists($this, '_' . $name)) {
            $func = '_' . $name;
            return $this->$func($arguments, $funcArguments);
        }
        if ($name{0} != '_' && method_exists('\Xin\Lib\Utils', $name)) {
            return "\\Xin\\Lib\\Utils::" . $name . '(' . $arguments . ')';
        }
        if ($this->_compiler->getDI()->has('voltHelper') && method_exists($this->_compiler->getDI()->get('voltHelper'), $name)) {
            return "\$this->di->voltHelper->" . substr($name, 6) . '(' . $arguments . ')';
        }
    }

    public function compileFilter($name, $arguments, $funcArguments)
    {
        if (method_exists($this, '_filter_' . $name)) {
            $func = '_filter_' . $name;
            return $this->$func($arguments, $funcArguments);
        }
    }

    private function _ctrl($arguments, $funcArguments)
    {
        $ctrlName = $this->_compiler->expression($funcArguments[0]['expr']);
        if (isset($funcArguments[1])) {
            $params = $this->_compiler->expression($funcArguments[1]['expr']);
            return "\\Xin\\Lib\\Utils::loadUserControl(\$this->di,$ctrlName,$params)";
        }
        return "\\Xin\\Lib\\Utils::loadUserControl(\$this->di,$ctrlName)";
    }

    private function _conf($arguments, $funcArguments)
    {
        return "\\Xin\\Model\\Config::getValByKey($arguments)";
    }

    private function _u($arguments, $funcArguments)
    {
        static $data;
        if (!isset($data)) {
            $dispatcher = $this->_compiler->getDI()->get('dispatcher');
            $data = [
                'm' => $dispatcher->getModuleName(),
                'c' => $dispatcher->getControllerName(),
                'a' => $dispatcher->getActionName()
            ];
        }
        $i = strpos($arguments, ',');
        if ($i !== false) {
            $url = substr($arguments, 0, $i);
            $param = substr($arguments, $i + 1);
        } else {
            $url = $arguments;
        }
        if ($url == '' || $url == "''" || $url == '""') {
            $url = '$this->dispatcher->getModuleName()."/".$this->dispatcher->getControllerName()."/".$this->dispatcher->getActionName()';
            $param='$_GET';
        } else {
            $t = explode('/', $url);
            switch (count($t)) {
                case 2:
                    $url = '"' . $data['m'] . '/' . trim($url, '"\'') . '"';
                    break;
                case 1:
                    $url = '"' . $data['m'] . '/' . $data['c'] . '/' . trim($url, '"\'') . '"';
                    break;
            }
        }
        return "\Xin\Lib\Utils::url(" . $url . ($param ? ',' . $param : '') . ")";
    }

    private function _dataTag($arguments, $funcArguments)
    { 
        list($mod,$func)=explode('.',$funcArguments[0]['expr']['value']);
        //if(!$mod || !$func) return '';     
        $i=strpos($arguments,',');
        $args= $i===false ?'':substr($arguments,$i+1);
        $use=[];
        if(is_array($funcArguments[1]['expr']['left'])){
	        foreach($funcArguments[1]['expr']['left'] as $k=>$v){
	            switch($v['expr']['type']){
	                case 361:
	                    $use[]='$'.$v['expr']['left']['value'];
	                    break;
	                case 265:
	                    $use[]='$'.$v['expr']['value'];
	                    break;
	            }
	        }
        }
        $str="(function()".($use?' use ('.implode(',',$use).')':'')."{\$_mod=new \\Xin\\Module\\{$mod}\\DataTag();\$_mod->setDI(\$this->di);return \$_mod->{$func}(".$args.");})()";

        return $str;
    }

}