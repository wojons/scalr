<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * TemplatePermissionsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class TemplatePermissionsData extends AbstractDataType
{

    /**
     * The template ID
     *
     * @var string
     */
    public $id;

    /**
     * The list of accounts the template is available for
     *
     * @var string
     */
    public $account;

    /**
     * The ID of the domain to which the template belongs
     *
     * @var string
     */
    public $domainid;

    /**
     * True if this template is a public template, false otherwise
     *
     * @var string
     */
    public $ispublic;

    /**
     * The list of projects the template is available for
     *
     * @var string
     */
    public $projectids;

}