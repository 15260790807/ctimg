<?php
namespace Xin\Module\Model\Lib;

class Fields extends \Phalcon\Di\Injectable implements \ArrayAccess, \Iterator
{
    protected $_fields = [];
    private $posistion = 0;

    /**
     * 获取支持的所有字段控件对象清单
     * @return array
     */
    public static function getFieldTypes()
    {
        static $list;
        if (!isset($list)) {
            $list = array();
            $path = __DIR__ . '/field/';
            $ns = static::class;
            $ns = substr($ns, 0, strrpos($ns, '\\')) . '\\field\\';
            foreach (scandir($path) as $file) {
                if ($file == '.' || $file == '..' || is_dir($path . $file)) continue;
                $class = substr($file, 0, strpos($file, '.'));
                $classFullPath = $ns . $class;
                $o = new $classFullPath();
                $list[$class] = $o->getTitle();
            }
        }
        return $list;
    }

    /**
     * 获取指定类型的字段控件的实例
     * @param string $fieldType
     * @param string $name
     * @return Xin\Module\Model\Lib\FieldBase
     */
    public static function loadField($fieldType, $name=null)
    {
        if (!$fieldType) return false;
        $ns = static::class;
        $ns = substr($ns, 0, strrpos($ns, '\\')) . '\\field\\';
        $classFullPath = $ns . $fieldType;
        if (class_exists($classFullPath)) {
            $field= new $classFullPath($name);
            return $field;
        }else{
            return false;
        } 
    }

    public function addField($field)
    {
        $this->_fields[$field->getFieldName()] =$field;
    }

    public function validate(){
        foreach($this->_fields as $item){
            $item->validateForm();
        }
    }
    public function getValues(){
        $values=[];
        foreach($this->_fields as $item){
            $values[$item->getFieldName()]=$item->getValue();
        }
        return $values;
    }


    public function offsetSet($offset, $value)
    {
        $this->_fields[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_fields);
    }

    public function offsetUnset($offset)
    {
        unset($this->_fields[$offset]);
    }

    public function offsetGet($offset)
    {
        return array_key_exists($offset, $this->_fields) ? $this->_fields[$offset] : null;
    }

    public function rewind()
    {
        reset($this->_fields);
    }
    public function current()
    {
        return current($this->_fields);
    }
    public function key()
    {
        return key($this->_fields);
    }
    public function next()
    {
        next($this->_fields);
    }
    public function valid()
    {
        return $this->key() !== null;
    }

}
