<?php
namespace Scalr\Service\Aws\Iam\V20100508;

use Scalr\Service\Aws;
use Scalr\Service\Aws\AbstractApi;
use Scalr\Service\Aws\Iam\DataType\AccessKeyMetadataData;
use Scalr\Service\Aws\Iam\DataType\AccessKeyMetadataList;
use Scalr\Service\Aws\Iam\DataType\AccessKeyData;
use Scalr\Service\Aws\Iam\DataType\ServerCertificateMetadataData;
use Scalr\Service\Aws\Iam\DataType\ServerCertificateMetadataList;
use Scalr\Service\Aws\Iam\DataType\UserData;
use Scalr\Service\Aws\IamException;
use Scalr\Service\Aws\Iam;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\EntityManager;
use Scalr\Service\Aws\Client\ClientInterface;
use Scalr\Service\Aws\Iam\DataType\RoleData;
use SimpleXMLElement;
use DateTime;
use DateTimeZone;
use Scalr\Service\Aws\Iam\DataType\RoleList;
use Scalr\Service\Aws\Iam\DataType\InstanceProfileList;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Iam\DataType\InstanceProfileData;
use Scalr\Service\Aws\Iam\AbstractIamDataType;

/**
 * Iam Api messaging.
 *
 * Implements Iam Low-Level API Actions.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     13.11.2012
 */
class IamApi extends AbstractApi
{

    /**
     * @var Iam
     */
    protected $iam;

    /**
     * Constructor
     *
     * @param   Iam                $iam           An Iam instance
     * @param   ClientInterface    $client        Client Interface
     */
    public function __construct(Iam $iam, ClientInterface $client)
    {
        $this->iam = $iam;
        $this->client = $client;
    }

    /**
     * PutUserPolicy action
     *
     * Adds (or updates) a policy document associated with the specified user.
     *
     * @param   string     $userName       Name of the user to associate the policy with.
     *                                     Length constraints: Minimum length of 1. Maximum length of 128.
     * @param   string     $policyName     Name of the policy document.
     *                                     Length constraints: Minimum length of 1. Maximum length of 128.
     * @param   string     $policyDocument The policy document.
     *                                     Length constraints: Minimum length of 1. Maximum length of 131072.
     * @return  bool       Returns true if policy is added (or updated)
     * @throws  IamException
     * @throws  ClientException
     */
    public function putUserPolicy($userName, $policyName, $policyDocument)
    {
        $result = false;
        $options = array(
            'UserName'       => (string) $userName,
            'PolicyName'     => (string) $policyName,
            'PolicyDocument' => (string) $policyDocument,
        );
        $response = $this->client->call('PutUserPolicy', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->ResponseMetadata)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = true;
        }
        return $result;
    }

    /**
     * DeleteUserPolicy action
     *
     * @param   string     $userName       Name of the user to associate the policy with.
     *                                     Length constraints: Minimum length of 1. Maximum length of 128.
     * @param   string     $policyName     Name of the policy document.
     *                                     Length constraints: Minimum length of 1. Maximum length of 128.
     * @return  bool       Returns true if policy is successfully removed.
     * @throws  IamException
     * @throws  ClientException
     */
    public function deleteUserPolicy($userName, $policyName)
    {
        $result = false;
        $options = array(
            'UserName'       => (string) $userName,
            'PolicyName'     => (string) $policyName,
        );
        $response = $this->client->call('DeleteUserPolicy', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->ResponseMetadata)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = true;
        }
        return $result;
    }

    /**
     * GetUserPolicy action
     *
     * Retrieves the specified policy document for the specified user.
     *
     * @param   string     $userName       Name of the user to associate the policy with.
     *                                     Length constraints: Minimum length of 1. Maximum length of 128.
     * @param   string     $policyName     Name of the policy document.
     *                                     Length constraints: Minimum length of 1. Maximum length of 128.
     * @return  string     Returns policy document
     * @throws  IamException
     * @throws  ClientException
     */
    public function getUserPolicy($userName, $policyName)
    {
        $result = null;
        $options = array(
            'UserName'       => (string) $userName,
            'PolicyName'     => (string) $policyName,
        );
        $response = $this->client->call('GetUserPolicy', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->GetUserPolicyResult)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = rawurldecode((string) $sxml->GetUserPolicyResult->PolicyDocument);
        }
        return $result;
    }

    /**
     * CreateUser action
     *
     * Creates a new user for your AWS account.
     *
     * @param   string     $userName Name of the user to create.
     *                               Length constraints: Minimum length of 1. Maximum length of 512.
     * @param   string     $path     optional  The path for the user name.
     *                               Length constraints: Minimum length of 1. Maximum length of 64.
     * @return  UserData   Returns Information about the user.
     * @throws  IamException
     * @throws  ClientException
     */
    public function createUser($userName, $path = null)
    {
        $result = null;
        $options = array(
            'UserName' => (string) $userName,
        );
        if ($path !== null) {
            $options['Path'] = (string) $path;
        }
        $response = $this->client->call('CreateUser', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->CreateUserResult)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = $this->_loadUserData($sxml->CreateUserResult->User);
            if ($this->iam->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($result);
            }
        }
        return $result;
    }

    /**
     * Loads userdata from simple xml source
     *
     * @param   \SimpleXMLElement $sxml
     * @return  UserData Returns new user data object
     */
    protected function _loadUserData(SimpleXMLElement &$sxml)
    {
        $userData = new UserData();
        $userData->setIam($this->iam);
        $userData
            ->setPath((string)$sxml->Path)
            ->setUserName((string)$sxml->UserName)
            ->setUserId((string)$sxml->UserId)
            ->setArn((string)$sxml->Arn)
            ->setCreateDate(
                isset($sxml->CreateDate) ?
                new \DateTime((string)$sxml->CreateDate, new \DateTimeZone('UTC')) :
                new \DateTime(null, new \DateTimeZone('UTC'))
            )
        ;
        return $userData;
    }

    /**
     * GetUser action
     *
     * Retrieves information about the specified user, including the user's path, GUID, and ARN.
     * If you do not specify a user name, IAM determines the user name implicitly based on the
     * AWS Access Key ID signing the request.
     *
     * @param   string     $userName optional Name of the user to get information about.
     * @return  UserData   Returns Information about the user.
     * @throws  IamException
     * @throws  ClientException
     */
    public function getUser($userName = null)
    {
        $result = null;
        $options = array();
        if ($userName !== null) {
            $options['UserName'] = (string) $userName;
        }
        $response = $this->client->call('GetUser', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->GetUserResult)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = $this->_loadUserData($sxml->GetUserResult->User);
            if ($this->iam->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($result);
            }
        }
        return $result;
    }


    /**
     * DeleteUser action
     *
     * Deletes the specified user.
     * NOTE! The user must not belong to any groups, have any keys or signing certificates,
     * or have any attached policies.
     *
     * @param   string    $userName Name of the user to delete.
     * @return  bool      Returns TRUE if user is successfully removed.
     * @throws  IamException
     * @throws  ClientException
     */
    public function deleteUser($userName)
    {
        $result = false;
        $options = array(
            'UserName' => (string) $userName,
        );
        $response = $this->client->call('DeleteUser', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->ResponseMetadata)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $user = $this->iam->user->get($options['UserName']);
            if ($user instanceof UserData) {
                $this->getEntityManager()->detach($user);
            }
            $result = true;
        }
        return $result;
    }

    /**
     * ListAccessKeys action.
     *
     * Returns information about the Access Key IDs associated with the specified user.
     * If there are none, the action returns an empty list.
     * Although each user is limited to a small number of keys, you can still paginate the results using the
     * MaxItems and Marker parameters.
     * If the UserName field is not specified, the UserName is determined implicitly based on the
     * AWS Access Key ID used to sign the request. Because this action works for access keys under the AWS account,
     * this API can be used to manage root credentials even if the AWS account has no associated users.
     * Note!
     * To ensure the security of your AWS account, the secret access key is accessible only during key
     * and user creation.
     *
     * @param   string       $userName optional
     * @param   string       $marker   optional
     * @param   int          $maxItems optional
     * @return  AccessKeyMetadataList Returns information about access keys
     * @throws  IamException
     * @throws  ClientException
     */
    public function listAccessKeys($userName = null, $marker = null, $maxItems = null)
    {
        $result = null;
        $options = array();
        if (isset($userName)) {
            $options['UserName'] = (string) $userName;
        }
        if (isset($marker)) {
            $options['Marker'] = (string) $marker;
        }
        if (isset($maxItems)) {
            $options['MaxItems'] = (int) $maxItems;
        }
        $response = $this->client->call('ListAccessKeys', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->ListAccessKeysResult)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $ptr = $sxml->ListAccessKeysResult;
            $result = new AccessKeyMetadataList();
            $result->setIam($this->iam);
            $result->setIsTruncated((string)$ptr->IsTruncated === 'false' ? false : true);
            if ($result->getIsTruncated()) {
                $result->setMarker((string)$ptr->Marker);
            }
            if (!empty($ptr->AccessKeyMetadata->member)) {
                foreach ($ptr->AccessKeyMetadata->member as $v) {
                    $acm = new AccessKeyMetadataData();
                    $acm
                        ->setUserName((string)$v->UserName)
                        ->setAccessKeyId((string)$v->AccessKeyId)
                        ->setStatus((string)$v->Status)
                        ->setCreateDate(
                            isset($v->CreateDate) ?
                            new DateTime((string)$v->CreateDate, new DateTimeZone('UTC')) :
                            new DateTime(null, new DateTimeZone('UTC'))
                        )
                    ;
                    $result->append($acm);
                    unset($acm);
                }
            }
        }
        return $result;
    }

    /**
     * CreateAccessKey action.
     *
     * Creates a new AWS Secret Access Key and corresponding AWS Access Key ID for the specified user.
     * The default status for new keys is Active.
     *
     * If you do not specify a user name, IAM determines the user name implicitly based on the AWS Access
     * Key ID signing the request. Because this action works for access keys under the AWS account, you can
     * use this API to manage root credentials even if the AWS account has no associated users.
     *
     * IMPORTANT!
     * To ensure the security of your AWS account, the Secret Access Key is accessible only during
     * key and user creation.You must save the key (for example, in a text file) if you want to be able
     * to access it again. If a secret key is lost, you can delete the access keys for the associated user
     * and then create new keys.
     *
     * @param   string        $userName  optional The user name that the new key will belong to.
     * @return  AccessKeyData Returns information about access key
     * @throws  IamException
     * @throws  ClientException
     */
    public function createAccessKey($userName = null)
    {
        $result = null;
        $options = array(
            'UserName' => (string) $userName,
        );
        $response = $this->client->call('CreateAccessKey', $options);
        if ($response->getError() === false) {
            //Success
            $sxml = simplexml_load_string($response->getRawContent());
            if (!isset($sxml->CreateAccessKeyResult)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = new AccessKeyData();
            $result->setIam($this->iam);
            if (!empty($sxml->CreateAccessKeyResult->AccessKey)) {
                $ptr = $sxml->CreateAccessKeyResult->AccessKey;
                $result
                    ->setUserName((string)$ptr->UserName)
                    ->setAccessKeyId((string)$ptr->AccessKeyId)
                    ->setStatus((string)$ptr->Status)
                    ->setSecretAccessKey((string)$ptr->SecretAccessKey)
                    ->setCreateDate(
                        isset($ptr->CreateDate) ?
                        new \DateTime((string)$ptr->CreateDate, new \DateTimeZone('UTC')) :
                        new \DateTime(null, new \DateTimeZone('UTC'))
                    )
                ;
            }
        }
        return $result;
    }

    /**
     * DeleteAccessKey action
     *
     * Deletes the access key associated with the specified user.
     *
     * If you do not specify a user name, IAM determines the user name implicitly based on the AWS Access
     * Key ID signing the request. Because this action works for access keys under the AWS account, you can
     * use this API to manage root credentials even if the AWS account has no associated users.
     *
     * @param   string     $accessKeyId The Access Key ID for the Access Key ID
     *                                  and Secret Access Key you want to delete.
     * @param   string     $userName    optional Name of the user whose key you want to delete.
     * @return  bool       Returns TRUE if access key is successfully removed.
     * @throws  IamException
     * @throws  ClientException
     */
    public function deleteAccessKey($accessKeyId, $userName = null)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'AccessKeyId' => (string) $accessKeyId,
            'UserName'    => ($userName !== null ? (string) $userName : null),
        ));
    }

    /**
     * Creates a new role for your AWS account.
     *
     * The policy grants permission to an EC2 instance to assume the role.
     * Currently, only EC2 instances can assume roles.
     *
     * @param   string     $roleName                 Name of the role to create. (1 - 64 characters)
     * @param   string     $assumeRolePolicyDocument The policy that grants an entity permission to assume the role.
     *                                               Length constraints: Minimum length of 1. Maximum length of 131072.
     * @param   string     $path                     optional
     * @return  RoleData   Returns RoleData on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function createRole($roleName, $assumeRolePolicyDocument, $path = null)
    {
        return $this->_makeDataCall(ucfirst(__FUNCTION__), 'Role', array(
            'RoleName'                 => (string) $roleName,
            'AssumeRolePolicyDocument' => (string) $assumeRolePolicyDocument,
            'Path'                     => $path,
        ));
    }

    /**
     * Deletes the specified role.
     *
     * The role must not have any policies attached.
     *
     * @param   string     $roleName Name of the role to remove. (1 - 64 characters)
     * @return  boolean    Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function deleteRole($roleName)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'RoleName' => (string) $roleName,
        ));
    }

    /**
     * Loads RoleData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  RoleData Returns RoleData
     */
    protected function _loadRoleData(SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new RoleData();
            $item->setIam($this->iam);
            $item->roleId = (string) $sxml->RoleId;
            $item->roleName = (string) $sxml->RoleName;
            $item->arn = $this->exist($sxml->Arn) ? (string)$sxml->Arn : null;
            $item->assumeRolePolicyDocument = rawurldecode((string)$sxml->AssumeRolePolicyDocument);
            $item->createDate = $this->exist($sxml->CreateDate) ? new DateTime((string)$sxml->CreateDate, new DateTimeZone('UTC')) : null;
            $item->path = $this->exist($sxml->Path) ? (string)$sxml->Path : null;
        }
        return $item;
    }

    /**
     * Loads RoleList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  RoleList  Returns RoleList
     */
    protected function _loadRoleList(SimpleXMLElement $sxml)
    {
        $list = new RoleList();
        $list->setIam($this->iam);
        if (!empty($sxml->member)) {
            foreach ($sxml->member as $v) {
                $list->append($this->_loadRoleData($v));
            }
        }
        return $list;
    }

    /**
     * Loads InstanceProfileData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  InstanceProfileData Returns InstanceProfileData
     */
    protected function _loadInstanceProfileData(SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new InstanceProfileData();
            $item->setIam($this->iam);
            $item->instanceProfileId = (string) $sxml->InstanceProfileId;
            $item->instanceProfileName = (string) $sxml->InstanceProfileName;
            $item->arn = $this->exist($sxml->Arn) ? (string)$sxml->Arn : null;
            $item->createDate = $this->exist($sxml->CreateDate) ? new DateTime((string)$sxml->CreateDate, new DateTimeZone('UTC')) : null;
            $item->path = $this->exist($sxml->Path) ? (string)$sxml->Path : null;
            $item->setRoles($this->_loadRoleList($sxml->Roles));
        }
        return $item;
    }

    /**
     * Loads InstanceProfileList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  InstanceProfileList  Returns InstanceProfileList
     */
    protected function _loadInstanceProfileList(SimpleXMLElement $sxml)
    {
        $list = new InstanceProfileList();
        $list->setIam($this->iam);
        if (!empty($sxml->member)) {
            foreach ($sxml->member as $v) {
                $list->append($this->_loadInstanceProfileData($v));
            }
        }
        return $list;
    }

    /**
     * Loads ServerCertificateMetadataList from simple xml object
     *
     * @param   SimpleXMLElement $xml
     * @return  ServerCertificateMetadataList
     */
    protected function _loadServerCertificateList(SimpleXMLElement $xml)
    {
        $list = new ServerCertificateMetadataList();
        $list->setIam($this->iam);

        $list->setIsTruncated((string)$xml->IsTruncated === 'false' ? false : true);
        if ($list->getIsTruncated()) {
            $list->setMarker((string)$xml->Marker);
        }

        if (!empty($xml->member)) {
            foreach ($xml->member as $member) {
                $list->append($this->_loadServerCertificateMetadataData($member));
            }
        }

        return $list;
    }

    /**
     * Loads ServerCertificateMetadataData from simple xml object
     *
     * @param   SimpleXMLElement $xml
     * @return  ServerCertificateMetadataData
     */
    protected function _loadServerCertificateMetadataData(SimpleXMLElement $xml)
    {
        $item = null;
        if ($this->exist($xml)) {
            $item = new ServerCertificateMetadataData();
            $item->setIam($this->iam);
            $item->serverCertificateId = (string)$xml->ServerCertificateId;
            $item->serverCertificateName = (string)$xml->ServerCertificateName;
            $item->arn = $this->exist($xml->Arn) ? (string)$xml->Arn : null;
            $item->expiration = $this->exist($xml->Expiration) ? new DateTime((string)$xml->Expiration, new DateTimeZone('UTC')) : null;
            $item->uploadDate = $this->exist($xml->UploadDate) ? new DateTime((string)$xml->UploadDate, new DateTimeZone('UTC')) : null;
            $item->path = $this->exist($xml->Path) ? (string)$xml->Path : null;
        }

        return $item;
    }

    /**
     * Makes list call
     *
     * This method is used internally for making list calls
     *
     * @param   string    $action       Action
     * @param   string    $listname     The class name for the list
     * @param   array     $opt          Options list
     * @param   string    $listElement  optional Node name of list results in XML response
     * @return  ListDataType Returns the list
     */
    protected function _makeListCall($action, $listname, array $opt, $listElement = null)
    {
        $result = null;
        $options = array();
        foreach ($opt as $key => $value) {
            if ($value !== null) {
                $options[$key] = $value;
            }
        }
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            if (empty($listElement)) {
                $listElement = "{$listname}s";
            }
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->{'_load' . $listname . 'List'}($sxml->{$action . 'Result'}->{$listElement});
            $result->setIsTruncated(((string)$sxml->{$action . 'Result'}->IsTruncated) !== 'false');
            if ($result->getIsTruncated()) {
                $result->setMarker((string)$sxml->{$action . 'Result'}->Marker);
            }
        }
        return $result;
    }

    /**
     * Makes data call
     *
     * This method is used internally for making data calls
     *
     * @param   string    $action    Action
     * @param   string    $dataname  The class name for the data
     * @param   array     $opt       options list
     * @return  AbstractIamDataType Returns the list
     */
    protected function _makeDataCall($action, $dataname, array $opt)
    {
        $result = null;
        $options = array();
        foreach ($opt as $key => $value) {
            if ($value !== null) {
                $options[$key] = $value;
            }
        }
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = $this->{'_load' . $dataname . 'Data'}($sxml->{$action . 'Result'}->$dataname);
        }
        return $result;
    }

    /**
     * Makes boolean call
     *
     * This method is used internally for making list calls
     *
     * @param   string    $action    Action
     * @param   array     $opt       options list
     * @return  AbstractIamDataType Returns the list
     */
    protected function _makeBooleanCall($action, array $opt)
    {
        $result = false;
        $options = array();
        foreach ($opt as $key => $value) {
            if ($value !== null) {
                $options[$key] = $value;
            }
        }
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->ResponseMetadata)) {
                throw new IamException('Unexpected response! ' . $response->getRawContent());
            }
            $result = true;
        }
        return $result;
    }

    /**
     * Lists the roles that have the specified path prefix
     *
     * @param   string   $pathPrefix optional The path prefix for filtering the results
     * @param   string   $marker     optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string   $maxItems   optional Maximum number of the records you want in the response
     * @return  RoleList Returns RoleList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function listRoles($pathPrefix = null, $marker = null, $maxItems = null)
    {
        return $this->_makeListCall(ucfirst(__FUNCTION__), 'Role', array(
            'PathPrefix' => $pathPrefix,
            'Marker'     => $marker,
            'MaxItems'   => $maxItems,
        ));
    }

    /**
     * Lists the instance profiles that have the specified path prefix
     *
     * @param   string   $pathPrefix optional The path prefix for filtering the results
     * @param   string   $marker     optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string   $maxItems   optional Maximum number of the records you want in the response
     * @return  InstanceProfileList  Returns InstanceProfileList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function listInstanceProfiles($pathPrefix = null, $marker = null, $maxItems = null)
    {
        return $this->_makeListCall(ucfirst(__FUNCTION__), 'InstanceProfile', array(
            'PathPrefix' => $pathPrefix,
            'Marker'     => $marker,
            'MaxItems'   => $maxItems,
        ));
    }

    /**
     * Lists the instance profiles that have the specified associated role
     *
     * @param   string   $roleName   The name of the role to list instance profiles for. (1-64 characters)
     * @param   string   $marker     optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string   $maxItems   optional Maximum number of the records you want in the response
     * @return  InstanceProfileList  Returns InstanceProfileList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function listInstanceProfilesForRole($roleName, $marker = null, $maxItems = null)
    {
        return $this->_makeListCall(ucfirst(__FUNCTION__), 'InstanceProfile', array(
            'RoleName' => $roleName,
            'Marker'   => $marker,
            'MaxItems' => $maxItems,
        ));
    }

    /**
     * Retrieves information about the specified instance profile,
     * including the instance profile's path, GUID, ARN, and role
     *
     * @param   string   $instanceProfileName Name of the instance profile to get information about.
     * @return  InstanceProfileData Returns InstanceProfileData object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function getInstanceProfile($instanceProfileName)
    {
        return $this->_makeDataCall(ucfirst(__FUNCTION__), 'InstanceProfile', array(
            'InstanceProfileName' => (string) $instanceProfileName,
        ));
    }

    /**
     * Creates a new instance profile
     *
     * @param   string   $instanceProfileName Name of the instance profile.
     * @param   stirng   $path                optional The path to the instance profile.
     * @return  InstanceProfileData Returns InstanceProfileData object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function createInstanceProfile($instanceProfileName, $path = null)
    {
        return $this->_makeDataCall(ucfirst(__FUNCTION__), 'InstanceProfile', array(
            'InstanceProfileName' => (string) $instanceProfileName,
            'Path' => $path,
        ));
    }

    /**
     * Deletes an instance profile
     *
     * @param   string   $instanceProfileName Name of the instance profile.
     * @return  boolean Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function deleteInstanceProfile($instanceProfileName)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'InstanceProfileName' => (string) $instanceProfileName,
        ));
    }

    /**
     * Adds the specified role to the specified instance profile
     *
     * @param   string   $instanceProfileName The name of the instance profile to update
     * @param   string   $roleName            The name of the role to add
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function addRoleToInstanceProfile($instanceProfileName, $roleName)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'InstanceProfileName' => (string) $instanceProfileName,
            'RoleName' => (string) $roleName,
        ));
    }

    /**
     * Removes the specified role from the specified instance profile
     *
     * @param   string   $instanceProfileName The name of the instance profile to update
     * @param   string   $roleName            The name of the role to remove
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function removeRoleFromInstanceProfile($instanceProfileName, $roleName)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'InstanceProfileName' => (string) $instanceProfileName,
            'RoleName' => (string) $roleName,
        ));
    }

    /**
     * Updates the policy that grants an entity permission to assume a role
     *
     * @param   string   $roleName       The name of the role to update
     * @param   string   $policyDocument The policy that grants an entity permission to assume the role.
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function updateAssumeRolePolicy($roleName, $policyDocument)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'PolicyDocument' => (string) $policyDocument,
            'RoleName' => (string) $roleName,
        ));
    }

    /**
     * Gets an entity manager
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->iam->getEntityManager();
    }

    /**
     * Lists the server certificates that have the specified path prefix
     *
     * @param   string $pathPrefix              optional The path prefix for filtering the results
     * @param   string $marker                  optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string $maxItems                optional Maximum number of the records you want in the response
     * @return  ServerCertificateMetadataList   Returns ServerCertificateMetadataList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function listServerCertificates($pathPrefix = null, $marker = null, $maxItems = null)
    {
        return $this->_makeListCall(ucfirst(__FUNCTION__), 'ServerCertificate', array(
            "PathPrefix"    => $pathPrefix,
            "Marker"        => $marker,
            "MaxItems"      => $maxItems
        ), 'ServerCertificateMetadataList');
    }

    /**
     * Upload server certificate that have the specified path prefix
     *
     * @param   string   $certificateBody       The contents of the public key certificate in PEM-encoded format
     * @param   string   $privateKey            The contents of the private key in PEM-encoded format
     * @param   string   $serverCertificateName The name for the server certificate. The name of the certificate cannot contain any spaces
     * @param   string   $certificateChain      optional The contents of the certificate chain. This is typically a concatenation of the PEM-encoded public key certificates of the chain
     * @param   string   $pathPrefix            optional The path for the server certificate
     * @return  ServerCertificateMetadataData   Returns ServerCertificateMetadataData object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function uploadServerCertificate($certificateBody, $privateKey, $serverCertificateName, $certificateChain = null, $pathPrefix = null)
    {
        return $this->_makeDataCall(ucfirst(__FUNCTION__), 'ServerCertificateMetadata', array(
            "CertificateBody"       => $certificateBody,
            "PrivateKey"            => $privateKey,
            "ServerCertificateName" => $serverCertificateName,
            "CertificateChain"      => $certificateChain,
            "Path"                  => $pathPrefix
        ));
    }

    /**
     * Delete server certificate action
     *
     * Deletes the specified server certificate
     * NOTE! If you are using a server certificate with Elastic Load Balancing, deleting the certificate could have implications for your application
     *
     * @param   string $serverCertificateName The name of the server certificate you want to delete.
     * @return  bool Returns TRUE if server certificate has been successfully removed.
     * @throws  IamException
     * @throws  ClientException
     */
    public function deleteServerCertificate($serverCertificateName)
    {
        return $this->_makeBooleanCall(ucfirst(__FUNCTION__), array(
            'ServerCertificateName' => (string)$serverCertificateName
        ));
    }
}
