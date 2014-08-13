<?php

use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetsRequestData;
use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetList;
use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetData;
use Scalr\Service\Aws\Route53\DataType\RecordSetData;
use Scalr\Service\Aws\Route53\DataType\AliasTargetData;
use Scalr\Service\Aws\Route53\DataType\RecordList;
use Scalr\Service\Aws\Route53\DataType\RecordData;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\Service\Aws;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Tools_Aws_Route53_Recordsets extends Scalr_UI_Controller
{

    const CLOUDFRONT_ALIAS_ZONEID           = 'Z2FDTNDATAQYW2';

    const ELB_ALIAS_TARGET_TITLE            = 'Elastic load balancers';

    const S3_ALIAS_TARGET_TITLE             = 'S3 website endpoints';

    const CLOUDFRONT_ALIAS_TARGET_TITLE     = 'Cloudfront distributions';

    const RECORD_SETS_ALIAS_TARGET_TITLE    = 'Record sets in this hosted zone';

    /**
     * @param string $cloudLocation
     * @param string $zoneId
     * @param string $type          optional
     * @param string $aliases       optional
     * @param string $weighted      optional
     */
    public function xListAction($cloudLocation, $zoneId, $type = null, $aliases = null, $weighted = null)
    {
        $resultList = array();
        $marker = null;

        do {
            if (isset($recordListResponse)) {
                $marker = new MarkerType($recordListResponse->marker);
            }
            $recordListResponse = $this->environment
                    ->aws($cloudLocation)->route53->record
                    ->describe($zoneId, null, null, $marker);

            foreach ($recordListResponse as $record) {
                if (!empty($type) && $record->type != $type) {
                    continue;
                }
                if (!empty($aliases) && empty($record->aliasTarget->zoneId)) {
                    continue;
                }
                if (!empty($weighted) && !property_exists($record, 'weight')) {
                    continue;
                }
                $result = self::loadRecordSetData($record);
                $resultList[] = $result;
            }
        } while ($recordListResponse->marker !== null);

        $response = $this->buildResponseFromData($resultList, array('name', 'type'));
        $this->response->data($response);
    }

    /**
     * @param object $record
     */
    public static function loadRecordSetData($record)
    {
        $resourceRecordList = array();
        $result = array(
            'name' => $record->name,
            'type' => $record->type
        );
        if (!empty($record->resourceRecord)) {
            foreach ($record->resourceRecord as $value) {
                $resourceRecordList[] = $value->value;
            }
            $result['resourceRecord'] = $resourceRecordList;
            $result['ttl'] = $record->ttl;
            $result['alias'] = false;
        }
        if (!empty($record->aliasTarget)) {
            $result['alias'] = true;
            $result['aliasZoneId'] = $record->aliasTarget->zoneId;
            $result['dnsName'] = $record->aliasTarget->dnsName;
            $result['evaluateTargetHealth'] = $record->aliasTarget->evaluateTargetHealth;
        }
        if (property_exists($record, 'healthId')) {
            $result['healthId'] = $record->healthId;
        }
        if (property_exists($record, 'setIdentifier')) {
            $result['setIdentifier'] = $record->setIdentifier;
        }
        if (property_exists($record, 'weight')) {
            $result['weight'] = $record->weight;
            if (!empty($record->weight)) {
                $result['policy'] = 'weight';
            }
        }
        if (property_exists($record, 'region')) {
            $result['region'] = $record->region;
            if (!empty($record->region)) {
                $result['policy'] = 'region';
            }
        }
        if (property_exists($record, 'failover')) {
            $result['failover'] = strtolower($record->failover);
            if (!empty($record->failover)) {
                $result['policy'] = 'failover';
            }
        }
        if (empty($result['policy'])) {
            $result['policy'] = 'simple';
        }

        return $result;
    }

    /**
     * @param string $zoneId
     * @param string $policy
     * @param string $healthId
     * @param string $dnsName
     * @param string $action
     * @param string $aliasZoneId
     * @param string $evaluateTargetHealth
     * @param string $name
     * @param string $type
     * @param string $ttl
     * @param string $weight
     * @param string $setIdentifier
     * @param string $region
     * @param string $failover
     * @param string $cloudLocation
     * @param JsonData  $resourceRecord
     * @param JsonData $oldRecordSet       optional
     */
    public function xSaveAction($zoneId, $policy, $healthId, $dnsName,
            $action, $aliasZoneId, $evaluateTargetHealth, $name, $type,
            $ttl, $weight, $setIdentifier, $region, $failover, $cloudLocation, JsonData $resourceRecord,
            JsonData $oldRecordSet = null
        )
    {
        $rrsRequest = new ChangeRecordSetsRequestData();
        $rrsCnahgeList = new ChangeRecordSetList();
        if (!empty($oldRecordSet)) {
            $rrsCnahgeList->append(self::getRecordDeleteXml($oldRecordSet));
        }
        $rrsCnahgeListData = new ChangeRecordSetData($action);

        $rrsData = new RecordSetData(
            $name,
            $type,
            null,
            (!empty($healthId) ? $healthId : null)
        );

        if (!empty($dnsName)) {
            $alias = new AliasTargetData();
            $alias->zoneId = $aliasZoneId;
            $alias->dnsName = $dnsName;
            $alias->evaluateTargetHealth = strtolower($evaluateTargetHealth);
            $rrsData->setAliasTarget($alias);
        }
        else {
            $rrsData->ttl = $ttl;
            $recordList = new RecordList();
            foreach ($resourceRecord as $value) {
                $recordData = new RecordData($value);
                $recordList->append($recordData);
            }
            $rrsData->setResourceRecord($recordList);
        }

        if ('weight' == $policy) {
            $rrsData->weight = $weight;
        }
        if ('simple' != $policy) {
            $rrsData->setIdentifier = $setIdentifier;
        }
        if ('region' == $policy) {
            $rrsData->region = $region;
        }
        if ('failover' == $policy) {
            $rrsData->failover = strtoupper($failover);
        }
        $rrsCnahgeListData->setRecordSet($rrsData);
        $rrsCnahgeList->append($rrsCnahgeListData);
        $rrsRequest->setChange($rrsCnahgeList);

        $response = $this->environment->aws($cloudLocation)->route53->record->update($zoneId, $rrsRequest);
        $this->response->data(array('data' => $response));
    }

    /**
     * @param JsonData $recordSets JSON encoded structure
     * @param string $zoneId
     * @param string $cloudLocation
     */
    public function xDeleteAction(JsonData $recordSets, $zoneId, $cloudLocation)
    {
        $rrsRequest = new ChangeRecordSetsRequestData();
        $rrsCnahgeList = new ChangeRecordSetList();

        foreach ($recordSets as $recordSet) {
            $rrsCnahgeListData = self::getRecordDeleteXml($recordSet);
            $rrsCnahgeList->append($rrsCnahgeListData);
            $rrsRequest->setChange($rrsCnahgeList);
        }

        $response = $this->environment->aws($cloudLocation)->route53->record->update($zoneId, $rrsRequest);
        $this->response->data(array('data' => $response));
    }

    /**
     * @param string $zoneId
     * @param string $name
     * @param string $cloudLocation
     */
    public function xGetAliasTargetsAction($zoneId, $name, $cloudLocation)
    {
        $result = array();
        $name = rtrim($name, '.');

        $result['data'] = array_filter(
                array_merge(
                    $this->listLoadBalancerDomains($cloudLocation),
                    $this->listCloudFrontDomains($name, $cloudLocation),
                    $this->listRecordSetDomains($zoneId, $cloudLocation, $name),
                    $this->listS3Websites($name, $cloudLocation)
                )
            );

        $this->response->data($result);
    }

    /**
     * @param string $name
     */
    public function xGetS3TargetsAction($name)
    {
        $result = array();
        $name = rtrim($name, '.');

        $result['data'] = $this->listS3Websites($name);

        $this->response->data($result);
    }

    /**
     * @param string $cloudLocation
     * @param string $healthId          optional
     */
    protected function listHealthChecks($cloudLocation, $healthId = null)
    {
        $marker = null;
        $healthChecks = array();

        do {
            if (isset($checkList)) {
                $marker = new MarkerType($checkList->marker);
            }
            $checkList = $this->environment->aws($cloudLocation)->route53->health->describe($marker);

            foreach ($checkList as $check) {
                $checkResult = array();

                if (property_exists($check, 'healthId')) {
                    $checkResult = array(
                        'healthId'      => $check->healthId,
                        'protocol'      => $check->healthConfig->type,
                        'ipAddress'     => $check->healthConfig->ipAddress,
                        'port'          => $check->healthConfig->port,
                        'resourcePath'  => ltrim($check->healthConfig->resourcePath, '/')
                    );
                    if ($healthId == $check->healthId) {
                        $currentHealthCheck = $checkResult;
                        continue;
                    }
                    $healthChecks[] = $checkResult;
                }
            }
        } while ($checkList->marker !== null);

        if (isset($currentHealthCheck)) {
            $healthChecks = array_merge(array($currentHealthCheck), $healthChecks);
        }

        return $healthChecks;
    }

    /**
     * @param string $cloudLocation
     */
    protected function listLoadBalancerDomains($cloudLocation)
    {
        $result = array();

        $elbList = $this->environment->aws($cloudLocation)->elb->loadBalancer->describe();

        foreach ($elbList as $elb) {
            $result[] = array(
                'domainName' => $elb->dnsName,
                'aliasZoneId'=> $elb->canonicalHostedZoneNameId,
                'title'      => self::ELB_ALIAS_TARGET_TITLE
            );
        }

        return $result;
    }

    /**
     * @param string $name
     * @param string $cloudLocation
     */
    protected function listCloudFrontDomains($name, $cloudLocation)
    {
        $result = array();
        $marker = null;

        do {
            if (isset($distributionList)) {
                $marker = new MarkerType($distributionList->marker);
            }
            $distributionList = $this->environment->aws($cloudLocation)->cloudFront->distribution->describe($marker);

            foreach ($distributionList as $distribution) {
                foreach ($distribution->distributionConfig->aliases as $alias) {
                    if ($alias->cname == $name) {
                        $cname = $alias->cname;
                        break;
                    }
                }
                if (!empty($cname)) {
                    $result[] = array(
                        'domainName' => $cname,
                        'aliasZoneId'=> self::CLOUDFRONT_ALIAS_ZONEID,
                        'title'      => self::CLOUDFRONT_ALIAS_TARGET_TITLE
                    );
                    unset($cname);
                }
            }
        } while ($distributionList->marker !== null);

        return $result;
    }

    /**
     * @param string $zoneId
     * @param string $cloudLocation
     * @param string $name
     */
    protected function listRecordSetDomains($zoneId, $cloudLocation, $name)
    {
        $result = array();
        $marker = null;

        do {
            if (isset($rrsList)) {
                $marker = new MarkerType($rrsList->marker);
            }
            $rrsList = $this->environment
                    ->aws($cloudLocation)->route53->record
                    ->describe($zoneId, null, null, $marker);

            foreach ($rrsList as $record) {
                if ('NS' == $record->type || 'SOA' == $record->type || $name . '.' == $record->name) {
                    continue;
                }
                $result[] = array(
                    'domainName' => $record->name,
                    'aliasZoneId'=> $zoneId,
                    'title'      => self::RECORD_SETS_ALIAS_TARGET_TITLE
                );
            }
        } while ($rrsList->marker !== null);

        return $result;
    }

    /**
     * @param string $name
     * @param string $cloudLocation
     */
    protected function listS3Websites($name, $cloudLocation)
    {
        $result = array();
        $buckets = $this->environment->aws($cloudLocation)->s3->bucket->getWebsite($name);
        if ($buckets) {
            $location = $this->environment->aws($cloudLocation)->s3->bucket->getLocation($name);
            if (empty($location)) {
                $location = 'us-east-1';
            }
            $zoneIds = Aws::getCloudLocationsZoneIds();

            $result[] = array(
                    'domainName' => $name . 's3-website-' . $location . '.amazonaws.com',
                    'aliasZoneId'=> isset($zoneIds[$location]) ? $zoneIds[$location] : null,
                    'title'      => self::S3_ALIAS_TARGET_TITLE
                );
        }

        return $result;
    }

    /**
     * @param array $recordSet
     */
    public static function getRecordDeleteXml($recordSet)
    {
        $rrsCnahgeListData = new ChangeRecordSetData('DELETE');

        $rrsData = new RecordSetData(
            $recordSet['name'],
            $recordSet['type']
        );

        if (!empty($recordSet['resourceRecord'])) {
            $rrsData->ttl = $recordSet['ttl'];
            $recordList = new RecordList();
            foreach ($recordSet['resourceRecord'] as $value) {
                $recordData = new RecordData($value);
                $recordList->append($recordData);
            }
            $rrsData->setResourceRecord($recordList);
        }
        else {
            $alias = new AliasTargetData();
            $alias->zoneId = $recordSet['aliasZoneId'];
            $alias->dnsName = $recordSet['dnsName'];
            $alias->evaluateTargetHealth = strtolower($recordSet['evaluateTargetHealth']);
            $rrsData->setAliasTarget($alias);
        }

        if ($recordSet['policy'] != 'simple') {
            $rrsData->setIdentifier = $recordSet['setIdentifier'];
            if ($recordSet['policy'] == 'region') {
                $rrsData->region = $recordSet['region'];
            }
            if ($recordSet['policy'] == 'failover') {
                $rrsData->failover = strtoupper($recordSet['failover']);
            }
            if ($recordSet['policy'] == 'weight') {
                $rrsData->weight = $recordSet['weight'];
            }
        }

        if (!empty($recordSet['healthId'])) {
            $rrsData->healthId = $recordSet['healthId'];
        }

        $rrsCnahgeListData->setRecordSet($rrsData);

        return $rrsCnahgeListData;
    }

}