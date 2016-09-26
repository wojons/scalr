<?php
namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\System\Config\Yaml;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Api\Rest\Http\Request;
use Scalr\Tests\Functional\Api\V2\ApiTest;
use Scalr\Tests\Functional\Api\V2\Iterator\ApiFixtureIterator;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Model\Entity;
use Scalr\Model\Entity\Account;
use InvalidArgumentException;
use DirectoryIterator;
use Exception;
use SERVER_PLATFORMS;
use Scalr;

/**
 * Class ApiFixture
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (03.12.2015)
 */
abstract class ApiFixture
{
    /**
     * Regexp for object property prom fixtures
     * property example &{3}ProjectsData or &ProjectsData
     */
    const PROPERTY_REGEXP = '#^&(\{(\d{1,3})\})?(.*)$#';

    /**
     * Paths pointer
     */
    const PATHS_DATA = 'paths';

    /**
     * Acl const
     */
    const ACL_FULL_ACCESS = 'fullAccess';
    const ACL_NO_ACCESS = 'noAccess';
    const ACL_READ_ONLY_ACCESS = 'readOnly';

    /**
     * Api user namespace
     */
    const API_NAMESPACE = '\Scalr\Api\Service\User';

    /**
     * Test data pointer
     */
    const TEST_DATA = null;

    /**
     * API version
     *
     * @var string
     */
    protected static $apiVersion = 'v1beta0';

    /**
     * User identifier
     * @var int
     */
    protected static $testUserId;

    /**
     * Test user
     *
     * @var User
     */
    protected static $user;

    /**
     * Environment identifier
     *
     * @var int
     */
    protected static $testEnvId;

    /**
     * Environment instance
     *
     * @var Environment
     */
    protected static $env;

    /**
     * Acl types
     *
     * @var array
     */
    protected static $aclTypes = [
        self::ACL_FULL_ACCESS,
        self::ACL_NO_ACCESS,
        self::ACL_READ_ONLY_ACCESS
    ];

    /**
     * Users types
     *
     * @var array
     */
    protected static $userTypes = [
        User::TYPE_ACCOUNT_OWNER,
        User::TYPE_ACCOUNT_SUPER_ADMIN,
        User::TYPE_ACCOUNT_ADMIN,
        User::TYPE_TEAM_USER
    ];

    /**
     * Default fixtures operation
     *
     * @var array
     */
    protected static $defaultOperation = [
        'method'     => Request::METHOD_GET,
        'response'   => 200,
        'params'     => null,
        'filterable' => null,
        'body'       => null
    ];

    /**
     * Data from fixtures
     *
     * @var array
     */
    protected $sets = [];

    /**
     * Entity adapter name
     *
     * @var string
     */
    protected $adapterName;

    /**
     * Api endpoint type
     *
     * @var string
     */
    protected $type;

    /**
     * TestDataFixtures constructor.
     * @param array  $sets data from yaml fixtures
     * @param string $type api specifications type
     */
    public function __construct(array $sets, $type)
    {
        $this->sets = $sets;
        $this->type = $type;
    }

    /**
     * Generate test objects for ApiTest
     */
    abstract public function prepareTestData();

    /**
     * Generate data for ApiTest
     *
     * @param string $fixtures patch to fixtures directory
     * @param string $type api specifications type
     * @return array
     * @throws \Scalr\System\Config\Exception\YamlException
     */
    public static function loadData($fixtures, $type)
    {
        // set config
        static::$testUserId = \Scalr::config('scalr.phpunit.apiv2.userid');
        static::$user = User::findPk(static::$testUserId);
        static::$testEnvId = \Scalr::config('scalr.phpunit.apiv2.envid');
        static::$env = Environment::findPk(static::$testEnvId);
        $data = [];
        foreach (new ApiFixtureIterator(new DirectoryIterator($fixtures), $type) as $fileInfo) {
            $class = __NAMESPACE__ . '\\' . ucfirst($fileInfo->getBasename('.yaml'));
            try {
                /* @var $object ApiFixture */
                $object = new $class(Yaml::load($fileInfo->getPathname())->toArray(), $type);
                $object->prepareTestData();
                $pathInfo = $object->preparePathInfo();
            } catch (Exception $e) {
                $pathInfo = [array_merge([$class, $e, null, null ,null], static::$defaultOperation)];
            }
            $data = array_merge($data, $pathInfo);
        }
        return $data;
    }

    /**
     * Prepare options for each patch options
     *
     * @return array
     */
    protected function preparePathInfo()
    {
        if (array_key_exists(static::PATHS_DATA, $this->sets)) {
            $paramData = [];
            foreach ($this->sets[static::PATHS_DATA] as $pathInfo) {
                isset($pathInfo['acl']) && in_array($pathInfo['acl'], static::$aclTypes) ?: $pathInfo['acl'] = static::ACL_FULL_ACCESS;
                isset($pathInfo['userType']) && in_array($pathInfo['userType'], static::$userTypes) ?: $pathInfo['userType'] = User::TYPE_TEAM_USER;
                $adapter = isset($pathInfo['adapter']) ? $pathInfo['adapter'] : $this->adapterName;
                $pathInfo['adapter'] = static::API_NAMESPACE . '\\' . ucfirst(static::$apiVersion) . '\\Adapter\\' . ucfirst($adapter) . 'Adapter';
                foreach ($pathInfo['operations'] as $index => $operation) {
                    $operation = array_merge(static::$defaultOperation, $operation);
                    try {
                        $paramData[] = [
                            $pathInfo['uri'],
                            $this->type,
                            $pathInfo['adapter'],
                            $pathInfo['acl'],
                            $pathInfo['userType'],
                            $operation['method'],
                            $operation['response'],
                            (array)$this->resolveProperty($operation['params'], $index),
                            (array)$this->resolveProperty($operation['filterable'], $index),
                            (array)$this->resolveProperty($operation['body'], $index),
                        ];
                    } catch (InvalidArgumentException $e) {
                        $paramData[] = array_merge([get_class($this), $e, $pathInfo['adapter'], $pathInfo['acl'], $pathInfo['userType']], static::$defaultOperation);
                    }
                }
            }
            return $paramData;
        }

        throw new InvalidArgumentException(sprintf('Element %s should exist in fixtures', static::PATHS_DATA));
    }

    /**
     * Check Data if Data has reference another project resolve this data property
     *
     * @param  string $name data name
     */
    protected function prepareData($name)
    {
        if (!empty($this->sets[$name])) {
            foreach ($this->sets[$name] as $index => &$testData) {
                $object = [];
                foreach ($testData as $property => &$value) {
                    $value = $this->resolveProperty($value, $index);
                    if (preg_match('#^(\w*)\.(\w*)$#', $property, $math)) {
                        $propKey = array_pop($math);
                        $propertyName = array_pop($math);
                        $object[$propertyName] = [$propKey => $value];
                        unset($testData[$property]);
                    }
                }
                $testData = array_merge($testData, $object);
            }
        }
    }

    /**
     * If property has reference to the object, will return property or object
     *
     * @param $value string property name
     * @param $index int    property value
     * @return array
     */
    protected function resolveProperty($value, $index)
    {
        if (is_string($value) && preg_match(static::PROPERTY_REGEXP, $value, $matches)) {
            $objectData = explode('.', array_pop($matches), 2);
            $objectName = array_shift($objectData);
            $propValue = array_pop($objectData);

            $index = is_numeric($propIndex = array_pop($matches)) ? $propIndex : $index;
            if (!isset($this->sets[$objectName][$index])) {
                throw new InvalidArgumentException("$objectName with index $index don't exist in fixtures");
            }

            return ($propValue) ? $this->sets[$objectName][$index][$propValue] : $this->sets[$objectName][$index];
        }
        return $value;
    }

    /**
     * Create role category entity with data from fixtures
     *
     * @param string $name      Role category data name
     */
    protected function prepareRoleCategory($name)
    {
        foreach ($this->sets[$name] as &$rcData) {
            if (array_key_exists('envId', $rcData)) {
                $rcData['envId'] = static::$testEnvId;
            }
            if (array_key_exists('accountId', $rcData)) {
                $rcData['accountId'] = static::$user->getAccountId();
            }
            /* @var $rc Entity\RoleCategory */
            $rc = ApiTest::createEntity(new Entity\RoleCategory(), $rcData);
            $rcData['id'] = $rc->id;
        }
    }

    /**
     * Create role entity with data from fixtures
     *
     * @param string $name      Role category data name
     */
    protected function prepareRole($name)
    {
        foreach ($this->sets[$name] as &$roleData) {
            if (array_key_exists('envId', $roleData)) {
                $roleData['envId'] = static::$testEnvId;
            }
            if (array_key_exists('accountId', $roleData)) {
                $roleData['accountId'] = static::$user->getAccountId();
            }
            $settings = [];
            if (array_key_exists('settings', $roleData)) {
                $settings = $roleData['settings'];
                unset($roleData['settings']);
            }

            /* @var $role Entity\Role */
            $role = ApiTest::createEntity(new Entity\Role, $roleData);
            $roleData['id'] = $role->id;
            foreach ($settings as $name => $value) {
                ApiTest::createEntity(new Entity\RoleProperty(), [
                    'name' => $name,
                    'value' => $value,
                    'roleId' => $role->id
                ]);
                ApiTest::toDelete(Entity\RoleProperty::class, [$role->id, $name]);
            }
        }
    }

    /**
     * Creates and save farm role entity  with data from fixtures
     *
     * @param string $name      Role category data name
     */
    protected function prepareFarmRole($name)
    {
        foreach ($this->sets[$name] as &$frData) {
            /* @var  $fr Entity\FarmRole */
            $fr = new Entity\FarmRole();
            $settings = [];
            switch ($frData['platform']) {
                case SERVER_PLATFORMS::EC2:
                    $settings = [
                        Entity\FarmRoleSetting::INSTANCE_TYPE => 't1.micro',
                        Entity\FarmRoleSetting::AWS_AVAIL_ZONE => '',
                        Entity\FarmRoleSetting::SCALING_ENABLED => true,
                        Entity\FarmRoleSetting::SCALING_MIN_INSTANCES => 1,
                        Entity\FarmRoleSetting::SCALING_MAX_INSTANCES => 2
                    ];
                    break;
                case SERVER_PLATFORMS::GCE:
                    $settings = [
                        Entity\FarmRoleSetting::INSTANCE_TYPE => 'n1-standard-1',
                        Entity\FarmRoleSetting::GCE_CLOUD_LOCATION => 'us-central1-a',
                        Entity\FarmRoleSetting::SCALING_ENABLED => true,
                        Entity\FarmRoleSetting::SCALING_MIN_INSTANCES => 1,
                        Entity\FarmRoleSetting::SCALING_MAX_INSTANCES => 2
                    ];
                    break;
            }

            if (array_key_exists('settings', $frData)) {
                $settings = array_replace($settings, $frData['settings']);
                unset($frData['settings']);
            }

            foreach ($settings as $name => $setting) {
                $fr->settings[$name] = $setting;
            }

            $fr = ApiTest::createEntity($fr, $frData);
            $frData['id'] = $fr->id;
        }
    }

    /**
     * Creates and save Farm entity with data from fixtures
     *
     * @param string $name      Role category data name
     */
    protected function prepareFarm($name)
    {
        $this->prepareData($name);
        foreach ($this->sets[$name] as &$farmData) {
            $farm = new Entity\Farm();

            $farmData['changedById'] = static::$testUserId;
            $farmData['accountId'] = static::$user->getAccountId();
            $farmData['ownerId'] = static::$testUserId;
            if (empty($farmData['envId'])) {
                $farmData['envId'] = static::$testEnvId;
            }

            if (isset($farmData['settings'])) {
                foreach ($farmData['settings'] as $name => $setting) {
                    $farm->settings[$name] = $setting;
                }
                unset($farmData['settings']);
            }
            /* @var  $farm Entity\Farm */
            $farm = ApiTest::createEntity($farm, $farmData);
            $farmData['id'] = $farm->id;
        }
    }

    /**
     * Creates and save RoleImage entity  with data from fixtures
     *
     * @param string $name      Role category data name
     */
    protected function prepareRoleImage($name)
    {
        foreach ($this->sets[$name] as &$roleImageData) {
            if(in_array($roleImageData['platform'], [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::AZURE])) {
                $roleImageData['cloudLocation'] = '';
            }

            /* @var $image  Entity\Image */
            $image =  Entity\Image::findOne([
                ['cloudLocation' => $roleImageData['cloudLocation']],
                ['platform' => $roleImageData['platform']],
                ['$or'      => [['accountId' =>  static::$user->getAccountId()], ['accountId' => null]]],
            ]);

            if (empty($image)) {
                ApiTest::markTestIncomplete(sprintf(
                    'Image with cloudLocation %s and platform %s not isset', $roleImageData['cloudLocation'], $roleImageData['platform']
                ));
            }
            $roleImageData['imageId'] = $image->id;
            ApiTest::createEntity(new  Entity\RoleImage(),$roleImageData);
        }
    }

    /**
     * Creates and save CostCenter entity with data from fixtures
     *
     * @param string $name CostCenter data name
     */
    protected function prepareCostCenter($name)
    {
        foreach ($this->sets[$name] as &$ccData) {
            $ccData['accountId'] = self::$user->getAccountId();
            $properties = [];
            if (isset($ccData['properties'])) {
                $properties = $ccData['properties'];
                unset($ccData['properties']);
            }
            /* @var $cc CostCentreEntity */
            $cc = ApiTest::createEntity(new CostCentreEntity(), $ccData);
            $ccData['id'] = $cc->ccId;
            if (isset($properties['billingCode'])) {
                $cc->setProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, $properties['billingCode']);
                // to delete Cost Center properties
                ApiTest::toDelete(
                    CostCentrePropertyEntity::class,
                    [$cc->ccId, $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)]
                );
            }
        }
    }

    /**
     * Add CostCenter to account
     *
     * @param string $name CostCenter data name
     */
    protected function prepareAccountCostCenter($name)
    {
        foreach ($this->sets[$name] as &$accountCcData) {
            ApiTest::createEntity(
                new AccountCostCenterEntity(),
                ['ccId' => $accountCcData['ccId'], 'accountId' => static::$user->getAccountId()]
            );
        }
    }

    /**
     * Add Project entities for test
     *
     * @param string $name Project data name
     */
    protected function prepareProjects($name)
    {
        $ccId = Environment::findPk(static::$env->id)->getProperty(Account\EnvironmentProperty::SETTING_CC_ID);
        $this->prepareData($name);
        foreach ($this->sets[$name] as &$projectData) {
            $projectData['envId'] = self::$env->id;
            $projectData['accountId'] = self::$user->getAccountId();
            $projectData['createdById'] = self::$user->id;
            $projectData['createdByEmail'] = self::$user->email;
            $projectData['ccId'] = $ccId;
            $properties = [];
            if (isset($projectData['properties'])) {
                $properties = $projectData['properties'];
                unset($projectData['properties']);
            }

            /* @var $project ProjectEntity */
            $project = ApiTest::createEntity(new ProjectEntity(), $projectData);
            $projectData['id'] = $project->projectId;
            $project->setCostCenter(Scalr::getContainer()->analytics->ccs->get($projectData['ccId']));
            if (!empty($properties)) {
                if (isset($projectData['billingCode'])) {
                    $project->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $projectData['billingCode']);
                    ApiTest::toDelete(
                        ProjectPropertyEntity::class,
                        [$project->projectId, $project->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE)]
                    );
                }
                if (isset($projectData['leadEmail'])) {
                    $project->saveProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL, $projectData['leadEmail']);
                    ApiTest::toDelete(
                        ProjectPropertyEntity::class,
                        [$project->projectId, $project->getProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL)]
                    );

                }

                if (isset($projectData['description'])) {
                    $project->saveProperty(ProjectPropertyEntity::NAME_DESCRIPTION, $projectData['description']);
                    ApiTest::toDelete(
                        ProjectPropertyEntity::class,
                        [$project->projectId, $project->getProperty(ProjectPropertyEntity::NAME_DESCRIPTION)]
                    );
                }
            }
            $project->save();
        }
    }

    /**
     * Creates and save Script entities with data from fixtures
     *
     * @param string $name Script data name
     */
    protected function prepareScript($name)
    {
        foreach ($this->sets[$name] as &$scriptData) {
            $scriptData['envId'] = static::$testEnvId;
            $scriptData['accountId'] = static::$user->getAccountId();
            if (empty($scriptData['os'])) {
                $scriptData['os'] = Entity\Script::OS_LINUX;
            }

            /* @var $script Entity\Script */
            $script = ApiTest::createEntity(new Entity\Script(), $scriptData);
            $scriptData['id'] = $script->id;
        }
    }

    /**
     * Creates and save Account Team entities with data from fixtures
     *
     * @param string $name Account Team data name
     */
    protected function prepareAccountTeam($name)
    {
        foreach ($this->sets[$name] as &$accTeamData) {
            $accTeamData['accountId'] = static::$user->getAccountId();
            /* @var  $accTeam Account\Team() */
            $accTeam = ApiTest::createEntity(new Account\Team(), $accTeamData);
            $accTeamData['id'] = $accTeam->id;
        }
    }
}