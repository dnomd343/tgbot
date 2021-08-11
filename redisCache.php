<?php

class redisCache {
    private $redisSetting = array( // redis接口
        'host' => '127.0.0.1',
        'port' => 6379,
        'passwd' => ''
    );

    public function __construct($prefix) { // 类构建时指定前缀 
        $this->redisSetting['prefix'] = 'tgbot-' . $prefix . '-';
    }

    public function getData($key) { // 查询Redis缓存，不存在返回NULL
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] !== '') {
            $redis->auth($this->redisSetting['passwd']); // 密码认证
        }
        $redisKey = $this->redisSetting['prefix'] . $key;
        $redisValue = $redis->exists($redisKey) ? $redis->get($redisKey) : NULL;
        return $redisValue;
    }
    
    public function setData($key, $data, $cacheTTL = 600) { // 写入信息到Redis缓存 默认10min过期
        $redis = new Redis();
        $redis->connect($this->redisSetting['host'], $this->redisSetting['port']);
        if ($this->redisSetting['passwd'] !== '') {
            $redis->auth($this->redisSetting['passwd']); // 密码认证
        }
        $redisKey = $this->redisSetting['prefix'] . $key;
        $status = $redis->set($redisKey, $data); // 写入数据库
        $redis->pexpire($redisKey, $cacheTTL * 1000); // 设置过期时间 单位ms
        return $status;
    }
}

?>
