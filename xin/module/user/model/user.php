<?php

namespace Xin\Module\User\Model;
use Xin\Lib\Utils;

class User extends \Xin\Lib\ModelBase
{
    const COLUMN_STATUS_INVAILD="invaild";
    
    const MODELID_MEMBER=3;
    
    public function initialize()
    {
        parent::initialize();
        $this->hasOne("group_id", "\Xin\Module\User\Model\UserGroup", "id", array('alias' => 'UserGroup'));    
        $this->hasMany("id", "\Xin\Module\User\Model\UserCollect", "user_id", array('alias' => 'UserCollect'));    
        $this->hasMany("id", "\Xin\Module\Product\Model\ProductOptCustItemToUser", "user_id", array('alias' => 'UserCust'));  
    }

    /**
     * @return Model
     */
    public function getExtends(){
        switch ($this->user_type){
            case self::TYPE_ADMIN:
                $this->hasOne('id','Xin\Module\User\Model\User','uid', array('alias' => 'UserAdmin'));
                return $this->getUserAdmin();
                break;
        }
    }

    /**
     * @return Model
     */
    public function getExtendModel(){
        switch ($this->user_type){
            case self::TYPE_ADMIN:
                return new UserAdmin();
                break;
        }
    }
}

