<?php

class Scalr_UI_Controller_Operations extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'operationId';

    public function defaultAction($serverId, $operationId = null, $operation = null)
    {
        $this->detailsAction($serverId, $operationId, $operation);
    }

    /**
     * @param string serverId
     * @param string operationId optional
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function progressAction($serverId, $operationId = null)
    {
        $dbServer = DBServer::LoadByID($serverId);
        $this->user->getPermissions()->validate($dbServer);

        $info = $dbServer->scalarizr->operation->getStatus($operationId);
        var_dump($info);
    }

    private function getScalarizrPhaseName($eventName)
    {
        return sprintf("Wait for Agent %s phase to complete", $eventName);
    }

    /**
     * @param   string  $serverId
     * @param   string  $operationId    optional
     * @param   string  $operation      optional
     * @return  array
     * @throws Exception
     */
    public function getDetails($serverId, $operationId = null, $operation = null)
    {
        $dbServer = DBServer::LoadByID($serverId);

        if (!$dbServer)
            throw new Exception("Operation details not available yet.");

        $this->user->getPermissions()->validate($dbServer);

        $details = [];

        if ($operationId) {
            $opInfo = $dbServer->scalarizr->operation->getStatus($operationId);

            $info = $opInfo;

            $opStatus = 'In progress';
            $operation = $opInfo->name;

            switch ($opInfo->status) {
                case "in-progress":
                    $status = 'running';
                    break;
                case "failed":
                    $status = 'error';
                    $message = $opInfo->error;
                    $opStatus = 'Failed';
                    $download = true;
                    break;
                case "completed":
                    $status = 'complete';
                    $opStatus = 'Completed';
                    break;
            }

            $details[$opInfo->name] = array(
                'status' => $status,
                'message' => $message
            );
        } else if ($operation == 'Initialization') {
            /*
            + Provisioning Server
            + Booting OS
            + Waiting for the Scalarizr Agent to start
            + Agent HostInit phase
            + Agent BeforeHostUp phase
            + Agent HostUp phase
            + Done
             */
            $timeFormat = "H:i:s";
            $timings = $this->db->GetRow("SELECT * FROM servers_launch_timelog WHERE server_id = ?", array($dbServer->serverId));
            $messages = $this->db->Execute("SELECT * FROM `messages` WHERE `server_id` = ? AND (event_server_id = ? OR event_server_id IS NULL)",
                array($dbServer->serverId, $dbServer->serverId)
            );
            $msgInfo = [];
            while ($message = $messages->FetchRow())
                $msgInfo[$message['message_name']][$message['type']] = $message['status'];

            $details = [
                'Create Server record in Scalr' => ['status' => 'complete', 'timestamp' => Scalr_Util_DateTime::convertTz((int)$timings['ts_created'], $timeFormat)],
                'Provision Server in Cloud Platform' => ['status' => 'pending'],
                'Wait for OS to finish booting' => ['status' => 'pending'],
                'Wait for Scalarizr Agent to update and start' => ['status' => 'pending'],
                'HostInit' => ['status' => 'pending'],
                'BeforeHostUp' => ['status' => 'pending'],
                'HostUp' => ['status' => 'pending']
            ];

            $timeToBootOs = (int)$timings['ts_launched']+(int)$timings['time_to_boot'];
            $timeToBootScalarizr = (int)$timings['ts_launched']+(int)$timings['time_to_boot']+(int)$timings['time_to_hi'];
            $timeToProvisionServer = (int)$timings['ts_launched'] + ((int)$timings['ts_hi'] - $timeToBootOs);

            $isInitFailed = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED);

            foreach ($details as $phase => $d) {
                switch ($phase) {
                    case "Provision Server in Cloud Platform":
                        $launchError = $dbServer->GetProperty(SERVER_PROPERTIES::LAUNCH_ERROR);
                        if ($launchError) {
                            $details[$phase]['status'] = 'error';
                            $opStatus = 'Failed';
                            $details[$phase]['message'] = "Unable to launch instance:".  htmlspecialchars($launchError);
                            $download = true;
                            break 2;
                        } else {
                            if ($dbServer->status != SERVER_STATUS::PENDING_LAUNCH) {
                                if ($dbServer->GetRealStatus(true)->isPending()) {
                                    $details[$phase]['status'] = 'running';
                                    break 2;
                                }
                                else {
                                    $details[$phase]['status'] = 'complete';
                                    $details[$phase]['timestamp'] = Scalr_Util_DateTime::convertTz($timeToProvisionServer, $timeFormat);
                                }
                            } else
                                break 2;
                        }

                        break;
                    case "Wait for OS to finish booting":
                        if ($dbServer->status != SERVER_STATUS::PENDING || $isInitFailed) {
                            $details[$phase]['status'] = 'complete';
                            $details[$phase]['timestamp'] = Scalr_Util_DateTime::convertTz((int)$timeToBootOs, $timeFormat);
                        }
                        else {
                            $details[$phase]['status'] = 'running';
                            break 2 ;
                        }

                        break;

                    case "Wait for Scalarizr Agent to update and start":

                        if (isset($msgInfo['HostInit']['in']) || $dbServer->status == SERVER_STATUS::RUNNING || $isInitFailed) {
                            $details[$phase]['status'] = 'complete';
                            $details[$phase]['timestamp'] = Scalr_Util_DateTime::convertTz($timeToBootScalarizr, $timeFormat);
                        }
                        else {
                            $details[$phase]['status'] = 'running';
                            break 2 ;
                        }

                        break;

                    case "HostInit":
                    case "BeforeHostUp":
                    case "HostUp":

                        $newPhaseName = $this->getScalarizrPhaseName($phase);
                        $details[$newPhaseName] = $details[$phase];
                        unset($details[$phase]);

                        if ($phase == 'BeforeHostUp') {
                            if ($details[sprintf("Wait for Agent %s phase to complete", 'HostInit')]['status'] == 'complete')
                                $details[$newPhaseName]['status'] = 'running';
                        } elseif ($phase == 'HostUp') {
                            if ($details[sprintf("Wait for Agent %s phase to complete", 'BeforeHostUp')]['status'] == 'complete')
                                $details[$newPhaseName]['status'] = 'running';
                        }

                        if ($details[$newPhaseName]['status'] != 'error') {
                            if ($dbServer->status != SERVER_STATUS::RUNNING) {
                                if (isset($msgInfo[$phase]['in'])) {
                                    if ($msgInfo[$phase]['in'] == 0) {
                                        $details[$newPhaseName]['status'] = 'running';
                                        break 2 ;
                                    } elseif ($msgInfo[$phase]['in'] == 3) {
                                        $details[$newPhaseName]['status'] = 'error';
                                        $details[$newPhaseName]['message'] = "Unable to process inbound {$phase} message. Please contact your scalr administrator.";
                                        $download = true;
                                        $opStatus = 'Failed';
                                        break 2 ;
                                    }

                                    if (!isset($msgInfo[$phase]['out'])) {
                                        $details[$newPhaseName]['status'] = 'running';
                                        break 2 ;
                                    } else {
                                        if ($msgInfo[$phase]['out'] == 0) {
                                            $details[$newPhaseName]['status'] = 'running';
                                            break 2 ;
                                        } elseif ($msgInfo[$phase]['out'] == 1) {
                                            $details[$newPhaseName]['status'] = 'complete';
                                        } else {
                                            $details[$newPhaseName]['status'] = 'error';
                                            $details[$newPhaseName]['message'] = "Unable to deliver outbound {$phase} message. Please check connectivity between Scalr and Scalarizr (instance)";
                                            $download = true;
                                            $opStatus = 'Failed';
                                            break 2 ;
                                        }
                                    }
                                }
                            } else {
                                $details[$newPhaseName]['status'] = 'complete';
                            }
                        }

                        if ($details[$newPhaseName]['status'] == 'complete') {
                            if ($phase == 'HostInit')
                                $details[$newPhaseName]['timestamp'] = Scalr_Util_DateTime::convertTz((int)$timings['ts_hi'], $timeFormat);
                            elseif ($phase == 'BeforeHostUp')
                                $details[$newPhaseName]['timestamp'] = Scalr_Util_DateTime::convertTz((int)$timings['ts_bhu'], $timeFormat);
                            elseif ($phase == 'HostUp')
                                $details[$newPhaseName]['timestamp'] = Scalr_Util_DateTime::convertTz((int)$timings['ts_hu'], $timeFormat);
                        }

                        break;
                }
            }

            if ($isInitFailed) {
                if (in_array($details[$this->getScalarizrPhaseName('BeforeHostUp')]['status'], array('running', 'complete'))) {
                    $errorEvent = 'BeforeHostUp';
                    $errorPhaseName = $this->getScalarizrPhaseName($errorEvent);
                    $details[$this->getScalarizrPhaseName('HostUp')]['status'] = 'pending';
                }
                elseif ($dbServer->status == SERVER_STATUS::PENDING) {
                    $errorPhaseName = 'Wait for Scalarizr Agent to update and start';
                    $details['Wait for OS to finish booting']['status'] = 'complete';
                } else {
                    $errorEvent = 'HostInit';
                    $errorPhaseName = $this->getScalarizrPhaseName($errorEvent);
                    $details[$this->getScalarizrPhaseName('BeforeHostUp')]['status'] = 'pending';
                }

                $details[$errorPhaseName]['status'] = 'error';
                $details[$errorPhaseName]['message'] = htmlspecialchars($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_ERROR_MSG));
                $download = true;
                $opStatus = 'Failed';
            }

            /*
            foreach (array('HostInit', 'BeforeHostUp', 'HostUp') as $phase) {
                if ($details[$phase]) {
                    $newPhaseName = $this->getScalarizrPhaseName($phase);
                    $details[$newPhaseName] = (!empty($details[$newPhaseName])) ? array_merge($details[$phase], $details[$newPhaseName]) : $details[$phase];
                    unset($details[$phase]);
                }
            }
            */

            $info = array($msgInfo, $timings);

            if ($dbServer->status == SERVER_STATUS::RUNNING) {
                $details['Done'] = ['status' => 'complete', 'timestamp' => Scalr_Util_DateTime::convertTz((int)$timings['ts_hu'], $timeFormat)];
                $opStatus = 'Completed';
            }
            else {
                $details['Done'] = ['status' => 'pending'];

                if ($opStatus != 'Failed')
                    $opStatus = 'In progress';
            }

        }

        return [
            'serverId' => $dbServer->serverId,
            'status'	=> $opStatus,
            'serverStatus' => $dbServer->status,
            'name'		=> $operation,
            'details'   => $details,
            'download'  => $download,
            'debug' => $info
        ];
    }

    /**
     * @param   string  $serverId
     * @param   string  $operationId    optional
     * @param   string  $operation      optional
     */
    public function xGetDetailsAction($serverId, $operationId = null, $operation = null)
    {
        $this->response->data($this->getDetails($serverId, $operationId, $operation));
    }

    /**
     * @param   string  $serverId
     * @param   string  $operationId    optional
     * @param   string  $operation      optional
     */
    public function detailsAction($serverId, $operationId = null, $operation = null)
    {
        $this->response->page('ui/operations/details.js', $this->getDetails($serverId, $operationId, $operation));
    }
}
