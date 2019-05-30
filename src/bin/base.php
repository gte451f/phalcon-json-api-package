<?php

/**
 * store low level functions that are essential to system operation
 * TODO: Make these services or libraries in DI?
 */

// record MODEs that define when rules should be applied
// Rules system depends on these values
CONST CREATERULES = 1;
CONST READRULES = 2;
CONST UPDATERULES = 4;
CONST DELETERULES = 8;


if (!function_exists('array_merge_recursive_replace')) {
    /**
     * logic used to auto load the correct config based on environment
     * @param array $arrays ... List of arrays to merge
     * @return array
     */
    function array_merge_recursive_replace()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);

        foreach ($arrays as $array) {
            reset($base);
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($base[$key]) && @is_array($base[$key])) {
                    $base[$key] = array_merge_recursive_replace($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
        }
        return $base;
    }
}

if (!function_exists('array_deep_key')) {
    /**
     * Searches for a key deep in a complex array and returns its value.
     * Won't complain if any of those keys exists, instead of $array['one']['two'].
     * @example array_deep_key_exists('one.two', ['one' => ['two' => 2]]) === true
     * @param string $address Dot-separated key names
     * @param array $array
     * @return null|mixed the value, if found; null otherwise.
     */
    function array_deep_key(array $array, $address)
    {
        $keys = explode('.', $address);
        $inside = $array;
        foreach ($keys as $key) {
            if (array_key_exists($key, $inside)) {
                $inside = $inside[$key];
            } else {
                return null;
            }
        }
        return $inside;
    }
}

if (!function_exists('array_deep_key_exists')) {
    /**
     * Searches for a key deep in a complex array.
     * @example array_deep_key_exists('one.two', ['one' => ['two' => 2]]) === true
     * @param string $path Dot-separated key names
     * @param array $array
     * @return bool
     */
    function array_deep_key_exists($path, array $array): bool
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('array_flatten')) {
    /**
     * Flattens all entries of a matrix into a single, long array of values.
     * @param array $matrix A multi-dimensional array
     * @param bool $assoc If the given array is associative (and keys should be maintained) or not
     * @return array
     */
    function array_flatten(array $matrix, $assoc = false): array
    {
        if (!$assoc) {
            $result = [];
            array_walk_recursive($matrix, function ($v) use (&$result) {
                $result[] = $v;
            });
            return $result;
        } else {
            return is_array(current($matrix)) ? call_user_func_array('array_merge', $matrix) : $matrix;
        }
    }
}

if (!function_exists('array_merge_if_not_null')) {
    /**
     * Merge two arrays that may have different keys. Keep the not null value of the same key
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    function array_merge_if_not_null(array $arr1, array $arr2): array
    {
        foreach ($arr2 as $key => $val) {
            $is_set_and_not_null = isset($arr1[$key]);
            if ($val == null && $is_set_and_not_null) {
                $arr2[$key] = $arr1[$key];
            }
        }
        return array_merge($arr1, $arr2);
    }
}