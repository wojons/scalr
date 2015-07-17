<?php

namespace Scalr\Tests\Scripting;

use Scalr\Tests\TestCase;
use Scalr_Scripting_GlobalVariables;

/**
 * Global variables tests
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.0
 */
class GlobalVariablesTest extends TestCase
{
    /**
     * Data provider for the testGlobalVariablesFunctional
     *
     * @return array
     */
    public function providerLoad()
    {
        $variables = [];

        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR] = [
            'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
            'default'   => '',
            'locked'    => '',
            'current'   => [
                'name'          => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
                'value'         => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
                'flagFinal'     => 0,
                'flagRequired'  => Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT,
                'flagHidden'    => 1,
                'format'        => '',
                'validator'     => '',
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_SCALR,
            ],
            'flagDelete' => '',
            'scopes'     => [Scalr_Scripting_GlobalVariables::SCOPE_SCALR]
        ];

        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT] = [
            'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
            'default'   => [
                'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
                'value'     => '******',
                'scope'     => Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT
            ],
            'locked'    => [
                'flagFinal'     => 0,
                'flagRequired'  => Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT,
                'flagHidden'    => 1,
                'value'         => '******',
                'format'        => '',
                'validator'     => '',
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_SCALR,
            ],
            'current'   => [
                'name'          => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
                'value'         => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT),
                'flagFinal'     => 0,
                'flagRequired'  => '',
                'flagHidden'    => 0,
                'format'        => '',
                'validator'     => '',
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT,
            ],
            'flagDelete' => '',
            'scopes'     => [Scalr_Scripting_GlobalVariables::SCOPE_SCALR, Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT]
        ];

        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT] = [
            'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
            'default'   => [
                'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
                'value'     => '******',
                'scope'     => Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT
            ],
            'locked'    => [
                'flagFinal'     => 0,
                'flagRequired'  => Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT,
                'flagHidden'    => 1,
                'format'        => '',
                'validator'     => '',
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_SCALR,
                'value'         => '******'
            ],
            'current'   => [
                'name'          => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR),
                'value'         => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT),
                'flagFinal'     => 0,
                'flagRequired'  => '',
                'flagHidden'    => 0,
                'format'        => '',
                'validator'     => '',
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT,
            ],
            'flagDelete' => '',
            'scopes'     => [
                Scalr_Scripting_GlobalVariables::SCOPE_SCALR,
                Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT,
                Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT
            ]
        ];

        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_FARM] = [
            'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM),
            'default'   => '',
            'locked'    => '',
            'current'   => [
                'name'          => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM),
                'value'         => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM),
                'flagFinal'     => 1,
                'flagRequired'  => '',
                'flagHidden'    => 0,
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_FARM,
                'format'        => '',
                'validator'     => '',
            ],
            'flagDelete' => '',
            'scopes'     => [Scalr_Scripting_GlobalVariables::SCOPE_FARM]
        ];

        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ROLE] = [
            'name'      => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE),
            'default'   => '',
            'locked'    => '',
            'current'   => [
                'name'          => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE),
                'value'         => $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE),
                'flagFinal'     => 0,
                'flagRequired'  => Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE,
                'flagHidden'    => 0,
                'format'        => '',
                'validator'     => '',
                'scope'         => Scalr_Scripting_GlobalVariables::SCOPE_ROLE,
            ],
            'flagDelete' => '',
            'scopes'     => [Scalr_Scripting_GlobalVariables::SCOPE_ROLE]
        ];

        $variables[] = [$setVars];

        return $variables;
    }

    /**
     * @test
     * @dataProvider providerLoad
     * @functional
     */
    public function testGlobalVariablesFunctional($setVars)
    {
        $db = \Scalr::getDb();
        $this->deleteTestVariables();

        $envId = \Scalr::config('scalr.phpunit.envid');
        $this->assertNotEmpty($envId);

        $env = \Scalr_Environment::init()->loadById($envId);
        $this->assertInstanceOf('\\Scalr_Environment', $env);

        $accountId = $env->clientId;

        // create admin variable
        $adminVars = new Scalr_Scripting_GlobalVariables();

        $this->assertTrue($adminVars->setValues(
            [$setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR]], 0, 0, 0, '', true, true)
        );

        // check created admin var
        $values = $adminVars->getValues();
        $this->assertNotEmpty($values);

        foreach ($values as $value) {
            if (strpos($value['name'], self::getInstallationId()) !== false) {
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertNotEmpty($value['current']);
                $this->assertEquals($value['current']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertEquals($value['current']['flagRequired'], Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
                $this->assertEquals($value['current']['flagHidden'], 1);
                $this->assertEquals($value['current']['flagFinal'], 0);
                $this->assertEquals($value['current']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_SCALR);
                break;
            }
        }

        unset($values, $value);

        // check another list function
        $listValues = $adminVars->listVariables();

        foreach ($listValues as $value) {
            if (strpos($value['name'], self::getInstallationId()) !== false) {
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertEquals($value['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertEquals($value['private'], 1);
                break;
            }
        }

        unset($listValues, $value);

        // get account variable that has been inherited from admin
        $accountVar = new Scalr_Scripting_GlobalVariables(
            $accountId,
            0,
            Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT
        );

        $values = $accountVar->getValues();

        foreach ($values as $value) {
            if (strpos($value['name'], self::getInstallationId()) !== false) {
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertArrayNotHasKey('current', $value);
                $this->assertNotEmpty($value['locked']);
                $this->assertNotEmpty($value['default']);
                $this->assertEquals($value['locked']['flagRequired'], Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
                $this->assertEquals($value['locked']['flagHidden'], 1);
                $this->assertEquals($value['locked']['flagFinal'], 0);
                $this->assertEquals($value['default']['value'], "******");
                $this->assertEquals($value['locked']['value'], "******");
                $this->assertEquals($value['default']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_SCALR);

                break;
            }
        }

        unset($values, $value);

        // override admin var on account lvl
        $this->assertTrue($accountVar->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT]], 0, 0, 0, '', true, true));

        $values = $accountVar->getValues();
        $this->assertNotEmpty($values);

        foreach ($values as $value) {
            if (strpos($value['name'], self::getInstallationId()) !== false) {
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertNotEmpty($value['current']);
                $this->assertEquals($value['current']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT));
                $this->assertEmpty($value['current']['flagRequired']);
                $this->assertEquals($value['current']['flagHidden'], 0);
                $this->assertEquals($value['current']['flagFinal'], 0);
                $this->assertEquals($value['current']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
                $this->assertNotEmpty($value['locked']);
                $this->assertNotEmpty($value['default']);
                $this->assertEquals($value['locked']['flagRequired'], Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
                $this->assertEquals($value['locked']['flagHidden'], 1);
                $this->assertEquals($value['locked']['flagFinal'], 0);
                $this->assertEquals($value['default']['value'], "******");
                $this->assertEquals($value['locked']['value'], "******");
                $this->assertEquals($value['default']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_SCALR);
                $this->assertEquals(count($value['scopes']), 2);
                break;
            }
        }

        unset($values, $value);

        // override account var on environment level
        $envVar = new Scalr_Scripting_GlobalVariables(
            $accountId,
            $envId,
            Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT
        );

        $this->assertTrue($envVar->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT]], 0, 0, 0, '', true, true));

        $values = $envVar->getValues();
        $this->assertNotEmpty($values);

        foreach ($values as $value) {
            if (strpos($value['name'], self::getInstallationId()) !== false) {
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertNotEmpty($value['current']);
                $this->assertEquals($value['current']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT));
                $this->assertEmpty($value['current']['flagRequired']);
                $this->assertEquals($value['current']['flagHidden'], 0);
                $this->assertEquals($value['current']['flagFinal'], 0);
                $this->assertEquals($value['current']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT);
                $this->assertNotEmpty($value['locked']);
                $this->assertEquals($value['locked']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_SCALR);
                $this->assertNotEmpty($value['default']);
                $this->assertEquals($value['locked']['flagRequired'], Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
                $this->assertEquals($value['locked']['flagHidden'], 1);
                $this->assertEquals($value['locked']['flagFinal'], 0);
                $this->assertEquals($value['default']['value'], "******");
                $this->assertEquals($value['locked']['value'], "******");
                $this->assertEquals($value['default']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT);
                $this->assertEquals(count($value['scopes']), 3);
                break;
            }
        }

        unset($values, $value);

        // test validation errors

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR];
        $errorMock['name'] = 'Invalid-name';

        $errors = $adminVars->validateValues([$errorMock]);
        $this->assertNotEmpty($errors);
        $this->assertEquals("Invalid name", $adminVars->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT];
        $errorMock['current']['flagFinal'] = 1;

        $errors = $envVar->validateValues([$errorMock]);
        $this->assertEquals("You can't redefine advanced settings (flags, format, validator)", $envVar->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR];
        $errorMock['current']['flagRequired'] = 'env';
        $errorMock['current']['flagFinal'] = 1;

        $errors = $adminVars->validateValues([$errorMock]);
        $this->assertEquals("You can't set final and required flags both", $adminVars->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT];
        $errorMock['current']['flagHidden'] = 1;

        $errors = $envVar->validateValues([$errorMock]);
        $this->assertEquals("You can't redefine advanced settings (flags, format, validator)", $envVar->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT];
        $errorMock['current']['format'] = 1;

        $errors = $envVar->validateValues([$errorMock]);
        $this->assertEquals("You can't redefine advanced settings (flags, format, validator)", $envVar->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT];
        $errorMock['current']['validator'] = 1;

        $errors = $envVar->validateValues([$errorMock]);
        $this->assertEquals("You can't redefine advanced settings (flags, format, validator)", $envVar->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR];
        $errorMock['current']['validator'] = "test null" . chr(0) . "byte";

        $errors = $adminVars->validateValues([$errorMock]);
        $this->assertContains('Value isn\'t valid because of validation pattern', $envVar->getErrorMessage($errors));

        $errorMock = $setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR];
        $errorMock['current']['value'] = "test null" . chr(0) . "byte";

        $errors = $adminVars->validateValues([$errorMock]);
        $this->assertContains("contains invalid non-printable characters (e.g. NULL characters)", $envVar->getErrorMessage($errors));

        // delete admin variable
        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR]['flagDelete'] = 1;
        $this->assertTrue($adminVars->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_SCALR]], 0, 0, 0, '', true, true));

        $values = $adminVars->listVariables();

        foreach ($values as $value) {
            $this->assertNotEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
        }

        unset($values, $value);

        // check if account variable exists after deletion parent variable
        $values = $accountVar->getValues();
        $this->assertNotEmpty($values);

        foreach ($values as $value) {
            if (strpos($value['name'], self::getInstallationId()) !== false) {
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
                $this->assertEquals(count($value['scopes']), 1);
                $this->assertArrayNotHasKey('default', $value);
                $this->assertArrayNotHasKey('locked', $value);
                $this->assertNotEmpty($value['current']);
                $this->assertEmpty($value['current']['flagRequired']);
                $this->assertEquals($value['current']['flagHidden'], 0);
                $this->assertEquals($value['current']['flagFinal'], 0);
                $this->assertEquals($value['current']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT));
                break;
            }
        }

        unset($values, $value);

        // delete account variable
        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT]['flagDelete'] = 1;
        $this->assertTrue($accountVar->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_ACCOUNT]], 0, 0, 0, '', true, true));

        $values = $accountVar->listVariables();

        foreach ($values as $value) {
            $this->assertNotEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
        }

        unset($values, $value);

        // delete environment variable
        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT]['flagDelete'] = 1;
        $this->assertTrue($envVar->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT]], 0, 0, 0, '', true, true));

        $values = $envVar->listVariables();

        foreach ($values as $value) {
            $this->assertNotEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_SCALR));
        }

        unset($values, $value);

        // select random farm
        $farmId = $db->GetOne("SELECT f.id FROM farms f LIMIT 1");
        $this->assertNotEmpty($farmId);

        $farm = \DBFarm::LoadByID($farmId);
        $this->assertInstanceOf('\\DBFarm', $farm);

        // create farm variable
        $farmVar = new Scalr_Scripting_GlobalVariables(
            $farm->GetEnvironmentObject()->clientId,
            $farm->GetEnvironmentObject()->id,
            Scalr_Scripting_GlobalVariables::SCOPE_FARM
        );

        $this->assertTrue($farmVar->setValues(
            [$setVars[Scalr_Scripting_GlobalVariables::SCOPE_FARM]], 0, $farmId, 0, '', true, true)
        );

        $farmRoles = $farm->GetFarmRoles();
        $this->assertNotEmpty($farmRoles);

        $farmRole = reset($farmRoles);
        $this->assertInstanceOf('\\DBFarmRole', $farmRole);
        /* @var $farmRole \DBFarmRole */
        $role = $farmRole->GetRoleObject();
        $roleId = $role->id;

        // create role variable
        $roleVar = new Scalr_Scripting_GlobalVariables(
            0,
            0,
            Scalr_Scripting_GlobalVariables::SCOPE_ROLE
        );

        $this->assertTrue($roleVar->setValues(
            [$setVars[Scalr_Scripting_GlobalVariables::SCOPE_ROLE]], $roleId, 0, 0, '', true, true)
        );

        // check 2 created variables in farm role
        $farmRoleVar = new Scalr_Scripting_GlobalVariables(
            $farm->GetEnvironmentObject()->clientId,
            $farm->GetEnvironmentObject()->id,
            Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE
        );

        $values = $farmRoleVar->getValues($roleId, $farmId, $farmRole->ID);
        $countTestVars = 0;

        foreach ($values as $value) {
            if ($value['name'] == $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE)) {
                $countTestVars++;
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE));
                $this->assertArrayNotHasKey('current', $value);
                $this->assertNotEmpty($value['locked']);
                $this->assertNotEmpty($value['default']);
                $this->assertEquals($value['locked']['flagRequired'], Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);
                $this->assertEquals($value['locked']['flagHidden'], 0);
                $this->assertEquals($value['locked']['flagFinal'], 0);
                $this->assertEquals($value['default']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE));
                $this->assertEquals($value['locked']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE));
                $this->assertEquals($value['default']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            }

            if ($value['name'] == $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM)) {
                $countTestVars++;
                $this->assertEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM));
                $this->assertArrayNotHasKey('current', $value);
                $this->assertNotEmpty($value['locked']);
                $this->assertNotEmpty($value['default']);
                $this->assertEmpty($value['locked']['flagRequired']);
                $this->assertEquals($value['locked']['flagHidden'], 0);
                $this->assertEquals($value['locked']['flagFinal'], 1);
                $this->assertEquals($value['default']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM));
                $this->assertEquals($value['locked']['value'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM));
                $this->assertEquals($value['default']['scope'], Scalr_Scripting_GlobalVariables::SCOPE_FARM);
            }
        }

        $this->assertEquals($countTestVars, 2);

        unset($values, $value);

        // delete role variable
        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_ROLE]['flagDelete'] = 1;
        $this->assertTrue($roleVar->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_ROLE]], $roleId, 0, 0, '', true, true));

        $values = $roleVar->listVariables();

        foreach ($values as $value) {
            $this->assertNotEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_ROLE));
        }

        unset($values, $value);

        // delete farm variable
        $setVars[Scalr_Scripting_GlobalVariables::SCOPE_FARM]['flagDelete'] = 1;
        $this->assertTrue($farmVar->setValues([$setVars[Scalr_Scripting_GlobalVariables::SCOPE_FARM]], 0, $farmId, 0, '', true, true));

        $values = $farmVar->listVariables();

        foreach ($values as $value) {
            $this->assertNotEquals($value['name'], $this->getVarTestName(Scalr_Scripting_GlobalVariables::SCOPE_FARM));
        }

        unset($values, $value);
    }

    /**
     * Deletes test variables from all tables
     *
     * @throws \Exception
     */
    private function deleteTestVariables()
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
            $db->Execute("
                DELETE FROM $table WHERE name LIKE '%" . self::getInstallationId() . "'
            ");
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