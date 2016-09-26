<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * AffinityGroupData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class AffinityGroupData extends AbstractDataType
{

    /**
     * The ID of the affinity group
     *
     * @var string
     */
    public $id;

    /**
     * The account owning the affinity group
     *
     * @var string
     */
    public $account;

    /**
     * The description of the affinity group
     *
     * @var string
     */
    public $description;

    /**
     * The domain name of the affinity group
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID of the affinity group
     *
     * @var string
     */
    public $domainid;

    /**
     * The name of the affinity group
     *
     * @var string
     */
    public $name;

    /**
     * The type of the affinity group
     *
     * @var string
     */
    public $type;

    /**
     * Virtual machine Ids associated with this affinity group
     *
     * @var string
     */
    public $virtualmachineIds;

}