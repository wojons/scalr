<?php
namespace Scalr\Modules;

use \SERVER_PLATFORMS;
use \Exception;

class PlatformFactory
{
    private static $cache = array();

    /**
     * Returns the list of Rackspace clouds
     *
     * @return array
     */
    public static function getRackspacePlatforms()
    {
        return [SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK];
    }

    /**
     * Gets the list of canonical OpenStack based clouds
     *
     * @return  array
     */
    public static function getCanonicalOpenstackPlatforms()
    {
        return array_diff(static::getOpenstackBasedPlatforms(), static::getRackspacePlatforms());
    }

    /**
     * Gets the list of OpenStack based clouds
     *
     * @return  array  Returns the list of OpenStack based clouds
     */
    public static function getOpenstackBasedPlatforms()
    {
        return array(
            SERVER_PLATFORMS::OPENSTACK,
            SERVER_PLATFORMS::OCS,
            SERVER_PLATFORMS::NEBULA,
            SERVER_PLATFORMS::MIRANTIS,
            SERVER_PLATFORMS::VIO,
            SERVER_PLATFORMS::VERIZON,
            SERVER_PLATFORMS::CISCO,
            SERVER_PLATFORMS::HPCLOUD,
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
     * @param   string  $platform            A platform name
     * @param   bool    $canonical  optional Only canonical OpenStack platforms
     *
     * @return bool Returns true if specified cloud is OpenStack based or false otherwise
     */
    public static function isOpenstack($platform, $canonical = false)
    {
        return in_array($platform, $canonical ? static::getCanonicalOpenstackPlatforms() : self::getOpenstackBasedPlatforms());
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
     * Checks wheter specified cloud is Rackspace cloud
     *
     * @param   string    $platform A platform name
     * @return  boolean   Returns true if specified cloud is Rackspace cloud or false otherwise
     */
    public static function isRackspace($platform)
    {
        return in_array($platform, static::getRackspacePlatforms());
    }

    /**
     * Create platform instance
     *
     * @param string $platform
     * @return \Scalr\Modules\PlatformModuleInterface
     */
    public static function NewPlatform($platform)
    {
        if (!array_key_exists($platform, self::$cache)) {
            $ucPlatform = ucfirst($platform);
            if ($platform == SERVER_PLATFORMS::GCE) {
                self::$cache[$platform] = new \Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule();
            } elseif ($platform == SERVER_PLATFORMS::AZURE) {
                self::$cache[$platform] = new \Scalr\Modules\Platforms\Azure\AzurePlatformModule();
            } elseif (in_array($platform, array(SERVER_PLATFORMS::OCS, SERVER_PLATFORMS::NEBULA, SERVER_PLATFORMS::MIRANTIS, SERVER_PLATFORMS::VIO, SERVER_PLATFORMS::CISCO, SERVER_PLATFORMS::HPCLOUD))) {
                self::$cache[$platform] = new \Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule($platform);
            } elseif ($platform == SERVER_PLATFORMS::VERIZON) {
                self::$cache[$platform] = new \Scalr\Modules\Platforms\Verizon\VerizonPlatformModule();
            } elseif ($platform == SERVER_PLATFORMS::RACKSPACENG_UK) {
                self::$cache[$platform] = new \Scalr\Modules\Platforms\RackspaceNgUk\RackspaceNgUkPlatformModule();
            } elseif ($platform == SERVER_PLATFORMS::RACKSPACENG_US) {
                self::$cache[$platform] = new \Scalr\Modules\Platforms\RackspaceNgUs\RackspaceNgUsPlatformModule();
            } elseif (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'Platforms' . DIRECTORY_SEPARATOR . $ucPlatform . DIRECTORY_SEPARATOR . $ucPlatform . 'PlatformModule.php')) {
                $class = __NAMESPACE__ . '\\Platforms\\' . $ucPlatform . '\\' . $ucPlatform . 'PlatformModule';
                self::$cache[$platform] = new $class();
            } else {
                throw new Exception(sprintf("Platform %s is not supported by Scalr", $platform));
            }
        }

        return self::$cache[$platform];
    }

    /**
     * Gets the list of public clouds
     *
     * @return  array  Returns the list of public clouds
     */
    public static function getPublicPlatforms()
    {
        return array(
            SERVER_PLATFORMS::EC2,
            SERVER_PLATFORMS::IDCF,
            SERVER_PLATFORMS::GCE,
            SERVER_PLATFORMS::RACKSPACE,
            SERVER_PLATFORMS::RACKSPACENG_UK,
            SERVER_PLATFORMS::RACKSPACENG_US,
            SERVER_PLATFORMS::AZURE
        );
    }

    /**
     * Checks wheter specified cloud is public
     *
     * @param   string    $platform A platform name
     * @return  boolean   Returns true if specified cloud is public or false otherwise
     */
    public static function isPublic($platform)
    {
        return in_array($platform, self::getPublicPlatforms());
    }

    /**
     * Clears static cache
     */
    public static function warmup()
    {
        self::$cache = [];
    }
}
