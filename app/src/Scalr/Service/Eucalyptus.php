<?php
namespace Scalr\Service;

/**
 * Eucalyptus cloud client
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    19.11.2013
 */
class Eucalyptus extends Aws
{
    /**
     * The list of the service urls
     * @var array
     */
    private $aUrl;

    /**
     * The list of available cloud locations
     *
     * @var array
     */
    private $availableCloudLocations;

    /**
     * Gets implemented web service interfaces for Eucalyptus api client
     *
     * @return     array Returns Returns the list of available (implemented) web service interfaces
     */
    public function getAvailableServiceInterfaces()
    {
        return array(
            self::SERVICE_INTERFACE_S3,
            self::SERVICE_INTERFACE_EC2,
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws::getAvailableRegions()
     */
    public function getAvailableRegions($ignoreCache = false)
    {
        if (!isset($this->availableCloudLocations) || $ignoreCache) {
            $env = $this->getEnvironment();
            if (!($env instanceof \Scalr_Environment)) {
                throw new EucalyptusException(sprintf("Scalr_Environment object has not been set for %s yet.", get_class($this)));
            }

            $db = $env->getContainer()->adodb;
            $this->availableCloudLocations = array();
            $res = $db->Execute("
                SELECT DISTINCT `group` FROM `client_environment_properties`
                WHERE env_id = ? AND `name` LIKE 'eucalyptus.%' AND `group` != ''
            ", array(
            	$env->id
            ));
            while ($rec = $res->FetchRow()) {
                $this->availableCloudLocations[] = $rec['group'];
            }
        }

        return $this->availableCloudLocations;
    }

    /**
     * Sets service url
     *
     * @param   string    $service  The name of the service (ec2, s3, ...)
     * @param   string    $url      The URL for EC2 service
     * @return  Eucalyptus
     */
    public function setUrl($service, $url)
    {
        $this->aUrl[$service] = $url;
        return $this;
    }

    /**
     * Gets the url of the specified service
     *
     * @param   string    $service The name of the service (ec2, s3)
     * @return  string    Returns the URL of the service
     */
    public function getUrl($service)
    {
        return isset($this->aUrl[$service]) ? $this->aUrl[$service] : null;
    }
}