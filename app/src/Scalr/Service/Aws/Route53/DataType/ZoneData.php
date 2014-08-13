<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * ZoneData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\Aws\Route53\DataType\ZoneConfigData        $zoneConfig     The Hosted Zone Config data
 * @property  \Scalr\Service\Aws\Route53\DataType\ZoneChangeInfoData    $changeInfo     The Hosted Zone Change Info data
 * @property  \Scalr\Service\Aws\Route53\DataType\ZoneDelegationSetList $delegationSet  The Hosted Zone Delegation Set data
 *
 */
class ZoneData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('zoneConfig', 'changeInfo', 'delegationSet');

    /**
     * The identifier for the hosted zone
     *
     * @var string
     */
    public $zoneId;

    /**
     * DNS domain name
     *
     * @var string
     */
    public $name;

    /**
     * Unique identifier of the hosted zone
     *
     * @var string
     */
    public $callerReference;

    /**
     * Number of resource record sets in the hosted zone
     *
     * @var string
     */
    public $resourceRecordSetCount;

    /**
     * Constructor
     *
     * @param   string     $name   required     DNS domain name
     *
     */
    public function __construct($name = null)
    {
        $this->name = $name;
        $this->callerReference = uniqid();
    }

    /**
     * Sets zoneConfig
     *
     * @param   ZoneConfigData $zoneConfig
     * @return  ZoneData
     */
    public function setZoneConfig(ZoneConfigData $zoneConfig = null)
    {
        return $this->__call(__FUNCTION__, array($zoneConfig));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Route53.AbstractRoute53DataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->zoneId === null) {
            throw new Route53Exception(sprintf('zoneId has not been initialized for the "%s" yet!', get_class($this)));
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $top = $xml->createElementNS('https://route53.amazonaws.com/doc/2013-04-01/', 'CreateHostedZoneRequest');
        $xml->appendChild($top);

        $top->appendChild($xml->createElement('Name', $this->name));
        $top->appendChild($xml->createElement('CallerReference', $this->callerReference));

        if ($this->zoneConfig instanceof ZoneConfigData) {
            $this->zoneConfig->appendContentToElement($top);
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }

    /**
     * DeleteZone action
     *
     * @param   string       $zoneId  ID of the hosted zone.
     * @return  bool         Returns true on success
     * @throws  ClientException
     * @throws  Route53Exception
     */
    public function delete($zoneId)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getRoute53()->zone->delete($zoneId);
    }
}