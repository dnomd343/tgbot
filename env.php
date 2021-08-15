<?php

function loadEnv($filename) { // 读取环境变量文件
    $file = fopen($filename, 'r');
    $data = array();
    while (!feof($file)) { // 逐行读入文件
        $raw = trim(fgets($file));
        if ($raw == '') { continue; } // 跳过空行
        if (substr($raw, 0, 1) === '#') { continue; } // 跳过注释
        $record = explode('=', $raw);
        if (count($record) === 2) {
            $data[$record[0]] = $record[1]; // 合法记录
        }
    }
    fclose($file);
    return $data;
}

?>