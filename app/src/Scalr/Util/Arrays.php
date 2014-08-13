<?php

/**
 * Array utils
 *
 * @author Marat Komarov
 */
class Scalr_Util_Arrays
{

    /**
     * @see http://ua2.php.net/manual/en/function.array-merge-recursive.php#93905
     * @return array
     */
   public static function mergeReplaceRecursive()
   {
        // Holds all the arrays passed
        $params = func_get_args();

        // First array is used as the base, everything else overwrites on it
        $return = array_shift ($params);

        // Merge all arrays on the first array
        foreach ($params as $array) {
            foreach ($array as $key => $value) {
                // Numeric keyed values are added (unless already there)
                if (is_numeric($key) && (!in_array($value, $return))) {
                    if (is_array ($value )) {
                        $return [] = self::mergeReplaceRecursive ($return [$key], $value);
                    } else {
                        $return [] = $value;
                    }

                // String keyed values are replaced
                } else {
                    if (isset ($return[$key]) && is_array ($value) && is_array ($return [$key])) {
                        $return[$key] = self::mergeReplaceRecursive($return[$key], $value);
                    } else {
                        $return[$key] = $value;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Calculates median
     *
     * @param   array    $array  Not empty array with numeric values
     * @return  number   Returns median or false for empty array
     * @since   5.0 (01.04.2014)
     */
    public static function median($array)
    {
        $number = count($array);

        if ($number == 0) {
            return false;
        }

        $mid = floor($number / 2);

        sort($array, SORT_NUMERIC);

        if ($number % 2 == 0) {
            $median = ($array[$mid] + $array[$mid - 1]) / 2;
        } else {
            $median = $array[$mid];
        }

        return $median;
    }
}