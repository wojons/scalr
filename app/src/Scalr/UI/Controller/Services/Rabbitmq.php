<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity;
use Scalr\System\Http\Client\Request;

class Scalr_UI_Controller_Services_Rabbitmq extends Scalr_UI_Controller
{
    const REQUEST_TIMEOUT = 600;

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_SERVICES_RABBITMQ);
    }

    /**
     * Returns page with RabbitMQ role status
     *
     * @param  int $farmId     Identifier of the Farm
     * @param  int $farmRoleId optional Identifier of the FarmRole
     */
    public function statusAction($farmId, $farmRoleId = null)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);
        
        $list = [];

        foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
            if (!$dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                continue;
            }
            $farmRole = [
                'id'    => $dbFarmRole->ID,
                'alias' => $dbFarmRole->Alias
            ];
            if (empty($farmRoleId) && empty($list) || $dbFarmRole->ID == $farmRoleId) {
                $rabbitmq = [];
                $rabbitmq['status'] = '';
                $rabbitmq['showSetup'] = false;
                $rabbitmq['showStatusLabel'] = true;

                $cpUrl = $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_URL);
                if ($cpUrl) {
                    $serverId = $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID);
                    try {
                        $dbServer = DBServer::LoadByID($serverId);
                        
                        if ($dbServer->status == SERVER_STATUS::RUNNING) {
                            $rabbitmq['username'] = 'scalr';
                            $rabbitmq['password'] = $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_PASSWORD);
                            $rabbitmq['url'] = $cpUrl;

                            $url = str_replace('/mgmt/', '/api/overview', $rabbitmq['url']);

                            $httpRequest = new Request();
                            $httpRequest->setRequestMethod('GET');
                            $httpRequest->setRequestUrl($url);
                            $httpRequest->setOptions([
                                'redirect'       => 5,
                                'timeout'        => 30,
                                'connecttimeout' => 10
                            ]);
                            $httpRequest->setHeaders(array(
                                'Authorization' => 'Basic ' . base64_encode($rabbitmq['username'] . ':' . $rabbitmq['password'])
                            ));
                            
                            $response = \Scalr::getContainer()->http->sendRequest($httpRequest);
                            
                            $data = $response->getBody()->toString();
                            
                            $result = json_decode($data, true);
                            
                            if ($result) {
                                $rabbitmq['overview'] = $result;
                            }
                        } else {
                            throw new \Scalr\Exception\ServerNotFoundException();
                        }
                    } catch (\Scalr\Exception\ServerNotFoundException $e) {
                        $rabbitmq['status'] = "Control panel was installed, however server wasn't found";
                        $rabbitmq['showSetup'] = true;
                        $dbFarmRole->ClearSettings('rabbitmq.cp');
                    } catch (Exception $e) {
                        if (isset($e->innerException)) {
                            $msg = $e->innerException->getMessage();
                        } else {
                            $msg = $e->getMessage();
                        }

                        $rabbitmq['status'] = "Error retrieving information about control panel: \"{$msg}\"";
                    }
                } else {
                    if ($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUESTED) == '1') {
                        if ($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG)) {
                            $rabbitmq['showSetup'] = true;
                            $rabbitmq['status'] = 'Server returned error: "' . $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG) . '"';
                        } else {
                            if ($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME) > (time() - self::REQUEST_TIMEOUT)) {
                                $rabbitmq['status'] = "Request was sent at " .
                                    Scalr_Util_DateTime::convertTz((int) $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME)) .
                                    ". Please wait...";
                            } else {
                                $rabbitmq['showSetup'] = true;
                                $rabbitmq['status'] = "Request timeout exceeded. Request was sent at " .
                                    Scalr_Util_DateTime::convertTz((int) $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME));
                            }
                        }
                    } else {
                        if ($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_PASSWORD)) {
                            $rabbitmq['showSetup'] = true;
                        } else {
                            $rabbitmq['status'] = 'Rabbitmq cluster not initialized yet. Please wait ...';
                            $rabbitmq['showStatusLabel'] = false;
                        }
                    }
                }

                $rabbitmq['password'] = $dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_PASSWORD);
                $farmRole['data'] = $rabbitmq;
            }
            
            $list[] = $farmRole;
        }
        
        $this->response->page('ui/services/rabbitmq/status.js', [
            'list' => $list
        ]);
    }

    /**
     * Sets up RabbitMQ control panel
     *
     * @param  int $farmRoleId Farm role ID
     */
    public function xSetupCpAction($farmRoleId)
    {
        $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
        $this->user->getPermissions()->validate($dbFarmRole);

        if ($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_URL)) {
            $this->response->failure("CP already installed");
        } else {
            if (($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUESTED) == '1') &&
                ($dbFarmRole->GetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME) > (time() - self::REQUEST_TIMEOUT))
            ) {
                $this->response->failure("CP already installing");
            } else {
                $dbServers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
                if (count($dbServers)) {
                    // install panel
                    $msg = new Scalr_Messaging_Msg_RabbitMq_SetupControlPanel();
                    $dbServers[0]->SendMessage($msg);

                    $dbFarmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUESTED, 1, Entity\FarmRoleSetting::TYPE_LCL);
                    $dbFarmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME, time(), Entity\FarmRoleSetting::TYPE_LCL);
                    $dbFarmRole->SetSetting(Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG, "", Entity\FarmRoleSetting::TYPE_LCL);

                    $this->response->success("CP installing");
                    $this->response->data(array("status" => "Request was sent at " . Scalr_Util_DateTime::convertTz((int) time()) . ". Please wait..."));
                } else {
                    $this->response->failure("No running server");
                }
            }
        }
    }
}
