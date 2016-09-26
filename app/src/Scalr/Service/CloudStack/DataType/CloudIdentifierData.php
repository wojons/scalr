<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * CloudIdentifierData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class CloudIdentifierData extends AbstractDataType
{

    /**
     * The cloud identifier
     *
     * @var string
     */
    public $cloudidentifier;

    /**
     * The signed response for the cloud identifier
     *
     * @var string
     */
    public $signature;

    /**
     * The user ID for the cloud identifier
     *
     * @var string
     */
    public $userid;

}