<?php

namespace Scalr\Model\Entity\Server;

use Scalr\Model\Entity\Setting;

/**
 * Server Property entity
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 *
 * @Entity
 * @Table(name="server_properties")
 */
class Property extends Setting
{

    /**
     * Server UUID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * Fetch all or specified properties for certain server(s)
     *
     * @param array|string $serverIds           UUID of server(s)
     * @param array        $properties optional Property names to fetch
     * @return Property[] Array of Property
     * @throws \InvalidArgumentException
     */
    public static function fetch($serverIds, array $properties = [])
    {
        $criteria = [];
        if (is_array($serverIds)) {
            $criteria[] = ["serverId" => ['$in' => $serverIds]];
        } elseif (is_string($serverIds)) {
            $criteria[] = ["serverId" => $serverIds];
        }
        if (empty($criteria)) {
            throw new \InvalidArgumentException("You must specify at least one server");
        }
        if (! empty($properties)) {
            $criteria[] = ["name" => ['$in' => $properties]];
        }
        return self::find($criteria);
    }
}
