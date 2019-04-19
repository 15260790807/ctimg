<?php

namespace Xin\Module\Model\Model;

use \Phalcon\Db\Column as Column;
use Xin\Lib\ModelBase;

class Model extends ModelBase
{
    const COLUMN_KIND_DOC='document';
    const COLUMN_KIND_INDEPEND='independ';
    const COLUMN_KIND_USER='user';

    public $settings;

    public function initialize()
    {
        parent::initialize();
        $this->allowEmptyStringValues(array('settings'));
        $this->hasMany("id", "\Xin\Module\Model\Model\ModelField", "model_id", array('alias' => 'ModelField'));
    }

    protected function beforeValidationOnCreate()
    {
        $this->name=trim(strtolower($this->name));
        switch($this->kind){
            case self::COLUMN_KIND_DOC:
                $this->main_table='document';
                $this->extand_table='document_'.$this->name;
                break;
            case self::COLUMN_KIND_INDEPEND:
                $this->main_table=$this->name;
                $this->extand_table= $this->useext?$this->main_table.'_data':'';
                break;
            case self::COLUMN_KIND_USER:
                $this->main_table='user';
                $this->extand_table='user_'.$this->name;
                break;
            default:
                throw new \InvalidArgumentException('无效的模型类型');
        }        
        $this->useext=null;
        
        if (self::count(['main_table=?0 and extand_table=?1', 'bind' => [$this->main_table, $this->extand_table]])) {
            throw new \Exception('模型表已被使用');
            return;
        }
    }
    protected function setSettings($val)
    {
        $this->settings = is_array($val) ?json_encode($val,JSON_UNESCAPED_UNICODE):'{}';
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
            case 'afterCreate':
                $this->afterCreate();
                break;
        }
    }

    protected function afterCreate()
    {
        $modelT = new ModelT();
        $modelT->setModelId($this->id);
        $conn = $this->getWriteConnection();
        if($this->kind==self::COLUMN_KIND_INDEPEND){
            $conn->createTable(
                $modelT->getSource(),
                null,
                array(
                    "columns" => array(
                        new Column("id", [
                            "type" => Column::TYPE_INTEGER,
                            "size" => 10,
                            "notNull" => true,
                            "autoIncrement" => true,
                            'primary' => true
                        ]),
                        new Column("create_time", [
                            "type" => Column::TYPE_INTEGER,
                            "size" => 10,
                            "notNull" => true,
                            "autoIncrement" => false,
                            'primary' => false,
                            'default' => 0
                        ]),
                        new Column("update_time", [
                            "type" => Column::TYPE_INTEGER,
                            "size" => 10,
                            "notNull" => true,
                            "autoIncrement" => false,
                            'primary' => false,
                            'default' => 0
                        ]),
                        new Column("delete_time", [
                            "type" => Column::TYPE_INTEGER,
                            "size" => 10,
                            "notNull" => true,
                            "autoIncrement" => false,
                            'primary' => false,
                            'default' => 0
                        ]),
                        new Column("status", [
                            "type" => Column::TYPE_INTEGER,
                            "size" => 10,
                            "notNull" => true,
                            "autoIncrement" => false,
                            'primary' => false,
                            'default' => 0
                        ])
                    )
                )
            );
        }
        if ($this->extand_table) {
            $modelT->switchExtandTable(1);
            
            if($this->kind==self::COLUMN_KIND_DOC){
                $columns=[
                    new Column("document_id",[
                        "type" => Column::TYPE_INTEGER,
                        "size" => 10,
                        "notNull" => true,
                        'primary' => true
                    ]),
                    new Column("content",[
                        "type" => Column::TYPE_TEXT
                    ])
                ];
            }else{
                $columns=[
                    new Column($this->name."_id",[
                        "type" => Column::TYPE_INTEGER,
                        "size" => 10,
                        "notNull" => true,
                        'primary' => true
                    ])
                ];
            }
            $conn->createTable(
                $modelT->getSource(),
                null,
                array(
                    "columns" => $columns
                )
            );
        }
    }
}
