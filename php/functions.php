<?php

/**
 * if $arr[$key] == $value then echo $true else echo $false
 * @param $arr array
 * @param $key string
 * @param $value mixed
 * @param $true string
 * @param $false string Default ''
 */
function condOut($arr, $key, $value, $true, $false = '') {
    echo $arr[$key] == $value
        ? $true
        : $false;
}
