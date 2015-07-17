<?php

use Scalr\Service\Aws\Route53\DataType\HealthData;
use Scalr\Service\Aws\Route53\DataType\HealthConfigData;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Tools_Aws_Route53_Healthchecks extends Scalr_UI_Controller
{

    /**
     * @param string $cloudLocation
     */
    public function xListAction($cloudLocation)
    {
        $result = [];
        $marker = null;

        do {
            if (isset($checkList)) {
                $marker = new MarkerType($checkList->marker);
            }
            $checkList = $this->environment->aws($cloudLocation)->route53->health->describe($marker);

            foreach ($checkList as $check) {
                if (property_exists($check, 'healthId')) {
                    $checkResult = [
                        'healthId'          =>  $check->healthId,
                        'ipAddress'         => $check->healthConfig->ipAddress,
                        'port'              => $check->healthConfig->port,
                        'protocol'          => strtolower(str_replace('_STR_MATCH', '', $check->healthConfig->type)),
                        'hostName'          => $check->healthConfig->domainName,
                        'searchString'      => $check->healthConfig->searchString,
                        'stringMatching'    => !empty($check->healthConfig->searchString) ? true : false,
                        'requestInterval'   => $check->healthConfig->requestInterval,
                        'failureThreshold'  => $check->healthConfig->failureThreshold,
                        'resourcePath'      => ltrim($check->healthConfig->resourcePath, '/')
                    ];
                    $result[] = $checkResult;
                }
            }
        } while ($checkList->marker !== null);

        $response = $this->buildResponseFromData($result, ['hostName', 'resourcePath', 'searchString', 'ipAddress'], true);
        $this->response->data($response);
    }

    /**
     * @param string $cloudLocation
     * @param string $healthId
     */
    public function infoAction($cloudLocation, $healthId)
    {
        $check = $this->environment->aws($cloudLocation)->route53->health->fetch($healthId);

        $checkResult = [];

        if (property_exists($check, 'healthId')) {
            $checkResult = [
                'healthId'          =>  $check->healthId,
                'ipAddress'         => $check->healthConfig->ipAddress,
                'port'              => $check->healthConfig->port,
                'protocol'          => strtolower(str_replace('_STR_MATCH', '', $check->healthConfig->type)),
                'hostName'          => $check->healthConfig->domainName,
                'searchString'      => $check->healthConfig->searchString,
                'stringMatching'    => !empty($check->healthConfig->searchString) ? true : false,
                'requestInterval'   => $check->healthConfig->requestInterval,
                'failureThreshold'  => $check->healthConfig->failureThreshold,
                'resourcePath'      => ltrim($check->healthConfig->resourcePath, '/')
            ];
        }

        $this->response->page('ui/tools/aws/route53/healthchecks/info.js', ['data' => $checkResult]);
    }

    /**
     * @param string $protocol
     * @param string $ipAddress
     * @param string $port
     * @param string $requestInterval
     * @param string $failureThreshold
     * @param string $cloudLocation
     * @param string $hostName          optional
     * @param string $resourcePath      optional
     * @param string $searchString      optional
     */
    public function xCreateAction($protocol, $ipAddress, $port, $requestInterval, $failureThreshold, $cloudLocation,
            $hostName = null, $resourcePath = null, $searchString = null
        )
    {
        $healthData = new HealthData();
        $protocol = strtoupper($protocol);
        $healthConfig = new HealthConfigData(
            $ipAddress,
            $port,
            null,
            null,
            null,
            null,
            $requestInterval,
            $failureThreshold
        );

        if ('TCP' != $protocol) {
            $healthConfig->domainName = !empty($hostName) ? $hostName : null;
            $healthConfig->resourcePath = !empty($resourcePath) ? $resourcePath : null;
        }

        if (('HTTP' == $protocol || 'HTTPS' == $protocol) && !empty($searchString)) {
            $healthConfig->searchString = $searchString;
            $protocol .= '_STR_MATCH';
        }

        $healthConfig->type = $protocol;
        $healthData->setHealthConfig($healthConfig);
        $response = $this->environment->aws($cloudLocation)->route53->health->create($healthData);

        $this->response->data(['data' => $response]);
    }

    /**
     * @param JsonData $healthId JSON encoded structure
     * @param string $cloudLocation
     */
    public function xDeleteAction(JsonData $healthId, $cloudLocation)
    {
        $aws = $this->environment->aws($cloudLocation);

        foreach ($healthId as $id) {
            $aws->route53->health->delete($id);
        }

        $this->response->success();
    }

}