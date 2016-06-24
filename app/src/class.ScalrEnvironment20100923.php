<?php

use Scalr\Model\Entity;

class ScalrEnvironment20100923 extends ScalrEnvironment20090305
{
    protected function GetScalingMetrics()
    {
        $ResponseDOMDocument = $this->CreateResponse();

        $metricsNode = $ResponseDOMDocument->createElement("metrics");

        $dbFarmRole = $this->DBServer->GetFarmRoleObject();
        $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
        foreach($scalingManager->getFarmRoleMetrics() as $farmRoleScalingMetric)
        {
            $scalingMetric = $farmRoleScalingMetric->getMetric();

            if ($scalingMetric->accountId == 0)
                continue;

            $metric = $ResponseDOMDocument->createElement("metric");
            $metric->setAttribute("id", $scalingMetric->id);
            $metric->setAttribute("name", $scalingMetric->name);

            $metricFilePath = $ResponseDOMDocument->createElement("path", $scalingMetric->filePath);
            $metricRM = $ResponseDOMDocument->createElement("retrieve-method", $scalingMetric->retrieveMethod);

            $metric->appendChild($metricFilePath);
            $metric->appendChild($metricRM);

            $metricsNode->appendChild($metric);
        }

        $ResponseDOMDocument->documentElement->appendChild($metricsNode);

        return $ResponseDOMDocument;
    }

    protected function GetServiceConfiguration()
    {
        $ResponseDOMDocument = $this->CreateResponse();

        $node = $ResponseDOMDocument->createElement("newPresetsUsed", 1);
        $ResponseDOMDocument->documentElement->appendChild($node);

        return $ResponseDOMDocument;
    }
}
