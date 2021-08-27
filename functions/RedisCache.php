<?php

class RedisCache {
    private $redisSetting = array(); // Redis接口参数

    public function __construct($prefix) { // 初始化
        global $env;
        $this->redisSetting = array(
            'host' => $env['REDIS_HOST'],
            'port' => $env['REDIS_PORT'],
            'passwd' => $env['REDIS_PASSWD'],
            'prefix' => $env['REDIS_PREFIX'] . '-' . $prefix . '-'
        );
    }
    
    public function getData($key) { // 查询Redis缓存 不存在返回NULL
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] !== '') {
            $redis->auth($this->redisSetting['passwd']); // 密码认证
        }
        $redisKey = $this->redisSetting['prefix'] . $key;
        $redisValue = $redis->exists($redisKey) ? $redis->get($redisKey) : NULL;
        return $redisValue;
    }
    
    public function setData($key, $data, $cacheTTL = 0) { // 写入Redis缓存 默认不过期
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] !== '') {
            $redis->auth($this->redisSetting['passwd']); // 密码认证
        }
        $redisKey = $this->redisSetting['prefix'] . $key;
        $status = $redis->set($redisKey, $data); // 写入数据库
        if ($cacheTTL > 0) {
            $redis->pexpire($redisKey, $cacheTTL * 1000); // 设置过期时间 单位 ms = s * 1000
        }
        return $status;
    }

    public function delData($key) { // 删除Redis缓存
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] !== '') {
            $redis->auth($this->redisSetting['passwd']); // 密码认证
        }
        $redisKey = $this->redisSetting['prefix'] . $key;
        return $redis->del($redisKey);
    }
}

?>
