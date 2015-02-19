<?php

namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Exception\AnalyticsException;
/**
 * ReportEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (23.06.2014)
 * @Entity
 * @Table(name="reports",service="cadb")
 */
class ReportEntity extends \Scalr\Model\AbstractEntity
{

    const SUBJECT_TYPE_CC = 1;

    const SUBJECT_TYPE_PROJECT = 2;

    const PERIOD_DAILY = 1;

    const PERIOD_WEEKLY = 2;

    const PERIOD_MONTHLY = 3;

    const PERIOD_QUARTELY = 4;

    const STATUS_ENABLED = 1;

    const STATUS_DISABLED = 0;

    /**
     * identifier (UUID)
     *
     * @Id
     * @GeneratedValue("UUID")
     * @Column(type="uuid")
     * @var string
     */
    public $uuid;

    /**
     * The type of the subject
     * 1 - CC , 2 - Project
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $subjectType;

    /**
     * Identifier of the CC or Project
     *
     * @Column(type="uuid",nullable=true)
     * @var string
     */
    public $subjectId;

    /**
     * Period
     *
     * @Column(type="integer")
     * @var int
     */
    public $period;

    /**
     * Recipients
     *
     * Comma separated list of the emails is allowed
     *
     * @var string
     */
    public $emails;

    /**
     * Status
     *
     * @Column(type="integer")
     * @var int
     */
    public $status;

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::save()
     */
    public function save()
    {
        if (empty($this->emails)) {
            throw new AnalyticsException(sprintf("Email must be set for the %s.", get_class($this)));
        }

        parent::save();
    }
}
