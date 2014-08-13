<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Event definition
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="event_definitions")
 */
class EventDefinition extends AbstractEntity
{
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
     * Event's name
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Description
     *
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * The identifier of the client's account
     *
     * @Column(type="integer")
     * @var integer
     */
    public $accountId;

    /**
     * The identifier of the client's environment
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * @param $accountId
     * @param $envId
     * @return array [name => description]
     */
    public static function getList($accountId, $envId)
    {
        $retval = [];
        // TODO: before enabling accountId, fill column!!!
        foreach (self::find([/*['accountId' => $accountId], */['envId' => $envId]]) as $ev) {
            /* @var EventDefinition $ev */
            $retval[$ev->name] = $ev->description;
        }

        return $retval;
    }
}
