<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Exception;
use \Scalr_Account;
use \Scalr_Environment;
use \DBFarm;
use \SERVER_PROPERTIES;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

class Update20150123100257 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ef5a42be-2e24-4840-9edc-3b412e535cd6';

    protected $depends = [];

    protected $description = 'Hosted Scalr CA phase 3 init';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        $refuse = !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
        if (!$refuse && !\Scalr::isHostedScalr()) {
            $refuse = "This upgrade is intended only for Hosted Scalr installation";
        }
        return $refuse;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $analytics = \Scalr::getContainer()->analytics;

        if (!\Scalr::isHostedScalr()) {
            $this->console->warning("Terminating as this upgrade script is only for Hosted Scalr installation.");
            return;
        }

        $this->console->out("Creates default Cost Center for an each Account");

        $rs = $this->db->Execute("SELECT id FROM `clients`");

        while ($rec = $rs->FetchRow()) {
            try {
                $account = Scalr_Account::init()->loadById($rec['id']);
            } catch (Exception $e) {
                continue;
            }

            $this->console->out("Processing %s (%d) account...", $account->name, $account->id);

            //Whether the Account already has account level Cost Center assigned to it
            $ccs = $account->getCostCenters()->filterByAccountId($account->id);

            if (count($ccs) > 0) {
                //We assume that the account has already been initialized
                continue;
            }

            try {
                //Gets account owner user to be CC Lead
                $owner = $account->getOwner();
            } catch (Exception $e) {
                continue;
            }

            //Creates default Cost Center and Project
            $cc = $analytics->usage->createHostedScalrAccountCostCenter($account, $owner);

            //Associates default CC with the account
            $accountCc = new AccountCostCenterEntity($account->id, $cc->ccId);
            $accountCc->save();

            //Gets project entity
            /* @var $project ProjectEntity */
            $project = $cc->getProjects()[0];

            foreach ($this->db->GetAll("SELECT id FROM client_environments WHERE client_id = ?", [$account->id]) as $row) {
                try {
                    $environment = Scalr_Environment::init()->loadById($row['id']);
                } catch (Exception $e) {
                    continue;
                }

                $this->console->out("- Environment: %s (%d) CC: %s", $environment->name, $environment->id, $cc->ccId);

                //Creates association
                $environment->setPlatformConfig([Scalr_Environment::SETTING_CC_ID => $cc->ccId]);

                foreach ($this->db->GetAll("SELECT id FROM farms WHERE env_id = ?", [$environment->id]) as $r) {
                    try {
                        $farm = DBFarm::LoadByID($r['id']);
                    } catch (Exception $e) {
                        continue;
                    }

                    $this->console->out("- - Farm: %s (%d) Project: %s", $farm->Name, $farm->ID, $project->projectId);

                    //Associates farm with default Project
                    $farm->SetSetting(DBFarm::SETTING_PROJECT_ID, $project->projectId);

                    unset($farm);
                }

                $this->console->out("- Updating server properties for environment %s (%d)", $environment->name, $environment->id);

                $this->db->Execute("
                    INSERT `server_properties` (`server_id`, `name`, `value`)
                    SELECT s.`server_id`, ?, ? FROM `servers` s WHERE s.env_id = ?
                    ON DUPLICATE KEY UPDATE `value` = ?
                ", [
                    SERVER_PROPERTIES::FARM_PROJECT_ID,
                    $project->projectId,
                    $environment->id,
                    $project->projectId
                ]);

                $this->db->Execute("
                    INSERT `server_properties` (`server_id`, `name`, `value`)
                    SELECT s.`server_id`, ?, ? FROM `servers` s WHERE s.env_id = ?
                    ON DUPLICATE KEY UPDATE `value` = ?
                ", [
                    SERVER_PROPERTIES::ENV_CC_ID,
                    $cc->ccId,
                    $environment->id,
                    $cc->ccId
                ]);

                unset($environment);
            }

            unset($ccs);
            unset($owner);
            unset($account);
        }
    }
}