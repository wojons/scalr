<?php
namespace Scalr\Modules;

use Scalr\Exception;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\CloudLocation;
use Scalr\Model\Entity\CloudInstanceType;

abstract class AbstractPlatformModule
{
    protected $platform;

    /**
     * DI Container
     *
     * @var \Scalr\DependencyInjection\Container
     */
    protected $container;

    /**
     * @var \ADODB_mysqli
     */
    protected $db;


    public function __construct()
    {
        $this->container = \Scalr::getContainer();
        $this->db = $this->container->adodb;
    }

    /**
     * Gets DI container
     *
     * @return  \Scalr\DependencyInjection\Container  Returns DI container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Gets platform property
     *
     * @deprecated by cloud credentials
     * @param    string             $name           The name of the platform property
     * @param    \Scalr_Environment $env            The environment
     * @param    string             $encrypted      optional This is ignored
     * @param    string             $cloudLocation  optional The cloud location
     * @return   string             Returns the value of the specified platform property
     */
    public function getConfigVariable($name, \Scalr_Environment $env, $encrypted = true, $cloudLocation = '')
    {
        $name = $this->platform ? "{$this->platform}.{$name}" : $name;

        return $env->getPlatformConfigValue($name, $encrypted, $cloudLocation);
    }

    /**
     * Sets the values for the specified platform properties
     *
     * @deprecated by cloud credentials
     * @param    array              $pars          Associative array of the keys -> value
     * @param    \Scalr_Environment $env           The environment object
     * @param    string             $encrypted     optional This parameter is already ignored
     * @param    string             $cloudLocation The cloud location
     */
    public function setConfigVariable($pars, \Scalr_Environment $env, $encrypted = true, $cloudLocation = '')
    {
        $config = array();

        foreach ($pars as $key => $v) {
            $index = $this->platform ? "{$this->platform}.{$key}" : $key;
            $config[$index] = $v;
        }

        $env->setPlatformConfig($config, $encrypted, $cloudLocation);
    }

    public function ResumeServer(\DBServer $DBServer)
    {
        $DBServer->update([
            'status'    => \SERVER_STATUS::RESUMING,
            'dateAdded' => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(\Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        //This method is supposed to be overridden
        return $this->platform ? $this->container->analytics->prices->hasPriceForUrl($this->platform, '') : false;
    }

    /**
     * Gets endpoint url for private clouds
     *
     * @param \Scalr_Environment $env       The scalr environment object
     * @param string             $group     optional The group name
     * @return string|null Returns endpoint url for private clouds. Null otherwise.
     */
    public function getEndpointUrl(\Scalr_Environment $env, $group = null)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getOrphanedServers()
     * @throws \Scalr\Exception\NotYetImplementedException
     */
    public function getOrphanedServers(Environment $environment, $cloudLocation, $instanceIds = null)
    {
        throw new Exception\NotYetImplementedException(sprintf("Orphaned server's listing has not been implemented for the platform '%s' yet", $this->platform));
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceType()
     */
    public function getInstanceType($instanceTypeId, \Scalr_Environment $env, $cloudLocation = null)
    {
        $cloudLocationId = CloudLocation::calculateCloudLocationId($this->platform, $cloudLocation, $this->getEndpointUrl($env));
        $cit = CloudInstanceType::findPk($cloudLocationId, $instanceTypeId);

        if ($cit === null) {
            $instanceTypes = $this->getInstanceTypes($env, $cloudLocation, true);

            if (!empty($instanceTypes[$instanceTypeId])) {
                $cit = CloudInstanceType::findPk($cloudLocationId, $instanceTypeId);
            }
        }

        return $cit;
    }

    /**
     * Gets active instance types for the specified cloud platform, url and location
     * from the cache
     *
     * @param   string     $platform      A cloud platform
     * @param   string     $url           A cloud endpoint url
     * @param   string     $cloudLocation A cloud location
     *
     * @return  \Scalr\Model\Collections\ArrayCollection|boolean
     *          Returns collection of the CloudInstanceType entities on success or false otherwise
     */
    protected function getCachedInstanceTypes($platform, $url, $cloudLocation)
    {
        //Gets a lifetime of the cached data from the config
        $lifetime = (int) \Scalr::config('scalr.cache.instance_types.lifetime');

        //If this lifetime equals zero it means we have to warm-up cache
        if ($lifetime === 0) {
            CloudLocation::warmUp();
            //We have no cached instance types
            return false;
        }

        //Checks whether there are active instance types in the cache taking lifetime into account.
        if (!CloudLocation::hasInstanceTypes($platform, $url, $cloudLocation, $lifetime)) {
            //No cached data
            return false;
        }

        //Fetches cloud location entity from the cache
        $cl = CloudLocation::findPk(CloudLocation::calculateCloudLocationId($platform, $cloudLocation, $url));

        //It is possible that it might have been already removed
        if (!($cl instanceof CloudLocation)) {
            //No cached data
            return false;
        }

        //Retrieves an instance types for this cloud location.
        //We should actually return only active types.
        return $cl->getActiveInstanceTypes();
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getImageInfo()
     */
    public function getImageInfo(\Scalr_Environment $environment, $cloudLocation, $imageId)
    {
        return [];
    }
}
