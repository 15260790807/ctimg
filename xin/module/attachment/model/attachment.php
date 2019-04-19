<?php

namespace Xin\Module\Attachment\Model;
use Xin\Lib\ModelBase;
use Phalcon\Mvc\Model\Behavior\SoftDelete;

class Attachment extends ModelBase
{
    protected $_module='attachment';

    public function initialize()
    {
        parent::initialize();
        $this->addBehavior(
            new SoftDelete(
                [
                    'field' => 'status',
                    'value' => self::COLUMN_STATUS_DELETE,
                ]
            )
        );
    }

    public static function findHash($hash){
        $di=\Phalcon\Di::getDefault();
        $id=intval($di->get('crypt')->decryptBase64($hash));
        return self::findFirstById($id);
    }
    public function getHash(){
        return $this->di->get('crypt')->encryptBase64(str_pad($this->id,11,'0',STR_PAD_LEFT));
    }
    public function getUrl(){
        return $this->di->get('config')['module'][$this->_module]['uploadUriPrefix'].$this->path;
    }
}
