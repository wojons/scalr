<?php
namespace Scalr\Service\OpenStack\Services;

use Scalr\Service\OpenStack\Exception\OpenStackException;

/**
 * OpenStack service interface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    04.12.2012
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
     * Gets andpoint url
     *
     * @return  string Returns endpoint url without trailing slash
     * @throws  OpenStackException
     */
    public function getEndpointUrl();

    /**
     * Gets a version number
     *
     * @return  string Returns major version of the interface (V1, V2)
     */
    public function getVersion();

    /**
     * Gets a list of supported versions
     *
     * @return   array  Returns the list of supported versions
     */
    public function getSupportedVersions();

    /**
     * Sets the number of the version
     *
     * @param   string  $version  Major version number (V1, V2)
     * @return  ServiceInterface
     */
    public function setVersion($version);

    /**
     * Gets an API handler for the appropriated version
     *
     * @return  object Returns Api handler
     */
    public function getApiHandler();

    /**
     * Gets the list of available handlers
     *
     * @return  array Returns the list of available handlers
     */
    public function getAvailableHandlers();

}