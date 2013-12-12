<?php

class PlatformFactory
{
    private static $cache = array();

    /**
     * Gets the list of OpenStack based clouds
     *
     * @return  array  Returns the list of OpenStack based clouds
     */
    public static function getOpenstackBasedPlatforms()
    {
        return array(
        	SERVER_PLATFORMS::OPENSTACK,
            SERVER_PLATFORMS::ECS,
            SERVER_PLATFORMS::OCS,
            SERVER_PLATFORMS::NEBULA,
            SERVER_PLATFORMS::RACKSPACENG_UK,
            SERVER_PLATFORMS::RACKSPACENG_US
        );
    }

    /**
     * Gets the list of CloudStack based clouds
     *
     * @return  array  Returns the list of CloudStack based clouds
     */
    public static function getCloudstackBasedPlatforms()
    {
        return array(
            SERVER_PLATFORMS::IDCF,
            SERVER_PLATFORMS::CLOUDSTACK
        );
    }

    /**
     * Checks wheter specified cloud is OpenStack based
     *
     * @param   string    $platform A platform name
     * @return  boolean   Returns true if specified cloud is OpenStack based or false otherwise
     */
    public static function isOpenstack($platform)
    {
        return in_array($platform, self::getOpenstackBasedPlatforms());
    }

    /**
     * Checks wheter specified cloud is CloudStack based
     *
     * @param   string    $platform A platform name
     * @return  boolean   Returns true if specified cloud is CloudStack based or false otherwise
     */
    public static function isCloudstack($platform)
    {
        return in_array($platform, self::getCloudstackBasedPlatforms());
    }

    /**
     * Create platform instance
     *
     * @param string $platform
     * @return IPlatformModule
     */
    public static function NewPlatform($platform)
    {
        if (!array_key_exists($platform, self::$cache)) {
            if ($platform == SERVER_PLATFORMS::EC2)
                self::$cache[$platform] = new Modules_Platforms_Ec2();
            elseif ($platform == SERVER_PLATFORMS::GCE)
                self::$cache[$platform] = new Modules_Platforms_GoogleCE();
            elseif ($platform == SERVER_PLATFORMS::EUCALYPTUS)
                self::$cache[$platform] = new Modules_Platforms_Eucalyptus();
            elseif ($platform == SERVER_PLATFORMS::RACKSPACE)
                self::$cache[$platform] = new Modules_Platforms_Rackspace();
            elseif ($platform == SERVER_PLATFORMS::NIMBULA)
                self::$cache[$platform] = new Modules_Platforms_Nimbula();
            elseif ($platform == SERVER_PLATFORMS::CLOUDSTACK)
                self::$cache[$platform] = new Modules_Platforms_Cloudstack();
            elseif ($platform == SERVER_PLATFORMS::IDCF)
                self::$cache[$platform] = new Modules_Platforms_Idcf();
            elseif ($platform == SERVER_PLATFORMS::UCLOUD)
                self::$cache[$platform] = new Modules_Platforms_uCloud();

            elseif ($platform == SERVER_PLATFORMS::OPENSTACK)
                self::$cache[$platform] = new Modules_Platforms_Openstack();
            elseif ($platform == SERVER_PLATFORMS::ECS)
                self::$cache[$platform] = new Modules_Platforms_Openstack(SERVER_PLATFORMS::ECS);
            elseif ($platform == SERVER_PLATFORMS::OCS)
                self::$cache[$platform] = new Modules_Platforms_Openstack(SERVER_PLATFORMS::OCS);
            elseif ($platform == SERVER_PLATFORMS::NEBULA)
                self::$cache[$platform] = new Modules_Platforms_Openstack(SERVER_PLATFORMS::NEBULA);
            elseif ($platform == SERVER_PLATFORMS::RACKSPACENG_UK)
                self::$cache[$platform] = new Modules_Platforms_RackspaceNgUk();
            elseif ($platform == SERVER_PLATFORMS::RACKSPACENG_US)
                self::$cache[$platform] = new Modules_Platforms_RackspaceNgUs();
            else
                throw new Exception(sprintf("Platform %s is not supported by Scalr", $platform));
        }

        return self::$cache[$platform];
    }
}
