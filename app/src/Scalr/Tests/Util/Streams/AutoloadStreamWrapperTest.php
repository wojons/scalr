<?php

namespace Scalr\Tests\Util\Streams;

use Scalr\Tests\TestCase;
use Scalr\Util\Stream\FileStream;

/**
 * Class AutoloadStreamWrapperTest
 * @package Scalr\Tests\Util\Stream
 */
class AutoloadStreamWrapperTest extends TestCase
{

    const WRAPPER_EXTENSION = '.php';

    public function providerSchemas()
    {
        $wrappersPath = SRCPATH . str_replace('\\', '/', FileStream::WRAPPERS_PACKAGE);
        $wrappersDir = opendir($wrappersPath);

        $schemas = [];
        while(($file = readdir($wrappersDir)) !== false) {
            if(substr($file, -strlen(FileStream::WRAPPER_SUFFIX . static::WRAPPER_EXTENSION)) == FileStream::WRAPPER_SUFFIX . static::WRAPPER_EXTENSION) {
                if(!($schemas[] = array(strtolower(substr($file, 0, -strlen(FileStream::WRAPPER_SUFFIX . static::WRAPPER_EXTENSION)))))) {
                    array_pop($schemas);
                }
            }
        }

        return $schemas;
    }

    /**
     * @test
     * @dataProvider providerSchemas
     * @expectedException \Scalr\Exception\FileNotFoundException
     */
    public function testAutoload($scheme)
    {
        new FileStream("{$scheme}://localhost/foo");
    }

}