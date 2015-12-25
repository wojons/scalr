<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Client entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="clients")
 */
class Client extends AbstractEntity
{

    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Client short name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * Status
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $status;

    /**
     * Billed flag
     *
     * @Column(name="isbilled",type="boolean")
     * @var bool
     */
    public $billed = 0;

    /**
     * Due time
     *
     * @Column(name="dtdue",type="datetime",nullable=true)
     * @var DateTime
     */
    public $due;

    /**
     * Activ flag
     *
     * @Column(name="isactive",type="boolean")
     * @var bool
     */
    public $active = 0;

    /**
     * Client full name
     *
     * @Column(name="fullname",type="string",nullable=true)
     * @var string
     */
    public $fullName;

    /**
     * Client organization name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $org;

    /**
     * Client country
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $country;

    /**
     * Client state
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $state;

    /**
     * Client city
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $city;

    /**
     * Client ZIP-code
     *
     * @Column(name="zipcode",type="string",nullable=true)
     * @var string
     */
    public $zipCode;

    /**
     * Client address
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $address1;

    /**
     * Client address
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $address2;

    /**
     * Client phone
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $phone;

    /**
     * Client fax
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $fax;

    /**
     * Added time
     *
     * @Column(name="dtadded",type="datetime",nullable=true)
     * @var DateTime
     */
    public $added;

    /**
     * Welcome e-mail sent flag
     *
     * @Column(name="iswelcomemailsent",type="boolean")
     * @var bool
     */
    public $welcomeMailSent = false;

    /**
     * Login attempts
     *
     * @Column(type="integer")
     * @var int
     */
    public $loginAttempts = 0;

    /**
     * Last login attempt time
     *
     * @Column(name="dtlastloginattempt",type="datetime",nullable=true)
     * @var DateTime
     */
    public $lastLoginAttempt;

    /**
     * Comments
     *
     * @Column(type="string")
     * @var string
     */
    public $comments;

    /**
     * Priority
     *
     * @Column(type="integer",nullable=false)
     * @var int
     */
    public $priority = 0;

    /**
     * Checks account limits
     *
     * @param  string  $limitName
     * @param  integer $limitValue
     * @return boolean
     */
    public function checkLimit($limitName, $limitValue)
    {
        return Limit::findOne([
            ['accountId' => $this->id],
            ['name'      => $limitName],
        ])->check($limitValue);
    }
}