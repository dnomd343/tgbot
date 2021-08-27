<?php

require_once 'models/tgDC.php';
require_once 'models/ipInfo.php';
require_once 'models/cfopPic.php';
require_once 'models/kmsCheck.php';
require_once 'models/ntpCheck.php';
require_once 'models/icpQuery.php';
require_once 'models/tldQuery.php';
require_once 'models/punycode.php';
require_once 'models/whoisQuery.php';

function cmdRoute($cmd) { // 命令功能模块路由
    switch ($cmd) {
        case '/ip':
            return (new ipInfoEntry);
        case '/dc':
            return (new tgDCEntry);
        case '/kms':
            return (new kmsCheckEntry);
        case '/ntp':
            return (new ntpCheckEntry);
        case '/icp':
            return (new icpQueryEntry);
        case '/tld':
            return (new tldQueryEntry);
        case '/cfop':
            return (new cfopPicEntry);
        case '/whois':
            return (new whoisQueryEntry);
        case '/punyc':
        case '/punycode':
            return (new punycodeEntry);
    }
    return null; // 命令不存在
}

function route($message) { // 请求路由
    global $tgEnv, $botAccount;
    $message = trim($message); // 去除前后空字符
    if (!$tgEnv['isGroup']) { // 当前为私聊模式
        $reply = tgReply::match();
        if ($reply !== null) { // 有待回复记录
            $message = $reply['cmd'] . ' ' . $message;
        }
    }
    if (strpos($message, '/') !== 0) { return; } // 命令必须以 / 开头
    $temp = explode(' ', $message);
    $cmd = $temp[0];
    if (count($temp) === 1) { // 命令无参数
        $rawParam = '';
    } else { // 命令带有参数
        unset($temp[0]);
        $rawParam = implode(' ', $temp); // 获得参数
    }
    if ($tgEnv['isGroup']) { // 当前为群组
        if (substr($cmd, -strlen($botAccount) - 1) === '@' . $botAccount) {
            $cmd = substr($cmd, 0, strlen($cmd) - strlen($botAccount) - 1); // 分离@机器人
        }
    }
    $rawParam = trim($rawParam);
    $entry = cmdRoute($cmd); // 获取功能模块入口
    if (!$entry) { return; } // 命令不存在
    if ($tgEnv['isCallback']) {
        $entry->callback($rawParam); // 回调请求
    } else {
        $entry->query($rawParam); // 普通请求
    }
}

?>
