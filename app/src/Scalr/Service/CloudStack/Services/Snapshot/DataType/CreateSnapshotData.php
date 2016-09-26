<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateSnapshotData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateSnapshotData extends AbstractDataType
{

    /**
     * Required
     * The ID of the disk volume
     *
     * @var string
     */
    public $volumeid;

    /**
     * The account of the snapshot.
     * The account parameter must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * The domain ID of the snapshot.
     * If used with the account parameter, specifies a domain for the account associated with the disk volume.
     *
     * @var string
     */
    public $domainid;

    /**
     * Policy id of the snapshot, if this is null, then use MANUAL_POLICY.
     *
     * @var string
     */
    public $policyid;

    /**
     * Quiesce vm if true
     *
     * @var string
     */
    public $quiescevm;

    /**
     * Constructor
     *
     * @param   string  $volumeid    The ID of the disk volume
     */
    public function __construct($volumeid)
    {
        $this->volumeid = $volumeid;
    }

}
