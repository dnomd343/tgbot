<?php

require_once 'models/tgDC.php';
require_once 'models/ipInfo.php';
require_once 'models/cfopPic.php';
require_once 'models/kmsCheck.php';
require_once 'models/ntpCheck.php';
require_once 'models/punycode.php';
require_once 'models/whoisQuery.php';

$cmds = array( // 命令列表
    'ip',
    'dc',
    'kms',
    'ntp',
    'cfop',
    'whois',
    'punycode'
);

function route($cmd, $rawParam) { // 命令请求路由
    switch ($cmd) {
        case 'ip':
            $entry = new ipInfoEntry;
            break;
        case 'dc':
            $entry = new tgDCEntry;
            break;
        case 'kms':
            $entry = new kmsCheckEntry;
            break;
        case 'ntp':
            $entry = new ntpCheckEntry;
            break;
        case 'cfop':
            $entry = new cfopPicEntry;
            break;
        case 'whois':
            $entry = new whoisQueryEntry;
            break;
        case 'punycode':
            $entry = new punycodeEntry;
            break;
    }
    if ($entry) {
        global $tgEnv;
        if (!$tgEnv['isCallback']) {
            $entry->query($rawParam);
        } else {
            $entry->callback($rawParam);
        }
    }
}

?>
