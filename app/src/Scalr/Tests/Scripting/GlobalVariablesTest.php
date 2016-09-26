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

    const ERR_VALUE_ISNT_VALID_1 = "Value isn't valid because of validation pattern";

    const ERR_VALUE_ISNT_VALID_JSON = "The value is not valid JSON";

    const ERR_YOU_CANT_CHANGE_FINAL_VARIABLE = "You can't change final variable locked on scalr level";

    const ERR_YOU_CANT_CHANGE_FINAL_VARIABLE_ACC_LEVEL = "You can't change final variable locked on account level";

    const ERR_PREFIX_SCALR_IS_RESERVED = "Prefix 'SCALR_' is reserved and cannot be used for user GVs";

    const ERR_PATTERN_IS_NOT_VALID_INVALID_STRUCTURE = "Validation pattern is not valid: invalid structure";

    const ERR_YOU_CANT_SET_FINAL_AND_REQUIRED_FLAG_BOTH = "You can't set final and required flags both";

    const ERR_NAME_SHOULD_CONTAIN = "Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long.";

    const ERR_CATEGORY_SHOULD_CONTAIN = "Category should contain only letters, numbers, dashes and underscores, start and end with letter and be from 2 to 32 chars long";

    const FORMAT_JSON = 'json';

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

        if (self::isSkippedFunctionalTest(self::TEST_TYPE_UI)) {
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

        /* @var $farm Farm */
        $farm = Farm::findOne([['envId' => $env->id]]);
        if ($farm) {
            self::$vars[ScopeInterface::SCOPE_FARM] = new Scalr_Scripting_GlobalVariables($env->clientId, $env->id, ScopeInterface::SCOPE_FARM);
            self::$args[ScopeInterface::SCOPE_FARM] = [0, $farm->id, 0, ''];

            /* @var $farmRole FarmRole */
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
        ], [$this->getVarTestName('required_var_2') => ['value' => [self::ERR_VALUE_ISNT_VALID_1]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('required_var_2'),
            'value'         => '0'
        ], [$this->getVarTestName('required_var_2') => ['value' => [self::ERR_VALUE_ISNT_VALID_1]]]];

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
        ], ['Invalid-name' => ['name' => [self::ERR_NAME_SHOULD_CONTAIN]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => '1name',
            'value'         => '',
        ], ['1name' => ['name' => [self::ERR_NAME_SHOULD_CONTAIN]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => 'a',
            'value'         => '',
        ], ['a' => ['name' => [self::ERR_NAME_SHOULD_CONTAIN]]]];

        $longName = 'abcdefghikl1abcdefghik21abcdefghik31abcdefghik41abcdefghik51abcdefghik61abcdefghik71abcdefghik81abcdefghik91abcdefghik1abcdefghia';
        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $longName,
            'value'         => '',
        ], [$longName => ['name' => [self::ERR_NAME_SHOULD_CONTAIN]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'category'      => 'category.name'
        ], [$this->getVarTestName('name') => ['category' => [self::ERR_CATEGORY_SHOULD_CONTAIN]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'category'      => 'scalr_ui_defaults'
        ], [$this->getVarTestName('name') => ['category' => [self::ERR_PREFIX_SCALR_IS_RESERVED]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'category'      => 'scalr_1'
        ], [$this->getVarTestName('name') => ['category' => [self::ERR_PREFIX_SCALR_IS_RESERVED]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => 'scalr_test',
            'value'         => '',
        ], ['scalr_test' => ['name' => [self::ERR_PREFIX_SCALR_IS_RESERVED]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'flagFinal'     => '1',
            'flagRequired'  => 'environment'
        ], [$this->getVarTestName('name') => ['flagFinal' => [self::ERR_YOU_CANT_SET_FINAL_AND_REQUIRED_FLAG_BOTH], 'flagRequired' => [self::ERR_YOU_CANT_SET_FINAL_AND_REQUIRED_FLAG_BOTH]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "/test null" . chr(0) . "byte/"
        ], [$this->getVarTestName('name') => ['validator' => ["Validation pattern is not valid (NULL byte)"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "^[0-9]$"
        ], [$this->getVarTestName('name') => ['validator' => [self::ERR_PATTERN_IS_NOT_VALID_INVALID_STRUCTURE]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "/^[0-9]$/es"
        ], [$this->getVarTestName('name') => ['validator' => [self::ERR_PATTERN_IS_NOT_VALID_INVALID_STRUCTURE]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '',
            'validator'     => "/^//test$/i"
        ], [$this->getVarTestName('name') => ['validator' => ["Validation pattern is not valid: Unknown modifier '/'"]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '1',
            'validator'     => "/^[23]$/is"
        ], [$this->getVarTestName('name') => ['value' => [self::ERR_VALUE_ISNT_VALID_1]]]];

        $cases[] = [ScopeInterface::SCOPE_SCALR, 'set', [
            'name'          => $this->getVarTestName('name'),
            'value'         => '0',
            'validator'     => "/^[23]$/is"
        ], [$this->getVarTestName('name') => ['value' => [self::ERR_VALUE_ISNT_VALID_1]]]];

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

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{"name": "user", "age": 25}',
            'format'         => self::FORMAT_JSON
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{name: "user", age: 25}',
            'format'         => self::FORMAT_JSON
        ], [$this->getVarTestName('testJsonVariable') => ['value' => [self::ERR_VALUE_ISNT_VALID_JSON]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{\'name\': "user", \'age\': 25}',
            'format'         => self::FORMAT_JSON
        ], [$this->getVarTestName('testJsonVariable') => ['value' => [self::ERR_VALUE_ISNT_VALID_JSON]]]];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{"name": user, "age": 25}',
            'format'         => self::FORMAT_JSON
        ], [$this->getVarTestName('testJsonVariable') => ['value' => [self::ERR_VALUE_ISNT_VALID_JSON]]]];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{"name": "user"}'
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ACCOUNT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{"name": "user", "age": 25}',
            'format'         => self::FORMAT_JSON
        ], true];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'          => $this->getVarTestName('testJsonVariable'),
            'value'         => '{name: "user"}'
        ], [$this->getVarTestName('testJsonVariable') => ['value' => [self::ERR_VALUE_ISNT_VALID_JSON]]]];

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
        ], [$this->getVarTestName('final_var') => ['value' => [self::ERR_YOU_CANT_CHANGE_FINAL_VARIABLE]]]];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'      => $this->getVarTestName('final_var'),
            'value'     => '1'
        ], [$this->getVarTestName('final_var') => ['value' => [self::ERR_YOU_CANT_CHANGE_FINAL_VARIABLE]]]];

        $cases[] = [ScopeInterface::SCOPE_FARM, 'set', [
            'name'      => $this->getVarTestName('final_var'),
            'value'     => '2'
        ], [$this->getVarTestName('final_var') => ['value' => [self::ERR_YOU_CANT_CHANGE_FINAL_VARIABLE]]]];

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
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['name' => [self::ERR_PREFIX_SCALR_IS_RESERVED]]]];

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
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['value' => [self::ERR_VALUE_ISNT_VALID_1]]]];

        $cases[] = [ScopeInterface::SCOPE_ENVIRONMENT, 'set', [
            'name'    => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'   => 'a'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['value' => [self::ERR_VALUE_ISNT_VALID_1]]]];

        $cases[] = [ScopeInterface::SCOPE_FARM, 'set', [
            'name'     => 'SCALR_UI_DEFAULT_STORAGE_RE_USE',
            'value'    => '1'
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['name' => [self::ERR_PREFIX_SCALR_IS_RESERVED]]]];

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
        ], ['SCALR_UI_DEFAULT_STORAGE_RE_USE' => ['value' => [self::ERR_YOU_CANT_CHANGE_FINAL_VARIABLE_ACC_LEVEL]]]];

        return $cases;
    }

    /**
     * @test
     * @dataProvider providerLoad
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