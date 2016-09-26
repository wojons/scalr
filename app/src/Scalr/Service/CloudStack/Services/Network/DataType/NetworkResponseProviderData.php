<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * NetworkResponseProviderData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class NetworkResponseProviderData extends AbstractDataType
{

    /**
     * uuid of the network provider
     *
     * @var string
     */
    public $id;

    /**
     * True if individual services can be enabled/disabled
     *
     * @var string
     */
    public $canenableindividualservice;

    /**
     * The destination physical network
     *
     * @var string
     */
    public $destinationphysicalnetworkid;

    /**
     * The provider name
     *
     * @var string
     */
    public $name;

    /**
     * The physical network this belongs to
     *
     * @var string
     */
    public $physicalnetworkid;

    /**
     * Services for this provider
     *
     * @var string
     */
    public $servicelist;

    /**
     * State of the network provider
     *
     * @var string
     */
    public $state;

}