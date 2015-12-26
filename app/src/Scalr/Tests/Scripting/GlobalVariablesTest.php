<?php

namespace Scalr\Tests\Scripting;

use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Tests\TestCase;
use Scalr_Scripting_GlobalVariables;
use Scalr\DataType\ScopeInterface;

/**
 * Global variables tests
 *
 * @author    Igor Vodiasov <invar@scalr.com>
 * @since     5.10.11
 */
class GlobalVariablesTest extends TestCase
{
    /**
     * Array of Scalr_Scripting_GlobalVariables objects for each scope except SERVER
     *
     * @var array
     */
    protected static $vars;

    /**
     * Array of arguments for methods setValues, getValues, listVariables for each scope except SERVER
     *
     * @var array
     */
    protected static $args;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (\Scalr::config('scalr.phpunit.skip_functional_tests')) {
            return;
        }

        $db = \Scalr::getDb();
        self::deleteTestVariables();

        $envId = \Scalr::config('scalr.phpunit.envid');
        if (!$envId) {
            return;
        }

        $env = \Scalr_Environment::init()->loadById($envId);

        self::$vars[ScopeInterface::SCOPE_SCALR] = new Scalr_Scripting_GlobalVariables();
        self::$vars[ScopeInterface::SCOPE_ACCOUNT] = new Scalr_Scripting_GlobalVariables($env->clientId, 0, ScopeInterface::SCOPE_ACCOUNT);
        self::$vars[ScopeInterface::SCOPE_ENVIRONMENT] = new Scalr_Scripting_GlobalVariables($env->clientId, $env->id, ScopeInterface::SCOPE_ENVIRONMENT);
        self::$args[ScopeInterface::SCOPE_SCALR] = self::$args[ScopeInterface::SCOPE_ACCOUNT] = self::$args[ScopeInterface::SCOPE_ENVIRONMENT] = [0, 0, 0, ''];

        /* @var Farm $farm */
        $farm = Farm::findOne([['envId' => $env->id]]);
        if ($farm) {
            self::$vars[ScopeInterface::SCOPE_FARM] = new Scalr_Scripting_GlobalVariables($env->clientId, $env->id, ScopeInterface::SCOPE_FARM);
            self::$args[ScopeInterface::SCOPE_FARM] = [0, $farm->id, 0, ''];

            /* @var FarmRole $farmRole */
            $farmRole = FarmRole::findOne([['farmId' => $farm->id]]);
            if ($farmRole) {
                self::$vars[ScopeInterface::SCOPE_ROLE] = new Scalr_Scripting_GlobalVariables($env->clientId, $env->id, ScopeInterface::SCOPE_ROLE);
                self::$args[ScopeInterface::SCOPE_ROLE] = [$farmRole->roleId, 0, 0, ''];

                self::$vars[ScopeInterface::SCOPE_FARMROLE] = new Scalr_Scripting_GlobalVariables($env->clientId, $env->id, ScopeInterface::SCOPE_FARMROLE);
                self::$args[ScopeInterface::SCOPE_FARMROLE] = [$farmRole->roleId, $farm->id, $farmRole->id, ''];
            }
        }
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        self::deleteTestVariables();
    }

    /**
     * Data provider for the testGlobalVariablesFunctional
     *
     * @return array
     */
    public function providerLoad()
    {
        $cases = [];

        // TODO: tests for FARM, ROLE, FARMROLE scopes

        /*
         * TEST 1 (simple variable):
         *  - variable is not existed
         *  - add variable
         *  - check variable in scalr and account scope
         *  - list variable
         *  - update variable in account scope
         *  - check variable in scalr and account scope
         *  - delete variable in scalr scope
         *  - check variable in scalr and account scope
         */
        $cases[] = [ScopeInterface::SCOPE_SCALR, 'get', [
            'name' => $this->getVarTestName('scalr_var')
        ], false];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name' => $this->getVarTestName('scalr_var'),
            'value' => '123'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'get', [
            'name' => $this->getVarTestName('scalr_var'),
            'current'   => [
                'name'          => $this->getVarTestName('scalr_var'),
                'value'         => '123',
                'flagFinal'     => '0',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => '',
                'scope'         => ScopeInterface::SCOPE_SCALR,
                'category'      => ''
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR],
            'category' => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'get', [
            'name' => $this->getVarTestName('scalr_var'),
            'default'   => [
                'name'          => $this->getVarTestName('scalr_var'),
                'value'         => '123',
                'scope'         => ScopeInterface::SCOPE_SCALR,
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR],
            'category' => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'list', [
            'name' => $this->getVarTestName('scalr_var'),
            'value' => '123',
            'private' => 0
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name' => $this->getVarTestName('scalr_var'),
            'value' => '234',
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'get', [
            'name' => $this->getVarTestName('scalr_var'),
            'current'   => [
                'name'          => $this->getVarTestName('scalr_var'),
                'value'         => '123',
                'flagFinal'     => '0',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => '',
                'scope'         => ScopeInterface::SCOPE_SCALR,
                'category'      => ''
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR],
            'category' => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'get', [
            'name' => $this->getVarTestName('scalr_var'),
            'current' => [
                'name'          => $this->getVarTestName('scalr_var'),
                'value'         => '234',
                'scope'         => ScopeInterface::SCOPE_ACCOUNT,
                'category'      => '',
                'flagFinal'     => '0',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => ''
            ],
            'default'   => [
                'name'          => $this->getVarTestName('scalr_var'),
                'value'         => '123',
                'scope'         => ScopeInterface::SCOPE_SCALR,
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR, ScopeInterface::SCOPE_ACCOUNT],
            'category' => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name' => $this->getVarTestName('scalr_var'),
            'flagDelete' => 1
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'get', [
            'name' => $this->getVarTestName('scalr_var')
        ], false];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'get', [
            'name' => $this->getVarTestName('scalr_var'),
            'current'   => [
                'name'          => $this->getVarTestName('scalr_var'),
                'value'         => '234',
                'flagFinal'     => '0',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => '',
                'scope'         => ScopeInterface::SCOPE_ACCOUNT,
                'category'      => ''
            ],
            'scopes' => [ScopeInterface::SCOPE_ACCOUNT],
            'category' => ''
        ], true];

        /*
         * TEST 2 (required in account scope variable with category):
         *  - add variable
         */
        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('required_var'),
            'value'         => '',
            'flagFinal'     => '0',
            'flagRequired'  => ScopeInterface::SCOPE_ACCOUNT,
            'flagHidden'    => '0',
            'format'        => '',
            'validator'     => '',
            'scope'         => ScopeInterface::SCOPE_SCALR,
            'category'      => 'category_one-test'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name' => $this->getVarTestName('required_var'),
            'value' => ''
        ], [$this->getVarTestName('required_var') => ['value' => [sprintf("%s is required variable", $this->getVarTestName('required_var'))]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name' => $this->getVarTestName('required_var'),
            'value' => '0'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'get', [
            'name' => $this->getVarTestName('required_var'),
            'current'   => [
                'name'          => $this->getVarTestName('required_var'),
                'value'         => '0',
                'scope'         => ScopeInterface::SCOPE_ACCOUNT,
                'category'      => '',
                'flagFinal'     => '0',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => ''
            ],
            'locked'   => [
                'value'         => '',
                'flagFinal'     => '0',
                'flagRequired'  => ScopeInterface::SCOPE_ACCOUNT,
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => '',
                'scope'         => ScopeInterface::SCOPE_SCALR,
                'category'      => 'category_one-test'
            ],
            'default'   => [
                'name'          => $this->getVarTestName('required_var'),
                'value'         => '',
                'scope'         => ScopeInterface::SCOPE_SCALR,
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR, ScopeInterface::SCOPE_ACCOUNT],
            'category' => 'category_one-test'
        ], true];

        /*
         * TEST 3 (check validation)
         */
        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '',
            'flagFinal'     => '0',
            'flagRequired'  => ScopeInterface::SCOPE_ACCOUNT,
            'flagHidden'    => '0',
            'format'        => '',
            'validator'     => '/^[12]$/',
            'scope'         => ScopeInterface::SCOPE_SCALR,
            'category'      => 'category_one-test'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'get', [
            'name' => $this->getVarTestName('required_var_2'),
            'current'   => [
                'name'          => $this->getVarTestName('required_var_2'),
                'value'         => '',
                'flagFinal'     => '0',
                'flagRequired'  => ScopeInterface::SCOPE_ACCOUNT,
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '/^[12]$/',
                'description'   => '',
                'scope'         => ScopeInterface::SCOPE_SCALR,
                'category'      => 'category_one-test'
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR],
            'category' => 'category_one-test'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '3',
            'validator'     => '/^[12]$/'
        ], [$this->getVarTestName('required_var_2') => ['value' => ["Value isn't valid because of validation pattern"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '0'
        ], [$this->getVarTestName('required_var_2') => ['value' => ["Value isn't valid because of validation pattern"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '1'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '2',
            'flagFinal'     => '1'
        ], [$this->getVarTestName('required_var_2') => ['flagFinal' => ["You can't redefine advanced settings (flags, format, validator, category)"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '2',
            'flagHidden'    => '1'
        ], [$this->getVarTestName('required_var_2') => ['flagHidden' => ["You can't redefine advanced settings (flags, format, validator, category)"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '2',
            'format'        => '%d'
        ], [$this->getVarTestName('required_var_2') => ['format' => ["You can't redefine advanced settings (flags, format, validator, category)"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '2',
            'validator'     => '[0-9]'
        ], [$this->getVarTestName('required_var_2') => ['validator' => ["You can't redefine advanced settings (flags, format, validator, category)"]]]];

        // check to case sensitivity
        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('REQUIRED_var_2'),
            'value'         => '',
        ], [$this->getVarTestName('REQUIRED_var_2') => ['name' => [sprintf("Name has been already defined as \"%s\"", $this->getVarTestName('required_var_2'))]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => 'Invalid-name',
            'value'         => '',
        ], ['Invalid-name' => ['name' => ["Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long."]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => '1name',
            'value'         => '',
        ], ['1name' => ['name' => ["Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long."]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => 'a',
            'value'         => '',
        ], ['a' => ['name' => ["Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long."]]]];

        $longName = 'abcdefghikl1abcdefghik21abcdefghik31abcdefghik41abcdefghik51abcdefghik61abcdefghik71abcdefghik81abcdefghik91abcdefghik1abcdefghia';
        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $longName,
            'value'         => '',
        ], [$longName => ['name' => ["Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long."]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'category'      => 'category.name'
        ], [$this->getVarTestName('name') => ['category' => ["Category should contain only letters, numbers, dashes and underscores, start and end with letter and be from 2 to 32 chars long"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'category'      => 'scalr_ui_defaults'
        ], [$this->getVarTestName('name') => ['category' => ["Prefix 'SCALR_' is reserved and cannot be used for user GVs"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'category'      => 'scalr_1'
        ], [$this->getVarTestName('name') => ['category' => ["Prefix 'SCALR_' is reserved and cannot be used for user GVs"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => 'scalr_test',
            'value'         => '',
        ], ['scalr_test' => ['name' => ["'SCALR_' prefix is reserved and cannot be used for user GVs"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'flagFinal'     => '1',
            'flagRequired'  => 'environment'
        ], [$this->getVarTestName('name') => ['flagFinal' => ["You can't set final and required flags both"], 'flagRequired' => ["You can't set final and required flags both"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "/test null" . chr(0) . "byte/"
        ], [$this->getVarTestName('name') => ['validator' => ["Validation pattern is not valid (NULL byte)"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "^[0-9]$"
        ], [$this->getVarTestName('name') => ['validator' => ["Validation pattern is not valid: invalid structure"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "/^[0-9]$/es"
        ], [$this->getVarTestName('name') => ['validator' => ["Validation pattern is not valid: invalid structure"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "/^//test$/i"
        ], [$this->getVarTestName('name') => ['validator' => ["Validation pattern is not valid: Unknown modifier '/'"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '1',
            'validator'     => "/^[23]$/is"
        ], [$this->getVarTestName('name') => ['value' => ["Value isn't valid because of validation pattern"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '0',
            'validator'     => "/^[23]$/is"
        ], [$this->getVarTestName('name') => ['value' => ["Value isn't valid because of validation pattern"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'format'        => 'd'
        ], [$this->getVarTestName('name') => ['format' => ["Format isn't valid"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'format'        => '%d%f'
        ], [$this->getVarTestName('name') => ['format' => ["Format isn't valid"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => "test null" . chr(0) . "byte"
        ], [$this->getVarTestName('name') => ['value' => [sprintf("Variable %s contains invalid non-printable characters (e.g. NULL characters).
                You might have copied it from another application that submitted invalid characters.
                To solve this issue, you can type in the variable manually.", $this->getVarTestName('name'))]
        ]]];

        /*
         * TEST 4 (final variable)
         */
        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('final_var'),
            'value'         => '5',
            'flagFinal'     => '1',
            'flagRequired'  => '',
            'flagHidden'    => '0',
            'format'        => '',
            'validator'     => '',
            'scope'         => ScopeInterface::SCOPE_SCALR,
            'category'      => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'get', [
            'name' => $this->getVarTestName('final_var'),
            'current'   => [
                'name'          => $this->getVarTestName('final_var'),
                'value'         => '5',
                'flagFinal'     => '1',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '',
                'description'   => '',
                'scope'         => ScopeInterface::SCOPE_SCALR,
                'category'      => ''
            ],
            'scopes' => [ScopeInterface::SCOPE_SCALR],
            'category' => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'      => $this->getVarTestName('final_var'),
            'value'     => '0'
        ], [$this->getVarTestName('final_var') => ['value' => ["You can't change final variable locked on scalr level"]]]];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'      => $this->getVarTestName('final_var'),
            'value'     => '1'
        ], [$this->getVarTestName('final_var') => ['value' => ["You can't change final variable locked on scalr level"]]]];

        $cases[] = [ScopeInterface::SCOPE_FARM, 'set', [
            'name'      => $this->getVarTestName('final_var'),
            'value'     => '2'
        ], [$this->getVarTestName('final_var') => ['value' => ["You can't change final variable locked on scalr level"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'      => $this->getVarTestName('final_var'),
            'value'     => '6'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'get', [
            'name'      => $this->getVarTestName('final_var'),
            'default'   => [
                'name'  => $this->getVarTestName('final_var'),
                'value' => '6',
                'scope' => ScopeInterface::SCOPE_SCALR,
            ],
            'scopes'    => [ScopeInterface::SCOPE_SCALR],
            'category'  => ''
        ], true];

        /*
         * TEST 5 (ui config vars)
         */
        $cases[] = [ScopeInterface::SCOPE_FARM, 'set', [
            'name'     => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'    => '1'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['name' => ["'SCALR_' prefix is reserved and cannot be used for user GVs"]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'     => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'    => '1'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'list', [
            'name'  => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value' => '1'
        ], false];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'getuidefaults', [
            'name'  => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value' => '1'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'    => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'   => 'a'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['value' => ["Value isn't valid because of validation pattern"]]]];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'    => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'   => 'a'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['value' => ["Value isn't valid because of validation pattern"]]]];

        $cases[] = [ScopeInterface::SCOPE_FARM, 'set', [
            'name'     => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'    => '1'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['name' => ["'SCALR_' prefix is reserved and cannot be used for user GVs"]]]];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'   => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'  => '0'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'getuidefaults', [
            'name' => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value' => '0'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'    => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'   => ''
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'getuidefaults', [
            'name' => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value' => '1'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'         => '1',
            'scope'         => ScopeInterface::SCOPE_ACCOUNT,
            'flagFinal'     => '1'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'get', [
            'name' => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'current'   => [
                'name'          => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
                'value'         => '1',
                'flagFinal'     => '1',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '/^[01]$/',
                'description'   => 'Reuse block storage device if an instance is replaced.',
                'scope'         => ScopeInterface::SCOPE_ACCOUNT,
                'category'      => 'scalr_ui_defaults'
            ],
            'scopes' => [ScopeInterface::SCOPE_ACCOUNT],
            'category' => 'scalr_ui_defaults'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'get', [
            'name'      => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'default'   => [
                'name'          => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
                'value'         => '1',
                'scope'         => ScopeInterface::SCOPE_ACCOUNT,
            ],
            'locked'   => [
                'value'         => '1',
                'flagFinal'     => '1',
                'flagRequired'  => '',
                'flagHidden'    => '0',
                'format'        => '',
                'validator'     => '/^[01]$/',
                'description'   => 'Reuse block storage device if an instance is replaced.',
                'scope'         => ScopeInterface::SCOPE_ACCOUNT,
                'category'      => 'scalr_ui_defaults'
            ],
            'scopes'    => [ScopeInterface::SCOPE_ACCOUNT],
            'category'  => 'scalr_ui_defaults'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'          => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'         => '0'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['value' => ["You can't change final variable locked on account level"]]]];

        return $cases;
    }

    /**
     * @test
     * @dataProvider providerLoad
     * @functional
     */
    public function testGlobalVariablesFunctional($scope, $action, $var, $expectedResult)
    {
        if (empty(self::$vars[$scope]) || empty(self::$args[$scope])) {
            $this->markTestSkipped(sprintf('No object for scope: %s', $scope));
        }

        switch ($action) {
            case 'set':
                $result = call_user_func_array([self::$vars[$scope], 'setValues'], array_merge([[$var]], self::$args[$scope], [false]));
                $this->assertEquals($expectedResult, $result);
                break;

            case 'get':
                $values = call_user_func_array([self::$vars[$scope], 'getValues'], self::$args[$scope]);
                $flag = false;
                foreach ($values as $value) {
                    if ($value['name'] == $var['name']) {
                        $flag = true;
                        $this->assertEquals($var, $value);
                        break;
                    }
                }

                $this->assertEquals($expectedResult, $flag);
                break;

            case 'list':
                $values = call_user_func_array([self::$vars[$scope], 'listVariables'], self::$args[$scope]);
                $flag = false;
                foreach ($values as $value) {
                    if ($value['name'] == $var['name']) {
                        if ($value['name'] == $var['name']) {
                            $flag = true;
                            $this->assertEquals($var, $value);
                            break;
                        }
                    }
                }
                $this->assertEquals($expectedResult, $flag);
                break;

            case 'getuidefaults':
                $values = call_user_func_array([self::$vars[$scope], 'getUiDefaults'], self::$args[$scope]);
                $this->assertTrue(isset($values[$var['name']]));
                $this->assertTrue(isset($values[$var['name']]) && $values[$var['name']] === $var['value']);
                break;
        }
    }

    /**
     * Deletes test variables from all tables
     *
     * @throws \Exception
     */
    public static function deleteTestVariables()
    {
        $db = \Scalr::getDb();

        $tables = [
            'variables',
            'account_variables',
            'client_environment_variables',
            'role_variables',
            'farm_variables',
            'farm_role_variables',
            'server_variables'
        ];

        foreach ($tables as $table) {
            $db->Execute("DELETE FROM $table WHERE name LIKE '%" . self::getInstallationId() . "' OR name = 'SCALR_UI_DEFAULT_STORAGE_RE_USE'");
        }

    }

    /**
     * Gets test name for GV
     *
     * @param string $suffix
     * @return string
     */
    private function getVarTestName($suffix = null)
    {
        return str_replace('-', '', $this->getTestName($suffix));
    }

}