<?php
namespace Xin\Module\Model\Lib;

use Xin\Module\Model\Model\ModelT;


abstract class FieldBase extends \Phalcon\Di\Injectable
{
    protected $_settings = [], $_name, $_value, $_title, $_disableFetchForm = false;

    /**
     * @param string $name 字段名称
     * @param array $settings 设定参数及值
     */
    public function __construct($name = null)
    {
        $this->_name = $name;
    }

    public function getAttr()
    {
        return [];
    }


    public abstract function getTitle();

    /**
     * 验证配置项是否正确，不通过抛出异常
     * @return void
     */
    protected abstract function validateSetting();

    public function getSettings()
    {
        return $this->_settings;
    }
    public function setSettings($val)
    {
        $this->_settings = $val;
    }
    public function getFieldName()
    {
        return $this->_name;
    }
    public function getValue()
    {
        if ($this->_value) {
            return $this->_value;
        }
        if (!$this->_disableFetchForm && $val = $_POST[$this->getFieldName()]) {
            return $val;
        }
        return $this->_settings['defaultValue'];
    }
    public function setValue($val)
    {
        $this->_value = $val;
    }
    public function getFieldTitle()
    {
        return $this->_title;
    }
    public function setFieldTitle($val)
    {
        $this->_title = $val;
    }
    public function disableFetchForm($val)
    {
        $this->_disableFetchForm = $val;
    }
    public function getFieldType()
    {
        return strtolower(basename(str_replace('\\', '/', get_class($this))));
    }

    /**
     * 输出配置表单
     */
    public function renderSetting()
    {
        $view = \Phalcon\Di::getDefault()->get('view');
        $dirs = $view->getViewsDir();
        $tpl = XIN_DIR . 'module/model/view/model/field/';
        if (!in_array($tpl, $dirs)) {
            $dirs[] = $tpl;
            $view->setViewsDir($dirs);
        }
        $view->partial($this->getFieldType() . '/setting', ['settings' => $this->_settings]);
    }
    /**
     * 输出配置表单
     */
    public function renderForm()
    {
        $view = \Phalcon\Di::getDefault()->get('view');
        $dirs = $view->getViewsDir();
        $tpl = XIN_DIR . 'module/model/view/model/field/';
        if (!in_array($tpl, $dirs)) {
            $dirs[] = $tpl;
            $view->setViewsDir($dirs);
        }
        $view->partial($this->getFieldType() . '/form', ['settings' => $this->_settings, 'value' => $this->_value, 'name' => $this->_name]);
    }



    /**
     * 获取该字段存储于数据库的格式
     * @return array
     */
    protected function getDbColumn()
    {
        $col = $this->getColumn();
        $types = array(
            'varchar' => \Phalcon\Db\Column::TYPE_VARCHAR
        );
        if (array_key_exists($col['type'], $types)) {
            $col['type'] = $types[$col['type']];
        }
        return $col;
    }

    /**
     * 获取该字段逻辑格式
     * @return array()
     */
    protected function getColumn()
    {
        $size = $this->_settings['lengthMax'];
        return array(
            "type" => 'varchar',
            "size" => $size,
            "notNull" => true
        );
    }

    public function applyTo(ModelT $model)
    {
        return true;
    }
}
