<?php

namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * ProjectPropertyEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.02.2014)
 * @Entity
 * @Table(name="project_properties")
 */
class ProjectPropertyEntity extends \Scalr\Model\AbstractEntity
{
    const NAME_BILLING_CODE = 'billing.code';

    const NAME_DESCRIPTION = 'description';

    /**
     * The email of the Project's lead
     */
    const NAME_LEAD_EMAIL = 'lead.email';

    /**
     * Cost centre identifier (UUID)
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $projectId;

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
