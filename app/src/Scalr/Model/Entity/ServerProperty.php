<?php

namespace Scalr\Model\Entity;

/**
 * Server Property entity
 *
 * @author  Igor Vodiasov <invar@scalr.com>
 * @since   5.11.6 (25.01.2016)
 *
 * @Entity
 * @Table(name="server_properties")
 */
class ServerProperty extends Setting
{
    /**
     * Server ID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $serverId;
}
