<?php
namespace Scalr\Service\CloudStack\DataType;

use DateTime;

/**
 * TemplateResponseData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with network
 *
 * @property  \Scalr\Service\CloudStack\DataType\DetailsData      $details
 * Additional key/value details tied with template
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class TemplateResponseData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags', 'details');

    /**
     * The template ID
     *
     * @var string
     */
    public $id;

    /**
     * The template ID
     *
     * @var string
     */
    public $account;

    /**
     * The account id to which the template belongs
     *
     * @var string
     */
    public $accountid;

    /**
     * True if the ISO is bootable, false otherwise
     *
     * @var string
     */
    public $bootable;

    /**
     * Checksum of the template
     *
     * @var string
     */
    public $checksum;

    /**
     * The date this template was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * True if the template is managed across all Zones, false otherwise
     *
     * @var string
     */
    public $crossZones;

    /**
     * The template display text
     *
     * @var string
     */
    public $displaytext;

    /**
     * The name of the domain to which the template belongs
     *
     * @var string
     */
    public $domain;

    /**
     * The ID of the domain to which the template belongs
     *
     * @var string
     */
    public $domainid;

    /**
     * The format of the template.
     *
     * @var string
     */
    public $format;

    /**
     * The ID of the secondary storage host for the template
     *
     * @var string
     */
    public $hostid;

    /**
     * The name of the secondary storage host for the template
     *
     * @var string
     */
    public $hostname;

    /**
     * The hypervisor on which the template runs
     *
     * @var string
     */
    public $hypervisor;

    /**
     * True if template contains XS/VMWare tools inorder to support dynamic scaling of VM cpu/memory
     *
     * @var string
     */
    public $isdynamicallyscalable;

    /**
     * True if the template is extractable, false otherwise
     *
     * @var string
     */
    public $isextractable;

    /**
     * True if this template is a featured template, false otherwise
     *
     * @var string
     */
    public $isfeatured;

    /**
     * True if this template is a public template, false otherwise
     *
     * @var string
     */
    public $ispublic;

    /**
     * True if the template is ready to be deployed from, false otherwise.
     *
     * @var string
     */
    public $isready;

    /**
     * The template name
     *
     * @var string
     */
    public $name;

    /**
     * The ID of the OS type for this template.
     *
     * @var string
     */
    public $ostypeid;

    /**
     * The name of the OS type for this template.
     *
     * @var string
     */
    public $ostypename;

    /**
     * True if the reset password feature is enabled, false otherwise
     *
     * @var string
     */
    public $passwordenabled;

    /**
     * The project name of the template
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the template
     *
     * @var string
     */
    public $projectid;

    /**
     * The date this template was removed
     *
     * @var string
     */
    public $removed;

    /**
     * The size of the template
     *
     * @var string
     */
    public $size;

    /**
     * The template ID of the parent template if present
     *
     * @var string
     */
    public $sourcetemplateid;

    /**
     * True if template is sshkey enabled, false otherwise
     *
     * @var string
     */
    public $sshkeyenabled;

    /**
     * The status of the template
     *
     * @var string
     */
    public $status;

    /**
     * The tag of this template
     *
     * @var string
     */
    public $templatetag;

    /**
     * The type of the template
     *
     * @var string
     */
    public $templatetype;

    /**
     * The ID of the zone for this template
     *
     * @var string
     */
    public $zoneid;

    /**
     * The name of the zone for this template
     *
     * @var string
     */
    public $zonename;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  TemplateResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }

    /**
     * Sets details
     *
     * @param   DetailsData $details
     * @return  TemplateResponseData
     */
    public function setDetails(DetailsData $details = null)
    {
        return $this->__call(__FUNCTION__, array($details));
    }

}