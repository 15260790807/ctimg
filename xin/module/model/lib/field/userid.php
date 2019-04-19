<?php
namespace Xin\Module\Model\Lib\Field;


class Userid extends \Xin\Module\Model\Lib\FieldBase {
    public function getTitle(){
        return '用户id';
    }

    protected function getColumn(){
        $size=$this->_settings['lengthMax'];
        return array(
            "type"    => 'int',
            "size"    => $size,
            "notNull" => true
        );
    }

    public function getValue(){
        return \Phalcon\Di::getDefault()->get('auth')->getTicket()['uid'];
    }

    public function validateSetting(){
    }
}