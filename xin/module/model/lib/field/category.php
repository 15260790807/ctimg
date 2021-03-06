<?php
namespace Xin\Module\Model\Lib\Field;


class Category extends \Xin\Module\Model\Lib\FieldBase {
    public function getTitle(){
        return '分类';
    }

    protected function getColumn(){
        $size=$this->_settings['lengthMax'];
        return array(
            "type"    => 'int',
            "size"    => $size,
            "notNull" => true
        );
    }


    public function validateSetting(){
        if($this->_settings['lengthMin']<0){
            throw new \Exception('长度范围不允许为负数');
        }
        if(!$this->_settings['lengthMax'] || $this->_settings['lengthMax']>500){
            throw new \Exception('请设置长度范围的上限，最大不超过500');
        }
    }


}