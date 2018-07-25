<?php
/**
 * Created by PhpStorm.
 * User: zhengjiayuan
 * Date: 2018/7/14
 * Time: 19:41
 */
class PdoMySQL{
    public static $config = array();    // 设置连接参数,配置信息
    public static $link = null;     // 保存连接标识符
    public static $pconnect = false; // 是否开启长连接
    public static $dbVersion = null; // 保存数据库版本
    public static $connected = false; // 是否连接成功
    public static $PDOStatement = null; // 保存PDOStatement对象
    public static $queryStr = null; // 保存最后执行的操作
    public static $error = null; // 报错错误信息
    public static $lastInserted = null; // 保存上一步插入操作产生AUTO_INCREMENT
    public static $numRows = 0; // 上一步操作产生受影响的记录的条数

    /**
     * 连接PDO
     * PdoMySQL constructor.
     * @param string $dbConfig
     */
    public function __construct($dbConfig=''){
        // 检查是否启动相关配置
        /*if (class_exists("PDO")){
            self::throw_exception('不支持PDO,请先开启');
        }*/
        // 当参数没有设置相关配置，则使用默认配置
        if (!is_array($dbConfig)){
            $dbConfig=array(
                'hostname'=>DB_HOST,
                'username'=>DB_USER,
                'password'=>DB_PWD,
                'database'=>DB_NAME,
                'hostport'=>DB_PORT,
                'dbms'=>DB_TYPE,
                'dsn'=>DB_TYPE.":host=".DB_HOST.";dbname=".DB_NAME
            );
        }
        if (empty($dbConfig['hostname']))
            self::throw_exception('没有定义数据库配置,请先定义');
        self::$config=$dbConfig;
        // 连接options参数
        if (empty(self::$config['params']))
            self::$config['params'] = array();
        if (!isset(self::$link)){
            // 当没有连接标识时
            $configs = self::$config;
            if (self::$pconnect){
                // 开启长连接，添加到配置数组中
                $configs['params'][constant("PDO::ATTR_PERSISTENT")]=true;
            }
            // 创建连接
            try{
                self::$link = new PDO($configs['dsn'],$configs['username'],$configs['password'],$configs['params']);
            }catch (PDOException $e){
                self::throw_exception($e->getMessage());
            }
            // 当连接失败输出错误
            if (!self::$link){
                self::throw_exception('PDO连接错误');
                return false;
            }
            // 设置数据库自字符集
            self::$link->exec('SET NAMES '.DB_CHARSET);
            // 获取服务器版本属性
            self::$dbVersion=self::$link->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
            // 将连接状态设置为真
            self::$connected=true;
            // 释放配置资源
            unset($configs);
        }

    }

    /**
     * 得到所有记录
     * @param null $sql
     * @return mixed
     */
    public static function getAll($sql=null){
        if ($sql!=null){
            // 查询sql存在时
            self::query($sql);
        }
        // 获取关联结果集
        $result = self::$PDOStatement->fetchAll(constant("PDO::FETCH_ASSOC"));
        return $result;
    }

    /**
     * 得到结果一条记录
     * @param null $sql
     * @return mixed
     */
    public static function getRow($sql=null){
        if ($sql!=null){
            // 查询sql存在时
            self::query();
        }
        $result = self::$PDOStatement->fetch(constant("PDO::FETCH_ASSOC"));
        return $result;
    }

    /**
     * array (
     *  'username' => 'imooc',
     *  'password' => 'imooc'
     * )
     * insert user(`ùsername`,`password`)
     * values('aa','aa' );
     * @param $data
     * @param $table
     * @return bool|int
     */
    public static function add($data,$table){
        // 取出键名 username,passowrd 数组
        $keys = array_keys($data);
        // 加上反引号 `ùsername` `password`
        array_walk($keys,array('PdoMySQL','addSpecialChar'));
        // 数组间 加上 逗号
        $fieldsStr = join(',',$keys);
        // 值变成这样 'aa','aa'
        $values = "'".join("','",array_values($data))."'";
        $sql = "insert {$table}({$fieldsStr}) values({$values})";
        return self::execute($sql);
    }

    /**
     * array (
     *  'username' => 'imooc',
     *  'password' => 'imooc'
     * )
     * @param $data
     * @param $table
     * @param null $where
     * @param null $order
     * @param int $limit
     * @return bool|int
     */
    public static function update($data,$table,$where=null,$order=null,$limit=0){
        $sets = '';
        // 取出键值对 成字符串 username='imooc',password='imooc',
        foreach ($data as $key=>$val){
            $sets .= $key. "='" .$val."',";
        }
        // 去除字符串最后一个逗号
        $sets = rtrim($sets,',');
        $sql = "update {$table} set {$sets} ".self::parseWhere($where)
            .self::parseOrder($order)
            .self::parseLimit($limit);
        return self::execute($sql);
    }

    /**
     * 删除记录操作
     * @param $table
     * @param null $where
     * @param null $order
     * @param int $limit
     * @return bool|int
     */
    public static function delete($table,$where=null,$order=null,$limit=0){
        $sql="delete from {$table} ".self::parseWhere($where).self::parseOrder($order).self::parseLimit($limit);
        return self::execute($sql);
    }

    /**
     * 得到最后执行的sql语句
     * @return bool|null
     */
    public static function getLastSql(){
        $link = self::$link;
        if (!$link) return false;
        return self::$queryStr;
    }

    /**
     * 得到上一步插入操作产生AUTO_INCREMENT
     * @return bool|null
     */
    public static function getLastInsertId(){
        $link = self::$link;
        if (!$link) return false;
        return self::$lastInserted;
    }

    /**
     * 得到数据库中数据库表
     * @return array
     */
    public static function showTable(){
        $tables = array();
        if (self::query("show tables")){
            $result = self::getAll();
            foreach ($result as $key=>$val){
                // current() 函数返回数组中的当前元素的值
                $tables[$key]=current($val);
            }
        }
        return $tables;
    }

    /**
     *根据主键查找记录
     * @param $tabName
     * @param $priId
     * @param string $fileds
     * @return mixed
     */
    public static function findById($tabName,$priId,$fields='*'){
        $sql = 'select %s from %s where id = %d';
        return self::getRow(sprintf($sql,self::parseFields($fields),$tabName,$priId));
    }

    /**
     * 执行普通查询
     * @param $tables
     * @param null $where
     * @param string $fields
     * @param null $group
     * @param null $having
     * @param null $order
     * @param null $limit
     * @return mixed
     */
    public static function find($tables,$where=null,$fields='*',$group=null,$having=null,$order=null,$limit=null){
        $sql = 'select '.self::parseFields($fields).' from '.$tables
            .self::parseWhere($where)
            .self::parseGroup($group)
            .self::parseHaving($order)
            .self::parseOrder($order)
            .self::parseLimit($limit);
        $dataAll = self::getAll($sql);
        return count($dataAll) == 1 ? $dataAll[0]:$dataAll;
    }

    /**
     * 解析where条件
     * @param $where
     * @return string
     */
    public static function parseWhere($where){
        $whereStr='';
        if (is_string($where)&&!empty($where)){
            $whereStr = $where;
        }
        return empty($whereStr)?'' : ' where '.$whereStr;
    }

    /**
     * 解析 group by
     * @param $group
     * @return string
     */
    public static function parseGroup($group){
        $groupStr = '';
        if (is_array($group)){
            // 为数组时 在条件中间加逗号
            $groupStr .= ' group by '.implode(',',$group);
        }elseif (is_string($group)&&!empty($group)){
            // 为字符串时，说明字符串中间有逗号
            $groupStr .= ' group by '.$group;
        }
        return empty($groupStr)?'':$groupStr;
    }

    /**
     * 对分组结果通过having字句进行二次筛选
     * @param $having
     * @return string
     */
    public static  function  parseHaving($having){
        $havingStr = '';
        if (is_string($having) && !empty($having)){
            $havingStr = ' having '.$having;
        }
        return $havingStr;
    }


    /**
     * 解析order by
     * @param $order
     * @return string
     */
    public static function parseOrder($order){
        $orderStr = '';
        if (is_array($order)){
            // 为数组时 在条件中间加逗号
            $orderStr .= ' order by '.join(',', $order);
        }elseif (is_string($order) && !empty($order)){
            $orderStr .= ' order by '.$order;
        }
        return $orderStr;
    }

    /**
     * 解析限制显示条数limit
     * @param $limit
     * @return string
     */
    public static function parseLimit($limit){
        $limitStr = '';
        if (is_array($limit)){
            if (count($limit) > 1){
                // 数组 (1,3)
                $limitStr .= ' limit '.$limit[0].','.$limit[1];
            }else{
                // 数组 (3)
                $limitStr .= ' limit '.$limit[0];
            }
        }elseif (is_string($limit)&& !empty($limit)){
            $limitStr .= ' limit '.$limit;
        }
        return $limitStr;
    }

    /**
     * 解析字段
     * @param $fields
     * @return string
     */
    public static function parseFields($fields){
        if (is_array($fields)){
            // 为数组时 数组中每个值 加 反引号
            array_walk($fields,array('PdoMySQL','addSpecialChar'));
            // 数组每个中间加入 , 返回一个string
            $fieldsStr=implode(',',$fields);
        }elseif (is_string($fields) && !empty($fields)){
            if (strpos($fields,'`') === false){
                // 用 === 是可能 ` 在第一个位置
                // 字符串打散为数组
                $fields = explode(',',$fields);
                array_walk($fields,array('PdoMySQL','addSpecialChar'));
                $fieldsStr=implode(',',$fields);
            }else{
                $fieldsStr = $fields;
            }
        }else{
            $fieldsStr='*';
        }
        return $fieldsStr;
    }

    /**
     * 通过反引号使用字段,
     * @param $value
     * @return string
     */
    public static function addSpecialChar(&$value){
        if ($value === '*' || strpos($value,'.')!==false||strpos($value,'`')!==false){
            // 不做处理
        }elseif (strpos($value,'`')===false){
            $value='`'.trim($value).'`';
        }
        return $value;
    }
    /**
     * 执行增 删 改 成功返回 影响条数 失败返回 假 输出错误信息
     * @param null $sql
     * @return bool|int
     */
    public static function execute($sql=null){
        $link=self::$link;
        if (!$link) return false; // 连接标识不存在 直接返回
        self::$queryStr = $sql;
        if (!empty(self::$PDOStatement)) self::free(); // 结果集不为空 释放结果集
        $result = $link->exec(self::$queryStr); // 成功返回 真 失败返回 假
        self::haveErrorThrowException(); // 有错误时 会输出相关错误
        if ($result){
            self::$lastInserted = $link->lastInsertId();
            self::$numRows = $result;
            return self::$numRows;
        }else
            return false;
    }


    /**
     * 释放结果集
     */
    public static function free(){
        self::$PDOStatement = null;
    }

    public static function query($sql=''){
        $link=self::$link;
        if (!$link) return false; // 连接标识不存在 直接返回
        if (!empty(self::$PDOStatement)) self::free(); // 结果集不为空 释放结果集
        self::$queryStr = $sql;
        // 创建PDOStatement对象
        self::$PDOStatement = $link->prepare(self::$queryStr);
        // 执行得到是否成功
        $res = self::$PDOStatement->execute();
        self::haveErrorThrowException();
        return $res;
    }

    public static function haveErrorThrowException(){
        $obj = empty(self::$PDOStatement)?self::$link : self::$PDOStatement;
        $arrError = $obj->errorInfo();
        // print_r($arrError);
        if ($arrError[0] != '00000'){
            self::$error = 'SQLSTATE: '.$arrError[0].'SQL Error: '.$arrError[2].'<br/>Error SQL: '.self::$queryStr;
            self::throw_exception(self::$error);
            return false;
        }
        if (self::$queryStr==''){
            self::throw_exception('没有执行SQL语句');
            return false;
        }
    }

    /**
     * 自定义错误处理
     * @param $errMsg
     */
    public static function throw_exception($errMsg){
        echo '<div style="width: 80%;background-color: #ABCDEF;color: black; font-size: 20px;padding: 20px 0px;">
        '.$errMsg.'
</div>';
    }

    /**
     * 销毁连接对象，关闭数据库
     */
    public static function close(){
        self::$link=null;
    }
}