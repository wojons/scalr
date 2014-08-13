<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53ListDataType;

/**
 * RecordList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @method    string             getName()  getName()      Gets an associated domain name.
 * @method    \Scalr\Service\Aws\Route53\DataType\RecordList         setName()  setName($id)   Sets an associated domain name.
 */
class RecordList extends AbstractRoute53ListDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array('name');

    /**
     * Constructor
     *
     * @param array|RecordData  $aListData  RecordData List
     */
    public function __construct ($aListData = null)
    {
        parent::__construct(
            $aListData,
            'value',
            'Scalr\\Service\\Aws\\Route53\\DataType\\RecordData'
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'value', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        if ($this->count() > 0) {
            $items = $xml->createElement('ResourceRecords');
            $xml->appendChild($items);
            foreach ($this as $item) {
                $item->appendContentToElement($items);
            }
            unset($items);
        }
        return $returnAsDom ? $xml : $xml->saveXML();
    }
}