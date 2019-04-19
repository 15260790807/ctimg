<?php

namespace Xin\App\Admin\Model;

use Xin\Lib\ModelBase;

class Access extends ModelBase
{
    const COLUMN_OBJECTTYPE_ROLE="role";
    const COLUMN_OBJECTTYPE_USER="user";
    
    const COLUMN_ACCESSTYPE_PAGE="page";
    const COLUMN_ACCESSTYPE_DATA="data";
    const COLUMN_ACCESSTYPE_ACTION="action";
}
