<?php

namespace Scalr\Model\Entity;

/**
 * Client Setting entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="client_settings")
 */
class ClientSetting extends Setting
{

    /**
     * Farm identifier
     *
     * @Id
     * @Column(name="clientid",type="integer")
     * @var int
     */
    public $clientId;

    /**
     * @Id
     * @Column(name="key",type="string")
     * @var string
     */
    public $name;
}