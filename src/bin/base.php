<?php
/**
 * store low level functions that are essential to system operation
 * TODO: Make these services or libraries in DI?
 */

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
     * @param string $address Dot-separated key names
     * @param array $array
     * @return bool
     */
    function array_deep_key_exists($address, array $array)
    {
        $keys = explode('.', $address);
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