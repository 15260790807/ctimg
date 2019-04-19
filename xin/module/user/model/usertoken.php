<?php
namespace Xin\Module\User\Model;

class UserToken extends \Xin\Lib\ModelBase
{
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('uid', '\Xin\Model\User', 'id', array(
            'alias' => 'user'
        ));
    }

}
