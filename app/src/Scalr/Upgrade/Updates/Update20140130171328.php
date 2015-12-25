<?php

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity;

/**
 * Initializes farms derived properties for running servers.
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    4.5.2 (30.01.2014)
 */
class Update20140130171328 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '7ec5da23-3311-4ab4-a3c4-d3101759ef16';

    protected $depends = array();

    protected $description = 'Initializes farm derived properties for running servers.';

    protected $ignoreChanges = true;

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
        $rs = $this->db->Execute("
            SELECT s.server_id, s.farm_id
            FROM servers s
            JOIN farms f ON f.id = s.farm_id
            WHERE s.status IN ('Running', 'Pending launch', 'Pending terminate')
        ");
        while ($rec = $rs->FetchRow()) {
            try {
                $dbServer = \DBServer::LoadByID($rec['server_id']);
                $farm = \DBFarm::LoadByID($rec['farm_id']);
                $environment = $dbServer->GetEnvironmentObject();
                $dbServer->SetProperties(array(
                    \SERVER_PROPERTIES::FARM_CREATED_BY_ID    => $farm->createdByUserId,
                    \SERVER_PROPERTIES::FARM_CREATED_BY_EMAIL => $farm->createdByUserEmail,
                    \SERVER_PROPERTIES::FARM_PROJECT_ID       => $farm->GetSetting(Entity\FarmSetting::PROJECT_ID),
                    \SERVER_PROPERTIES::ENV_CC_ID             => $environment->getPlatformConfigValue(\Scalr_Environment::SETTING_CC_ID),
                ));
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}