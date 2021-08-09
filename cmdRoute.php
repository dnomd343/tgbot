<?php

$cmds = array( // 命令列表
    'test'
);

function route($cmd, $rawParam) { // 命令请求路由
    switch ($cmd) {
        case 'test':
            test($rawParam);
            break;
    }
}

function routeCallback($cmd, $rawParam) { // 回调请求路由
    switch ($cmd) {
        case 'test':
            testCallback($rawParam);
            break;
    }
}

?>
