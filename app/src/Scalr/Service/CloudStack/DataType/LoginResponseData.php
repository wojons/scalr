<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * LoginResponseData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class LoginResponseData extends AbstractDataType
{

    /**
     * Username
     *
     * @var string
     */
    public $username;

    /**
     * User id
     *
     * @var string
     */
    public $userid;

    /**
     * Password
     *
     * @var string
     */
    public $password;

    /**
     * Domain ID that the user belongs to
     *
     * @var string
     */
    public $domainid;

    /**
     * The time period before the session has expired
     *
     * @var string
     */
    public $timeout;

    /**
     * The account name the user belongs to
     *
     * @var string
     */
    public $account;

    /**
     * First name of the user
     *
     * @var string
     */
    public $firstname;

    /**
     * Last name of the user
     *
     * @var string
     */
    public $lastname;

    /**
     * The account type (admin, domain-admin, read-only-admin, user)
     *
     * @var string
     */
    public $type;

    /**
     * User time zone
     *
     * @var string
     */
    public $timezone;

    /**
     * User time zone offset from UTC 00:00
     *
     * @var string
     */
    public $timezoneoffset;

    /**
     * Session key that can be passed in subsequent Query command calls
     *
     * @var string
     */
    public $sessionkey;

}