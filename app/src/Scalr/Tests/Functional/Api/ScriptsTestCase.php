<?php

namespace Scalr\Tests\Functional\Api;

use Exception;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleScript;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;

/**
 * Test case for Scripts, ScriptVersions and RoleScripts tests
 *
 * @author N.V.
 */
class ScriptsTestCase extends ApiTestCase
{

    const ENTITY_NAMESPACE = 'Scalr\Model\Entity';

    /**
     * Test UUID
     * Used as part of created object identifiers and descriptions
     *
     * @var string
     */
    protected $uuid;

    public function __construct($name = null, $data = [], $dataName = null)
    {
        parent::__construct($name, $data, $dataName);

        $this->uuid = uniqid($this->getTestName());
    }

    /**
     * Creates scripts from definition
     *
     * @param array $scriptsData    Scripts definitions
     *
     * @return Script[]
     */
    public static function generateScripts($scriptsData)
    {
        $scripts = [];

        $number = 1;

        foreach ($scriptsData as $scriptData) {
            $scriptData['accountId'] = static::$user->getAccountId();
            $scriptData['createdById'] = static::$user->getId();
            $scriptData['envId'] = static::$env->id;

            $name = static::getTestName();

            $scriptData['name'] = "{$name}-script-{$number}";
            $scriptData['description'] = "{$name}-script-{$number}";

            $scripts[] = static::createEntity(new Script(), $scriptData);
        }

        return $scripts;
    }

    /**
     * Creates versions for specified script from versions definitions
     *
     * @param Script    $script         Script for witch created versions
     * @param array     $versionsData   Versions definitions
     *
     * @return ScriptVersion[]
     *
     * @throws Exception
     */
    public static function generateVersions(Script $script, array $versionsData)
    {
        $versions = [];

        try {
            $latestVersion = $script->getLatestVersion()->version + 1;
        } catch (Exception $e) {
            $latestVersion = 1;
        }

        foreach ($versionsData as $versionData) {
            $versionData['scriptId'] = $script->id;
            $versionData['version'] = $latestVersion++;

            $versionData['changedById'] = static::$testUserId;
            $versionData['changedByEmail'] = static::$user->getEmail();

            $versions[] = static::createEntity(new ScriptVersion(), $versionData);;
        }

        return $versions;
    }

    /**
     * Creates orchestration rule for specified role from rule definitions
     *
     * @param Role  $role
     * @param array $rulesData
     *
     * @return array
     */
    public static function generateRules(Role $role, array $rulesData)
    {
        $rules = [];

        foreach ($rulesData as $ruleData) {
            $ruleData['roleId'] = $role->id;

            $rules[] = static::createEntity(new RoleScript(), $ruleData);
        }

        return $rules;
    }
}