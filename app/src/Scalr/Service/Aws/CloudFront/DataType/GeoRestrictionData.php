<?php
namespace Scalr\Service\Aws\CloudFront\DataType;

use Scalr\Service\Aws\CloudFront\AbstractCloudFrontDataType;

/**
 * GeoRestrictionData
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.11.2015
 *
 * @method    string                getDistributionId()  getDistributionId()      Gets an associated distribution ID.
 * @method    GeoRestrictionData    setDistributionId()  setDistributionId($id)   sets an associated distribution ID.
 */
class GeoRestrictionData extends AbstractCloudFrontDataType
{
    const RESTRICTION_TYPE_NONE = 'none';

    const RESTRICTION_TYPE_WHITELIST = 'whitelist';

    const RESTRICTION_TYPE_BLACKLIST = 'blacklist';

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
     * @var string
     */
    public $restrictionType;

    /**
     * Locations
     *
     * @var array
     */
    public $locations = [];

    /**
     * @param string $restrictionType optional
     */
    public function __construct($restrictionType = null)
    {
        $this->restrictionType = $restrictionType ?: self::RESTRICTION_TYPE_NONE;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::toXml()
     */
    public function toXml($returnAsDom = false, &$known = null)
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $top = $xml->createElement('GeoRestriction');
        $xml->appendChild($top);

        $top->appendChild($xml->createElement('RestrictionType', $this->restrictionType));

        $top->appendChild($xml->createElement('Quantity', count($this->locations)));

        if (!empty($this->locations)) {
            $items = $xml->createElement('Items');
            $top->appendChild($items);
            foreach ($this->locations as $location) {
                $items->appendChild($xml->createElement('Location', $location));
            }
        }

        return $returnAsDom ? $xml : $xml->saveXML();
    }
}