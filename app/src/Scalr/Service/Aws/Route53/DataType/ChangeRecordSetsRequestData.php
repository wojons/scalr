<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53\AbstractRoute53DataType;

/**
 * ChangeRecordSetsRequestData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\Aws\Route53\DataType\ChangeRecordSetList $change
 *
 */
class ChangeRecordSetsRequestData extends AbstractRoute53DataType
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
    protected $_properties = array('change');

    /**
     * Optional comment about the changes in this change batch request
     *
     * @var string
     */
    public $comment;

    /**
     * Constructor
     *
     * @param   string     $comment   optional comment about the changes in this change batch request
     */
    public function __construct($comment = null)
    {
        $this->comment = $comment;
    }

    /**
     * Sets change
     *
     * @param   ChangeRecordSetList $change
     * @return  ChangeRecordSetsRequestData
     */
    public function setChange(ChangeRecordSetList $change = null)
    {
        return $this->__call(__FUNCTION__, array($change));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $top = $xml->createElementNS('https://route53.amazonaws.com/doc/2013-04-01/', 'ChangeResourceRecordSetsRequest');
        $xml->appendChild($top);
        $top->appendChild($xml->createElement('ChangeBatch'));
        if ($this->comment !== null) {
            $top->firstChild->appendChild($xml->createElement('Comment', $this->comment));
        }
        if ($this->change instanceof ChangeRecordSetList) {
            $this->change->appendContentToElement($top->firstChild);
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }

}