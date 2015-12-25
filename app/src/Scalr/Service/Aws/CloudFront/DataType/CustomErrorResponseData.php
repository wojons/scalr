<?php
namespace Scalr\Service\Aws\CloudFront\DataType;

use Scalr\Service\Aws\CloudFront\AbstractCloudFrontDataType;

/**
 * CustomErrorResponseData
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.11.2015
 *
 * @method    string                     getDistributionId()  getDistributionId()      Gets an associated distribution ID.
 * @method    CustomErrorResponseData    setDistributionId()  setDistributionId($id)   sets an associated distribution ID.
 */
class CustomErrorResponseData extends AbstractCloudFrontDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('distributionId');

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = [];

    /**
     * HTTP status code for which you want to customize the response
     *
     * @var string
     */
    public $errorCode;

    /**
     * Path to custom error page
     *
     * @var string
     */
    public $responsePagePath;

    /**
     * HTTP status code that you want CloudFront to return along with the custom error page
     *
     * @var string
     */
    public $responseCode;

    /**
     * Minimum TTL for this ErrorCode
     *
     * @var int
     */
    public $errorCachingMinTtl;

    /**
     * Constructor
     *
     * @param string $errorCode          optional HTTP status code for which you want to customize the response
     * @param string $responsePagePath   optional Path to custom error page
     * @param string $responseCode       optional HTTP status code that you want CloudFront to return along with the custom error page
     * @param string $errorCachingMinTtl optional Minimum TTL for this ErrorCode
     */
    public function __construct($errorCode = null, $responsePagePath = null, $responseCode = null, $errorCachingMinTtl = null)
    {
        $this->errorCode = $errorCode;
        $this->responsePagePath = $responsePagePath;
        $this->responseCode = $responseCode;
        $this->errorCachingMinTtl = $errorCachingMinTtl;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $top = $xml->createElement('CustomErrorResponse');
        $xml->appendChild($top);

        $top->appendChild($xml->createElement('ErrorCode', $this->name));
        $top->appendChild($xml->createElement('ResponsePagePath', $this->responsePagePath));
        $top->appendChild($xml->createElement('ResponseCode', $this->responseCode));
        $top->appendChild($xml->createElement('ErrorCachingMinTTL', $this->errorCachingMinTtl));

        return $returnAsDom ? $xml : $xml->saveXML();
    }
}