<?php

require_once 'models/tgDC.php';
require_once 'models/kmsCheck.php';

$cmds = array( // 命令列表
    'dc',
    'kms'
);

function route($cmd, $rawParam) { // 命令请求路由
    switch ($cmd) {
        case 'dc':
            tgDC($rawParam);
            break;
        case 'kms':
            kmsCheck($rawParam);
            break;
    }
}

function routeCallback($cmd, $rawParam) { // 回调请求路由
    switch ($cmd) {
        //
    }
}

?>
