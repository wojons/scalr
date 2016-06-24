<?php

namespace Scalr\UI;
use Scalr\UI\Request\JsonData;

// TODO: move to Scalr\Ui namespace

/**
 * Some useful methods for UI
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 */
class Utils
{
    /**
     * Convert extjs-coded order value to AbstractEntity::find format
     *
     * @param  JsonData $order
     * @param  array    $default
     * @param  array    $allowed
     * @return array
     */
    public static function convertOrder(JsonData $order, array $default = [], array $allowed = [])
    {
        $result = [];
        foreach ($order as $param) {
            if (isset($param['property']) && isset($param['direction']) && $param['property'] && in_array($param['property'], $allowed)) {
                $direction = strtolower($param['direction']);
                $result[$param['property']] = $direction == 'asc';
            }
        }

        return empty($result) ? $default : $result;
    }

    /**
     * Returns scalarizr repozitory title
     *
     * @param  string $repo
     * @return string
     */
    public static function getScalarizrUpdateRepoTitle($repo)
    {
        $repos = [
            'latest' => 'Scalr Agent Hosted Edition',
            'stable' => 'Scalr Agent Enterprise Edition'
        ];
        
        return isset($repos[$repo]) ? $repos[$repo] : $repo;
    }
}
