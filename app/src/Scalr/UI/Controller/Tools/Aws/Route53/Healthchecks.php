<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Route53\DataType\HealthData;
use Scalr\Service\Aws\Route53\DataType\HealthConfigData;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Tools_Aws_Route53_Healthchecks extends Scalr_UI_Controller
{

    /**
     * Gets Aws object
     *
     * @return Aws
     */
    private function getAws()
    {
        return $this->environment->aws(Aws::REGION_US_EAST_1);
    }

    /**
     * Describes health checks
     */
    public function xListAction()
    {
        $result = [];
        $marker = null;

        do {
            if (isset($checkList)) {
                $marker = new MarkerType($checkList->marker);
            }
            $checkList = $this->getAws()->route53->health->describe($marker);

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
     * @param string $healthId
     */
    public function infoAction($healthId)
    {
        $check = $this->getAws()->route53->health->fetch($healthId);

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
     * @param string $hostName          optional
     * @param string $resourcePath      optional
     * @param string $searchString      optional
     */
    public function xCreateAction($protocol, $ipAddress, $port, $requestInterval, $failureThreshold,
            $hostName = null, $resourcePath = null, $searchString = null
        )
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ROUTE53, Acl::PERM_AWS_ROUTE53_MANAGE);

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
        $response = $this->getAws()->route53->health->create($healthData);

        $this->response->data(['data' => $response]);
    }

    /**
     * @param JsonData $healthId JSON encoded structure
     */
    public function xDeleteAction(JsonData $healthId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ROUTE53, Acl::PERM_AWS_ROUTE53_MANAGE);

        $aws = $this->getAws();

        foreach ($healthId as $id) {
            $aws->route53->health->delete($id);
        }

        $this->response->success();
    }

}