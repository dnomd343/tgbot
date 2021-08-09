<?php

require_once 'models/tgDC.php';

$cmds = array( // 命令列表
    'dc'
);

function route($cmd, $rawParam) { // 命令请求路由
    switch ($cmd) {
        case 'dc':
            tgDC($rawParam);
            break;
    }
}

function routeCallback($cmd, $rawParam) { // 回调请求路由
    switch ($cmd) {
        //
    }
}

?>
