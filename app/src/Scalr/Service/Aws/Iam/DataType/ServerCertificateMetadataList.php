<?php

namespace Scalr\Service\Aws\Iam\DataType;

use Scalr\Service\Aws\Iam\AbstractIamListDataType;

/**
 * ServerCertificateMetadataList
 *
 * @author N.V.
 *
 * @method   ServerCertificateMetadataData get() get($position = null) Gets ServerCertificateMetadataData at specified
 *                                                                     position in the list.
 */
class ServerCertificateMetadataList extends AbstractIamListDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('marker', 'isTruncated');

    /**
     * Constructor
     *
     * @param array|ServerCertificateMetadataData  $aListData  ServerCertificateMetadataData List
     */
    public function __construct($aListData = null)
    {
        parent::__construct($aListData, array('serverCertificateId'), __NAMESPACE__ . '\\ServerCertificateMetadataData');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.ListDataType::getQueryArray()
     */
    public function getQueryArray($uriParameterName = 'ServerCertificateId', $member = true)
    {
        return parent::getQueryArray($uriParameterName);
    }
}