<?php
namespace Scalr\Model\Entity\Account\User;

use Scalr\Model\AbstractEntity;

/**
 * ApiKeyEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4 (17.02.2015)
 * @Entity
 * @Table(name="account_user_apikeys")
 */
class ApiKeyEntity extends AbstractEntity
{
    /**
     * The unique identifier of the key
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="AccessKey")
     * @var string
     */
    public $keyId;

    /**
     * Key name
     *
     * @Column(type="string")
     * @var string
     */
    public $name = '';

    /**
     * The identifier of the user
     *
     * @Column(type="integer")
     * @var int
     */
    public $userId;

    /**
     * The secret key to sign signature
     *
     * @GeneratedValue("CUSTOM")
     * @Column(type="SecretKey")
     * @var string
     */
    public $secretKey;

    /**
     * Whether the key is active
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $active = true;

    /**
     * The timestamp when the record is created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $created;

    /**
     * The timestamp when the record was used last time
     *
     * @Column(name="last_used",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $lastUsed;

    /**
     * Constructor
     *
     * @param    int    $userId   optional The identifier of the user
     * @param    bool   $active   optional Whether the API key is active
     */
    public function __construct($userId = null, $active = null)
    {
        $this->userId = $userId;
        $this->active = is_null($active) ? true : (bool) $active;
        $this->created = new \DateTime('now');
    }
}