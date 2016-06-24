<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingRuleAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingMetricAdapter;
use Scalr\Model\Entity;
use Scalr\Exception\ModelException;

/**
 * User/ScalingMetric API Controller
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.9 (05.02.2016)
 */
class ScalingMetrics extends ApiController
{
    /**
     * Gets default search criteria for Scaling Metric
     *
     * @return  array Returns array of the default search criteria
     */
    private function getDefaultCriteria()
    {
        return $this->getScopeCriteria();
    }

    /**
     * Gets the list of the available Scaling Metric
     *
     * @return ListResultEnvelope
     */
    public function describeAction()
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS);

        return $this->adapter('scalingMetric')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Create a new Custom Scaling Metric in this Environment
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function createAction()
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS, Acl::PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var ScalingMetricAdapter $metricAdapter */
        $metricAdapter = $this->adapter('scalingMetric');

        //Pre validates the request object
        $metricAdapter->validateObject($object, Request::METHOD_POST);
        $object->scope = $this->getScope();

        /* @var $metric Entity\ScalingMetric */
        //Converts object into Role entity
        $metric = $metricAdapter->toEntity($object);

        $metricAdapter->validateEntity($metric);
        $metric->alias = ScalingRuleAdapter::METRIC_BASIC;
        $metric->algorithm = Entity\ScalingMetric::ALGORITHM_SENSOR;

        //Saves entity
        $metric->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($metricAdapter->toData($metric));
    }

    /**
     * Gets Scaling Metric from database
     *
     * @param string $metricName Scaling metric's name.
     * @param bool   $restrictToCurrentScope optional Whether it should additionally check that role corresponds to current scope
     * @return Entity\ScalingMetric
     * @throws ApiErrorException
     */
    public function getScalingMetric($metricName, $restrictToCurrentScope = false)
    {
        $criteria = $this->getDefaultCriteria();
        $criteria[] = ['name' => $metricName];

        /* @var $event Entity\ScalingMetric */
        $metric = Entity\scalingMetric::findOne($criteria);
        if (empty($metric)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf("The Scaling Metric either does not exist or isn't in scope for the current %s.", $this->getScope()));
        }

        if ($restrictToCurrentScope && $metric->getScope() !== $this->getScope()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION,
                "The Scaling Metric is not either from the {$this->getScope()} scope or owned by your {$this->getScope()}."
            );
        }
        return $metric;
    }

    /**
     * Fetches detailed info about the Scaling Metric
     *
     * @param string $metricName Scaling metric's name.
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function fetchAction($metricName)
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS);

        return $this->result($this->adapter('scalingMetric')->toData($this->getScalingMetric($metricName)));
    }

    /**
     *  Modifies Custom Scaling Metrics attributes
     *
     * @param string $metricName Scaling metric's name.
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function modifyAction($metricName)
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS, Acl::PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $metricAdapter ScalingMetricAdapter */
        $metricAdapter = $this->adapter('scalingMetric');

        //Pre validates the request object
        $metricAdapter->validateObject($object, Request::METHOD_PATCH);

        $metric = $this->getScalingMetric($metricName, true);

        //Copies all alterable properties to fetched Role Entity
        $metricAdapter->copyAlterableProperties($object, $metric);

        //Re-validates an Entity
        $metricAdapter->validateEntity($metric);

        //Saves verified results
        $metric->save();

        return $this->result($metricAdapter->toData($metric));
    }

    /**
     * Delete custom Scaling Metric from database
     *
     * @param string $metricName  Scaling metric's name.
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function deleteAction($metricName)
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS, Acl::PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE);

        $metric = $this->getScalingMetric($metricName, true);

        if ($metric->isUsed()) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, 'Scaling Metric is in use and can not be removed.');
        }

        $metric->delete();

        return $this->result(null);
    }
}