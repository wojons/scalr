<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Modules\PlatformFactory;
/**
 * Cost Analytics Notifications service
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (09.06.2014)
 */
class Notifications
{

    /**
     * Analytics Database connection instance
     *
     * @var \ADODB_mysqli
     */
    protected $cadb;

    /**
     * Scalr Database connection instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Constructor
     *
     * @param \ADODB_mysqli $cadb Database connection instance
     */
    public function __construct($cadb)
    {
        $this->cadb = $cadb;
        $this->db = \Scalr::getContainer()->adodb;
    }

    /**
     * Gets all active financial admins
     *
     * @return   array  Returns all financial admins array(Scalr_Account_User)
     */
    public function getFinancialAdmins()
    {
        $rs = $this->db->Execute("SELECT id FROM account_users WHERE type = ? AND status = ?", [
           \Scalr_Account_User::TYPE_FIN_ADMIN,
           \Scalr_Account_User::STATUS_ACTIVE,
        ]);

        $result = [];

        while ($row = $rs->FetchRow()) {
            $user = \Scalr_Account_User::init()->loadById($row['id']);
            $result[$user->id] = $user;
        }

        return $result;
    }

    /**
     * Gets list of the emails of all financial admins
     *
     * @return   array
     */
    public function getFinancialAdminEmails()
    {
        $emails = [];
        foreach ($this->getFinancialAdmins() as $user) {
            /* @var $user \Scalr_Account_User */
            $emails[] = $user->getEmail();
        }
        return $emails;
    }

    /**
     * Raises onCloudAdd notification event
     *
     * @param   string              $platform    A platform name.
     * @param   \Scalr_Environment  $environment An environment object which cloud is created in.
     * @param   \Scalr_Account_User $user        An user who has created platform.
     */
    public function onCloudAdd($platform, $environment, $user)
    {
        $container = \Scalr::getContainer();
        $analytics = $container->analytics;

        //Nothing to do in case analytics is disabled
        if (!$analytics->enabled) return;

        if (!($environment instanceof \Scalr_Environment)) {
            $environment = \Scalr_Environment::init()->loadById($environment);
        }

        $pm = PlatformFactory::NewPlatform($platform);

        //Check if there are some price for this platform and endpoint url
        if (($endpointUrl = $pm->hasCloudPrices($environment)) === true) {
            return;
        }

        //Disabled or badly configured environment
        if ($endpointUrl === false && !in_array($platform, [\SERVER_PLATFORMS::EC2, \SERVER_PLATFORMS::GCE])) {
            return;
        }

        //Send a message to financial admin if there are not any price for this cloud
        $baseUrl = rtrim($container->config('scalr.endpoint.scheme') . "://" . $container->config('scalr.endpoint.host') , '/');

        //Disable notifications for hosted Scalr
        if (!\Scalr::isAllowedAnalyticsOnHostedScalrAccount($environment->clientId)) {
            return;
        }

        $emails = $this->getFinancialAdminEmails();

        //There isn't any financial admin
        if (empty($emails)) {
            return;
        }

        $emails = array_map(function($email) {
            return '<' . trim($email, '<>') . '>';
        }, $emails);

        try {
            $res = $container->mailer->sendTemplate(
                SCALR_TEMPLATES_PATH . '/emails/analytics_on_cloud_add.eml.php',
                [
                    'isEc2'             => ($platform == \SERVER_PLATFORMS::EC2),
                    'userEmail'         => $user->getEmail(),
                    'isSupported'       => in_array($platform, $analytics->prices->getSupportedClouds()),
                    'cloudName'         => \SERVER_PLATFORMS::GetName($platform),
                    'linkToPricing'     => $baseUrl . '/#/analytics/pricing?platform=' . urlencode($platform)
                                         . '&url=' . urlencode($endpointUrl === false ? '' : $analytics->prices->normalizeUrl($endpointUrl)),
                ],
                join(',', $emails)
            );
        } catch (\Exception $e) {
        }
    }
}