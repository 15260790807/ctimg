<?php

namespace Xin\App\Admin\Model;

use Xin\Lib\ModelBase;

class Account extends ModelBase
{
    public $settings;
    public function initialize()
    {
        parent::initialize();
        $this->hasOne("uid", "\Xin\Module\User\Model\User", "id", array('alias' => 'User'));
        $this->hasMany("uid", "\Xin\App\Admin\Model\AccountToRole", "user_id", array('alias' => 'AccountToRole'));
        $this->hasManyToMany(
            "uid",
            "Xin\App\Admin\Model\AccountToRole",
            "user_id",
            "role_id",
            "Xin\App\Admin\Model\Role",
            "id",
            array('alias' => 'Role')
        );
    }


    public function fireEvent($event)
    {
        switch($event){
            case 'afterFetch':
                $this->settings = json_decode($this->settings, 1);
                break;
            case 'beforeSave':
                is_array($this->settings) && $this->settings = json_encode($this->settings,JSON_UNESCAPED_UNICODE);
                break;
        }
    }
}
