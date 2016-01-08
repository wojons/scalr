<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Exception;
use Scalr\Modules\PlatformFactory;
use Scalr_Environment;
use SERVER_PLATFORMS;

class Update20150401154148 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '65a09e51-a2c0-4461-837f-db65d8cd11b3';

    protected $depends = [];

    protected $description = 'AWS tags && Openstack metadata refactoring';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    protected $deprecatedTags = [
        'scalr-env-id'       => '{SCALR_ENV_ID}',
        'scalr-owner'        => '{SCALR_FARM_OWNER_EMAIL}',
        'scalr-farm-id'      => '{SCALR_FARM_ID}',
        'scalr-farm-role-id' => '{SCALR_FARM_ROLE_ID}',
        'scalr-server-id'    => '{SCALR_SERVER_ID}'
    ];

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
        return $this->hasTable('client_environment_properties');
    }

    private function getEnabledPlatforms(Scalr_Environment $environment)
    {
        $enabled = [];

        foreach (array_keys(SERVER_PLATFORMS::getList()) as $platform) {
            $value = $this->db->GetOne("
                SELECT value
                FROM client_environment_properties
                WHERE env_id = ? AND `group` = ''
                AND name = ?
                LIMIT 1
            ", [$environment->id, $platform . '.is_enabled']);

            if (!empty($value)) {
                $enabled[] = $platform;
            }
        }

        return $enabled;
    }

    protected function run1($stage)
    {
        foreach ($this->db->GetAll("SELECT id FROM client_environments") as $row) {
            try {
                $environment = Scalr_Environment::init()->loadById($row['id']);
            } catch (Exception $e) {
                continue;
            }

            foreach ($this->getEnabledPlatforms($environment) as $platform) {
                if ($platform == SERVER_PLATFORMS::EC2 || PlatformFactory::isOpenstack($platform)) {
                    $paramName = $platform == SERVER_PLATFORMS::EC2 ? 'aws.tags' : 'openstack.tags';
                    $nameTagValue = null;

                    $row = $this->db->GetRow("SELECT * FROM `governance` WHERE env_id = ? AND category = ? AND name = ?", array(
                        $environment->id,
                        $platform,
                        $paramName
                    ));

                    $newValue = [
                        'value'                 => $this->deprecatedTags,
                        'allow_additional_tags' => 1
                    ];

                    if ($row) {
                        if ($row['enabled'] == 1) {
                            $value = json_decode($row['value'], true);

                            foreach ((array)$value['value'] as $k => $v) {
                                if ($platform == SERVER_PLATFORMS::EC2 && $k == 'Name') {
                                    $nameTagValue = $v;
                                    continue;
                                }

                                if (!isset($newValue['value'][$k])) {
                                    $newValue['value'][$k] = $v;
                                }
                            }

                            $newValue['allow_additional_tags'] = $value['allow_additional_tags'] == 1 ? 1 : 0;
                        }
                    }

                    $newValue = json_encode($newValue);

                    $this->db->Execute("
                        INSERT INTO `governance`
                        SET `env_id` = ?,
                            `category` = ?,
                            `name` = ?,
                            `value` = ?,
                            `enabled` = ?
                        ON DUPLICATE KEY UPDATE
                            `value` = ?,
                            `enabled` = ?
                    ", array(
                        $environment->id,
                        $platform,
                        $paramName,
                        $newValue,
                        1,

                        $newValue,
                        1
                    ));

                    if ($nameTagValue) {
                        $nameTagValue = json_encode(['value' => $nameTagValue]);

                        $this->db->Execute("
                            INSERT INTO `governance`
                            SET `env_id` = ?,
                                `category` = ?,
                                `name` = ?,
                                `value` = ?,
                                `enabled` = ?
                            ON DUPLICATE KEY UPDATE
                                `value` = ?,
                                `enabled` = ?
                        ", array(
                            $environment->id,
                            $platform,
                            'aws.instance_name_format',
                            $nameTagValue,
                            1,

                            $nameTagValue,
                            1
                        ));
                    }
                }
            }
        }
    }
}