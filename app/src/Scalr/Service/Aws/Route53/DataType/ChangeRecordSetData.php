<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * ChangeRecordSetData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\Aws\Route53\DataType\RecordSetData        $recordSet     The Record Set data
 *
 */
class ChangeRecordSetData extends AbstractRoute53DataType
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
    protected $_properties = array('recordSet');

    /**
     * CREATE | DELETE | UPSERT
     *
     * @var string
     */
    public $action;

    /**
     * Constructor
     *
     * @param   string     $action   required CREATE | DELETE | UPSERT
     */
    public function __construct($action = null)
    {
        $this->action = $action;
    }

    /**
     * Sets recordSet
     *
     * @param   RecordSetData  $recordSet
     * @return  ChangeRecordSetData
     */
    public function setRecordSet(RecordSetData  $recordSet = null)
    {
        return $this->__call(__FUNCTION__, array($recordSet));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $change = $xml->createElement('Change');
        $xml->appendChild($change);
        $change->appendChild($xml->createElement('Action', $this->action));
        if ($this->recordSet instanceof RecordSetData) {
            $this->recordSet->appendContentToElement($change);
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }

}