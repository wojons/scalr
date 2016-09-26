<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * SecurityGroupData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList    $tags
 * The list of resource tags associated with the rule
 *
 * @property  \Scalr\Service\CloudStack\DataType\EgressruleList      $egressrule
 * The list of egress rules associated with the security group
 *
 * @property  \Scalr\Service\CloudStack\DataType\IngressruleList     $ingressrule
 * The list of ingress rules associated with the security group
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class SecurityGroupData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('egressrule', 'ingressrule', 'tags');

    /**
     * The ID of the security group
     *
     * @var string
     */
    public $id;

    /**
     * The account owning the security group
     *
     * @var string
     */
    public $account;

    /**
     * The description of the security group
     *
     * @var string
     */
    public $description;

    /**
     * The domain name of the security group
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID of the security group
     *
     * @var string
     */
    public $domainid;

    /**
     * The name of the security group
     *
     * @var string
     */
    public $name;

    /**
     * The project name of the group
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the group
     *
     * @var string
     */
    public $projectid;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  SecurityGroupData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }

    /**
     * Sets egressrulet
     *
     * @param   EgressruleList  $egressrule
     * @return  SecurityGroupData
     */
    public function setEgressrule(EgressruleList  $egressrule = null)
    {
        return $this->__call(__FUNCTION__, array($egressrule));
    }

    /**
     * Sets ingressrule
     *
     * @param   IngressruleList  $ingressrule
     * @return  SecurityGroupData
     */
    public function setIngressrule(IngressruleList $ingressrule = null)
    {
        return $this->__call(__FUNCTION__, array($ingressrule));
    }
}