<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140306123554 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'f494ebac-0df3-4c0f-bee0-9f0af0485f48';

    protected $depends = array('ff8cbdda-28a3-4f3b-bcdf-1ab338d018b3');

    protected $description = 'Convert params of widget monitoring';

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
        //implement validation whether the stage one has valid environment before update
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
        $dashboards = $this->db->Execute('SELECT user_id, env_id FROM account_user_dashboard');
        foreach ($dashboards as $keys) {
            try {
                $user = new \Scalr_Account_User();
                $user->loadById($keys['user_id']);
                $dash = $user->getDashboard($keys['env_id']);

                if (! (is_array($dash) &&
                    isset($dash['configuration']) && is_array($dash['configuration']) &&
                    isset($dash['flags']) && is_array($dash['flags'])
                )) {
                    // old configuration, remove it
                    $this->db->Execute('DELETE FROM account_user_dashboard WHERE user_id = ? AND env_id = ?', array($keys['user_id'], $keys['env_id']));
                    continue;
                }

                foreach ($dash['configuration'] as &$column) {
                    foreach ($column as &$widget) {
                        if ($widget['name'] == 'dashboard.monitoring') {
                            $metrics = array(
                                'CPUSNMP' => 'cpu',
                                'LASNMP' => 'la',
                                'NETSNMP' => 'net',
                                'ServersNum' => 'snum',
                                'MEMSNMP' => 'mem'
                            );

                            $params = array(
                                'farmId' => $widget['params']['farmid'],
                                'period' => $widget['params']['graph_type'],
                                'metrics' => $metrics[$widget['params']['watchername']],
                                'title' => $widget['params']['title'],
                                'hash' => $this->db->GetOne('SELECT hash FROM farms WHERE id = ?', array($widget['params']['farmid']))
                            );

                            if (stristr($widget['params']['role'], "INSTANCE_")) {
                                $ar = explode('_', $widget['params']['role']);
                                $params['farmRoleId'] = $ar[1];
                                $params['index'] = $ar[2];
                            } else {
                                if ($widget['params']['role'] != 'FARM' && $widget['params']['role'] != 'role') {
                                    $params['farmRoleId'] = $widget['params']['role'];
                                }
                            }

                            $widget['params'] = $params;
                        }
                    }
                }

                $user->setDashboard($keys['env_id'], $dash);
            } catch(\Exception $e) {
                $this->console->warning($e->getMessage());
            }
        }
    }
}
