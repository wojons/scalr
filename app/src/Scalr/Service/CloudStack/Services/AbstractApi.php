<?php
namespace Scalr\Service\CloudStack\Services;

/**
 * AbstractApi
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
abstract class AbstractApi
{

    /**
     * Escapes string to pass it over http request
     *
     * @param   string   $str
     * @return  string
     */
    protected function escape($str)
    {
        return rawurlencode($str);
    }
}