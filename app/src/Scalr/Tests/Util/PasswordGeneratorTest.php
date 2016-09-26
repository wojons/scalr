<?php

namespace Scalr\Tests\Util;

use Scalr\Tests\TestCase;

/**
 * Password generator test
 *
 * @author N.V.
 */
class PasswordGeneratorTest extends TestCase
{

    const TEST_SETS_COUNT = 1000;
    const PASSWORD_LENGTH = 4;

    public function testMinimumComplexity()
    {
        $sets = [
            'l' => str_split('abcdefghjkmnpqrstuvwxyz'),
            'u' => str_split('ABCDEFGHJKMNPQRSTUVWXYZ'),
            'd' => str_split('1234567890'),
            's' => str_split('!@#$%&*?'),
        ];

        for ($i = 0; $i < static::TEST_SETS_COUNT; $i++) {
            $password = str_split(\Scalr::GenerateSecurePassword(static::PASSWORD_LENGTH));

            foreach ($sets as $setName => $set) {
                $this->assertNotEmpty(array_intersect($password, $set), "Password doesn't contain any characters from the group '{$setName}'");
            }
        }
    }
}