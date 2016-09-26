<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * HealthConfigData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 *
 * @method    string             getHealthId()  getHealthId()      Gets an associated health check ID.
 * @method    \Scalr\Service\Aws\Route53\DataType\HealthConfigData   setHealthId()  setHealthId($id)   Sets an associated health check ID.
 */
class HealthConfigData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('healthId');

    /**
     * IP address of the endpoint to check
     *
     * @var string
     */
    public $ipAddress;

    /**
     * Port on the endpoint to check
     *
     * @var int
     */
    public $port;

    /**
     * HTTP | HTTPS | HTTP_STR_MATCH | HTTPS_STR_MATCH | TCP
     *
     * @var string
     */
    public $type;

    /**
     * Path of the file that you want Amazon Route 53 to request
     *
     * @var string
     */
    public $resourcePath;

    /**
     * Domain name of the endpoint to check
     *
     * @var string
     */
    public $domainName;

    /**
     * If Type is HTTP_STR_MATCH or HTTPS_STR_MATCH,
     * the string to search for in the response body
     * from the specified resource
     *
     * @var string
     */
    public $searchString;

    /**
     * 10 | 30
     *
     * @var int
     */
    public $requestInterval;

    /**
     * Integer between 1 and 10
     *
     * @var int
     */
    public $failureThreshold;

    /**
     * Constructor
     *
     * @param   string     $ipAddress required  The IPv4 address of the endpoint on which you want Amazon Route 53 to perform health checks
     * @param   int        $port      optional  The port on the endpoint on which you want Amazon Route 53 to perform health checks
     * @param   string     $type      required  HTTP | HTTPS | HTTP_STR_MATCH | HTTPS_STR_MATCH | TCP
     * @param   string     $resourcePath optional(All Types Except TCP)
     * @param   string     $domainName  optional(All Types Except TCP)
     * @param   string     $searchString optional(HTTP_STR_MATCH and HTTPS_STR_MATCH Only)
     * @param   int        $requestInterval optional 10 | 30
     * @param   int        $failureThreshold optional Integers between 1 and 10
     *
     */
    public function __construct(
            $ipAddress = null,
            $port = null,
            $type = null,
            $resourcePath = null,
            $domainName = null,
            $searchString = null,
            $requestInterval = null,
            $failureThreshold = null
            )
    {
        $this->ipAddress = $ipAddress;
        $this->port = $port;
        $this->type = $type;
        $this->resourcePath = $resourcePath;
        $this->domainName = $domainName;
        $this->searchString = $searchString;
        $this->requestInterval = $requestInterval;
        $this->failureThreshold = $failureThreshold;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $top = $xml->createElement('HealthCheckConfig');
        $xml->appendChild($top);

        $top->appendChild($xml->createElement('IPAddress', $this->ipAddress));
        if ($this->port !== null) {
            $top->appendChild($xml->createElement('Port', $this->port));
        }
        $top->appendChild($xml->createElement('Type', $this->type));
        if ($this->resourcePath !== null) {
            $top->appendChild($xml->createElement('ResourcePath', $this->resourcePath));
        }
        if ($this->domainName !== null) {
            $top->appendChild($xml->createElement('FullyQualifiedDomainName', $this->domainName));
        }
        if ($this->searchString !== null) {
            $top->appendChild($xml->createElement('SearchString', $this->searchString));
        }
        if ($this->requestInterval !== null) {
            $top->appendChild($xml->createElement('RequestInterval', $this->requestInterval));
        }
        if ($this->failureThreshold !== null) {
            $top->appendChild($xml->createElement('FailureThreshold', $this->failureThreshold));
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }

}