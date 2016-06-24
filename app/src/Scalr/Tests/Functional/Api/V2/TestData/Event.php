<?php
namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity;
use Scalr\Tests\Functional\Api\V2\ApiTest;


class Event extends ApiFixture
{
    /**
     * Event definitions created for specifics request
     */
    const TEST_DATA_EVENT_DEFINITIONS = 'EventDefinitions';

    /**
     * Role categories created for specifics request
     */
    const TEST_DATA_ROLE_CATEGORIES = 'RoleCategories';

    /**
     * Scripts created for specifics request
     */
    const TEST_DATA_SCRIPTS = 'Scripts';

    /**
     * Roles created for specifics request
     */
    const TEST_DATA_ROLES = 'Roles';

    /**
     * Role scripts created for specifics request
     */
    const TEST_DATA_ROLE_SCRIPTS = 'RoleScripts';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'EventsData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'event';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_EVENT_DEFINITIONS])) {
            foreach ($this->sets[static::TEST_DATA_EVENT_DEFINITIONS] as &$eventDefinitionData) {
                $eventDefinitionData['envId'] = static::$testEnvId;
                $eventDefinitionData['accountId'] = static::$user->getAccountId();
                /* @var $eventDefinition Entity\EventDefinition */
                $eventDefinition = ApiTest::createEntity(new Entity\EventDefinition(), $eventDefinitionData);
                $eventDefinitionData['id'] = $eventDefinition->id;
            }
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE_CATEGORIES])) {
            foreach ($this->sets[static::TEST_DATA_ROLE_CATEGORIES] as &$roleCategoryData) {
                $roleCategoryData['envId'] = static::$testEnvId;
                $roleCategoryData['accountId'] = static::$user->getAccountId();
                /* @var $roleCategory Entity\RoleCategory */
                $roleCategory = ApiTest::createEntity(new Entity\RoleCategory(), $roleCategoryData);
                $roleCategoryData['id'] = $roleCategory->id;
            }
        }

        if (!empty($this->sets[static::TEST_DATA_SCRIPTS])) {
            $this->prepareScript(static::TEST_DATA_SCRIPTS);
        }

        $this->prepareData(static::TEST_DATA_ROLES);

        if (!empty($this->sets[static::TEST_DATA_ROLES])) {
            foreach ($this->sets[static::TEST_DATA_ROLES] as &$roleData) {
                $roleData['envId'] = static::$testEnvId;
                $roleData['accountId'] = static::$user->getAccountId();
                /* @var $role Entity\Role */
                $role = ApiTest::createEntity(new Entity\Role(), $roleData);
                $roleData['id'] = $role->id;
            }
        }

        $this->prepareData(static::TEST_DATA_ROLE_SCRIPTS);

        if (!empty($this->sets[static::TEST_DATA_ROLE_SCRIPTS])) {
            foreach ($this->sets[static::TEST_DATA_ROLE_SCRIPTS] as &$roleScriptData) {
                /* @var $roleScript Entity\RoleScript */
                $roleScript = ApiTest::createEntity(new Entity\RoleScript(), $roleScriptData);
                $roleScriptData['id'] = $roleScript->id;
            }
        }

        $this->prepareData(static::TEST_DATA);
    }
}
