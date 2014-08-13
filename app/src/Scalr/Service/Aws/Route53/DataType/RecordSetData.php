<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * RecordSetData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\Aws\Route53\DataType\RecordList         $resourceRecord   The Resource record data
 * @property  \Scalr\Service\Aws\Route53\DataType\AliasTargetData    $aliasTarget      The Alias target data
 *
 */
class RecordSetData extends AbstractRoute53DataType
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
    protected $_properties = array('resourceRecord', 'aliasTarget');

    /**
     * DNS domain name
     *
     * @var string
     */
    public $name;

    /**
     * DNS record type
     *
     * @var string
     */
    public $type;

    /**
     * Time to live in seconds
     *
     * @var string
     */
    public $ttl;

    /**
     * ID of a Amazon Route 53 health check
     *
     * @var string
     */
    public $healthId;

    /**
     * Unique description for this resource record set
     *
     * @var string
     */
    public $setIdentifier;

    /**
     * Value between 0 and 255
     *
     * @var int
     */
    public $weight;

    /**
     * Amazon EC2 region name
     *
     * @var string
     */
    public $region;

    /**
     * PRIMARY | SECONDARY
     *
     * @var string
     */
    public $failover;

    /**
     * Constructor
     *
     * @param   string     $name   required     The name of the domain
     * @param   string     $type   required     The resource record set type the record listing begins from
     * @param   string     $ttl   optional      All resource record sets except aliases
     * @param   string     $healthId   optional The ID of the health check. Required When Checking the Health of Endpoints
     * @param   int        $setIdentifier   optional    Weighted, latency, and failover resource record sets only
     * @param   int        $weight   optional   Weighted resource record sets only
     * @param   string     $region   optional   Latency resource record sets only
     * @param   string     $failover   optional Failover resource record sets only
     *
     */
    public function __construct(
            $name = null,
            $type = null,
            $ttl = null,
            $healthId = null,
            $setIdentifier = null,
            $weight = null,
            $region = null,
            $failover = null
            )
    {
        $this->name = $name;
        $this->type = $type;
        $this->ttl = $ttl;
        $this->healthId = $healthId;
        $this->setIdentifier = $setIdentifier;
        $this->weight = $weight;
        $this->region = $region;
        $this->failover = $failover;
    }

    /**
     * Sets resourceRecord
     *
     * @param   RecordList $resourceRecord
     * @return  RecordSetData
     */
    public function setResourceRecord($resourceRecord = null)
    {
        if ($resourceRecord !== null && !($resourceRecord instanceof RecordList)) {
            $resourceRecord = new RecordList($resourceRecord);
        }
        return $this->__call(__FUNCTION__, array($resourceRecord));
    }

    /**
     * Sets aliasTarget
     *
     * @param   AliasTargetData $aliasTarget
     * @return  RecordSetData
     */
    public function setAliasTarget(AliasTargetData $aliasTarget = null)
    {
        if ($aliasTarget !== null && !($aliasTarget instanceof AliasTargetData)) {
            $aliasTarget = new AliasTargetData($aliasTarget);
        }
        return $this->__call(__FUNCTION__, array($aliasTarget));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Route53.AbstractRoute53DataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->name === null) {
            throw new Route53Exception(sprintf('name has not been initialized for the "%s" yet!', get_class($this)));
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
        $top = $xml->createElement('ResourceRecordSet');
        $xml->appendChild($top);
        $top->appendChild($xml->createElement('Name', $this->name));
        $top->appendChild($xml->createElement('Type', $this->type));
        if ($this->setIdentifier !== null) {
            $top->appendChild($xml->createElement('SetIdentifier', $this->setIdentifier));
        }
        if ($this->weight !== null) {
            $top->appendChild($xml->createElement('Weight', $this->weight));
        }
        if ($this->region !== null) {
            $top->appendChild($xml->createElement('Region', $this->region));
        }
        if ($this->failover !== null) {
            $top->appendChild($xml->createElement('Failover', $this->failover));
        }
        if ($this->ttl !== null) {
            $top->appendChild($xml->createElement('TTL', $this->ttl));
        }
        if ($this->aliasTarget instanceof AliasTargetData && $this->aliasTarget !== null) {
            $this->aliasTarget->appendContentToElement($top);
        }
        if ($this->resourceRecord instanceof RecordList && $this->resourceRecord !== null) {
            $this->resourceRecord->appendContentToElement($top);
        }
        if ($this->healthId !== null) {
            $top->appendChild($xml->createElement('HealthCheckId', $this->healthId));
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }

}