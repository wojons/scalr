<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Tests\Functional\Api\V2\ApiTest;
use Scalr\Model\Entity;
use DateTime;

/**
 * Class Server
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.21 (20.04.2016)
 */
class Server extends ApiFixture
{
    /**
     * Farm Role Image created for specifics request
     */
    const TEST_DATA_ROLE_IMAGE = 'RoleImage';

    /**
     * Farm Roles created for specifics request
     */
    const TEST_DATA_FARM_ROLE = 'FarmRole';

    /**
     * Farm created for specifics request
     */
    const TEST_DATA_FARM = 'Farm';
    
    /**
     * Role created for specifics request
     */
    const TEST_DATA_ROLE = 'Role';
    
    /**
     * Const test data servers
     */
    const TEST_DATA_SERVERS = 'Servers';

    /**
     * Const test data server properties
     */
    const TEST_DATA_SERVER_PROPERTIES = 'ServerProperties';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'ServerData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'server';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_ROLE])) {
            $this->prepareData(static::TEST_DATA_ROLE);
            $this->prepareRole(static::TEST_DATA_ROLE);
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE_IMAGE])) {
            $this->prepareData(static::TEST_DATA_ROLE_IMAGE);
            $this->prepareRoleImage(static::TEST_DATA_ROLE_IMAGE);
        }

        if (!empty($this->sets[static::TEST_DATA_FARM])) {
            $this->prepareFarm(static::TEST_DATA_FARM);
        }

        if (!empty($this->sets[static::TEST_DATA_FARM_ROLE])) {
            $this->prepareData(static::TEST_DATA_FARM_ROLE);
            $this->prepareFarmRole(static::TEST_DATA_FARM_ROLE);
        }

        if (!empty(static::TEST_DATA_SERVERS)) {
            $this->prepareData(static::TEST_DATA_SERVERS);
            foreach ($this->sets[static::TEST_DATA_SERVERS] as &$serverData) {
                $serverData['envId'] = static::$testEnvId;
                $serverData['accountId'] = static::$user->getAccountId();
                $serverData['added'] = new DateTime('now');
                /* @var  $server Entity\Server() */
                $server = new Entity\Server();
                $serverData['properties'][Entity\Server::LAUNCHED_BY_EMAIL] = static::$user->getEmail();
                foreach ($serverData['properties'] as $name => $value) {
                    $server->properties[$name] = $value;
                    //to delete server properties
                    ApiTest::toDelete(Entity\ServerProperty::class, [$serverData['serverId'], $name]);
                }
                unset($serverData['properties']);
                $server = ApiTest::createEntity($server, $serverData);
                $serverData['id'] = $server->id;
            }
        }
        $this->prepareData(static::TEST_DATA);
    }
}