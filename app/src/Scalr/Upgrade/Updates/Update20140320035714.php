<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfigEndpoint;
use Scalr\Model\Entity\WebhookConfig;
use Scalr\Model\Entity\WebhookConfigEvent;
use Scalr\Model\Entity\WebhookConfigFarm;

class Update20140320035714 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'cc7f0f71-f771-4840-96ec-7d6c68da9e8a';

    protected $depends = array();

    protected $description = 'Initializing webhooks system';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    /**
     * Checks whether the update of the stage ONE is applied.
     *
     * Verifies whether current update has already been applied to this install.
     * This ensures avoiding the duplications. Implementation of this method should give
     * the definite answer to question "has been this update applied or not?".
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the update has already been applied.
     */
    protected function isApplied1($stage)
    {
        return false;
    }

    /**
     * Validates an environment before it will try to apply the update of the stage ONE.
     *
     * Validates current environment or inspects circumstances that is expected to be in the certain state
     * before the update is applied. This method may not be overridden from AbstractUpdate class
     * which means current update is always valid.
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the environment meets the requirements.
     */
    protected function validateBefore1($stage)
    {
        return true;
    }

    /**
     * Performs upgrade literally for the stage ONE.
     *
     * Implementation of this method performs update steps needs to be taken
     * to accomplish upgrade successfully.
     *
     * If there are any error during an execution of this scenario it must
     * throw an exception.
     *
     * @param   int  $stage  optional The stage number
     * @throws  \Exception
     */
    protected function run1($stage)
    {
        $observers = $this->db->Execute("SELECT * FROM farm_event_observers WHERE event_observer_name = 'MailEventObserver'");
        while ($observer = $observers->FetchRow()) {
            $dbFarm = \DBFarm::LoadByID($observer['farmid']);

            // Create endpoint
            $endpointId = $this->db->GetOne("SELECT endpoint_id FROM webhook_endpoints WHERE env_id = ? AND url = ?", array(
                $dbFarm->EnvID,
                'SCALR_MAIL_SERVICE'
            ));
            if ($endpointId) {
                $endpoint = WebhookEndpoint::findPk(bin2hex($endpointId));
            } else {
                $endpoint = new WebhookEndpoint();
                $endpoint->level = WebhookEndpoint::LEVEL_ENVIRONMENT;
                $endpoint->accountId = $dbFarm->ClientID;
                $endpoint->envId = $dbFarm->EnvID;
                $endpoint->securityKey = \Scalr::GenerateRandomKey(64);
                $endpoint->isValid = true;
                $endpoint->url = "SCALR_MAIL_SERVICE";
                $endpoint->save();
            }

            //Create webhook configuration
            $webhook = new WebhookConfig();
            $webhook->level = WebhookConfig::LEVEL_ENVIRONMENT;
            $webhook->accountId = $dbFarm->ClientID;
            $webhook->envId = $dbFarm->EnvID;

            $webhook->name = "MailEventObserver(FarmID: {$dbFarm->ID})";
            $webhook->postData = $this->db->GetOne("SELECT value FROM farm_event_observers_config WHERE `key` = ? AND observerid = ?", array(
                'EventMailTo',
                $observer['id']
            ));
            $webhook->save();

            //save endpoints
            $configEndpoint = new WebhookConfigEndpoint();
            $configEndpoint->webhookId = $webhook->webhookId;
            $configEndpoint->setEndpoint($endpoint);
            $configEndpoint->save();

            //save events
            $dbEvents = $this->db->Execute("SELECT * FROM farm_event_observers_config WHERE `key` LIKE '%Notify' AND observerid = ?", array(
                $observer['id']
            ));
            while ($info = $dbEvents->FetchRow()) {
                preg_match('/On([A-Za-z0-9]+)Notify/si', $info['key'], $matches);
                $configEvent = new WebhookConfigEvent();
                $configEvent->webhookId = $webhook->webhookId;
                $configEvent->eventType = $matches[1];
                $configEvent->save();
            }

            //save farms
            $configFarm = new WebhookConfigFarm();
            $configFarm->webhookId = $webhook->webhookId;
            $configFarm->farmId = $dbFarm->ID;
            $configFarm->save();
        }
    }
}