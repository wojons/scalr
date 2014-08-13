<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Tag link entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (02.07.2014)
 *
 * @Entity
 * @Table(name="tag_link")
 */
class TagLink extends AbstractEntity
{
    /**
     * Tag's ID
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $tagId;

    /**
     * Resource name (max 32 chars)
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $resource;

    /**
     * Resource ID
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $resourceId;

    public function getName()
    {
        $tag = Tag::findPk($this->tagId);
        /* @var Tag $tag */
        return $tag ? $tag->name : '*Unknown tag*';
    }
}
