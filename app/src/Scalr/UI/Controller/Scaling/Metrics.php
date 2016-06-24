<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;

/**
 * Class Scalr_UI_Controller_Scaling_Metrics.
 */
class Scalr_UI_Controller_Scaling_Metrics extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'metricId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xGetListAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS);
        $this->response->data(['metrics' => Entity\ScalingMetric::getList($this->getEnvironmentId())]);
    }

    /**
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function getListAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS);
        $this->response->data(['metrics' => Entity\ScalingMetric::getList($this->getEnvironmentId())]);
    }

    /**
     * Save metric.
     *
     * @param  string $name
     * @param  string $retrieveMethod
     * @param  string $calcFunction
     * @param  int    $metricId       optional
     * @param  string $filePath       optional
     * @param  bool   $isInvert       optional
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws \Scalr\Exception\ModelException
     */
    public function xSaveAction($name, $retrieveMethod, $calcFunction = null, $metricId = null, $filePath = null, $isInvert = false)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS, Acl::PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE);

        $validator = new Validator;

        if ($metricId) {
            /* @var $metric Entity\ScalingMetric */
            $metric = Entity\ScalingMetric::findPk($metricId);

            if (!$metric) {
                throw new Scalr_UI_Exception_NotFound();
            }

            $this->user->getPermissions()->validate($metric);
        } else {
            $metric = new Entity\ScalingMetric();
            $metric->accountId = $this->user->getAccountId();
            $metric->envId = $this->getEnvironmentId();
            $metric->alias = 'custom';
            $metric->algorithm = Entity\ScalingMetric::ALGORITHM_SENSOR;
        }

        if (!preg_match('/^' . Entity\ScalingMetric::NAME_REGEXP . '$/', $name)) {
            $validator->addError('name', 'Metric name should be both alphanumeric and greater than 5 chars');
        }

        if ($retrieveMethod == Entity\ScalingMetric::RETRIEVE_METHOD_URL_REQUEST) {
            $validator->addErrorIf($validator->validateUrl($filePath) !== true, 'filePath', 'Invalid URL');
        } else {
            $validator->addErrorIf($validator->validateNotEmpty($calcFunction) !== true, 'calcFunction', 'Calculation function is required');
        }

        $criteria = [];
        $criteria[] = ['name' => $name];
        if ($metricId) {
            $criteria[] = ['id' => ['$ne' => $metricId]];
        }

        if (Entity\ScalingMetric::findOne($criteria)) {
            $validator->addError('name', 'Metric with the same name already exists');
        }

        if ($validator->isValid($this->response)) {
            $metric->name = $name;
            $metric->filePath = $filePath;
            $metric->retrieveMethod = $retrieveMethod;
            $metric->calcFunction = $calcFunction;
            $metric->isInvert = $isInvert;

            $metric->save();

            $this->response->success('Scaling metric has been successfully saved.');
            $this->response->data(['metric' => get_object_vars($metric)]);
        }
    }

    /**
     * Remove metrics.
     *
     * @param JsonData $metrics json array of metricId to remove
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws \Scalr\Exception\ModelException
     */
    public function xRemoveAction(JsonData $metrics)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS, Acl::PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE);

        $processed = [];
        $err = [];

        foreach ($metrics as $metricId) {
            try {
                if (!$this->db->GetOne("SELECT id FROM farm_role_scaling_metrics WHERE metric_id=? LIMIT 1", [$metricId])) {
                    /* @var $metric Entity\ScalingMetric */
                    $metric = Entity\ScalingMetric::findOne([['id' => $metricId], ['envId' => $this->getEnvironmentId()]]);

                    if (!$metric) {
                        throw new Scalr_UI_Exception_NotFound();
                    }

                    $metric->delete();
                    $processed[] = $metricId;
                } else {
                    $err[] = sprintf(_('Metric #%s is used and cannot be removed'), $metricId);
                }
            } catch (Exception $e) {
                $err[] = $e->getMessage();
            }
        }

        if (!count($err)) {
            $this->response->success('Selected metric(s) successfully removed');
        } else {
            $this->response->warning(implode("\n", $err));
        }

        $this->response->data(['processed' => $processed]);
    }

    /**
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function viewAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS);
        $this->response->page('ui/scaling/metrics/view.js');
    }

    /**
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListMetricsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_SCALING_METRICS);

        $criteria = [['$or' => [['envId' => $this->getEnvironmentId()],['envId' => null]]]];
        $metrics = (array) Entity\ScalingMetric::result(Entity\ScalingMetric::RESULT_ENTITY_COLLECTION)
            ->find($criteria, null, ['id' => true, 'name' => true, 'filePath' => true]);

        $this->response->data(['data' => $metrics, 'total' => count($metrics)]);
    }
}
