<?php

namespace Scalr\Tests\Util;

use Scalr\Tests\TestCase;
use Scalr\Util\CryptoTool;

class CryptoToolTest extends TestCase
{

    /**
     * @var CryptoTool
     */
    protected static $cryptoDes;

    /**
     * @var CryptoTool
     */
    protected static $cryptoAes;

    /**
     * @var CryptoTool
     */
    protected static $cryptoSzr;

    protected static $testSzr = false;
    // require python with M2Crypto installed

    public static function initCrypto()
    {
        if (!self::$cryptoAes) {
            self::$cryptoAes = \Scalr::getContainer()->crypto;
        }

        if (!self::$cryptoDes) {
            self::$cryptoDes = \Scalr::getContainer()->crypto(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, null, 24, 8);
        }

        if (!self::$cryptoSzr) {
            $key = file_get_contents(APPPATH . "/etc/.cryptokey");
            self::$cryptoSzr = \Scalr::getContainer()->srzcrypto($key);
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
        $this->assertEquals($string, self::$cryptoAes->decrypt(self::$cryptoAes->encrypt($string)));
    }

    /**
     * @test
     * @dataProvider providerTestCrypto
     */
    public function testCryptoDes($string)
    {
        $this->assertEquals($string, self::$cryptoDes->decrypt(self::$cryptoDes->encrypt($string)));
    }

    /**
     * @test
     * @dataProvider providerTestCrypto
     */
    public function testCryptoSzr($string)
    {
        if (self::$testSzr) {
            $key = base64_encode(self::$cryptoSzr->getCryptoKey());
            $str = escapeshellarg($string);
            $cmd = 'python ' . __DIR__ . "/CryptoToolSzr.py encrypt {$str} {$key}";
            exec($cmd, $result);
            $this->assertEquals(self::$cryptoSzr->encrypt($string), $result[0]);
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
            $key = base64_encode(self::$cryptoSzr->getCryptoKey());
            $str = escapeshellarg(self::$cryptoSzr->encrypt($string));
            exec('python ' . __DIR__ . "/CryptoToolSzr.py decrypt {$str} {$key}", $result);
            $this->assertEquals($string, $result[0]);
        } else {
            $this->markTestSkipped();
        }
    }
}
