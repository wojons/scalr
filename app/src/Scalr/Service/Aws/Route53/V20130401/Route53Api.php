<?php
namespace Scalr\Service\Aws\Route53\V20130401;

use DateTime, DateTimeZone;
use Scalr\Service\Aws\AbstractApi;
use Scalr\Service\Aws\Route53;
use Scalr\Service\Aws\Client\QueryClient\Route53QueryClient;
use Scalr\Service\Aws\Route53\DataType\ZoneList;
use Scalr\Service\Aws\Route53\DataType\ZoneData;
use Scalr\Service\Aws\Route53\DataType\ZoneConfigData;
use Scalr\Service\Aws\Route53\DataType\HealthList;
use Scalr\Service\Aws\Route53\DataType\HealthData;
use Scalr\Service\Aws\Route53\DataType\HealthConfigData;
use Scalr\Service\Aws\Route53\DataType\RecordSetList;
use Scalr\Service\Aws\Route53\DataType\RecordSetData;
use Scalr\Service\Aws\Route53\DataType\RecordList;
use Scalr\Service\Aws\Route53\DataType\RecordData;
use Scalr\Service\Aws\Route53\DataType\AliasTargetData;
use Scalr\Service\Aws\Route53\DataType\ChangeData;
use Scalr\Service\Aws\Route53\DataType\ZoneChangeInfoData;
use Scalr\Service\Aws\Route53\DataType\ZoneDelegationSetList;
use Scalr\Service\Aws\Route53\DataType\ZoneServerData;
use Scalr\Service\Aws\Route53\DataType\ChangeRecordSetsRequestData;
use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\Service\Aws\Client\ClientException;

/**
 * Route53 Api messaging.
 *
 * Implements Route53 Low-Level API Actions.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class Route53Api extends AbstractApi
{

    /**
     * @var Route53
     */
    protected $route53;

    /**
     * Constructor
     * @param   Route53            $route53       Route53 instance
     * @param   Route53QueryClient $client        Client Interface
     */
    public function __construct(Route53 $route53, Route53QueryClient $client)
    {
        $this->route53 = $route53;
        $this->client = $client;
    }

    /**
     * Gets an EntityManager
     *
     * @return \Scalr\Service\Aws\EntityManager
     */
    public function getEntityManager()
    {
        return $this->route53->getEntityManager();
    }

    /**
     * GET Hosted Zone List action
     *
     * To list the hosted zones associated with your AWS account.
     *
     * @param   MarkerType       $marker optional The query parameters.
     * @return  ZoneList         Returns the list of Hosted zones.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function describeHostedZones(MarkerType $marker = null)
    {
        $result = null;
        $options = array();
        $aQueryString = array();
        if ($marker !== null) {
            if ($marker->marker !== null) {
                $aQueryString[] = 'marker=' . self::escape($marker->marker);
            }
            if ($marker->maxItems !== null) {
                $aQueryString[] = 'maxitems=' . self::escape($marker->maxItems);
            }
        }

        $response = $this->client->call('GET', $options, '/hostedzone' . (!empty($aQueryString) ? '?' . join('&', $aQueryString) : ''));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->_loadHostedZoneListData($sxml);
            $result->setOriginalXml($response->getRawContent());
        }
        return $result;
    }

    /**
     * POST Hosted Zone action
     *
     * This action creates a new hosted zone.
     *
     * @param   ZoneData|string $config zone data object or XML document
     * @return  ZoneData        Returns created hosted zone.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function createHostedZone($config)
    {
        $result = null;
        $options = array(
            '_putData' => ($config instanceof ZoneData ? $config->setRoute53($this->route53)->toXml() : (string) $config),
        );
        $response = $this->client->call('POST', $options, '/hostedzone');
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->_loadHostedZoneData($sxml);
        }
        return $result;
    }

    /**
     * GET Hosted zone action
     *
     * @param   string           $zoneId  ID of the hosted zone.
     * @return  ZoneData Returns hosted zone.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function getHostedZone($zoneId)
    {
        $result = null;
        $options = array();
        $response = $this->client->call('GET', $options, sprintf('/hostedzone/%s', self::escape($zoneId)));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!empty($sxml->HostedZone)) {
                $result = $this->_loadHostedZoneData($sxml);
                $result->setOriginalXml($response->getRawContent());
            }
        }
        return $result;
    }

    /**
     * Loads ZoneListData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  ZoneList Returns ZoneListData
     */
    protected function _loadHostedZoneListData(\SimpleXMLElement $sxml)
    {
        $result = new ZoneList();
        $result->setRoute53($this->route53);
        $result->setMarker($this->exist($sxml->NextMarker) ? (string)$sxml->NextMarker : null);
        $result->setMaxItems($this->exist($sxml->MaxItems) ? (int)$sxml->MaxItems : null);
        $result->setIsTruncated($this->exist($sxml->IsTruncated) ? ((string)$sxml->IsTruncated == 'true') : null);

        if (!empty($sxml->HostedZones)) {
            foreach ($sxml->HostedZones->HostedZone as $v) {
                $item = $this->_loadListHostedZoneData($v);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ZoneData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  ZoneData Returns ZoneData
     */
    protected function _loadListHostedZoneData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v)) {
            $zoneId = str_replace('/hostedzone/', '', $v->Id);
            $item = new ZoneData();
            $item->setRoute53($this->route53);
            $item
                ->setZoneId((string) $zoneId)
                ->setName((string) $v->Name)
                ->setCallerReference((string) $v->CallerReference)
                ->setZoneConfig($this->exist($v->Config) ? $this->_loadHostedZoneConfigData($v->Config) : null)
                ->setResourceRecordSetCount((string) $v->ResourceRecordSetCount)
            ;
        }
        return $item;
    }

    /**
     * Loads ZoneData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  ZoneData Returns ZoneData
     */
    protected function _loadHostedZoneData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v->HostedZone)) {
            $zoneId = str_replace('/hostedzone/', '', $v->HostedZone->Id);
            $item = new ZoneData();
            $item->setRoute53($this->route53);
            $item
                ->setZoneId((string) $zoneId)
                ->setName((string) $v->HostedZone->Name)
                ->setCallerReference((string) $v->HostedZone->CallerReference)
                ->setZoneConfig($this->exist($v->HostedZone->Config) ? $this->_loadHostedZoneConfigData($v->HostedZone->Config) : null)
                ->setResourceRecordSetCount((string) $v->HostedZone->ResourceRecordSetCount)
            ;
            if ($this->exist($v->ChangeInfo)) {
                $item->setChangeInfo($this->exist($v->ChangeInfo) ? $this->_loadHostedZoneChangeInfoData($v->ChangeInfo) : null);
            }
            if ($this->exist($v->DelegationSet)) {
                $item->setDelegationSet($this->exist($v->DelegationSet) ? $this->_loadHostedZoneDelegationSetList($v->DelegationSet) : null);
            }
        }
        return $item;
    }

     /**
     * Loads ZoneConfigData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  ZoneConfigData Returns ZoneConfigData
     */
    protected function _loadHostedZoneConfigData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new ZoneConfigData();
            $item->setRoute53($this->route53);
            $item->comment = $this->exist($sxml->Comment) ? (string) $sxml->Comment : null;
        }
        return $item;
    }

    /**
     * Loads ZoneChangeInfoData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  ZoneChangeInfoData Returns ZoneChangeInfoData
     */
    protected function _loadHostedZoneChangeInfoData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v)) {
            $id = (string)$v->Id;
            $item = new ZoneChangeInfoData();
            $item->setRoute53($this->route53);
            $item
                ->setId($id)
                ->setStatus($this->exist($v->Status) ? (string) $v->Status : null)
                ->setSubmittedAt($this->exist($v->SubmittedAt) ? new DateTime((string)$v->SubmittedAt, new DateTimeZone('UTC')) : null)
            ;
        }
        return $item;
    }

    /**
     * Loads ZoneDelegationSetList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  ZoneDelegationSetList Returns ZoneDelegationSetList
     */
    protected function _loadHostedZoneDelegationSetList(\SimpleXMLElement $sxml)
    {
        $result = new ZoneDelegationSetList();
        $result->setRoute53($this->route53);

        if (!empty($sxml->NameServers)) {
            foreach ($sxml->NameServers->NameServer as $v) {
                $item = $this->_loadHostedZoneServerData($v);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ZoneServerData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  ZoneServerData Returns ZoneServerData
     */
    protected function _loadHostedZoneServerData(\SimpleXMLElement $v)
    {
        $item = new ZoneServerData();
        $item->setRoute53($this->route53);
        $item->setNameServer((string) $v);
        return $item;
    }

    /**
     * DELETE Hosted Zone action
     *
     * @param   string                        $zoneId ID of the hosted zone.
     * @return  bool|ChangeData               Returns ChangeData on success.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function deleteHostedZone($zoneId)
    {
        $result = false;
        $options = array();
        $response = $this->client->call('DELETE', $options, sprintf('/hostedzone/%s', self::escape($zoneId)));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!empty($sxml->ChangeInfo)) {
                $result = $this->_loadChangeData($sxml->ChangeInfo);
                $result->setOriginalXml($response->getRawContent());
            }
        }
        return $result;
    }

    /**
     * POST Resource Record action
     *
     * This action creates a new hosted zone.
     *
     * @param   string $zoneId hosted zone id
     * @param   ChangeRecordSetsRequestData|string $config request data object or XML document
     * @return  ChangeData Returns change data.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function updateRecordSets($zoneId, $config)
    {
        $result = null;
        $options = array(
            '_putData' => ($config instanceof ChangeRecordSetsRequestData ? $config->setRoute53($this->route53)->toXml() : (string) $config),
        );
        $response = $this->client->call('POST', $options, sprintf('/hostedzone/%s', self::escape($zoneId)) . '/rrset');
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!empty($sxml->ChangeInfo)) {
                $result = $this->_loadChangeData($sxml->ChangeInfo);
                $result->setOriginalXml($response->getRawContent());
            }
        }
        return $result;
    }

    /**
     * GET Record Sets List action
     *
     * @param   string       $zoneId required Id of the hosted zone.
     * @param   string       $name optional
     * @param   string       $type optional
     * @param   MarkerType       $marker optional The query parameters.
     * @return  RecordSetList       Returns the list of resource record sets.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function describeRecordSets($zoneId, $name, $type, MarkerType $marker = null)
    {
        $result = null;
        $options = array();
        $aQueryString = array();

        if ($marker !== null) {
            if ($marker->marker !== null) {
                $aQueryString[] = 'identifier=' . self::escape($marker->marker);
            }
            if ($marker->maxItems !== null) {
                $aQueryString[] = 'maxitems=' . self::escape($marker->maxItems);
            }
        }
        if ($name !== null) {
            $aQueryString[] = 'name=' . self::escape($name);
        }
        if ($type !== null) {
            $aQueryString[] = 'type=' . self::escape($type);
        }

        $response = $this->client->call('GET', $options, sprintf('/hostedzone/%s', self::escape($zoneId)) . '/rrset' . (!empty($aQueryString) ? '?' . join('&', $aQueryString) : ''));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->_loadRecordSetListData($sxml);
        }
        return $result;
    }

    /**
     * GET Change action
     *
     * @param   string           $changeId  required.
     * @return  ChangeData Returns change data.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function getChange($changeId)
    {
        $result = null;
        $options = array();
        $response = $this->client->call('GET', $options, sprintf('/change/%s', self::escape($changeId)));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!empty($sxml->ChangeInfo)) {
                $result = $this->_loadChangeData($sxml->ChangeInfo);
                $result->setOriginalXml($response->getRawContent());
            }
        }
        return $result;
    }

    /**
     * POST Health CHeck action
     *
     * This action creates a new health check.
     *
     * @param   HealthData|string $config health check data object or xml document
     * @return  HealthData Returns created health check.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function createHealthCheck($config)
    {
        $result = null;
        $options = array(
            '_putData' => ($config instanceof HealthData ? $config->setRoute53($this->route53)->toXml() : (string) $config),
        );
        $response = $this->client->call('POST', $options, '/healthcheck');
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->_loadHealthCheckData($sxml);
        }
        return $result;
    }

    /**
     * Loads RecordSetListData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  RecordSetListData Returns RecordSetListData
     */
    protected function _loadRecordSetListData(\SimpleXMLElement $sxml)
    {
        $result = new RecordSetList();
        $result->setRoute53($this->route53);
        $result->setMaxItems($this->exist($sxml->MaxItems) ? (int)$sxml->MaxItems : null);
        $result->setIsTruncated($this->exist($sxml->IsTruncated) ? ((string)$sxml->IsTruncated == 'true') : null);
        $result->setNextRecordName($this->exist($sxml->NextRecordName) ? (string)$sxml->NextRecordName : null);
        $result->setNextRecordType($this->exist($sxml->NextRecordType) ? (string)$sxml->NextRecordType : null);

        if (!empty($sxml->ResourceRecordSets)) {
            foreach ($sxml->ResourceRecordSets->ResourceRecordSet as $v) {
                $item = $this->_loadRecordSetData($v);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads RecordSetData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  RecordSetData Returns RecordSetData
     */
    protected function _loadRecordSetData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v)) {
            $name = (string)$v->Name;
            $item = new RecordSetData();
            $item->setRoute53($this->route53);
            $item
                ->setName($name)
                ->setType($this->exist($v->Type) ? (string) $v->Type : null)
                ->setTtl($this->exist($v->TTL) ? (string) $v->TTL : null)
                ->setResourceRecord($this->exist($v->ResourceRecords) ? $this->_loadRecordListData($v->ResourceRecords) : null)
                ->setHealthId($this->exist($v->HealthCheckId) ? (string) $v->HealthCheckId : null)
                ->setSetIdentifier($this->exist($v->SetIdentifier) ? (string) $v->SetIdentifier : null)
                ->setWeight($this->exist($v->Weight) ? (int) $v->Weight : null)
                ->setAliasTarget($this->exist($v->AliasTarget) ? $this->_loadAliasTargetData($v->AliasTarget) : null)
                ->setRegion($this->exist($v->Region) ? (string) $v->Region : null)
                ->setFailover($this->exist($v->Failover) ? (string) $v->Failover : null)
            ;
        }
        return $item;
    }

    /**
     * Loads RecordListData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  RecordListData Returns RecordListData
     */
    protected function _loadRecordListData(\SimpleXMLElement $sxml)
    {
        $result = new RecordList();
        $result->setRoute53($this->route53);

        if (!empty($sxml)) {
            foreach ($sxml->ResourceRecord as $v) {
                $item = $this->_loadRecordData($v);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads RecordData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  RecordData Returns RecordData
     */
    protected function _loadRecordData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v)) {
            $value = (string)$v->Value;
            $item = new RecordData();
            $item->setRoute53($this->route53);
            $item->setValue($value);
        }
        return $item;
    }

    /**
     * Loads AliasTargetData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  AliasTargetData Returns AliasTargetData
     */
    protected function _loadAliasTargetData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v)) {
            $zoneId = (string)$v->HostedZoneId;
            $item = new AliasTargetData();
            $item->setRoute53($this->route53);
            $item
                ->setZoneId($zoneId)
                ->setDnsName($this->exist($v->DNSName) ? (string) $v->DNSName : null)
                ->setEvaluateTargetHealth($this->exist($v->EvaluateTargetHealth) ? (string) $v->EvaluateTargetHealth : null)
            ;
        }
        return $item;
    }

    /**
     * Loads Change data from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  ChangeData Returns ChangeData
     */
    protected function _loadChangeData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $batchId = str_replace('/change/', '', $sxml->Id);
            $item = new ChangeData();
            $item->setRoute53($this->route53);
            $item
                ->setChangeId($batchId)
                ->setStatus($this->exist($sxml->Status) ? (string) $sxml->Status : null)
                ->setSubmittedAt($this->exist($sxml->SubmittedAt) ? new DateTime((string)$sxml->SubmittedAt, new DateTimeZone('UTC')) : null)
            ;
        }
        return $item;
    }

    /**
     * GET Health check action
     *
     * @param   string           $healthId  required.
     * @return  HealthData Returns health check data.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function getHealthCheck($healthId)
    {
        $result = null;
        $options = array();
        $response = $this->client->call('GET', $options, sprintf('/healthcheck/%s', self::escape($healthId)));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!empty($sxml->HealthCheck)) {
                $result = $this->_loadHealthCheckData($sxml->HealthCheck);
                $result->setOriginalXml($response->getRawContent());
            }
        }
        return $result;
    }

    /**
     * GET Health Checks List action
     *
     * @param   MarkerType       $marker optional The query parameters.
     * @return  HealthList Returns the list of health checks.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function describeHealthChecks(MarkerType $marker = null)
    {
        $result = null;
        $options = array();
        $aQueryString = array();
        if ($marker !== null) {
            if ($marker->marker !== null) {
                $aQueryString[] = 'marker=' . self::escape($marker->marker);
            }
            if ($marker->maxItems !== null) {
                $aQueryString[] = 'maxitems=' . self::escape($marker->maxItems);
            }
        }

        $response = $this->client->call('GET', $options, '/healthcheck' . (!empty($aQueryString) ? '?' . join('&', $aQueryString) : ''));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->_loadHealthCheckListData($sxml);
            $result->setOriginalXml($response->getRawContent());
        }

        return $result;
    }

    /**
     * DELETE Health check action
     *
     * @param   string                        $healthId ID of the health check.
     * @return  bool                          Returns TRUE on success.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function deleteHealthCheck($healthId)
    {
        $result = false;
        $options = array();
        $response = $this->client->call('DELETE', $options, sprintf('/healthcheck/%s', self::escape($healthId)));
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = true;
        }
        return $result;
    }

    /**
     * Loads HealthListData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  HealthListData Returns HealthListData
     */
    protected function _loadHealthCheckListData(\SimpleXMLElement $sxml)
    {
        $result = new HealthList();
        $result->setRoute53($this->route53);
        $result->setMarker($this->exist($sxml->NextMarker) ? (string)$sxml->NextMarker : null);
        $result->setMaxItems($this->exist($sxml->MaxItems) ? (int)$sxml->MaxItems : null);
        $result->setIsTruncated($this->exist($sxml->IsTruncated) ? ((string)$sxml->IsTruncated == 'true') : null);

        if (!empty($sxml->HealthChecks)) {
            foreach ($sxml->HealthChecks->HealthCheck as $v) {
                $item = $this->_loadHealthCheckData($v);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads HealthData from simple xml object
     *
     * @param   \SimpleXMLElement $v
     * @return  HealthData Returns HealthData
     */
    protected function _loadHealthCheckData(\SimpleXMLElement $v)
    {
        $item = null;
        if ($this->exist($v)) {
            $healthId = (string)$v->Id;
            $item = new HealthData();
            $item->setRoute53($this->route53);
            $item
                ->setHealthId($healthId)
                ->setCallerReference($v->CallerReference)
                ->setHealthConfig($this->exist($v->HealthCheckConfig) ? $this->_loadHealthCheckConfigData($v->HealthCheckConfig) : null)
            ;
        }
        return $item;
    }

     /**
     * Loads HealthConfigData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  HealthConfigData Returns HealthConfigData
     */
    protected function _loadHealthCheckConfigData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new HealthConfigData();
            $item->setRoute53($this->route53);
            $item->ipAddress = (string) $sxml->IPAddress;
            $item->port = $this->exist($sxml->Port) ? (int) $sxml->Port : null;
            $item->type = (string) $sxml->Type;
            $item->resourcePath = $this->exist($sxml->ResourcePath) ? (string) $sxml->ResourcePath : null;
            $item->domainName = $this->exist($sxml->FullyQualifiedDomainName) ? (string) $sxml->FullyQualifiedDomainName : null;
            $item->searchString = $this->exist($sxml->SearchString) ? (string) $sxml->SearchString : null;
            $item->requestInterval = $this->exist($sxml->RequestInterval) ? (int) $sxml->RequestInterval : null;
            $item->failureThreshold = $this->exist($sxml->FailureThreshold) ? (int) $sxml->FailureThreshold : null;
        }
        return $item;
    }
}