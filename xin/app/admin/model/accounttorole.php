<?php
namespace Xin\App\Admin\Model;
use Xin\Lib\ModelBase;

class AccountToRole extends ModelBase
{
	public function initialize()
    {
        parent::initialize();
        $this->belongsTo("user_id", "Xin\App\Admin\Model\Account", "id", array('alias' => 'Account'));
        $this->belongsTo("role_id", "Xin\App\Admin\Model\Role", "id", array('alias' => 'Role'));
    }
}
