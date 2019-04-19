<?php


namespace Xin\Module\Model\Model;
use \Phalcon\Db\Column as Column;
use Xin\Lib\ModelBase;
use Xin\Module\Model\Lib\Fields;

class ModelField extends ModelBase
{
    public $settings;
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo(
            'model_id',
            "\Xin\Module\Model\Model\Model",
            'id'
        );
    }

    protected function beforeValidationOnCreate(){
        if(self::count(['field=?0 and model_id=?1','bind'=>[$this->field,$this->model_id]])){
            throw new \Exception('已存在相同字段');
        }
    }
    public function beforeSave()
    { 
        is_array($this->settings) && $this->settings = json_encode($this->settings,JSON_UNESCAPED_UNICODE);
    }
    
    public function fireEvent($event)
    {
        switch($event){
            case 'afterFetch':
                $this->settings = json_decode($this->settings, 1);
                break;
            case 'afterCreate':
                $this->afterCreate();
                break;
        }
    }

    protected function afterCreate(){
        
    }

    /**
     * 返回字段类
     * @return \Xin\Module\Model\Lib\FieldBase
     */
    public function toField(){
        if($field= Fields::loadField($this->formtype,$this->field)){
            $field->setSettings($this->settings);
            $field->setFieldTitle($this->title);
            return $field;
        }
    }

}
