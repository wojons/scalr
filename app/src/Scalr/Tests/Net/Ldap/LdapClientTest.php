<?php
namespace Scalr\Tests\Net\Ldap;

use Scalr\Net\Ldap\LdapClient;
use Scalr\Tests\TestCase;

/**
 * LdapClientTest test
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    10.06.2013
 */
class LdapClientTest extends TestCase
{

    public function providerRealEscape()
    {
        return array(
            array(' aria ', '\\ aria\\ '),
            array("woo\n\ndoo*", 'woodoo\*'),
            array('<>(),#+;"=', '\\<\\>\\(\\)\\,\\#\\+\\;\\"\\='),
        );
    }

    /**
     * @test
     * @dataProvider providerRealEscape
     * @param   string     $input    Input string
     * @param   string     $expected Expected string
     */
    public function testRealEscape($input, $expected)
    {
        $this->assertEquals($expected, LdapClient::realEscape($input));
    }

    /**
     * @test
     */
    public function testLdapFunctional()
    {
        if (!\Scalr::config('scalr.connections.ldap.user')) {
            $this->markTestSkipped('scalr.connections.ldap section is not defined in the config.');
        }

        $config = \Scalr::getContainer()->get('ldap.config');
        $ldap = \Scalr::getContainer()->ldap($config->user, $config->password);
        $valid = $ldap->isValidUser();
        $this->assertTrue($valid, 'It expects valid user\'s credentials. ' . $ldap->getLog());
        $ldap->unbind();

        $ldap = \Scalr::getContainer()->ldap($config->user, '');
        $valid = $ldap->isValidUser();
        $this->assertFalse($valid, 'User can be authenticated without password. ' . $ldap->getLog());
        $ldap->unbind();

        $ldap = \Scalr::getContainer()->ldap($config->user, 'invalidpassword');
        $valid = $ldap->isValidUser();
        $this->assertFalse($valid, 'User with invalid password should not be authenticated. ' . $ldap->getLog());
        $ldap->unbind();
    }
}