<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * ZoneConfigData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 *
 * @method    string           getZoneId()  getZoneId()      Gets an associated zone ID.
 * @method    \Scalr\Service\Aws\Route53\DataType\ZoneConfigData   setZoneId()  setZoneId($id)   Sets an associated zone ID.
 */
class ZoneConfigData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('zoneId');

    /**
     * Any comments you want to include about the hosted zone (up to 128 characters)
     *
     * @var string
     */
    public $comment;

    /**
     * Constructor
     *
     * @param   string     $comment   optional
     */
    public function __construct($comment = null)
    {
        $this->comment = $comment;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $top = $xml->createElement('HostedZoneConfig');
        $xml->appendChild($top);
        $top->appendChild($xml->createElement('Comment', $this->comment));

        return $returnAsDom ? $xml : $xml->saveXML();
    }
}