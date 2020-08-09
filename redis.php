<?php

//function linkRedis(){
//    $config = require('config.php');
//    $redis = new Redis();
//    $redis->connect( $config['REDIS_HOST'], 6379); // 连接 Redis
//    $redis->auth($config['REDIS_PASS']); // 密码验证
////    $redis->select(2);// 选择数据库 2
//    return $redis;
//}

class RedisSet {

    private  $redis;
    public function __construct()
    {
        $config = require('config.php');
        $this->redis = new Redis();
        $this->redis->connect( $config['REDIS_HOST'], 6379); // 连接 Redis
        $this->redis->auth($config['REDIS_PASS']); // 密码验证
    }

    public function setValue($key,$value,$expire = null) {
        if(!empty($expire)) return $this->redis->set($key,$value,$expire);
        else return $this->redis->set($key,$value);
    }

    public function getValue($key) {
        return $this->redis->get($key);
    }

}

