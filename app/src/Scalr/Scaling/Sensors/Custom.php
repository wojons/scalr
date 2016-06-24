<?php

use Scalr\Model\Entity\ScalingMetric;
use Scalr\System\Http\Client\Request;

class Scalr_Scaling_Sensors_Custom extends Scalr_Scaling_Sensor
{
    public function __construct()
    {
    }

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $retval = array();
        
        if ($farmRoleMetric->getMetric()->retrieveMethod == ScalingMetric::RETRIEVE_METHOD_URL_REQUEST) {
            $url = $dbFarmRole->applyGlobalVarsToValue($farmRoleMetric->getMetric()->filePath);
            if ($url != "") {
                try {
                    $req = new Request();
                    $req->setOptions([
                        'redirect'      => 10,
                        'timeout'        => 10,
                        'connecttimeout' => 10
                    ]);
                    
                    $req->setSslOptions([
                        'verifypeer' => false,
                        'verifyhost' => false
                    ]);
                    
                    $req->setRequestMethod('GET');
                    $req->setRequestUrl($url);
                    
                    $response = \Scalr::getContainer()->http->sendRequest($req);
                    if ($response->getResponseCode() != 200) {
                        throw new Exception($response->getBody()->toString());
                    } else {
                        return [(int)$response->getBody()->toString()];
                    }
                } catch (Exception $e) {
                    \Scalr::getContainer()->logger(__CLASS__)->warn(new FarmLogMessage(
                        $dbFarmRole->FarmID,
                        sprintf("Unable to read '%s' value from '%s' URL: %s",
                            $farmRoleMetric->getMetric()->name,
                            $url,
                            $e->getMessage()
                        ),
                        null,
                        null,
                        $dbFarmRole->ID
                     ));
                }
            }
        } else {
            $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
            $dbFarm = $dbFarmRole->GetFarmObject();
    
            if (count($servers) == 0) {
                return array();
            }
    
            foreach ($servers as $dbServer) {
                $metrics = $dbServer->scalarizr->system->scalingMetrics();
                foreach ($metrics as $metric) {
                    if ($metric->id == $farmRoleMetric->metricId) {
                        if ($metric->error) {
                            \Scalr::getContainer()->logger(__CLASS__)->warn(new FarmLogMessage(
                                $dbServer,
                                sprintf("Unable to read '%s' value from server %s: %s",
                                    !empty($metric->name) ? $metric->name : null,
                                    $dbServer->getNameByConvention(),
                                    !empty($metric->error) ? $metric->error : null
                                )
                            ));
                        } else {
                            $retval[] = $metric->value;
                        }
    
                        break;
                    }
                }
            }
        }

        return $retval;
    }
}