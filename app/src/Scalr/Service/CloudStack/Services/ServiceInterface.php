<?php
namespace Scalr\Service\CloudStack\Services;

/**
 * CloudStack service interface
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
interface ServiceInterface
{
    /**
     * Gets a service name.
     *
     * Returned name must start with the lower case letter.
     *
     * @return  string Returns service interface name.
     */
    public static function getName();

    /**
     * Gets a service type.
     *
     * @return  string Returns service interface type.
     */
    public static function getType();

    /**
     * Gets a version number
     *
     * @return  string Returns version of the interface
     */
    public function getVersion();

    /**
     * Gets an API handler for the appropriated version
     *
     * @return  object Returns Api handler
     */
    public function getApiHandler();

}