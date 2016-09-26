<?php
namespace Scalr\Service\CloudStack\DataType;

use DateTime;

/**
 * EventResponseData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class EventResponseData extends AbstractDataType
{

    /**
     * The ID of the event
     *
     * @var string
     */
    public $id;

    /**
     * The account name for the account that owns the object being acted on in the event
     * (e.g. the owner of the virtual machine, ip address, or security group)
     *
     * @var string
     */
    public $account;

    /**
     * The date the event was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * A brief description of the event
     *
     * @var string
     */
    public $description;

    /**
     * The name of the account's domain
     *
     * @var string
     */
    public $domain;

    /**
     * The id of the account's domain
     *
     * @var string
     */
    public $domainid;

    /**
     * The event level (INFO, WARN, ERROR)
     *
     * @var string
     */
    public $level;

    /**
     * Whether the event is parented
     *
     * @var string
     */
    public $parentid;

    /**
     * The project name of the address
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the ipaddress
     *
     * @var string
     */
    public $projectid;

    /**
     * The state of the event
     *
     * @var string
     */
    public $state;

    /**
     * The type of the event (see event types)
     *
     * @var string
     */
    public $type;

    /**
     * The name of the user who performed the action
     * (can be different from the account if an admin is performing an action for a user,
     * e.g. starting/stopping a user's virtual machine)
     *
     * @var string
     */
    public $username;

}