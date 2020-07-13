<?php

//数据库类
class Mysql
{
    //将PDO对象保存至属性
    private $pdo;
    //将表名保存至属性
    private $table;
    //条件属性
    private $where;
    //字段属性
    private $fields = '*';
    //limit属性
    private $limit;
    //order属性
    private $order;
    //count属性
    private $count;


    //构造方法，自动PDO连接数据库
    public function __construct($table)
    {
        try {

            //引入配置文件
            $config = require('config.php');

            //连接MySQL数据库
            $pdo = new PDO('mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'], $config['DB_USER'], $config['DB_PASS']);

            //设置UTF8字符编码
            $pdo->query('SET NAMES UTF8');

            //保存PDO对象为属性
            $this->pdo = $pdo;

            //保存数据表名
            $this->table = '`' . $table . '`';

        } catch (PDOException $e) {
            //输出错误信息
            echo $e->getMessage();
        }
    }

    //内部自我实例化，静态方法
    public static function getMysql($table = '')
    {
        return new self($table);
    }

    //原生执行SQL 语句，返回准备对象
    public function query($sql)
    {
        //得到准备语句
        $stmt = $this->pdo->prepare($sql);

        //执行SQL语句
        $stmt->execute();

        //返回准备对象
        return $stmt;
    }

    //还可以返回PDO 对象，用于原生时的一些操作
    public function getPdo()
    {
        //返回PDO对象
        return $this->pdo;
    }

    //数据库新增操作
    public function add($data)
    {
        //得到字段数组
        $keys = array_keys($data);

        //将字段数组加上防止冲突的`符号
        foreach ($keys as $key => $value) {
            $keys[$key] = '`' . $value . '`';
        }

        //得到字段字符串
        $fields = implode(',', $keys);

        //给值字符串加上单引号
        foreach ($data as $key => $value) {
            $data[$key] = "'" . $value . "'";
        }

        //得到值字符串
        $values = implode(',', $data);


        //重组SQL语句
        $sql = "INSERT INTO $this->table ($fields) VALUES ($values)";

        //执行SQL语句
        $this->pdo->prepare($sql)->execute();

        //返回最新的ID值
        return $this->pdo->lastInsertId();
    }

    //数据库修改操作
    public function update($data)
    {

        //修改字符串
        $update = '';

        //得到剔除id 索引的数组
        unset($data['id']);

        //将字段数组加上防止冲突的`符号
        foreach ($data as $key => $value) {
            $update .= "`$key`='$value',";
        }

        //去掉最后的逗号
        $update = substr($update, 0, -1);

        //重组SQL语句
        $sql = "UPDATE $this->table SET $update $this->where";

//        echo $sql;

        //得到准备语句
        $stmt = $this->pdo->prepare($sql);

        //执行SQL语句
        $stmt->execute();

        //返回影响行数
        return $stmt->rowCount();

    }

    //数据库删除操作
    public function delete()
    {
        //重组SQL
        $sql = "DELETE FROM $this->table $this->where";

        //得到准备语句
        $stmt = $this->pdo->prepare($sql);

        //执行SQL语句
        $stmt->execute();

        //返回影响行数
        return $stmt->rowCount();
    }


    //单一查询方法
    public function find()
    {

        //重组SQL语句
        $sql = "SELECT $this->fields FROM $this->table $this->where LIMIT 1";

        //得到准备对象
        $stmt = $this->pdo->prepare($sql);

        //执行SQL语句
        $stmt->execute();

        //返回单一数据
        return $stmt->fetchObject();

    }

    //多查询方法
    public function select()
    {

        //重组SQL语句
        $sql = "SELECT $this->fields FROM $this->table $this->order $this->where $this->limit";

        //得到准备对象
        $stmt = $this->pdo->prepare($sql);

        //执行SQL语句
        $stmt->execute();

        //初始化列表对象
        $objects = [];

        //组装数据列表
        while ($rows = $stmt->fetchObject()) {
            $objects[] = $rows;
        }

        //将查询的记录数保存到count属性里
        $this->count = $stmt->rowCount();

        //返回数据对象数组
        return $objects;

    }

    //所有的where 条件都在这里
    public function where($param)
    {

        //使用指定条件判断
        $key = key($param);
        $value = current($param);

        //如果没有第二下标，则默认=
        $sign = empty($param[0]) ? '=' : $param[0];

        //合并成判断条件，保存到属性里去
        $this->where = "WHERE (`$key`$sign'$value')";

        //返回当前Mysql对象
        return $this;
    }

    //所有的字段设置都在这里
    public function fields($fields)
    {

        //给fields 加上`符号
        $fields = explode(',', $fields);

        //将字段数组加上防止冲突的`符号
        foreach ($fields as $key => $value) {
            $fields[$key] = "`$value`";
        }

        //得到值字符串
        $this->fields = implode(',', $fields);

        //Mysql对象
        return $this;
    }

    //所有的limit 设置都在这里
    public function limit($start, $end)
    {
        //得到Limit 字符串
        $this->limit = "LIMIT $start, $end";
        //返回当前Mysql 对象
        return $this;
    }

    //所有的order设置都在这里
    public function order($param)
    {

        //分割字符和排序关键字
        $order = explode(' ', $param);

        //组装排序SQL
        $this->order = "ORDER BY `$order[0]` ".strtoupper($order[1]);

        //返回Mysql对象
        return $this;
    }

    //返回count
    public function count()
    {
        //返回记录数
        return $this->count;
    }
}