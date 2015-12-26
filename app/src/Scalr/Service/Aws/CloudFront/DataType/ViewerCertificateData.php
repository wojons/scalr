<?php
namespace Scalr\Service\Aws\CloudFront\DataType;

use Scalr\Service\Aws\CloudFront\AbstractCloudFrontDataType;

/**
 * ViewerCertificateData
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.11.2015
 *
 * @method    string                getDistributionId()  getDistributionId()      Gets an associated distribution ID.
 * @method    ViewerCertificateData setDistributionId()  setDistributionId($id)   sets an associated distribution ID.
 */
class ViewerCertificateData extends AbstractCloudFrontDataType
{

    const SSL_SUPPORT_METHOD_VIP = 'vip';

    const SSL_SUPPORT_METHOD_SNI_ONLY = 'sni-only';

    const MINIMUM_PROTOCOL_VERSION_SSL_V3 = 'SSLv3';

    const MINIMUM_PROTOCOL_VERSION_TLS_V1 = 'TLSv1';

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
     * Optional
     *
     * @var string
     */
    public $iamCertificateId;

    /**
     * vip | sni-only
     *
     * @var string
     */
    public $sslSupportMethod;

    /**
     * SSLv3 | TLSv1
     *
     * @var string
     */
    public $minimumProtocolVersion;

    /**
     * Constructor
     * @param string $iamCertificateId optional
     * @param string $sslSupportMethod optional
     * @param string $minimumProtocolVersion optional
     */
    public function __construct($iamCertificateId = null, $sslSupportMethod = null, $minimumProtocolVersion = null)
    {
        $this->iamCertificateId = $iamCertificateId;
        $this->sslSupportMethod = $sslSupportMethod;
        $this->minimumProtocolVersion = $minimumProtocolVersion ?: self::MINIMUM_PROTOCOL_VERSION_SSL_V3;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $top = $xml->createElement('ViewerCertificate');
        $xml->appendChild($top);

        if (!empty($this->iamCertificateId)) {
            $top->appendChild($xml->createElement('IAMCertificateId', $this->iamCertificateId));
        } else {
            $top->appendChild($xml->createElement('CloudFrontDefaultCertificate', 'true'));
        }

        if (!empty($this->sslSupportMethod)) {
            $top->appendChild($xml->createElement('SSLSupportMethod', $this->sslSupportMethod));
        }

        $top->appendChild($xml->createElement('MinimumProtocolVersion', $this->minimumProtocolVersion));

        return $returnAsDom ? $xml : $xml->saveXML();
    }
}