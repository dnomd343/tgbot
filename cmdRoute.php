<?php

require_once 'models/tgDC.php';
require_once 'models/ipInfo.php';
require_once 'models/cfopPic.php';
require_once 'models/kmsCheck.php';
require_once 'models/ntpCheck.php';

$cmds = array( // 命令列表
    'ip',
    'dc',
    'kms',
    'ntp',
    'cfop'
);

function route($cmd, $rawParam) { // 命令请求路由
    switch ($cmd) {
        case 'ip':
            ipInfo($rawParam);
            break;
        case 'dc':
            tgDC($rawParam);
            break;
        case 'kms':
            kmsCheck($rawParam);
            break;
        case 'ntp':
            ntpCheck($rawParam);
            break;
        case 'cfop':
            cfopPic($rawParam);
            break;
    }
}

function routeCallback($cmd, $rawParam) { // 回调请求路由
    switch ($cmd) {
        case 'ip':
            ipInfoCallback($rawParam);
            break;
        case 'kms':
            kmsCheckCallback($rawParam);
            break;
        case 'cfop':
            cfopPicCallback($rawParam);
            break;
    }
}

?>
