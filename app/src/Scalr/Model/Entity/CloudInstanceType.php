<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use DateTime, stdClass;

/**
 * CloudInstanceType entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0.1 (01.10.2014)
 *
 * @Entity
 * @Table(name="cloud_instance_types")
 */
class CloudInstanceType extends AbstractEntity
{

    /**
     * Instance type is inactive
     */
    const STATUS_INACTIVE = 0;

    /**
     * Instance type is active
     */
    const STATUS_ACTIVE = 1;

    /**
     * Instance type is marked as obsolete and has to be refreshed
     */
    const STATUS_OBSOLETE = 2;

    /**
     * This type of flavour is unsupported by Scalr
     */
    const STATUS_UNSUPPORTED = 3;

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
     * Identifier of the Instance Type
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $instanceTypeId;

    /**
     * The name of the instance type
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Memory info
     *
     * @Column(type="string")
     * @var string
     */
    public $ram = '';

    /**
     * CPU info
     *
     * @Column(type="string")
     * @var string
     */
    public $vcpus = '';

    /**
     * Disk info
     *
     * @Column(type="string")
     * @var string
     */
    public $disk = '';

    /**
     * Storage type info
     *
     * @Column(type="string")
     * @var string
     */
    public $type = '';

    /**
     * Notes
     *
     * @Column(type="string")
     * @var string
     */
    public $note = '';

    /**
     * Misc options
     *
     * @Column(type="json")
     * @var object
     */
    public $options;

    /**
     * A status
     *
     * @Column(type="integer")
     * @var string
     */
    public $status;

    /**
     * Options which are supported and should be stored in options property
     *
     * @var array
     */
    protected static $_allowedOptions = ['ebsencryption', 'description'];

    /**
     * Options which are natively supported by the entity
     *
     * @var array
     */
    protected static $_allowedProperties = ['name', 'ram', 'vcpus', 'disk', 'type', 'note', 'status'];

    /**
     * Constructor
     *
     * @param   string $cloudLocationId optional An identifier of the cloud location (UUID)
     * @param   string $instanceTypeId  optional An identifier of the instance type
     */
    public function __construct($cloudLocationId = null, $instanceTypeId = null)
    {
        $this->cloudLocationId = $cloudLocationId;
        $this->instanceTypeId = $instanceTypeId;
        $this->options = new stdClass();
        $this->status = self::STATUS_ACTIVE;
    }

    /**
     * Synchronizes object's properties with those specified in array
     *
     * @param   array   $array  Array of the properties' values
     * @return  int     Returns number of the changes
     */
    public function updateProperties($array)
    {
        $changes = 0;

        //Options which are natively supported by the entity
        $props = array_merge(static::$_allowedProperties, static::$_allowedOptions);

        //Updates properties
        foreach ($props as $property) {
            if (array_key_exists($property, $array)) {
                if (in_array($property, static::$_allowedOptions)) {
                    if (!property_exists($this->options, $property) || $this->options->$property != $array[$property]) {
                        $this->options->$property = $array[$property];
                        $changes++;
                    }
                } else if ($this->$property != $array[$property]) {
                    $this->$property = $array[$property];
                    $changes++;
                }
            }
        }

        return $changes;
    }

    /**
     * Gets array of the properties
     *
     * @return  array Returns all properties as array
     */
    public function getProperties()
    {
        $result = [];
        // All properties
        foreach (static::$_allowedProperties as $property) {
            $result[$property] = $this->$property;
        }

        $opt = (array) $this->options;
        //Proceeds with options
        foreach (static::$_allowedOptions as $property) {
            if (array_key_exists($property, $opt)) {
                $result[$property] = $opt[$property];
            }
        }

        unset($result['status']);

        return $result;
    }
}