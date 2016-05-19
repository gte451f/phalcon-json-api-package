<?php

/**
 * store low level functions that are essential to system operation
 * TODO: Make these services or libraries in DI?
 */


/**
 * logic used to auto load the correct config based on environment
 *
 * @return array
 */
function array_merge_recursive_replace()
{
    $arrays = func_get_args();
    $base = array_shift($arrays);

    foreach ($arrays as $array) {
        reset($base);
        while (list ($key, $value) = @each($array)) {

            if (is_array($value) && isset($base[$key]) && @is_array($base[$key])) {
                $base[$key] = array_merge_recursive_replace($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
    }
    return $base;
}