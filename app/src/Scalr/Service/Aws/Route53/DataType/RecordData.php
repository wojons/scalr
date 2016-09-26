<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * RecordData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @method    string             getName()  getName()      Gets an associated domain name.
 * @method    \Scalr\Service\Aws\Route53\DataType\RecordList         setName()  setName($id)   Sets an associated domain name.
 */
class RecordData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('name');

    /**
     * Applicable value for the record type
     *
     * @var string
     */
    public $value;

    /**
     * Constructor
     *
     * @param   string     $value   required  Applicable value for the DNS record type
     */
    public function __construct($value = null)
    {
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $origin = $xml->createElement('ResourceRecord');
        $xml->appendChild($origin);
        $origin->appendChild($xml->createElement('Value', $this->value));

        return $returnAsDom ? $xml : $xml->saveXML();
    }

}