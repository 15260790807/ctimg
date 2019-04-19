<?php
namespace Xin\Lib;



class Mysql extends \Phalcon\Db\Adapter\Pdo\Mysql{
    public function getSQLStatement()
	{
		return $this->_sqlStatement.($this->_sqlVariables?' VAR='.\json_encode($this->_sqlVariables):'');
	}
}