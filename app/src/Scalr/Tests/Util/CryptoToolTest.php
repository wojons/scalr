<?php

namespace Scalr\Tests\Util;

use Scalr\Tests\TestCase;

class CryptoToolTest extends TestCase
{
    protected static $cryptoDes;
    protected static $cryptoAes;
    protected static $cryptoSzr;

    protected static $cryptoKey;
    protected static $testSzr = false;
    // require python with M2Crypto installed

    public static function initCrypto()
    {
        if (!self::$cryptoAes) {
            self::$cryptoAes = new \Scalr_Util_CryptoTool(
                MCRYPT_RIJNDAEL_256,
                MCRYPT_MODE_CFB,
                @mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CFB),
                @mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CFB)
            );
        }

        if (!self::$cryptoDes) {
            self::$cryptoDes = new \Scalr_Util_CryptoTool(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, 24, 8);
        }

        if (!self::$cryptoSzr) {
            self::$cryptoSzr = \Scalr_Messaging_CryptoTool::getInstance();
        }

        if (!self::$cryptoKey) {
            self::$cryptoKey = file_get_contents(APPPATH . "/etc/.cryptokey");
        }
    }

    /**
     * Data provider for testCrypto()
     */
    public function providerTestCrypto()
    {
        self::initCrypto();

        $cases = array();

        // this cases doesn't work similar in python decrypt as php
        /* TODO: fix
        $cases[] = array("\n");
        $cases[] = array("abracadabra\n");
        $cases[] = array(" ");
        $cases[] = array(" \n");
        $cases[] = array("abracadabra \n");
        $cases[] = array(" abracadabra \n");
        $cases[] = array("\t\n");
        $cases[] = array(" foo ");
        */

        // depends on block size (extended test = 256, short = 32)
        for ($i = 1; $i <= 32; $i++)
            $cases[] = array(\Scalr::GenerateRandomKey($i));

        return $cases;
    }

    /**
     * @test
     * @dataProvider providerTestCrypto
     */
    public function testCryptoAes($string)
    {
        $this->assertEquals($string, self::$cryptoAes->decrypt(
            self::$cryptoAes->encrypt($string, self::$cryptoKey),
            self::$cryptoKey
        ));
    }

    /**
     * @test
     * @dataProvider providerTestCrypto
     */
    public function testCryptoDes($string)
    {
        $this->assertEquals($string, self::$cryptoDes->decrypt(
            self::$cryptoDes->encrypt($string, self::$cryptoKey),
            self::$cryptoKey
        ));
    }

    /**
     * @test
     * @dataProvider providerTestCrypto
     */
    public function testCryptoSzr($string)
    {
        if (self::$testSzr) {
            $key = self::$cryptoKey;
            $str = escapeshellarg($string);
            $cmd = 'python ' . __DIR__ . "/CryptoToolSzr.py encrypt {$str} {$key}";
            exec($cmd, $result);
            $this->assertEquals(self::$cryptoSzr->encrypt($string, base64_decode(self::$cryptoKey)), $result[0]);
        } else {
            $this->markTestSkipped();
        }
    }

    /**
     * @test
     * @dataProvider providerTestCrypto
     */
    public function testDecryptoSzr($string)
    {
        if (self::$testSzr) {
            $key = self::$cryptoKey;
            $str = escapeshellarg(self::$cryptoSzr->encrypt($string, base64_decode(self::$cryptoKey)));
            exec('python ' . __DIR__ . "/CryptoToolSzr.py decrypt {$str} {$key}", $result);
            $this->assertEquals($string, $result[0]);
        } else {
            $this->markTestSkipped();
        }
    }
}
