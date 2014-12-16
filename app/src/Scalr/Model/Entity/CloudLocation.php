<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use DateTime, Exception;

/**
 * CloudLocation entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0.1 (01.10.2014)
 *
 * @Entity
 * @Table(name="cloud_locations")
 */
class CloudLocation extends AbstractEntity
{

    /**
     * Identifier
     *
     * This identifier is calculated using:
     * substr(sha1(platform + ';' + $cloud_location + ';' + $normalizedUrl), 0, 36)
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $cloudLocationId;

    /**
     * Cloud platform
     *
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * Normalized endpoint url
     *
     * @Column(type="string")
     * @var string
     */
    public $url;

    /**
     * The cloud location
     *
     * @Column(type="string")
     * @var string
     */
    public $cloudLocation;

    /**
     * Update date
     *
     * The date when this record was updated.
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $updated;

    /**
     * Collection of the instance types
     *
     * @var \Scalr\Model\Collections\ArrayCollection
     */
    private $instanceTypes;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->updated = new DateTime('now');
        $this->url = '';
    }

    /**
     * Gets instance types for this cloud location
     *
     * @return  \Scalr\Model\Collections\ArrayCollection Returns collection of the instance types
     */
    public function fetchInstanceTypes()
    {
        $this->instanceTypes = CloudInstanceType::find([['cloudLocationId' => $this->cloudLocationId]]);

        return $this->instanceTypes;
    }

    /**
     * Gets instance types associated with the cloud location
     *
     * @return \Scalr\Model\Collections\ArrayCollection
     */
    public function getInstanceTypes()
    {
        if ($this->instanceTypes === null) {
            $this->fetchInstanceTypes();
        }

        return $this->instanceTypes;
    }

    /**
     * Gets active instance types associated with the cloud location
     *
     * @return \Scalr\Model\Collections\ArrayCollection
     */
    public function getActiveInstanceTypes()
    {
        return $this->getInstanceTypes()->filterByStatus(CloudInstanceType::STATUS_ACTIVE);
    }

    /**
     * Initializes a new Entity using specified parameters
     *
     * @param   string   $platform      A cloud platform
     * @param   string   $cloudLocation A cloud location
     * @param   string   $url           optional A cloud endpoint url
     * @return  CloudLocation
     */
    public static function init($platform, $cloudLocation, $url = '')
    {
        $entity = new static;
        $entity->cloudLocationId = static::calculateCloudLocationId($platform, $cloudLocation, $url);
        $entity->platform = $platform;
        $entity->url = static::normalizeUrl($url);
        $entity->cloudLocation = $cloudLocation;

        return $entity;
    }

    /**
     * Checks whether current platform has cached instance types in database
     *
     * @param   string    $platform      A cloud platform
     * @param   string    $url           optional A cloud endpoint url
     * @param   string    $cloudLocation optional A cloud location
     * @param   int       $lifetime      optional Cache lifetime in seconds.
     *                                   If it isn't provided it will use scalr.cache.instance_types.lifetime config value
     * @return  boolean   Returns true if current platform has cached instance types in database
     */
    public static function hasInstanceTypes($platform, $url = '', $cloudLocation = null, $lifetime = null)
    {
        $db = \Scalr::getDb();

        $options = [$platform, CloudInstanceType::STATUS_ACTIVE, static::normalizeUrl($url)];
        $stmt = "";

        if ($cloudLocation !== null) {
            $options[] = $cloudLocation;
            $stmt .= " AND cl.`cloud_location` = ?";
        }

        if ($lifetime === null) {
            $lifetime = (int) \Scalr::config('scalr.cache.instance_types.lifetime');
        }

        $stmt .= " AND cl.`updated` > '" . date("Y-m-d H:i:s", time() - intval($lifetime)) . "'";

        $res = $db->GetOne("
            SELECT EXISTS(
                SELECT 1 FROM `cloud_locations` cl
                JOIN `cloud_instance_types` cit ON cit.cloud_location_id = cl.cloud_location_id
                WHERE cl.`platform` = ? AND cit.`status` = ? AND cl.`url` = ?" . $stmt . "
            )
        " , $options);

        return $res ? true : false;
    }

    /**
     * Updates instance types in a database
     *
     * @param   string    $platform       A cloud platform
     * @param   string    $url            A cloud endpoint url
     * @param   string    $cloudLocation  A cloud location
     * @param   array     $instanceTypes  Array of the instance types looks like [instanceTypeId => [prop => value]]
     */
    public static function updateInstanceTypes($platform, $url, $cloudLocation, array $instanceTypes)
    {
        //One representation for platforms which does not support different cloud locations
        if (empty($cloudLocation)) {
            $cloudLocation = '';
        }

        //Normalizes url to use in queries
        $url = static::normalizeUrl($url);

        //Search for cloud location record
        $cl = static::findOne([['platform' => $platform], ['url' => $url], ['cloudLocation' => $cloudLocation]]);

        if (!($cl instanceof CloudLocation)) {
            $isNew = true;
            //There are no records yet
            $cl = static::init($platform, $cloudLocation, $url);
        }

        //Starts database transaction
        $cl->db()->BeginTrans();

        try {
            if (!empty($isNew)) {
                //We have to create a parent table record in order to foreign key does not bark
                $cl->save();
            }

            //Existing instance types
            $updatedIds = [];

            //Updates instance types which were known before
            foreach ($cl->getInstanceTypes() as $cit) {
                /* @var $cit \Scalr\Model\Entity\CloudInstanceType */
                $changes = 0;

                if (!empty($instanceTypes[$cit->instanceTypeId]) && is_array($instanceTypes[$cit->instanceTypeId])) {
                    //Updates status
                    $changes = $cit->updateProperties(array_merge($instanceTypes[$cit->instanceTypeId], ['status' => $cit::STATUS_ACTIVE]));

                    //Remembers which instances have been handled
                    $updatedIds[] = $cit->instanceTypeId;
                } else {
                    //Deactivates this instance type as it does not exist for now
                    $cit->status = $cit::STATUS_INACTIVE;

                    $changes++;
                }

                //Updates a record only if real changes happen
                if ($changes) $cit->save();
            }

            //New instance types which were not known before
            foreach (array_diff_key($instanceTypes, array_flip($updatedIds)) as $instanceTypeId => $array) {
                if (empty($array) || !is_array($array)) {
                    continue;
                }

                $cit = new CloudInstanceType($cl->cloudLocationId, $instanceTypeId);
                $cit->updateProperties($array);
                $cit->status = $cit::STATUS_ACTIVE;
                $cit->save();
            }

            //Checks whether we need to refresh an update time
            if (empty($isNew)) {
                $cl->updated = new DateTime('now');
                $cl->save();
            }
        } catch (Exception $e) {
            $cl->db()->RollbackTrans();

            throw $e;
        }

        $cl->db()->CommitTrans();
    }

    /**
     * Calculates uuid for the specified entity
     *
     * @param   string    $platform       Cloud platform
     * @param   string    $cloudLocation  Cloud location
     * @param   string    $url            optional Cloud url
     * @return  string    Returns UUID
     */
    public static function calculateCloudLocationId($platform, $cloudLocation, $url = '')
    {
        $hash = sha1(sprintf("%s;%s;%s", $platform, $cloudLocation, self::normalizeUrl($url)));

        return sprintf(
            "%s-%s-%s-%s-%s",
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    /**
     * Normalizes url
     *
     * @param   string    $url  The url
     * @return  string    Returns normalized url
     */
    public static function normalizeUrl($url)
    {
        if (empty($url)) return '';

        $arr = parse_url($url);

        if (empty($arr['scheme'])) {
            //IMPORTANT! Normalized url can be used as a parameter
            $arr = parse_url('http://' . $url);
        }

        //Scheme should be omitted
        $ret = $arr['host'] . (isset($arr['port']) ? ':' . $arr['port'] : '') .
               (isset($arr['path']) ? rtrim($arr['path'], '/') : '');

        return $ret;
    }

    /**
     * Forces cache to warm-up.
     */
    public static function warmUp()
    {
        \Scalr::getDb()->Execute("
            UPDATE cloud_instance_types
            SET `status` = ?
            WHERE `status` = ?
        ", [
            CloudInstanceType::STATUS_OBSOLETE,
            CloudInstanceType::STATUS_ACTIVE
        ]);
    }
}