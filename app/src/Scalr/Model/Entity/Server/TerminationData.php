<?php

namespace Scalr\Model\Entity\Server;

use Scalr\Model\AbstractEntity;

/**
 * Servers termination data entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="servers_termination_data")
 */
class TerminationData extends AbstractEntity
{

    /**
     * Server UUID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * Request URL
     *
     * @Column(type="string")
     * @var string
     */
    public $requestUrl;

    /**
     * Request query
     *
     * @Column(type="string")
     * @var string
     */
    public $requestQuery;

    /**
     * HTTP request
     *
     * @Column(type="string")
     * @var string
     */
    public $request;

    /**
     * HTTP response code
     *
     * @Column(type="integer")
     * @var int
     */
    public $responseCode;

    /**
     * HTTP response status
     *
     * @Column(type="string")
     * @var string
     */
    public $responseStatus;

    /**
     * HTTP response
     *
     * @Column(type="string")
     * @var string
     */
    public $response;
}