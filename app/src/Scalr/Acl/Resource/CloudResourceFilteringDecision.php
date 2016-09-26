<?php

namespace Scalr\Acl\Resource;

/**
 * Cloud Resource Filtering Decision object
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    03.09.2015
 */
class CloudResourceFilteringDecision
{
    /**
     * Environment Id
     *
     * @var int
     */
    public $envId;

    /**
     * Filtering options
     *
     * @var array
     */
    public $filter = [];

    /**
     * All resources are allowed
     *
     * @var boolean
     */
    public $all = false;

    /**
     * ACL Resource Mode identifier
     *
     * @var int
     */
    public $mode;

    /**
     * The list of the Farms the user has access
     *
     * @var array
     */
    public $managedFarms = [];

    /**
     * Ignore filter and do rowwise filtering
     *
     * @var bool
     */
    public $rowwise;

    /**
     * Whether it should return empty result set
     *
     * @var bool
     */
    public $emptySet;

    /**
     * Checks whether specified meta tag matches the filtering criteria
     *
     * @param  int     $scalrMetaTag   The tag
     * @return boolean Returns true when scalr-meta tag matches the filtering criteria
     */
    public function matchScalrMetaTag($scalrMetaTag)
    {
        if ($this->all) {
            return true;
        }

        if ($this->rowwise) {
            if (isset($scalrMetaTag) && strpos($scalrMetaTag, 'v1:' . $this->envId . ':') === 0) {
                @list(,,$fid) = explode(':', $scalrMetaTag);

                if (!$fid || !in_array($fid, $this->managedFarms)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether specified AWS resource has the scalr-meta tag and that
     * it matches the filtering criteria
     *
     * @param    mixed   $cloudResource  Cloud Resource object which has tagSet property
     * @return   boolean Returns true if tag matches the filtering criteria
     */
    public function matchAwsResourceTag($cloudResource)
    {
        if ($this->all) {
            return true;
        }

        if (empty($cloudResource->tagSet)) {
            return null;
        }

        $scalrMetaTag = null;
        foreach ($cloudResource->tagSet as $tag) {
            /* @var $tag ResourceTagSetData */
            if ($tag->key == \Scalr_Governance::SCALR_META_TAG_NAME) {
                $scalrMetaTag = $tag->value;
            }
        }

        return $this->matchScalrMetaTag($scalrMetaTag);
    }
}