<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;

/**
 * Cost analytics tags
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (27.01.2014)
 */
class Tags
{
    /**
     * Cost Analytics database connection
     *
     * @var \ADODB_mysqli
     */
    protected $cadb;

    /**
     * Database instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Collection of the tags
     *
     * @var \ArrayObject
     */
    protected $collection;

    /**
     * Constructor
     *
     * @param \ADODB_mysqli $cadb Analytics database connection instance
     */
    public function __construct($cadb)
    {
        $this->cadb = $cadb;
        $this->db = \Scalr::getDb();
    }

    /**
     * Gets all tags
     *
     * @param    bool   $ignoreCache  optional Should it ignore cache or not
     * @return   \ArrayObject  Returns all tags
     */
    public function all($ignoreCache = false)
    {
        if ($this->collection === null || $ignoreCache) {
            $this->collection = new \ArrayObject(array());
            foreach (TagEntity::all() as $entity) {
                $this->collection[$entity->tagId] = $entity;
            }
        }
        return $this->collection;
    }

    /**
     * Gets a specified tag
     *
     * @param   int       $tagId       The identifier of the tag
     * @param   bool      $ignoreCache optional Should it ignore cache or not
     * @return  TagEntity Returns the TagEntity on success or null if it does not exist.
     */
    public function get($tagId, $ignoreCache = false)
    {
        $all = $this->all($ignoreCache);
        return isset($all[$tagId]) ? $all[$tagId] : null;
    }

    /**
     * Synchronizes the account level tag value
     *
     * It does not verify itself whether the cost analytics service is enabled
     *
     * @param   int     $accountId    The identifier of the client's account
     * @param   int     $tagId        The identifier of the clould analytics tag
     * @param   string  $valueId      The identifier of the tag's value
     * @param   string  $valueName    The name of the tag's value
     */
    public function syncValue($accountId, $tagId, $valueId, $valueName)
    {
        $tag = AccountTagEntity::findPk($accountId, $tagId, $valueId);
        if (!($tag instanceof AccountTagEntity)) {
            $tag = new AccountTagEntity();
            $tag->accountId = $accountId;
            $tag->tagId = $tagId;
            $tag->valueId = $valueId;
            $tag->valueName = $valueName;
        } else if ($tag->valueName != $valueName) {
            $tag->valueName = $valueName;
            if ($tagId == TagEntity::TAG_ID_FARM) {
                foreach ($this->db->GetAll("
                    SELECT fr.id AS farm_role_id, fr.alias
                    FROM farm_roles fr
                    WHERE fr.farmid = ?
                ", [$valueId]) as $v) {
                    //Updates all related farm roles
                    $this->syncValue($accountId, TagEntity::TAG_ID_FARM_ROLE, $v['farm_role_id'], sprintf('%s', $v['alias']));
                }
            }
        } else {
            $ignoreupdate = true;
        }
        if (!isset($ignoreupdate)) {
            $tag->save();
        }
    }
}