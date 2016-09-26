<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * AliasTargetData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @method    string             getName()  getName()      Gets an associated domain name.
 * @method    \Scalr\Service\Aws\Route53\DataType\AliasTargetData    setName()  setName($id)   Sets an associated domain name.
 */
class AliasTargetData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('name');

    /**
     * The identifier for the hosted zone
     *
     * @var string
     */
    public $zoneId;

    /**
     * DNS domain name for your
     * CloudFront distribution, Amazon S3 bucket,
     * Elastic Load Balancing load balancer,
     * or another resource record set
     * in this hosted zone
     *
     * @var string
     */
    public $dnsName;

    /**
     * true | false
     *
     * @var string
     */
    public $evaluateTargetHealth;

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
        $top = $xml->createElement('AliasTarget');
        $xml->appendChild($top);
        $top->appendChild($xml->createElement('HostedZoneId', $this->zoneId));
        $top->appendChild($xml->createElement('DNSName', $this->dnsName));
        $top->appendChild($xml->createElement('EvaluateTargetHealth', $this->evaluateTargetHealth));

        return $returnAsDom ? $xml : $xml->saveXML();
    }

}