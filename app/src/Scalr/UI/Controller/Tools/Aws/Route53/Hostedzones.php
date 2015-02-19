<?php

use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetList;
use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetsRequestData;
use Scalr\Service\Aws\Route53\DataType\ZoneConfigData;
use Scalr\Service\Aws\Route53\DataType\ZoneData;
use Scalr_UI_Controller_Tools_Aws_Route53_Recordsets as Recordsets;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Tools_Aws_Route53_Hostedzones extends \Scalr_UI_Controller
{

    /**
     * @param string $cloudLocation
     */
    public function xListAction($cloudLocation)
    {
        $marker = null;
        $result = array();

        do {
            if (isset($zonesList)) {
                $marker = new MarkerType($zonesList->marker);
            }
            $zonesList = $this->environment->aws($cloudLocation)->route53->zone->describe($marker);

            foreach ($zonesList as $zone) {
                $zoneResult = array(
                    'zoneId'            => $zone->zoneId,
                    'name'              => $zone->name,
                    'recordSetCount'    => $zone->resourceRecordSetCount,
                    'comment'           => !empty($zone->zoneConfig->comment) ? $zone->zoneConfig->comment : ''
                );
                $result[] = $zoneResult;
            }
        } while ($zonesList->marker !== null);

        $response = $this->buildResponseFromData($result, array('name', 'comment'), true);
        $this->response->data($response);
    }

    /**
     * @param string $cloudLocation
     * @param string $zoneId
     */
    public function infoAction($cloudLocation, $zoneId)
    {
        $zone = $this->environment->aws($cloudLocation)->route53->zone->fetch($zoneId);
        $delegationSet = array();
        foreach ($zone->delegationSet as $set) {
            $delegationSet[] = $set->nameServer;
        }
        $zoneResult = array(
                'zoneId'            => $zone->zoneId,
                'name'              => $zone->name,
                'recordSetCount'    => $zone->resourceRecordSetCount,
                'comment'           => !empty($zone->zoneConfig->comment) ? $zone->zoneConfig->comment : '',
                'delegationSet'     => $delegationSet
            );

        $this->response->page('ui/tools/aws/route53/hostedzones/info.js', array(
            'data' => $zoneResult
        ));
    }

    /**
     * @param string $cloudLocation
     * @param string $domainName
     * @param string $description optional
     */
    public function xCreateAction($cloudLocation, $domainName, $description = null)
    {
        $config = new ZoneData($domainName);
        $zoneConfig = new ZoneConfigData($description);
        $config->setZoneConfig($zoneConfig);
        $zone = $this->environment->aws($cloudLocation)->route53->zone->create($config);
        $delegationSet = array();
        foreach ($zone->delegationSet as $set) {
            $delegationSet[] = $set->nameServer;
        }
        $changeInfo = array(
            'changeId'    => !empty($zone->changeInfo->id) ? $zone->changeInfo->id : '',
            'status'      => !empty($zone->changeInfo->status) ? $zone->changeInfo->status : '',
            'submittedAt' => !empty($zone->changeInfo->submittedAt) ? $zone->changeInfo->submittedAt : '',
        );
        $zoneResult = array(
                'zoneId'            => $zone->zoneId,
                'name'              => $zone->name,
                'recordSetCount'    => $zone->resourceRecordSetCount,
                'comment'           => !empty($zone->zoneConfig->comment) ? $zone->zoneConfig->comment : '',
                'delegationSet'     => $delegationSet,
                'changeInfo'        => $changeInfo
            );
        $this->response->data(array('data' => $zoneResult));
    }

    /**
     * @param JsonData $zoneId JSON encoded structure
     * @param string $cloudLocation
     */
    public function xDeleteAction(JsonData $zoneId, $cloudLocation)
    {
        $aws = $this->environment->aws($cloudLocation);

        foreach ($zoneId as $id) {
            $customRecordSets = [];
            $nextName = null;
            $nextType = null;

            do {
                if (isset($recordsets)) {
                    $nextName = $recordsets->nextRecordName;
                    $nextType = $recordsets->nextRecordType;
                }

                $recordsets = $aws->route53->record->describe($id, $nextName, $nextType);

                foreach ($recordsets as $record) {
                    if ('NS' != $record->type && 'SOA' != $record->type) {
                        $result = Recordsets::loadRecordSetData($record);
                        $customRecordSets[] = $result;
                    }
                }
            } while (!empty($recordsets->isTruncated));

            if (!empty($customRecordSets)) {
                $this->deleteCustomRecordsets($customRecordSets, $id, $cloudLocation);
            }
            $aws->route53->zone->delete($id);
        }

        $this->response->success();
    }

    /**
     * @param JsonData|array $customRecordSets
     * @param string $zoneId
     * @param string $cloudLocation
     */
    public function deleteCustomRecordsets($customRecordSets, $zoneId, $cloudLocation)
    {
        $rrsRequest = new ChangeRecordSetsRequestData();
        $rrsCnahgeList = new ChangeRecordSetList();

        foreach ($customRecordSets as $recordSet) {
            $rrsCnahgeListData = Recordsets::getRecordDeleteXml($recordSet);
            $rrsCnahgeList->append($rrsCnahgeListData);
            $rrsRequest->setChange($rrsCnahgeList);
        }

        $this->environment->aws($cloudLocation)->route53->record->update($zoneId, $rrsRequest);
    }

}