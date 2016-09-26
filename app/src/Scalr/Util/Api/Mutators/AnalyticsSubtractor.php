<?php

namespace Scalr\Util\Api\Mutators;

use Scalr\System\Config\Yaml;
use Scalr\Util\Api\SpecMutator;

/**
 * AnalyticsSubtractor mutator, which corrects API specification according to Scalr analytics settings
 *
 * @author N.V.
 */
class AnalyticsSubtractor extends SpecMutator
{

    /**
     * {@inheritdoc}
     * @see ApiSpecMutator::apply()
     */
    public function apply(Yaml $config, $version)
    {
        if (!$config->{"scalr.analytics.enabled"}) {
            $this->removeItem('definitions.CostCenter');
            $this->removeItem('definitions.CostCenterForeignKey');

            $this->removeItem('definitions.Project');
            $this->removeItem('definitions.ProjectDetailResponse');
            $this->removeItem('definitions.ProjectForeignKey');
            $this->removeItem('definitions.ProjectListResponse');

            $this->removeItem('paths./{envId}/projects/');
            $this->removeItem('paths./{envId}/projects/{projectId}/');

            $this->removeItem('paths./{envId}/cost-centers/');
            $this->removeItem('paths./{envId}/cost-centers/{costCenterId}');

            $this->removeItem('definitions.Farm.properties.project');
            $this->removeItem('definitions.Farm.required', ['project']);
        }
    }
}