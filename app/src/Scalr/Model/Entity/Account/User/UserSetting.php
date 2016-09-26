<?php

namespace Scalr\Model\Entity\Account\User;

use Scalr\Model\Entity\AbstractSettingEntity;

/**
 * UserSetting entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0 (23.02.2015)
 *
 * @Entity
 * @Table(name="account_user_settings")
 */
class UserSetting extends AbstractSettingEntity
{
    const NAME_API_ACCESS_KEY = 'api.access_key';
    const NAME_API_SECRET_KEY = 'api.secret_key';
    const NAME_API_ENABLED = 'api.enabled';

    const NAME_RSS_LOGIN = 'rss.login';
    const NAME_RSS_PASSWORD = 'rss.password';

    //Last used environment
    const NAME_UI_ENVIRONMENT = 'ui.environment';
    const NAME_UI_TIMEZONE = 'ui.timezone';
    const NAME_UI_STORAGE_TIME = 'ui.storage.time';
    const NAME_UI_ANNOUNCEMENT_TIME = 'ui.announcement.time';

    const NAME_GRAVATAR_EMAIL = 'gravatar.email';

    const NAME_LDAP_EMAIL = 'ldap.email';
    const NAME_LDAP_USERNAME = 'ldap.username';

    const NAME_LEAD_VERIFIED = 'lead.verified';
    const NAME_LEAD_HASH = 'lead.hash';

    const NAME_SECURITY_2FA_GGL = 'security.2fa.ggl';
    const NAME_SECURITY_2FA_GGL_KEY = 'security.2fa.ggl.key';
    const NAME_SECURITY_2FA_GGL_RESET_CODE = 'security.2fa.ggl.reset_code';

    /**
     * The unique identifier of the user.
     * It references to the user.id column.
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $userId;

    /**
     * The name of the setting
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * The value of the setting
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $value;

    /**
     * Constructor
     *
     * @param   string $userId  optional The identifier of the user
     * @param   string $name    optional The name of the setting
     * @param   string $value   optional The value of the setting
     */
    public function __construct($userId = null, $name = null, $value = null)
    {
        $this->userId = $userId;
        $this->name = $name;
        $this->value = $value === null ? null : (string)$value;
    }
}