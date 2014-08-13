<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Modules\PlatformFactory;

class Update20140213144218 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '552ab6e0-1108-4094-b725-33a4d8d449be';

    protected $depends = array();

    protected $description = 'Creates table for security group rules comments';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('security_group_rules_comments');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Creating security_group_rules_comments table");
        $this->db->Execute("
            CREATE TABLE `security_group_rules_comments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `env_id` int(11) NOT NULL,
                `platform` varchar(20) NOT NULL,
                `cloud_location` varchar(50) NOT NULL,
                `vpc_id` VARCHAR(20) NOT NULL,
                `group_name` varchar(255) NOT NULL,
                `rule` varchar(255) NOT NULL,
                `comment` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_main` (`env_id`,`platform`,`cloud_location`, `vpc_id`, `group_name`, `rule`),
                CONSTRAINT `FK_security_group_rules_comments_env_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('security_group_rules_comments');
    }

    protected function run2($stage)
    {
        $envIds = $this->db->GetCol('SELECT DISTINCT env_id FROM `comments` WHERE env_id > 0');
        $this->console->out("Environments to process: " . count($envIds));
        foreach ($envIds as $index => $envId) {
            if ($this->db->GetOne('SELECT 1 FROM `security_group_rules_comments` WHERE env_id = ? LIMIT 1', array($envId))) {
                $this->console->out("Skip environment #{$index}(" . $envId . ")");
                continue;
            }
            try {
                $env = \Scalr_Environment::init()->loadById($envId);
            } catch (\Exception $e) {
                continue;
            }

            $locations = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::EC2)->getLocations($env);

            $container = \Scalr::getContainer();
            $container->environment = $env;

            foreach ($locations as $location => $locatonName) {
                try {
                    $sgList = $env->aws($location)->ec2->securityGroup->describe();
                } catch (\Exception $e) {
                    continue 2;
                }
                /* @var $sg SecurityGroupData */
                foreach ($sgList as $sg) {
                    $rules = array();
                    foreach ($sg->ipPermissions as $rule) {
                        /* @var $ipRange IpRangeData */
                        foreach ($rule->ipRanges as $ipRange) {
                            $rules[] = "{$rule->ipProtocol}:{$rule->fromPort}:{$rule->toPort}:{$ipRange->cidrIp}";
                        }
                        /* @var $group UserIdGroupPairData */
                        foreach ($rule->groups as $group) {
                            $ruleSg =  $group->userId . '/' . ($group->groupName ? $group->groupName : $group->groupId);
                            $rules[] = "{$rule->ipProtocol}:{$rule->fromPort}:{$rule->toPort}:{$ruleSg}";
                        }
                    }
                    foreach ($rules as $rule) {
                        $comment = $this->db->GetOne('SELECT comment FROM `comments` WHERE env_id = ? AND sg_name = ? AND rule = ? LIMIT 1', array($envId, $sg->groupName, $rule));
                        if ($comment) {
                            try {
                                $this->db->Execute("
                                    INSERT IGNORE `security_group_rules_comments` (`env_id`, `platform`, `cloud_location`, `vpc_id`, `group_name`, `rule`, `comment`)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ", array(
                                    $env->id,
                                    \SERVER_PLATFORMS::EC2,
                                    $location,
                                    $sg->vpcId,
                                    $sg->groupName,
                                    $rule,
                                    $comment
                                ));
                            } catch (\Exception $e) {}
                        }
                    }
                }
            }
            $this->console->out("Environment processed: #{$index}(" . $envId . ")");
        }
    }

}