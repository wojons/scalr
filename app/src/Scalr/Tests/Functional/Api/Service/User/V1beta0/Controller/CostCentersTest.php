<?php


namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity\Account\EnvironmentProperty;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\ApiTestResponse;

/**
 * CostCenters test
 *
 * @author N.V.
 */
class CostCentersTest extends ApiTestCase
{

    /**
     * Get Cost Centers using identifier
     *
     * @param string $ccId           Cost Center identifier
     * @param int    $envId optional Environment identifier
     *
     * @return ApiTestResponse
     */
    public function getCostCenter($ccId, $envId = null)
    {
        $uri = $envId === null ? self::getAccountApiUrl("/cost-centers/{$ccId}") : self::getUserApiUrl("/cost-centers/{$ccId}", $envId);

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * Return list of Cost Centers available in this account or environment
     *
     * @param array  $filters    optional filterable properties
     * @param int    $envId      optional Environment identifier
     * @param int    $maxResults optional Limits result set
     * @return array
     */
    public function listCostCenters(array $filters = [], $envId = null, $maxResults = null)
    {
        $envelope = null;
        $ccs = [];

        $uri = $envId === null ? self::getAccountApiUrl('/cost-centers') : self::getUserApiUrl('/cost-centers', $envId);

        do {
            $params = $filters;

            if ($maxResults) {
                $params[ApiController::QUERY_PARAM_MAX_RESULTS] = $maxResults;
            }

            if (isset($envelope->pagination->next)) {
                $parts = parse_url($envelope->pagination->next);
                parse_str($parts['query'], $params);
            }

            $response = $this->request($uri, Request::METHOD_GET, $params);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $this->assertDescribeResponseNotEmpty($response);

            $envelope = $response->getBody();

            foreach ($envelope->data as $v) {
                $ccs[] = $v;
            }
        } while (!empty($envelope->pagination->next) && !$maxResults);

        return $ccs;
    }

    /**
     * @test
     */
    public function testComplex()
    {
        $ccs = $this->listCostCenters([], self::$testEnvId, 1);

        $adapter = $this->getAdapter('costCenter');

        $filterable = $adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        foreach ($ccs as $cc) {
            foreach ($filterable as $property) {
                $filterValue = $cc->{$property};

                $listResult = $this->listCostCenters([$property => $filterValue], self::$testEnvId, 3);

                foreach ($listResult as $filtered) {
                    $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                }
            }

            $response = $this->getCostCenter($cc->id, self::$testEnvId);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            /* @var $dbCc CostCentreEntity */
            $dbCc = CostCentreEntity::findPk($cc->id);

            $this->assertFalse($dbCc->archived);
            $this->assertEquals($this->getEnvironment()->getProperty(EnvironmentProperty::SETTING_CC_ID), $dbCc->ccId);
            $this->assertObjectEqualsEntity($response->getBody()->data, $dbCc, $adapter);
        }

        /* @var $acCc CostCentreEntity */
        $acCc = $this->createCostCenter(['name' => $this->getTestName('account cost center')]);

        //get account cost centre from user api
        $response = $this->getCostCenter($acCc->ccId, self::$testEnvId);
        $this->assertEquals(403, $response->status, $this->printResponseError($response));
        $this->assertEmpty($this->listCostCenters(['name' => $acCc->ccId], self::$testEnvId));
        $this->assertEmpty($this->listCostCenters(['billingCode' => $acCc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)], self::$testEnvId));

        /* @var $acCcArch CostCentreEntity */
        $acCcArch = $this->createCostCenter([
            'name' => $this->getTestName('account archived cost center'),
            'archived' => CostCentreEntity::ARCHIVED
        ]);
        $this->assertEmpty($this->listCostCenters(['name' => $acCcArch->ccId], self::$testEnvId));
        $this->assertEmpty($this->listCostCenters(['billingCode' => $acCcArch->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)], self::$testEnvId));
    }

    /**
     * @test
     */
    public function testAccountComplex()
    {
        $ccsArch = $this->createCostCenter(['name' => $this->getTestName(), 'archived' => CostCentreEntity::ARCHIVED]);

        $ccs = $this->listCostCenters([], null, 1);

        $adapter = $this->getAdapter('costCenter');

        $filterable = $adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        foreach ($ccs as $cc) {
            foreach ($filterable as $property) {
                $filterValue = $cc->{$property};

                $listResult = $this->listCostCenters([$property => $filterValue], null, 3);

                foreach ($listResult as $filtered) {
                    $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                }
            }

            $response = $this->getCostCenter($cc->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            /* @var $dbCc CostCentreEntity */
            $dbCc = CostCentreEntity::findPk($cc->id);

            /* @var $acCs AccountCostCenterEntity */
            $acCs = AccountCostCenterEntity::findOne([['ccId' => $dbCc->ccId]]);

            $this->assertFalse($dbCc->archived);
            $this->assertEquals($this->getUser()->accountId, $acCs->accountId);
            $this->assertObjectEqualsEntity($response->getBody()->data, $dbCc, $adapter);
        }

        $filterByName = $this->listCostCenters(['name' => $ccsArch->name], null, 1);
        $this->assertNotEmpty($filterByName);
        foreach ($filterByName as $cc) {
            $this->assertObjectEqualsEntity($cc, $ccsArch, $adapter);
            $this->assertNotContains($cc, $ccs, "List of Cost Centers shouldn't  have an archived project", false, false);
        }

        $filterByBillingCode = $this->listCostCenters(['billingCode' => $ccsArch->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)], null, 1);
        $this->assertNotEmpty($filterByBillingCode);
        foreach ($filterByBillingCode as $cc) {
            $this->assertObjectEqualsEntity($cc, $ccsArch, $adapter);
            $this->assertNotContains($cc, $ccs, "List of Cost Centers shouldn't  have an archived project", false, false);
        }
    }

    /**
     * Creates new cost center for testing purposes
     *
     * @param array $data optional
     *
     * @return CostCentreEntity
     */
    public function createCostCenter(array $data = [])
    {
        $user = $this->getUser();

        $ccData = array_merge([
            'name'           => $this->getTestName(),
            'accountId'      => $user->getAccountId(),
            'createdById'    => $user->id,
            'createdByEmail' => $user->email
        ], $data);

        /* @var $cc CostCentreEntity */
        $cc = $this->createEntity(new CostCentreEntity(), $ccData);
        $cc->saveProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, $ccData['name']);

        $this->createEntity(new AccountCostCenterEntity(), [
            'ccId'      => $cc->ccId,
            'accountId' => $ccData['accountId']
        ]);

        return $cc;
    }

    /**
     * Also Removes Cost Centers properties generated for test
     *
     * {@inheritdoc}
     * @see Scalr\Tests\Functional\Api\ApiTestCase::tearDownAfterClass()
     */
    public static function tearDownAfterClass()
    {
        //We have to remove CostCenter properties as they don't have foreign keys
        foreach (static::$testData as $rec) {
            if ($rec['class'] === CostCentreEntity::class) {
                CostCentrePropertyEntity::deleteByCcId($rec['pk'][0]);
            }
        }

        parent::tearDownAfterClass();
    }
}