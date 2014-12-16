<?php

namespace Scalr\Modules;

interface PlatformModuleInterface
{
    /**
     * Gets DI container
     *
     * @return  \Scalr\DependencyInjection\Container  Returns DI container
     */
    public function getContainer();


    /**
     * Gets platform resume strategy
     *
     * @return  string reboot | init
     */
    public function getResumeStrategy();

    /**
     * Gets cloud locations
     *
     * @param  \Scalr_Environment  $environment The environment object.
     * @return array    Returns cloud locations available for current cloud platform.
     *                  Array looks like array(location => description).
     */
    public function getLocations(\Scalr_Environment $environment = null);

    /**
     * Launches new server
     *
     * @param   \DBServer                   $DBServer      The DBServer instance
     * @param   \Scalr_Server_LaunchOptions $launchOptions optional The options used in this instance
     * @return  \DBServer  Returns DBServer object on success or throws an exception
     */
    public function LaunchServer(\DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null);

    /**
     * Terminates specified server
     *
     * This method should throw valid InstanceNotFound excepiton for the case
     * when the node does not exist in the cloud.
     *
     * @param   \DBServer $DBServer The DB Server object
     * @return  bool      Returns TRUE on success or throws an Exception otherwise.
     * @throws  \Exception
     * @throws  \Scalr\Service\Exception\InstanceNotFound
     */
    public function TerminateServer(\DBServer $DBServer);

    /**
     * Reboots specified server
     *
     * This method should throw valid InstanceNotFound excepiton for the case
     * when the node does not exist in the cloud.
     *
     * @param   \DBServer $DBServer The DB Server object
     * @param   bool      $soft     Soft reboot or HARD
     * @return  bool      Returns   TRUE on success or throws an Exception otherwise.
     * @throws  \Exception
     * @throws  \Scalr\Service\Exception\InstanceNotFound
     */
    public function RebootServer(\DBServer $DBServer, $soft = true);

    /**
     * Creates server snapshot for specified bundle task
     *
     * @param   \BundleTask   $BundleTask  The bundle task object
     * @return  boolean       Returns true on success
     */
    public function CreateServerSnapshot(\BundleTask $BundleTask);

    /**
     * Checks server snapshot status
     *
     * @param   \BundleTask $BundleTask The Bundle Task object
     */
    public function CheckServerSnapshotStatus(\BundleTask $BundleTask);

    /**
     * Removes servers snapshot
     *
     * @param   \Scalr\Model\Entity\Image $image The Image object
     */
    public function RemoveServerSnapshot(\Scalr\Model\Entity\Image $image);

    /**
     * Gets server extended information
     *
     * @param   \DBServer $DBServer  The DBServer object
     * @return  array|bool  Returns array on success or false otherwise
     */
    public function GetServerExtendedInformation(\DBServer $DBServer, $extended = false);

    /**
     * Gets console output for the specified server
     *
     * @param   \DBServer $DBServer The DB Server object
     * @return  string    Returns console output if it is not empty otherwise it returns FALSE.
     *                    If server can not be found it throws an exception.
     * @throws  \Exception
     */
    public function GetServerConsoleOutput(\DBServer $DBServer);

    /**
     * Gets the status for the specified DB Server
     *
     * @param   \DBServer  $DBServer DB Server object
     * @return  \Scalr\Modules\Platforms\StatusAdapterInterface $status Returns the status
     */
    public function GetServerRealStatus(\DBServer $DBServer);

    /**
     * Gets IP Addresses for the specified DB Server
     *
     * @param   \DBServer     $DBServer  DB Server object
     * @return  array         Returns array looks like array(
     *                            'localIp'  => Local-IP,
     *                            'remoteIp' => Remote-IP,
     *                        )
     */
    public function GetServerIPAddresses(\DBServer $DBServer);

    /**
     * Checks whether specified server exists
     *
     * @param   \DBServer $DBServer  The DBServer object
     * @return  bool      Returns true if server exists of false otherwise
     */
    public function IsServerExists(\DBServer $DBServer);

    /**
     * Puts access data
     *
     * @param   \DBServer            $DBServer  The DBServer object
     * @param   \Scalr_Messaging_Msg $message   The message object
     */
    public function PutAccessData(\DBServer $DBServer, \Scalr_Messaging_Msg $message);

    /**
     * Clears internal cache
     */
    public function ClearCache();

    /**
     * Gets instance id for specified server
     *
     * @param   \DBServer $DBServer  The DBServer object
     * @return  string    Returns the identifier of the instance in the cloud
     */
    public function GetServerID(\DBServer $DBServer);

    /**
     * Gets cloud location for the specified server
     *
     * @param   \DBServer $DBServer  The DBServer object
     * @return  string    Returns cloud location of the specified server
     */
    public function GetServerCloudLocation(\DBServer $DBServer);

    /**
     * Gets type of the instance for the specified server
     *
     * @param   \DBServer $DBServer  The DBServer object
     * @return  string    Returns the flavor for specified DBServer
     */
    public function GetServerFlavor(\DBServer $DBServer);

    /**
     * Gets platform property
     *
     * @param    string             $name           The name of the platform property
     * @param    \Scalr_Environment $env            The environment
     * @param    string             $encrypted      optional This is ignored
     * @param    string             $cloudLocation  optional The cloud location
     * @return   string             Returns the value of the specified platform property
     */
    public function getConfigVariable($name, \Scalr_Environment $env, $encrypted = true, $cloudLocation = '');

    /**
     * Sets the values for the specified platform properties
     *
     * @param    array              $pars          Associative array of the keys -> value
     * @param    \Scalr_Environment $env           The environment object
     * @param    string             $encrypted     optional This parameter is already ignored
     * @param    string             $cloudLocation The cloud location
     */
    public function setConfigVariable($pars, \Scalr_Environment $env, $encrypted = true, $cloudLocation = '');

    /**
     * Gets the list of all available flavors for the specified environment and cloud location
     *
     * @param   \Scalr_Environment $env           optional The scalr environment object
     * @param   string             $cloudLocation optional The cloud location
     * @param   boolean            $details optional Return instance type with detalis (CPU, RAM, DISK, etc)
     * @return  array              Returns array of the available flavors.
     *                             It should look like array(flavor => name).
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false);

    /**
     * Checks whether there is some price for the appropriate cloud and url from the specified environment
     *
     * It returns first found url which has not any price set for.
     *
     * @param   \Scalr_Environment $env          The scalr environment object
     * @return  mixed              Returns first found url which has not any price set for.
     *                             Returns TRUE if there is some price for the cloud OR
     *                             Returns FALSE if it is public cloud and no price has been set
     */
    public function hasCloudPrices(\Scalr_Environment $env);
}
