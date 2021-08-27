<?php

class tgReply { // Telegram消息待回复记录
    function match() { // 匹配用户回复
        $redis = new RedisCache('reply');
        $userId = $GLOBALS['tgEnv']['userId'];
        $reply = $redis->getData($userId); // 查询用户是否有待回复记录
        if (!$reply) { return null; } // 无记录返回null
        $redis->delData($userId);
        return json_decode($reply, true); // 返回待回复命令
    }

    function add($cmd) { // 添加待回复记录
        $redis = new RedisCache('reply');
        $redis->setData($GLOBALS['tgEnv']['userId'], json_encode( // 缓存记录
            array('cmd' => $cmd)
        ));
    }
}

?>