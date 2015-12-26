<?php

namespace Scalr\Service\Azure\Services\Storage\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * AccountProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class AccountProperties extends AbstractDataType
{
    /**
     * The status of the storage account at the time the operation was called. Can be one of:
     *  - Creating – the storage account is being created.
     *               When an account is in “Creating” state, only properties that are specified as input for Create Account operation are returned.
     *  - ResolvingDNS – the DNS name for the storage account is being propagated
     *  - Succeeded – the storage account is active for use
     *
     * @var string
     */
    public $provisioningState;

    /**
     * One of the following account types (case-sensitive):
     *      Standard_LRS (Standard Locally-redundant storage)
     *      Standard_ZRS (Standard Zone-redundant storage)
     *      Standard_GRS (Standard Geo-redundant storage)
     *      Standard_RAGRS (Standard Read access geo-redundant storage)
     *      Premium_LRS (Premium Locally-redundant storage)
     *
     * @var string
     */
    public $accountType;

    /**
     * The URLs that are used to perform a retrieval of a public blob, queue or table object:
     * Example Format: [
     *      'blob'   => 'https://{accountName}.blob.core.windows.net/',
     *      'queue'  => 'https://{accountName}.queue.core.windows.net/',
     *      'table'  => 'https://{accountName}.table.core.windows.net/'
     * ]
     *
     * @var array
     */
    public $primaryEndpoints;

    /**
     * The location of the primary for the storage account.
     *
     * @var string
     */
    public $primaryLocation;

    /**
     * Indicates whether the primary location of the storage account is available or unavailable.
     * values: available|unavailable
     *
     * @var string
     */
    public $statusOfPrimary;

    /**
     * A timestamp of the most recent instance of a failover to the secondary location.
     * Only the most recent timestamp is retained.
     * This element is not returned if there has never been a failover instance.
     * Only available if the accountType is Standard_GRS or Standard_RAGRS.
     *
     * @var int
     */
    public $lastGeoFailoverTime;

    /**
     * The location of the geo-replicated secondary for the storage account.
     * Only available if the accountType is Standard_GRS or Standard_RAGRS.
     *
     * @var string
     */
    public $secondaryLocation;

    /**
     * Indicates whether the secondary location of the storage account is available or unavailable.
     * Only available if the accountType is Standard_GRS or Standard_RAGRS.
     * values: available|unavailable
     *
     * @var string
     */
    public $statusOfSecondary;

    /**
     * The URLs that are used to perform a retrieval of a public blob, queue or table object from the secondary storage account:
     * Example Format: [
     *      'blob'   => 'https://{accountName}-secondary.blob.core.windows.net/',
     *      'queue'  => 'https://{accountName}-secondary.queue.core.windows.net/',
     *      'table'  => 'https://{accountName}-secondary.table.core.windows.net/'
     * ]
     *
     * @var array
     */
    public $secondaryEndpoints;

    /**
     * Creation date and time of the storage account in UTC
     *
     * @var string
     */
    public $creationTime;

    /**
     * User assigned custom domain to this storage account.
     * Example Format: ['name' => "value"]
     *
     * @var array
     */
    public $customDomain;

    /**
     * Constructor
     *
     * @param   string     $accountType      Storage account type
     */
    public function __construct($accountType)
    {
        $this->accountType = $accountType;
    }

}