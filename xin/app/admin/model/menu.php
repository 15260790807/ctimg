<?php

namespace Xin\App\Admin\Model;

use Xin\Lib\ModelBase;

use \Phalcon\Validation;
use \Phalcon\Validation\Validator\PresenceOf;
use \Phalcon\Validation\Validator\Regex;
use \Phalcon\Validation\Validator\Numericality;

class Menu extends ModelBase
{
    public $settings;
    public function validation()
    {
        if ($this->getOperationMade() == self::OP_CREATE) {

            $validator = new Validation();
            $validator->add('title', new PresenceOf(array(
                "message" => 'The title is required'
            )));
            
            $validator->add('parentid', new Numericality());
            $validator->add('listorder', new Numericality());
            return $this->validate($validator);
        }
    }

    public function beforeSave()
    {
        if ($this->getOperationMade() == self::OP_DELETE) return;
        $this->isshow = $this->isshow ? 1 : 0;
        is_array($this->settings) && $this->settings = json_encode($this->settings,JSON_UNESCAPED_UNICODE);
    }
 
    public function fireEvent($event)
    {
        switch($event){
            case 'afterFetch':
                $this->settings = $this->settings ? json_decode($this->settings, 1) :[];
                break;
        }
    }

}
