<?php

namespace Scalr\Util\Api\Mutators;

use Scalr\System\Config\Yaml;
use Scalr\Util\Api\SpecMutator;

/**
 * CloudPlatformsSubstractor mutator, which corrects API specification according to allowed clouds settings
 *
 * @author N.V.
 */
class AllowedCloudsSubstractor extends SpecMutator
{

    /**
     * {@inheritdoc}
     * @see ApiSpecMutator::apply()
     */
    public function apply(Yaml $config, $version)
    {
        $clouds = \Scalr::config('scalr.allowed_clouds');

        if (isset($this->spec['definitions']['CloudLocation']['properties']['cloudPlatform']['enum'])) {
            $excessClouds = array_diff($this->spec['definitions']['CloudLocation']['properties']['cloudPlatform']['enum'], $clouds);

            if (!empty($excessClouds)) {
                $this->removeItem('definitions.CloudLocation.properties.cloudPlatform.enum', $excessClouds);
            }
        }

        if (in_array('ec2', $clouds)) {
            $clouds = array_merge($clouds, ['awsclassic', 'awsvpc']);
        }

        if (isset($this->spec['definitions']['PlacementConfiguration']['properties']['placementConfigurationType']['enum'])) {
            $placementConfigurationsTypes = $this->spec['definitions']['PlacementConfiguration']['properties']['placementConfigurationType']['enum'];
            $refs = $this->spec['definitions']['PlacementConfiguration']['x-concreteTypes'];

            foreach ($refs as $pos => $ref) {
                // '#/definitions/' length = 14
                // 'PlacementConfiguration' length = 22
                $refs[$pos] = substr($ref['$ref'], 14, -22);
            }

            $extraTypes = [];

            foreach ($placementConfigurationsTypes as $type) {
                // 'PlacementConfiguration' length = 22
                $typeName = substr($type, 0, -22);
                if (!in_array(strtolower($typeName), $clouds)) {
                    $extraTypes[] = $type;
                    $this->removeItem("definitions.{$type}");

                    $inRefs = array_search($typeName, $refs);
                    if ($inRefs !== false) {
                        $this->removeItem("definitions.PlacementConfiguration.x-concreteTypes", $inRefs);
                    }
                }
            }

            if (!empty($extraTypes)) {
                $this->removeItem('definitions.PlacementConfiguration.properties.placementConfigurationType.enum', $extraTypes);
            }
        }
    }
}