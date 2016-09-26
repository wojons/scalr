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
     * @param  array  $array  Not empty array with numeric values
     * @return float|int|boolean Returns median or false for an empty array
     * @since  5.0 (01.04.2014)
     */
    public static function median(array $array)
    {
        if (empty($array)) {
            return false;
        }

        $number = count($array);

        $mid = floor($number / 2);

        sort($array, SORT_NUMERIC);

        if ($number % 2 == 0) {
            $median = ($array[$mid] + $array[$mid - 1]) / 2;
        } else {
            $median = $array[$mid];
        }

        return $median;
    }

    /**
     * Calculates the percentile
     *
     * @param  array $array               Array of numeric values
     * @param  int   $percentile          The percentile
     * @param  bool  $round      optional Whether to round up the value
     * @return boolean|float|int Returns percentile of FALSE if no data provided
     * @throws \InvalidArgumentException
     */
    public static function percentile(array $array, $percentile, $round = true)
    {
        if (is_int($percentile) && $percentile > 1 && $percentile <= 100) {
            $percentile /= 100;
        } else {
            throw new \InvalidArgumentException("Percentile must be an integer within a range of 1..100");
        }

        if (empty($array)) {
            return false;
        }

        $count = count($array);

        $allindex = ($count - 1) * $percentile;
        $intvalindex = intval($allindex);
        $floatval = $allindex - $intvalindex;

        sort($array, SORT_NUMERIC);

        if (! is_float($floatval) || $count <= $intvalindex + 1){
            $result = $array[$intvalindex];
        } else {
            $result = $floatval * ($array[$intvalindex + 1] - $array[$intvalindex]) + $array[$intvalindex];
        }
        return $round ? ceil($result) : $result;
    }
}
