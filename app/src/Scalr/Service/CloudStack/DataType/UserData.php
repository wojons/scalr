<?php
namespace Scalr\Service\CloudStack\DataType;

use DateTime;
/**
 * UserData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class UserData extends AbstractDataType
{

    /**
     * The user ID
     *
     * @var string
     */
    public $id;

    /**
     * The account name of the user
     *
     * @var string
     */
    public $account;

    /**
     * The account ID of the user
     *
     * @var string
     */
    public $accountid;

    /**
     * The account type of the user
     *
     * @var string
     */
    public $accounttype;

    /**
     * The api key of the user
     *
     * @var string
     */
    public $apikey;

    /**
     * The date and time the user account was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * The domain name of the user
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID of the user
     *
     * @var string
     */
    public $domainid;

    /**
     * The user email address
     *
     * @var string
     */
    public $email;

    /**
     * The user firstname
     *
     * @var string
     */
    public $firstname;

    /**
     * The boolean value representing if the updating target is in caller's child domain
     *
     * @var string
     */
    public $iscallerchilddomain;

    /**
     * True if user is default, false otherwise
     *
     * @var string
     */
    public $isdefault;

    /**
     * The user lastname
     *
     * @var string
     */
    public $lastname;

    /**
     * The secret key of the user
     *
     * @var string
     */
    public $secretkey;

    /**
     * The user state
     *
     * @var string
     */
    public $state;

    /**
     * The timezone user was created in
     *
     * @var string
     */
    public $timezone;

    /**
     * The user name
     *
     * @var string
     */
    public $username;

}