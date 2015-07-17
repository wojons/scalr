<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Tag entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (02.07.2014)
 *
 * @Entity
 * @Table(name="tags")
 */
class Tag extends AbstractEntity
{
    const RESOURCE_SCRIPT = 'script';

    /**
     * ID
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * Tag's name
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Tag's accountId
     *
     * @Column(type="integer")
     * @var integer
     */
    public $accountId;

    /**
     * @param string $resource
     * @param int $resourceId
     * @return array
     */
    public static function getTags($resource, $resourceId)
    {
        $names = [];
        foreach (TagLink::find([['resource' => $resource],['resourceId' => $resourceId]]) as $l) {
            /* @var $l TagLink */
            $names[] = $l->getName();
        }

        return $names;
    }

    /**
     * @param $accountId
     * @return array
     */
    public static function getAll($accountId)
    {
        return array_map(function($t) { return $t->name; }, self::find([['accountId' => $accountId]])->getArrayCopy());
    }

    /**
     * @param array $tags
     * @param int $accountId
     * @param string $resource
     * @param int $resourceId
     */
    public static function setTags($tags, $accountId, $resource, $resourceId)
    {
        $tags = array_unique($tags);
        $tagId = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag) {
                $t = self::findOne([['name' => $tag], ['accountId' => $accountId]]);
                if (! $t) {
                    $t = new Tag();
                    $t->name = $tag;
                    $t->accountId = $accountId;
                    $t->save();
                }

                $tagId[] = $t->id;
            }
        }

        foreach (TagLink::find([['resource' => $resource],['resourceId' => $resourceId]]) as $l) {
            /* @var $l TagLink */
            if (! in_array($l->tagId, $tagId)) {
                $l->delete();
            } else {
                unset($tagId[array_search($l->tagId, $tagId)]);
            }
        }

        foreach ($tagId as $id) {
            $l = new TagLink();
            $l->tagId = $id;
            $l->resource = $resource;
            $l->resourceId = $resourceId;
            $l->save();
        }

        self::clearTags();
    }

    /**
     * @param string $resource
     * @param int $resourceId
     */
    public static function deleteTags($resource, $resourceId)
    {
        foreach (TagLink::find([['resource' => $resource],['resourceId' => $resourceId]]) as $l) {
            /* @var $l TagLink */
            $l->delete();
        }

        self::clearTags();
    }

    /**
     * Clear unused tags
     */
    public static function clearTags()
    {
        \Scalr::getDb()->Execute('DELETE FROM tags WHERE id NOT IN(SELECT tag_id FROM tag_link GROUP BY tag_id)');
    }
}
