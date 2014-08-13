<?php
namespace Scalr\Service\CloudStack\DataType;

use \DateTime;

/**
 * ListEventsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListEventsData extends AbstractDataType
{

    /**
     * List resources by account. Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * List only resources belonging to the domain specified
     *
     * @var string
     */
    public $domainid;

    /**
     * The duration of the event
     *
     * @var string
     */
    public $duration;

    /**
     * The end date range of the list you want to retrieve
     * (use format "yyyy-MM-dd" or the new format "yyyy-MM-dd HH:mm:ss")
     *
     * @var DateTime
     */
    public $enddate;

    /**
     * The time the event was entered
     *
     * @var string
     */
    public $entrytime;

    /**
     * The ID of the event
     *
     * @var string
     */
    public $id;

    /**
     * Defaults to false,
     * but if true, lists all resources from the parent specified by the domainId till leaves.
     *
     * @var string
     */
    public $isrecursive;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * The event level (INFO, WARN, ERROR)
     *
     * @var string
     */
    public $level;

    /**
     * If set to false, list only resources belonging to the command's caller;
     * if set to true - list resources that the caller is authorized to see.
     * Default value is false
     *
     * @var string
     */
    public $listall;

    /**
     * The start date range of the list you want to retrieve
     * (use format "yyyy-MM-dd" or the new format "yyyy-MM-dd HH:mm:ss")
     *
     * @var DateTime
     */
    public $startdate;

    /**
     * The event type (see event types)
     *
     * @var string
     */
    public $type;

}