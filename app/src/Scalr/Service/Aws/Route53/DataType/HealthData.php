<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * HealthData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\Aws\Route53\DataType\HealthConfigData $healthConfig   The Health Check Config data
 *
 */
class HealthData extends AbstractRoute53DataType
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
    protected $_properties = array('healthConfig');

    /**
     * The identifier for the health check
     *
     * @var string
     */
    public $healthId;

    /**
     * Unique identifier of the health check
     *
     * @var string
     */
    public $callerReference;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->callerReference = uniqid();
    }

    /**
     * Sets healthConfig
     *
     * @param   HealthData $healthConfig
     * @return  HealthConfigData
     */
    public function setHealthConfig(HealthConfigData $healthConfig = null)
    {
        return $this->__call(__FUNCTION__, array($healthConfig));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $top = $xml->createElementNS('https://route53.amazonaws.com/doc/2013-04-01/', 'CreateHealthCheckRequest');
        $xml->appendChild($top);

        $top->appendChild($xml->createElement('CallerReference', $this->callerReference));

        if ($this->healthConfig instanceof HealthConfigData) {
            $this->healthConfig->appendContentToElement($top);
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Route53.AbstractRoute53DataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->healthId === null) {
            throw new Route53Exception(sprintf('healthId has not been initialized for the "%s" yet!', get_class($this)));
        }
    }

    /**
     * Delete health check action
     *
     * @param   string       $healthId  The identifier for the health check
     * @return  bool         Returns true on success
     * @throws  ClientException
     * @throws  Route53Exception
     */
    public function delete($healthId)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getRoute53()->health->delete($healthId);
    }
}