<?php
/**
 * 信息验证模型基础类
 * @author zuitu@foxmail.com
 * @date 2013.01.02
 */
class Validate {
    // 操作状态
    const EXISTS_VALIDATE       =   0;// 表单存在字段则验证
    const MUST_VALIDATE         =   1;// 必须验证    
    const VALUE_VALIDATE        =   2;// 表单值不为空则验证    

    // 最近错误信息
    protected $error            =   array();    
    protected $data             =   array();

    protected $options          =   array();
    protected $_validate        =   array();  // 验证定义
    protected $_map             =   array();  // 字段映射定义
    // 是否自动检测数据表字段信息
    protected $autoCheckFields  =   false;    
    // 是否批处理验证
    protected $patchValidate    =   true;

    /**
     * 架构函数
     * 字段检查
     * @access public
     */
    public function __construct($validate=array()) {
        // 模型初始化
        $this->_initialize();
        if($validate) {
            $this->_validate = array_merge($this->_validate, $validate);
        }
    }

    // 回调方法 初始化模型
    protected function _initialize() {}

    /**
     * 创建数据对象 但不保存到数据库
     * @access public
     * @param mixed $data 创建数据
     * @return mixed
     */
     public function create($data='') {
        // 如果没有传值默认取POST数据
        if(empty($data)) {
            $data   =   $_POST;
        }elseif(is_object($data)){
            $data   =   get_object_vars($data);
        }
        // 验证数据
        if(empty($data) || !is_array($data)) {
            $this->error = 'DATA_TYPE_INVALID';
            return false;
        }

        // 数据自动验证
        if(!$this->autoValidation($data)) return false;

        // 赋值当前数据对象
        $this->data =   $data;
        // 返回创建的数据以供其他调用
        return $data;
     }

    /**
     * 设置数据对象值
     * @access public
     * @param mixed $data 数据
     * @return Model
     */
    public function data($data='') {
        if('' === $data && !empty($this->data)) {
            return $this->data;
        }
        if(is_object($data)){
            $data   =   get_object_vars($data);
        }elseif(is_string($data)){
            parse_str($data,$data);
        }elseif(!is_array($data)){
            throw_exception('DATA_TYPE_INVALID');
        }
        $this->data = $data;
        return $this;
    }

    /**
     * 自动表单验证
     * @access protected
     * @param array $data 创建数据
     * @return boolean
     */
    protected function autoValidation($data) {
        if(!empty($this->options['validate'])) {
            $_validate   =   $this->options['validate'];
            unset($this->options['validate']);
        }elseif(!empty($this->_validate)){
            $_validate   =   $this->_validate;
        }        
        // 属性验证
        if(isset($_validate)) { // 如果设置了数据自动验证则进行数据验证
            foreach($_validate as $key=>$val) {
                // 验证因子定义格式
                // array(field,rule,message,condition,type,when,params)
                // 判断是否需要执行验证
                if(empty($val[5])) {
                    $val[3]  =  isset($val[3])?$val[3]:self::EXISTS_VALIDATE;
                    $val[4]  =  isset($val[4])?$val[4]:'regex';
                    // 判断验证条件
                    switch($val[3]) {
                        case self::MUST_VALIDATE:   // 必须验证 不管表单是否有设置该字段
                            if(false === $this->_validationField($data,$val)) 
                                return false;
                            break;
                        case self::VALUE_VALIDATE:    // 值不为空的时候才验证
                            if('' != trim($data[$val[0]]))
                                if(false === $this->_validationField($data,$val)) 
                                    return false;
                            break;
                        default:    // 默认表单存在该字段就验证
                            if(is_string($val[0]) && strpos($val[0], ',')) {
                                $keys = explode(',', $val[0]);
                                if(isset($data[$keys[0]]))
                                    if(false === $this->_validationField($data,$val))
                                        return false;
                            }                                                      
                            if(isset($data[$val[0]])) {
                                //die('a');
                                if(false === $this->_validationField($data,$val)) 
                                    return false;
                            }
                    }
                }
            }
            // 批量验证的时候最后返回错误
            if(!empty($this->error)) return false;
        }
        return true;
    }

    /**
     * 验证表单字段 支持批量验证
     * 如果批量验证返回错误的数组信息
     * @access protected
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function _validationField($data,$val) {
        if(false === $this->_validationFieldItem($data,$val)){
            if($this->patchValidate) {
                $this->error[$val[0]]   =   $val[2];
            }else{
                $this->error            =   $val[2];
                return false;
            }
        }
        return ;
    }

    /**
     * 根据验证因子验证字段
     * @access protected
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function _validationFieldItem($data,$val) {
        switch(strtolower(trim($val[4]))) {
            case 'function':// 使用函数进行验证
            case 'callback':// 调用方法进行验证
                $args = isset($val[6])?(array)$val[6]:array();
                if(is_string($val[0]) && strpos($val[0], ','))
                    $val[0] = explode(',', $val[0]);
                if(is_array($val[0])){
                    // 支持多个字段验证
                    foreach($val[0] as $field)
                        $_data[$field] = $data[$field];
                    array_unshift($args, $_data);
                }else{
                    array_unshift($args, $data[$val[0]]);
                }
                if('function'==$val[4]) {
                    return call_user_func_array($val[1], $args);
                }else{
                    return call_user_func_array(array(&$this, $val[1]), $args);
                }
            case 'confirm': // 验证两个字段是否相同
                return $data[$val[0]] == $data[$val[1]];
            case 'unique': // 验证某个值是否唯一
                if(is_string($val[0]) && strpos($val[0],','))
                    $val[0]  =  explode(',',$val[0]);
                $map = array();
                if(is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field)
                        $map[$field]   =  $data[$field];
                }else{
                    $map[$val[0]] = $data[$val[0]];
                }
                if(!empty($data[$this->getPk()])) { // 完善编辑的时候验证唯一
                    $map[$this->getPk()] = array('neq',$data[$this->getPk()]);
                }
                if($this->where($map)->find())   return false;
                return true;
            default:  // 检查附加规则
                return $this->check($data[$val[0]],$val[1],$val[4]);
        }
    }

    /**
     * 使用正则验证数据
     * @access public
     * @param string $value  要验证的数据
     * @param string $rule 验证规则
     * @return boolean
     */
    public function regex($value,$rule) {
        $validate = array(
            'require'   =>  '/.+/',
            'email'     =>  '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'url'       =>  '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(?:[\/\?#][\/=\?%\-&~`@[\]\':+!#\w]*)?$/',
            'currency'  =>  '/^\d+(\.\d+)?$/',
            'number'    =>  '/^\d+$/',
            'zip'       =>  '/^\d{6}$/',
            'integer'   =>  '/^[-\+]?\d+$/',
            'double'    =>  '/^[-\+]?\d+(\.\d+)?$/',
            'english'   =>  '/^[A-Za-z]+$/',
            'date'      =>  '/([0-9]{3}[1-9]|[0-9]{2}[1-9][0-9]{1}|[0-9]{1}[1-9][0-9]{2}|[1-9][0-9]{3})年(((0[13578]|1[02])月)|((0[469]|11)月)|(02月))/',
            'mobile'    =>  '/^1[345]\d{9}$|^18\d{9}$|^0\d{9,10}$/',
            'username'  =>  '/^[\x{4e00}-\x{9fa5}\x{f900}-\x{fa2d}_\w]+$/u',
        );
        // 检查是否有内置的正则表达式
        if(isset($validate[strtolower($rule)]))
            $rule       =   $validate[strtolower($rule)];
        return preg_match($rule,$value)===1;
    }

    /**
     * 返回错误信息
     * @access public
     * @return string
     */
    public function getError(){
        return $this->error;
    }

    /**
     * 验证数据 支持 in between equal length regex expire ip_allow ip_deny
     * @access public
     * @param string $value 验证数据
     * @param mixed $rule 验证表达式
     * @param string $type 验证方式 默认为正则验证
     * @return boolean
     */
    public function check($value,$rule,$type='regex'){
        switch(strtolower(trim($type))) {
            case 'in': // 验证是否在某个指定范围之内 逗号分隔字符串或者数组
                $range   = is_array($rule)?$rule:explode(',',$rule);
                return in_array($value ,$range);
            case 'between': // 验证是否在某个范围
                if (is_array($rule)){
                    $min    =    $rule[0];
                    $max    =    $rule[1];
                }else{
                    list($min,$max)   =  explode(',',$rule);
                }
                return $value>=$min && $value<=$max;
            case 'equal': // 验证是否等于某个值
                return $value == $rule;
            case 'length': // 验证长度
                $length  =  (strlen($value)+mb_strlen($value,'utf-8'))/2; // 当前数据长度
                if(strpos($rule,',')) { // 长度区间
                    list($min,$max)   =  explode(',',$rule);
                    return $length >= $min && $length <= $max;
                }else{// 指定长度
                    return $length == $rule;
                }
            case 'expire':
                list($start,$end)   =  explode(',',$rule);
                if(!is_numeric($start)) $start   =  strtotime($start);
                if(!is_numeric($end)) $end   =  strtotime($end);
                return $_SERVER['REQUEST_TIME'] >= $start && $_SERVER['REQUEST_TIME'] <= $end;
            case 'ip_allow': // IP 操作许可验证
                return in_array(get_client_ip(),explode(',',$rule));
            case 'ip_deny': // IP 操作禁止验证
                return !in_array(get_client_ip(),explode(',',$rule));
            case 'regex':
            default:    // 默认使用正则验证 可以使用验证类中定义的验证名称
                // 检查附加规则
                return $this->regex($value,$rule);
        }
    }
}
