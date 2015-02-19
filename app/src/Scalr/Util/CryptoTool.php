<?php

namespace Scalr\Util;

use SplFileObject;

/**
 * CryptoTool
 *
 * @author  N.V.
 */
class CryptoTool
{
    const TENDER_IV = 'OpenSSL for Ruby';
    const HASH_ALGO = 'SHA1';

    /**
     * @var string
     */
    private $cryptoAlgo;

    /**
     * @var string
     */
    private $cipherMode;

    /**
     * Key size in bytes
     *
     * @var int
     */
    private $keySize;

    /**
     * Initialization Vector size in bytes
     *
     * @var int
     */
    private $ivSize;

    /**
     * Block size in bytes
     *
     * @var int
     */
    private $blockSize;

    /**
     * @var string
     */
    private $cryptoKey;

    /**
     * @var resource|null
     */
    private $cryptoKeyResource;

    /**
     * @var string
     */
    private $key;

    /**
     * Initialization Vector
     *
     * @var string
     */
    private $iv;

    /**
     * Calculates SHA256 hash
     *
     * @param string $input
     *
     * @return string Returns SHA256-hash
     */
    public static function hash($input)
    {
        return hash("sha256", $input);
    }

    /**
     * Generates random string with specified length
     *
     * @param int $length output length
     *
     * @return string Returns random string
     */
    public static function sault($length = 10)
    {
        return substr(md5(uniqid(rand(), true)), 0, $length);
    }

    /**
     * Generates tender token
     *
     * @param string $data
     *
     * @return string Returns token
     */
    public static function generateTenderMultipassToken($data)
    {
        $salted = \Scalr::config('scalr.ui.tender_api_key') . \Scalr::config('scalr.ui.tender_site_key');
        $hash = hash('sha1', $salted, true);
        $saltedHash = substr($hash, 0, 16);
        $iv = static::TENDER_IV;

        // double XOR first block
        for ($i = 0; $i < 16; $i++) {
            $data[$i] = $data[$i] ^ $iv[$i];
        }

        $crypto = \Scalr::getContainer()->crypto(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC, [$saltedHash, $iv], 16, 16);

        return urlencode($crypto->encrypt($data));
    }

    /**
     * Openssl snippet
     *
     * @param string      $data
     * @param string      $privateKey         private key
     * @param string|bool $privateKeyPassword optional password for private key
     *
     * @return string Returns decrypted data
     */
    public static function opensslDecrypt($data, $privateKey, $privateKeyPassword = false)
    {
        $key = @openssl_get_privatekey($privateKey, $privateKeyPassword);

        @openssl_private_decrypt($data, $result, $key);

        return $result;
    }

    /**
     * Generates signature string using current/given timestamp
     *
     * @param string $data      data to be signed
     * @param string $key       cryptographic key
     * @param int    $timestamp optional timestamp
     * @param string $algo      optional hash algorithm
     *
     * @return array Returns array containing hash and formatted time
     */
    public static function keySign($data, $key, $timestamp = null, $algo = 'SHA256')
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $canonical_string = $data . (is_numeric($timestamp) ? date("c", $timestamp) : $timestamp);
        $hash = base64_encode(hash_hmac($algo, $canonical_string, $key, 1));

        return $hash;
    }

    /**
     * Adds padding to end of text
     *
     * @param string $text      text to be encrypted
     * @param int    $blocksize cipher block size
     *
     * @return string
     */
    private static function pkcs5Padding($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);

        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * CryptoTool
     *
     * @param string                              $cryptoAlgo cryptographic algorithm
     * @param string                              $cipherMode block cipher mode
     * @param string|resource|SplFileObject|array $cryptoKey  cryptographic key or file or array containing key and IV
     * @param int                                 $keySize    key size in bytes
     * @param int                                 $blockSize     Initialization Vector size in bytes
     */
    public function __construct($cryptoAlgo = MCRYPT_RIJNDAEL_256, $cipherMode = MCRYPT_MODE_CFB,
                                $cryptoKey = null, $keySize = null, $blockSize = null)
    {
        $this->cryptoAlgo = $cryptoAlgo;
        $this->cipherMode = $cipherMode;

        $this->keySize = $keySize ?: mcrypt_get_key_size($cryptoAlgo, $cipherMode);
        $this->blockSize = $blockSize ?: mcrypt_get_block_size($cryptoAlgo, $cipherMode);

        $this->ivSize = $blockSize ?: mcrypt_get_iv_size($cryptoAlgo, $cipherMode);

        $this->setCryptoKey($cryptoKey);
    }

    public function __destruct()
    {
        if (is_resource($this->cryptoKeyResource)) {
            fclose($this->cryptoKeyResource);
        }
    }

    /**
     * Extracts key and initialization vector from cryptographic key
     *
     * @param string $cryptoKey optional cryptographic key
     *
     * @return array Returns array containing key and initialization vector
     */
    public function splitKeyIv($cryptoKey = null)
    {
        if ($cryptoKey === null) {
            $cryptoKey = $this->cryptoKey;
        }

        //Use first n bytes as key
        $key = substr($cryptoKey, 0, $this->keySize);

        //Use last m bytes as IV
        $iv = substr($cryptoKey, -$this->ivSize);

        return [$key, $iv];
    }

    /**
     * Encrypt input string
     *
     * @param string $string    data to be encrypted
     * @param string $cryptoKey optional cryptographic key
     *
     * @return string Returns encrypted string
     */
    public function encrypt($string, $cryptoKey = '')
    {
        $cryptoKey = $cryptoKey ? $cryptoKey : $this->cryptoKey;

        list ($key, $iv) = $this->splitKeyIv($cryptoKey);
        $string = static::pkcs5Padding($string, $this->blockSize);

        return base64_encode(mcrypt_encrypt($this->cryptoAlgo, $key, $string, $this->cipherMode, $iv));
    }

    /**
     * Decrypts string and remove padding
     *
     * @param string $string    encrypted string
     * @param string $cryptoKey optional cryptographic key used for encryption
     *
     * @return string Returns decrypted string without padding
     */
    public function decrypt($string, $cryptoKey = '')
    {
        $cryptoKey = $cryptoKey ? $cryptoKey : $this->cryptoKey;

        list ($key, $iv) = $this->splitKeyIv($cryptoKey);
        $ret = mcrypt_decrypt($this->cryptoAlgo, $key, base64_decode($string), $this->cipherMode, $iv);

        // Remove padding
        if ($length = strlen($ret)) {
            $paddingLen = ord($ret[strlen($ret) - 1]);
            $ret = substr($ret, 0, -$paddingLen);
        }

        return $ret;
    }

    /**
     * Old decrypt algo.
     *
     * Decrypts string and remove padding
     *
     * @deprecated
     * @param string $string    encrypted string
     * @param string $cryptoKey optional cryptographic key used for encryption
     *
     * @return string Returns decrypted string without padding
     */
    public function _decrypt($string, $cryptoKey = '')
    {
        $cryptoKey = $cryptoKey ? $cryptoKey : $this->cryptoKey;

        list ($key, $iv) = $this->splitKeyIv($cryptoKey);
        $ret = mcrypt_decrypt($this->cryptoAlgo, $key, base64_decode($string), $this->cipherMode, $iv);

        return trim($ret, "\x00..\x20");
    }

    /**
     * Generates signature string using current/given cryptographic key and timestamp
     *
     * @param string $data      data to be signed
     * @param string $key       optional cryptographic key
     * @param int    $timestamp optional timestamp
     * @param string $algo      optional hash algorithm
     *
     * @return array Returns array containing hash and formatted time
     */
    public function sign($data, $key = null, $timestamp = null, $algo = 'SHA256')
    {
        return static::keySign($data, $key ?: $this->cryptoKey, $timestamp, $algo);
    }

    /**
     * Setup default cryptographic key for encrypt/decrypt operations
     *
     * @param string|resource|SplFileObject $cryptoKey cryptographic key or file
     *
     * @return $this
     */
    public function setCryptoKey($cryptoKey)
    {
        if ($cryptoKey instanceof SplFileObject) {
            $key = [];

            while (!$cryptoKey->eof()) {
                $key[] = $cryptoKey->fgets();
            }

            $this->cryptoKey = implode('', $key);
        } else if (is_resource($cryptoKey) && get_resource_type($cryptoKey) == 'stream') {
            $this->cryptoKeyResource = $cryptoKey;

            $key = [];
            while (!feof($cryptoKey)) {
                $key[] = fgets($cryptoKey);
            }

            $this->cryptoKey = implode('', $key);
        } else if((is_string($cryptoKey) || is_numeric($cryptoKey)) && @file_exists($cryptoKey)) {
            $this->cryptoKey = file_get_contents($cryptoKey);
        } else {
            $this->cryptoKey = $cryptoKey;
        }

        if(is_array($cryptoKey)) {
            $this->cryptoKey = implode('', $cryptoKey);

            $this->key = isset($cryptoKey['key']) ? $cryptoKey['key'] : array_shift($cryptoKey);
            $this->iv = isset($cryptoKey['iv']) ? $cryptoKey['iv'] : array_shift($cryptoKey);
        } else {
            list($this->key, $this->iv) = $this->splitKeyIv();
        }

        return $this;
    }

    /**
     * Gets current cryptographic key
     *
     * @return string
     */
    public function getCryptoKey()
    {
        return $this->cryptoKey;
    }

    /**
     * Gets current cryptographic algorithm
     *
     * @return string
     */
    public function getCryptoAlgo()
    {
        return $this->cryptoAlgo;
    }

    /**
     * Gets current cipher mode
     *
     * @return string
     */
    public function getCipherMode()
    {
        return $this->cipherMode;
    }

    /**
     * Gets current cryptographic key size
     *
     * @return int Size in bytes
     */
    public function getKeySize()
    {
        return $this->keySize;
    }

    /**
     * Gets current block size
     *
     * @return int Block size in bytes
     */
    public function getBlockSize()
    {
        return $this->blockSize;
    }

    /**
     * Gets current Initialization Vector size
     *
     * @return int IV size in bytes
     */
    public function getIvSize()
    {
        return $this->ivSize;
    }

    /**
     * Gets current key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Gets current Initialization Vector
     *
     * @return string
     */
    public function getIv()
    {
        return $this->iv;
    }
}
