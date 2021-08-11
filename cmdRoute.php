<?php

require_once 'models/tgDC.php';
require_once 'models/ipInfo.php';
require_once 'models/cfopPic.php';
require_once 'models/kmsCheck.php';
require_once 'models/ntpCheck.php';
require_once 'models/whoisQuery.php';

$cmds = array( // 命令列表
    'ip',
    'dc',
    'kms',
    'ntp',
    'cfop',
    'whois'
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
            ntpCheck($rawParam);
            break;
        case 'cfop':
            cfopPic($rawParam);
            break;
        case 'whois':
            whoisQuery($rawParam);
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
