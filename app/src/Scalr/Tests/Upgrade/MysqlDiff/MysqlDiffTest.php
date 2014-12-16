<?php

namespace Scalr\Tests\Upgrade\MysqlDiff;

use ADODB_Exception;
use Exception;
use Scalr\Tests\WebTestCase;
use Scalr\Upgrade\MysqlDiff\DdlParser;
use Scalr\Upgrade\MysqlDiff\Diff;
use Scalr\Util\Stream\FileStream;
use SplFileObject;

/**
 * Class MysqlDiffTest
 * @package Scalr\Tests\Upgrade\MysqlDiff
 */
class MysqlDiffTest extends WebTestCase
{

    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Upgrade/MysqlDiff';
    }

    public function providerDiffFiles()
    {
        $dir = $this->getFixturesDirectory();

        return [
            [
                new SplFileObject($dir . '/diff_source.sql'), new SplFileObject($dir . '/diff_target.sql'), 'foobar',
                'barfoo'
            ]
        ];
    }

    public function createTestSchema(SplFileObject $input, $connection, $schema)
    {
        $connection->Execute("CREATE DATABASE IF NOT EXISTS {$schema};");
        $connection->Execute("USE {$schema};");

        $lines = [ ];
        while (!$input->eof()) {
            $line = trim($input->fgets());

            if (preg_match(DdlParser::DDL_COMMENT_REGEX, $line)) {
                continue;
            }

            if (substr($line, -1) != ';') {
                $lines[] = $line;
                continue;
            }
            $lines[] = $line;

            $statement = implode(' ', $lines);

            $connection->Execute($statement);
            $lines = [ ];
        }
    }

    /**
     * @test
     * @dataProvider providerDiffFiles
     */
    public function testDiff(SplFileObject $source, SplFileObject $target, $testSourceSchema, $testTargetSchema)
    {
        $connection = \Scalr::getDb();

        try {
            if (!@$connection->Execute("SELECT 1;")) {
                $this->markTestSkipped("No DB connection!");
            }
        } catch (Exception $e) {
            $this->markTestSkipped("No DB connection!");
        }

        try {
            $connection->Execute("SET FOREIGN_KEY_CHECKS=0;");
            $this->createTestSchema($source, $connection, $testSourceSchema);
            $this->createTestSchema($target, $connection, $testTargetSchema);

            $diff = new Diff(
                new FileStream("ddl://localhost/{$testSourceSchema}"),
                new FileStream("ddl://localhost/{$testTargetSchema}")
            );
            $statements = $diff->diff();

            $connection->Execute("USE {$testTargetSchema};");

            foreach ($statements as $statement) {
                $connection->Execute($statement);
            }

            $diff = new Diff(
                new FileStream("ddl://localhost/{$testSourceSchema}"),
                new FileStream("ddl://localhost/{$testTargetSchema}")
            );
            $statements = $diff->diff();

            $this->assertEquals("", implode("\n", $statements));


        } catch (ADODB_Exception $adoe) {
            $this->markTestSkipped($adoe->getMessage());
        } catch (Exception $e) {
            $this->fail($e->getMessage($e->getMessage()));
        }

        $connection->Execute("DROP DATABASE IF EXISTS `{$testSourceSchema}`");
        $connection->Execute("DROP DATABASE IF EXISTS `{$testTargetSchema}`");
    }

}
