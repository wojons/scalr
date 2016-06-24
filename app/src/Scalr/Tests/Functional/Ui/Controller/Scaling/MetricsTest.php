<?php

namespace Scalr\Tests\Functional\Ui\Controller\Scaling;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Scaling_Metrics.
 *
 * @author  Roman Kolodnitskyi <r.kolodnitskyi@scalr.com>
 * @since   17.06.2015
 */
class MetricsTest extends WebTestCase
{
    private $testMetricName;
    private $namePattern = '/^[A-Za-z0-9_]{5,}/';

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->testMetricName = 'testmetric' . $this->getInstallationId();
    }

    private function checkMetricList($response)
    {
        $this->assertResponseDataHasKeys(
            [
                'id',
                'name'  => $this->matchesRegularExpression($this->namePattern),
                'alias' => $this->logicalNot($this->isEmpty()),
                'scope' => $this->logicalNot($this->isEmpty())
            ],
            $response,
            true,
            'metrics'
        );

        foreach ($response['metrics'] as $id => $metric) {
            $this->assertEquals($id, $metric['id']);

            if ($metric['name'] == $this->testMetricName || $metric['name'] == 'Invalid-metric-Name!') {
                $this->request('/scaling/metrics/xRemove', ['metrics' => json_encode([$id])], 'POST');
            }
        }
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Scaling_Metrics::xGetListAction
     */
    public function testXGetListAction()
    {
        $response = $this->request('/scaling/metrics/xGetList');
        $this->checkMetricList($response);
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Scaling_Metrics::getListAction
     */
    public function testGetListAction()
    {
        $response = $this->request('/scaling/metrics/getList');
        $this->checkMetricList($response);
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Scaling_Metrics::xListMetricsAction
     */
    public function testXListMetricsAction()
    {
        $response = $this->request('/scaling/metrics/xListMetrics');
        $this->assertResponseDataHasKeys(
            [
                'id',
                'accountId' => $this->logicalNot($this->identicalTo(0)),
                'envId' => $this->logicalNot($this->identicalTo(0)),
                'name'  => $this->matchesRegularExpression($this->namePattern),
                'filePath',
                'retrieveMethod' => $this->logicalOr($this->matchesRegularExpression('/^(read|execute)$/'), $this->isNull()),
                'calcFunction'   => $this->logicalOr($this->matchesRegularExpression('/^(avg|sum|max)$/'), $this->isNull()),
                'algorithm' => $this->matchesRegularExpression('/^(Sensor|DateTime)$/'),
                'alias' => $this->logicalNot($this->isEmpty())
            ],
            $response,
            true
        );
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Scaling_Metrics::xSaveAction
     * @covers Scalr_UI_Controller_Scaling_Metrics::xRemoveAction
     * @depends testXGetListAction
     */
    public function testXSave()
    {
        //in first case try to create metric with invalid name and receive error
        $response = $this->request(
            '/scaling/metrics/xSave',
            [
                'name'  => 'Invalid-metric-Name!',
                'filePath'  => '',
                'retrieveMethod'    => 'read',
                'calcFunction'  => 'avg',
                'algorithm' => 'Sensor'
            ],
            'POST'
        );

        $this->assertArrayHasKey('errors', $response);

        if ($response['success'] && isset($response['metric'])) {
            $this->request('/scaling/metrics/xRemove', ['metrics' => json_encode([$response['metric']['id']])], 'POST');
            $this->assertTrue(false, 'Metric with invalid name creates success without validation errors.');
        }

        //then create valid metric
        $response = $this->request(
            '/scaling/metrics/xSave',
            [
                'name'  => $this->testMetricName,
                'filePath'  => '',
                'retrieveMethod'    => 'read',
                'calcFunction'  => 'avg',
                'algorithm' => 'Sensor'
            ],
            'POST'
        );

        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('successMessage', $response);
        $this->assertArrayHasKey('metric', $response);

        $id = $response['metric']['id'];

        //then try to create metric with same name and receive error
        $response = $this->request(
            '/scaling/metrics/xSave',
            [
                'name'  => $this->testMetricName,
                'filePath'  => '',
                'retrieveMethod'    => 'execute',
                'calcFunction'  => 'sum',
                'algorithm' => 'DateTime'
            ],
            'POST'
        );

        $this->assertArrayHasKey('errors', $response);

        if ($response['success'] && isset($response['metric'])) {
            $this->request('/scaling/metrics/xRemove', ['metrics' => json_encode([$response['metric']['id']])], 'POST');
            $this->assertTrue(false, 'Metric with duplicate name creates success without validation errors.');
        }

        //and finally remove this metric
        $response = $this->request('/scaling/metrics/xRemove', ['metrics' => json_encode([$id])], 'POST');
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('successMessage', $response);
    }
}
