<?php
namespace Scalr\Service\CloudStack\Services\Template\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListTemplatesData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListTemplatesData extends AbstractDataType
{

    /**
     * Required
     * Possible values are "featured", "self", "selfexecutable","sharedexecutable","executable", and "community".
     * featured : templates that have been marked as featured and public.
     * self : templates that have been registered or created by the calling user.
     * selfexecutable : same as self, but only returns templates that can be used to deploy a new VM.
     * sharedexecutable : templates ready to be deployed that have been granted to the calling user by another user.
     * executable : templates that are owned by the calling user, or public templates, that can be used to deploy a VM.
     * community : templates that have been marked as public but not featured.
     * all : all templates (only usable by admins).
     *
     * @var string
     */
    public $templatefilter;

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
     * The hypervisor for which to restrict the search
     *
     * @var string
     */
    public $hypervisor;

    /**
     * The template ID
     *
     * @var string
     */
    public $id;

    /**
     * Defaults to false, but if true, lists all resources from the parent specified by the domainId till leaves.
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
     * If set to false, list only resources belonging to the command's caller;
     * if set to true - list resources that the caller is authorized to see.
     * Default value is false
     *
     * @var string
     */
    public $listall;

    /**
     * The template name
     *
     * @var string
     */
    public $name;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

    /**
     * Show removed templates as well
     *
     * @var string
     */
    public $showremoved;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * List templates by zoneId
     *
     * @var string
     */
    public $zoneid;

    /**
     * Constructor
     *
     * @param   string  $templatefilter    Possible values are "featured", "self", "selfexecutable","sharedexecutable","executable", and "community".
     */
    public function __construct($templatefilter)
    {
        $this->templatefilter = $templatefilter;
    }

}
