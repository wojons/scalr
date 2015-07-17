<?php

namespace Scalr\Util\Api;

use Scalr\System\Config\Yaml;

/**
 * Abstract API Spec Mutator, interface for manipulation YAML-specification
 *
 * @author N.V.
 */
abstract class SpecMutator
{

    /**
     * Api spec reference
     *
     * @var array
     */
    private $spec;

    /**
     * Sets api spec
     *
     * @param $spec
     */
    public function setSpec(&$spec)
    {
        $this->spec = &$spec;
    }

    /**
     * Removes item from spec or values from item
     *
     * @param   string      $path            Item path
     * @param   array|null  $values optional Values which must be removed from item
     */
    public function removeItem($path, $values = null)
    {
        $parts = is_array($path) ? $path : (array) preg_split('/\.+/', $path);

        $entry = &$this->spec;

        $leave = $values === null ? 1 : 0;

        while (count($parts) > $leave) {
            $part = array_shift($parts);

            if (!isset($entry[$part])) {
                return;
            }

            $entry = &$entry[$part];
        }

        $count = count($parts);

        if ($values === null) {
            unset($entry[array_shift($parts)]);
        } else if (empty($count)) {
            $entry = array_values(array_diff($entry, $values));
        }
    }

    /**
     * Apply this mutator
     *
     * @param Yaml   $config  Scalr config
     * @param string $version Api version
     */
    public abstract function apply(Yaml $config, $version);
}