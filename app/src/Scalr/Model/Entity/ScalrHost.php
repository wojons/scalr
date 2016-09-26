<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * ScalrHost entity
 *
 * @author   Roman Kondratuk  <r.kondratuk@scalr.com>
 * @since    5.11.9 (11.02.2016)
 *
 * @Entity
 * @Table(name="scalr_hosts")
 */
class ScalrHost extends AbstractEntity
{
    /**
     * The name of the Scalr host
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $host;

    /**
     * Scalr version
     *
     * @Column(type="string")
     * @var string
     */
    public $version;

    /**
     * Scalr edition
     *
     * @Column(type="string")
     * @var string
     */
    public $edition;

    /**
     * Last git commit
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $gitCommit;

    /**
     * Date of last git commit
     *
     * @Column(type="datetime",nullable=true)
     * @var \DateTime
     */
    public $gitCommitAdded;
}
