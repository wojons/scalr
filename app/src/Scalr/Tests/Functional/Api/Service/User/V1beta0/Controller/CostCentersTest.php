<?php


namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Http\Request;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
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
     * @param string $ccId
     *
     * @return ApiTestResponse
     */
    public function getCostCenter($ccId)
    {
        $uri = self::getUserApiUrl("/cost-centers/{$ccId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    public function listCostCenters(array $filters = [])
    {
        $envelope = null;
        $projects = [];
        $uri = self::getUserApiUrl('/cost-centers');

        do {
            $params = $filters;

            if (isset($envelope->pagination->next)) {
                $parts = parse_url($envelope->pagination->next);
                parse_str($parts['query'], $params);
            }

            $response = $this->request($uri, Request::METHOD_GET, $params);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $this->assertDescribeResponseNotEmpty($response);

            $envelope = $response->getBody();

            $projects[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $projects);
    }

    /**
     * @test
     */
    public function textComplex()
    {
        $ccs = $this->listCostCenters();

        $adapter = $this->getAdapter('costCenter');

        foreach ($ccs as $cc) {
            foreach ($adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE] as $property) {
                foreach($this->listCostCenters([ $property => $cc->{$property} ]) as $filteredProject) {
                    $this->assertEquals($cc->{$property}, $filteredProject->{$property});
                }
            }

            $response = $this->getCostCenter($cc->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbProject = CostCentreEntity::findPk($cc->id);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbProject, $adapter);
        }
    }
}