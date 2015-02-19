<?php
namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * AccountTagEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (18.02.2014)
 * @Entity
 * @Table(name="account_tag_values",service="cadb")
 */
class AccountTagEntity extends \Scalr\Model\AbstractEntity
{
    /**
     * The identifier of the client's account
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * The identifier of the tag
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $tagId;

    /**
     * The value of the tag
     *
     * @Id
     * @var string
     */
    public $valueId;

    /**
     * The name of the tag value
     *
     * @var string
     */
    public $valueName;

    /**
     * Gets the name of the tag
     *
     * @param   mixed    $id          Identifier of the tag's value
     * @param   string   $tagId       Identifier of the tag
     * @param   int      $accountId   optional  Identifier of the account
     * @param   string   $ignoreCache optional TRUE if cache should be ignored, default value is false
     * @return  string   Returns the tag's value name
     */
    public static function fetchName($id, $tagId, $accountId = null, $ignoreCache = false)
    {
        static $cache;

        $name = null;

        $key = "$tagId|$id|$accountId";

        if (empty($id)) {
            $name = 'Unassigned resources';
        } else if ($ignoreCache || !isset($cache[$key])) {
            //Trying to find the name of the project in the tag values history
            $findParams = [['tagId' => $tagId], ['valueId' => $id]];

            if (!empty($accountId)) {
                $findParams[] = ['accountId' => $accountId];
            }

            if (null === ($pe = self::findOne($findParams))) {
                $name = $id;
            } else {
                $name = $pe->valueName;
            }

            if (!$ignoreCache) {
                $cache[$key] = $name;
            }
        } else {
            $name = $cache[$key];
        }

        return $name;
    }
}