<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * ClientEnvironmentCloudCredential entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="environment_cloud_credentials")
 */
class EnvironmentCloudCredentials extends AbstractEntity
{

    /**
     * Environment id
     *
     * @Id
     * @Column(type="integer")
     *
     * @var
     */
    public $envId;

    /**
     * Environment cloud
     *
     * @Id
     * @Column(type="string")
     *
     * @var
     */
    public $cloud;

    /**
     * Linked cloud credentials id
     *
     * @Column(type="uuidShort")
     *
     * @var
     */
    public $cloudCredentialsId;
}