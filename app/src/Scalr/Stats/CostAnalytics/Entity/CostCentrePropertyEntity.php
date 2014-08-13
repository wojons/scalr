<?php

namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * CostCentreEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (10.02.2014)
 * @Entity
 * @Table(name="cc_properties")
 */
class CostCentrePropertyEntity extends \Scalr\Model\AbstractEntity
{

    const NAME_BILLING_CODE = 'billing.code';

    const NAME_DESCRIPTION = 'description';

    /**
     * The email of the Cost centre's lead
     */
    const NAME_LEAD_EMAIL = 'lead.email';

    /**
     * Cost centre identifier (UUID)
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $ccId;

    /**
     * The name of the property
     *
     * @Id
     * @var string
     */
    public $name;

    /**
     * The value of the property for the cost centre
     *
     * @var string
     */
    public $value;
}
