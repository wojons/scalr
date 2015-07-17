<?php
namespace Scalr\Service\Aws\Kms\V20141101;

use DateTime;
use DateTimeZone;
use SimpleXMLElement;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Client\ClientInterface;
use Scalr\Service\Aws\Kms\DataType\KeyList;
use Scalr\Service\Aws\Kms\DataType\KeyData;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Kms\DataType\KeyMetadataData;
use Scalr\Service\Aws\Kms\AbstractKmsDataType;
use Scalr\Service\Aws\Kms\KmsListDataType;
use Scalr\Service\Aws\Kms\DataType\PolicyNamesData;
use Scalr\Service\Aws\Kms\DataType\AliasList;
use Scalr\Service\Aws\Kms\DataType\AliasData;
use Scalr\Service\Aws\Kms\DataType\GrantConstraintData;
use Scalr\Service\Aws\Kms\DataType\GrantData;

/**
 * KMS API
 *
 * Implements KMS Low-Level API Actions.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9  (19.06.2015)
 */
class KmsApi extends Aws\AbstractApi
{
    /**
     * @var Aws\Kms
     */
    protected $kms;

    /**
     * Constructor
     *
     * @param   Aws\Kms             $kms          Kms instance
     * @param   ClientInterface     $client       Client Interface
     */
    public function __construct(Aws\Kms $kms, ClientInterface $client)
    {
        $this->kms = $kms;
        $this->client = $client;
    }

    /**
     * Invokes API call with Data response
     *
     * @param    string    $apiCall   API call
     * @param    string    $name      The name of the data type
     * @param    array     $options   optional The options to request
     * @param    string    $dsname    optional The name within the SimpleXML data set
     * @return   AbstractKmsDataType  Returns result depends on the type of the object
     * @throws   ClientException
     */
    protected function invokeData($apiCall, $name, $options = [], $dsname = null)
    {
        $result = null;

        $dsname = $dsname ?: $name;

        $response = $this->client->call($apiCall, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            $response = null;

            $r = $sxml->{$apiCall . "Result"};

            $result = $this->{"_load" . $name . "Data"}($r->$dsname);

            if ($this->exist($r->Truncated) && in_array('truncated', $result->getPropertiesForInheritance())) {
                $result->truncated = $r->Truncated == 'true';

                if ($result->truncated) {
                    $result->nextMarker = $r->NextMarker;
                }
            }
        }

        return $result;
    }

    /**
     * Invokes API call with List response
     *
     * @param    string    $apiCall   API call
     * @param    string    $name      The name of the data type
     * @param    array     $options   optional The options to request
     * @param    string    $dsname    optional The name within the SimpleXML data set
     * @return   KmsListDataType Returns result depends on the type of the object
     * @throws   ClientException
     */
    protected function invokeList($apiCall, $name, $options = [], $dsname = null)
    {
        $result = null;

        $dsname = $dsname ?: $name . 's';

        $response = $this->client->call($apiCall, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            $r = $sxml->{$apiCall . "Result"};

            $response = null;

            $result = $this->_loadListByName($name, $r->$dsname);

            if ($this->exist($r->Truncated) && in_array('truncated', $result->getPropertiesForInheritance())) {
                $result->truncated = $r->Truncated == 'true';

                if ($result->truncated) {
                    $result->nextMarker = $r->NextMarker;
                }
            }

            if (in_array('requestId', $result->getPropertiesForInheritance())) {
                $result->requestId = $sxml->ResponseMetadata->RequestId;
            }
        }

        return $result;
    }

    /**
     * Loads lists from simple xml object
     *
     * @param   string            $name The name of the ListDataType extended object without suffix "List"
     * @param   SimpleXMLElement  $sxml The simplexmlelement object
     * @return  KmsListDataType   Returns loaded object
     */
    protected function _loadListByName($name, SimpleXMLElement $sxml)
    {
        $class = 'Scalr\\Service\\Aws\\Kms\\DataType\\' . $name . 'List';

        $list = new $class;
        $list->setKms($this->kms);

        if (!empty($sxml->member)) {
            foreach ($sxml->member as $v) {
                $item = $this->{'_load' . $name . 'Data'}($v);
                $list->append($item);
                unset($item);
            }
        }

        return $list;
    }

    /**
     * KeyData loader
     *
     * @param   SimpleXMLElement $v request object
     * @return  KeyData  Returns KeyData
     */
    protected function _loadKeyData(SimpleXMLElement $v)
    {
        $item = null;

        if ($this->exist($v)) {
            $item = new KeyData();
            $item->setKms($this->kms);
            $item->keyId = $this->exist($v->KeyId) ? (string) $v->KeyId : null;
            $item->keyArn = $this->exist($v->KeyArn) ? (string) $v->KeyArn : null;
        }

        return $item;
    }

    /**
     * GrantConstraintData loader
     *
     * @param   SimpleXMLElement $v request object
     * @return  GrantConstraintData  Returns GrantConstraintData
     */
    protected function _loadGrantConstraintData(SimpleXMLElement $v)
    {
        $item = null;

        if ($this->exist($v)) {
            $item = new GrantConstraintData();
            $item->setKms($this->kms);
            $item->encryptionContextEquals = $this->exist($v->EncryptionContextEquals) ? (string) $v->EncryptionContextEquals : null;
            $item->encryptionContextSubset = $this->exist($v->EncryptionContextSubset) ? (string) $v->EncryptionContextSubset : null;
        }

        return $item;
    }

    /**
     * GrantData loader
     *
     * @param   SimpleXMLElement $v request object
     * @return  GrantData  Returns GrantData
     */
    protected function _loadGrantData(SimpleXMLElement $v)
    {
        $item = null;

        if ($this->exist($v)) {
            $item = new GrantData();
            $item->setKms($this->kms);
            $item->grantId = $this->exist($v->GrantId) ? (string) $v->GrantId : null;
            $item->constraints = $this->_loadListByName('GrantConstraint', $v->Constraints);
            $item->granteePrincipal = $this->exist($v->GranteePrincipal) ? (string) $v->GranteePrincipal : null;
            $item->issuingAccount = $this->exist($v->IssuingAccount) ? (string) $v->IssuingAccount : null;
            $item->operations = $this->exist($v->Operations) ? (string) $v->Operations : null;
            $item->retiringPrincipal = $this->exist($v->RetiringPrincipal) ? (string) $v->RetiringPrincipal : null;
        }

        return $item;
    }

    /**
     * AliasData loader
     *
     * @param   SimpleXMLElement $v request object
     * @return  AliasData Returns AliasData
     */
    protected function _loadAliasData(SimpleXMLElement $v)
    {
        $item = null;

        if ($this->exist($v)) {
            $item = new AliasData();
            $item->setKms($this->kms);
            $item->aliasArn = $this->exist($v->AliasArn) ? (string) $v->AliasArn : null;
            $item->aliasName = $this->exist($v->AliasName) ? (string) $v->AliasName : null;
            $item->targetKeyId = $this->exist($v->TargetKeyId) ? (string) $v->TargetKeyId : null;
        }

        return $item;
    }

    /**
     * KeyMetadataData loader
     *
     * @param   SimpleXMLElement $v request object
     * @return  KeyMetadataData  Returns KeyMetadataData
     */
    protected function _loadKeyMetadataData(SimpleXMLElement $v)
    {
        $item = null;

        if ($this->exist($v)) {
            $item = new KeyMetadataData((string)$v->KeyId);
            $item->setKms($this->kms);
            $item->awsAccountId = $this->exist($v->AWSAccountId) ? (string)$v->AWSAccountId : null;
            $item->arn = $this->exist($v->Arn) ? (string)$v->Arn : null;
            $item->creationDate = $this->exist($v->CreationDate) ? new DateTime($v->CreationDate, new DateTimeZone('UTC')) : null;
            $item->description = $this->exist($v->Description) ? (string)$v->Description : null;
            $item->enabled = $this->exist($v->Enabled) ? (string)$v->Enabled == 'true' : null;
            $item->keyUsage = $this->exist($v->KeyUsage) ? (string)$v->KeyUsage : null;
        }

        return $item;
    }

    /**
     * PolicyNamesData loader
     *
     * @param   SimpleXMLElement $v request object
     * @return  PolicyNamesData  Returns PolicyNamesData
     */
    protected function _loadPolicyNamesData(SimpleXMLElement $v)
    {
        $item = null;

        if ($this->exist($v)) {
            $item = new PolicyNamesData();
            $item->setKms($this->kms);
            foreach ($v->member as $p) {
                $item->policyNames[] = (string) $p;
            }
        }

        return $item;
    }

    /**
     * ListKeys API call
     *
     * Lists the customer master keys.
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  KeyList
     * @throws  ClientException
     */
    public function listKeys($marker = null, $maxRecords = null)
    {
        $options = [];

        if (!empty($marker)) {
            $options['Marker'] = (string) $marker;
        }

        if (!empty($maxRecords)) {
            $options['Limit'] = intval($maxRecords);
        }

        return $this->invokeList(ucfirst(__FUNCTION__), 'Key', $options);
    }

    /**
     * DescribeKey API call
     *
     * Provides detailed information about the specified customer master key
     *
     * @param   string   $keyId  The unique identifier for the customer master key.
     *          This value can be a globally unique identifier, a fully specified ARN to either an alias or a key,
     *          or an alias name prefixed by "alias/".
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Alias ARN Example - arn:aws:kms:us-east-1:123456789012:alias/MyAliasName
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *          - Alias Name Example - alias/MyAliasName
     *
     * @return  KeyMetadataData Returns KeyMetadataData
     * @throws  ClientException
     */
    public function describeKey($keyId)
    {
        return $this->invokeData(ucfirst(__FUNCTION__), 'KeyMetadata', ['KeyId' => (string) $keyId]);
    }


    /**
     * GetKeyPolicy API call
     *
     * Retrieves a policy attached to the specified key.
     *
     * @param   string    $keyId       Unique identifier for the customer master key.
     *          This value can be a globally unique identifier or the fully specified ARN to a key.
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *
     * @param   string    $policyName  String that contains the name of the policy.
     *          Currently, this must be "default".
     *          Policy names can be discovered by calling ListKeyPolicies
     *
     * @return  object    Returns a policy document
     * @throws  ClientException
     */
    public function getKeyPolicy($keyId, $policyName)
    {
        $result = null;

        $response = $this->client->call('GetKeyPolicy', [
            'KeyId'      => (string) $keyId,
            'PolicyName' => (string) $policyName
        ]);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            $result = @json_decode((string)$sxml->GetKeyPolicyResult->Policy);
        }

        return $result;
    }

    /**
     * ListKeyPolicies API call
     *
     * Retrieves a list of policies attached to a key.
     *
     * @param   string   $keyId      The unique identifier for the customer master key.
     *          This value can be a globally unique identifier, a fully specified ARN to either an alias or a key,
     *          or an alias name prefixed by "alias/".
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Alias ARN Example - arn:aws:kms:us-east-1:123456789012:alias/MyAliasName
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *          - Alias Name Example - alias/MyAliasName
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  PolicyNamesData
     * @throws  ClientException
     */
    public function listKeyPolicies($keyId, $marker = null, $maxRecords = null)
    {
        $options = ['KeyId' => (string) $keyId];

        if (!empty($marker)) {
            $options['Marker'] = (string) $marker;
        }

        if (!empty($maxRecords)) {
            $options['Limit'] = intval($maxRecords);
        }

        return $this->invokeData(ucfirst(__FUNCTION__), 'PolicyNames', $options);
    }

    /**
     * ListAliases API call
     *
     * Lists all of the key aliases in the account..
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  AliasList
     * @throws  ClientException
     */
    public function listAliases($marker = null, $maxRecords = null)
    {
        $options = [];

        if (!empty($marker)) {
            $options['Marker'] = (string) $marker;
        }

        if (!empty($maxRecords)) {
            $options['Limit'] = intval($maxRecords);
        }

        return $this->invokeList(ucfirst(__FUNCTION__), 'Alias', $options, 'Aliases');
    }

    /**
     * ListGrants API call
     *
     * List the grants for a specified key.
     *
     * @param   string   $keyId      A unique identifier for the customer master key.
     *          This value can be a globally unique identifier or the fully specified ARN to a key.
     *          - Key ARN Example - arn:aws:kms:us-east-1:123456789012:key/12345678-1234-1234-1234-123456789012
     *          - Globally Unique Key ID Example - 12345678-1234-1234-1234-123456789012
     *
     * @param   string   $marker     optional Set it to the value of the NextMarker in the response you just received
     * @param   int      $maxRecords optional Maximum number of keys you want listed in the response [1, 1000]
     * @return  \Scalr\Service\Aws\Kms\DataType\GrantList
     * @throws  ClientException
     */
    public function listGrants($keyId, $marker = null, $maxRecords = null)
    {
        $options = ['KeyId' => (string) $keyId];

        if (!empty($marker)) {
            $options['Marker'] = (string) $marker;
        }

        if (!empty($maxRecords)) {
            $options['Limit'] = intval($maxRecords);
        }

        return $this->invokeList(ucfirst(__FUNCTION__), 'Grant', $options);
    }
}