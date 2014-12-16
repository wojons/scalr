<?php

namespace Scalr\Util\Stream\Wrappers;

use ADODB_Exception;
use ADODB_mysqli;
use Scalr\Exception\FileNotFoundException;
use Scalr\Util\Stream\StreamAdapter;

/**
 * Class DdlStreamWrapper
 *
 * @package Scalr\Util\Stream
 */
class DdlStreamWrapper extends StreamAdapter
{

    /**
     * StreamWrapper scheme
     */
    const SCHEME = 'ddl';

    /**
     * DB connection
     *
     * @var ADODB_mysqli
     */
    protected $resource;

    /**
     * Entries list
     *
     * @var array
     */
    private $entries;

    /**
     * @var string
     */
    private $lastStatement;

    /**
     * @var int
     */
    private $lastPosition;

    /**
     * @var string
     */
    private $pathComponents;

    /**
     * Gets the type of the path
     *
     * @param   string  $path
     * @return  string|number
     */
    protected static function getType($path)
    {
        switch (count(explode('/', ltrim(parse_url($path, PHP_URL_PATH), '/')))) {
            case 1:
            case 2:
                //return static::S_IFDIR;
            case 3:
                return static::S_IFREG;
            default:
                return 0;
        }
    }

    /**
     * Load data of SQL object
     *
     * @param   string $path
     * @throws  FileNotFoundException
     */
    private function openPath($path)
    {
        $this->path = $path;
        $this->pathComponents = explode('/', ltrim(parse_url($path, PHP_URL_PATH), '/'));

        $this->resource = \Scalr::getDb();

        switch (count($this->pathComponents)) {
            case 1:
                try {
                    $this->resource->Execute("USE {$this->pathComponents[0]};");
                    $this->entries = $this->resource->Execute("SHOW TABLES;");
                } catch (ADODB_Exception $e) {
                    throw new FileNotFoundException($path, $e->getCode(), $e);
                }
                break;
            case 2:
                try {
                    $this->resource->Execute("USE {$this->pathComponents[0]};");
                    $tables = $this->resource->Execute("SHOW TABLES;");

                    $exists = false;
                    $needle = $this->pathComponents[1];
                    foreach ($tables as $table) {
                        $table = array_shift($table);
                        if ($table == $needle) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        throw new FileNotFoundException($path);
                    }

                    $this->entries = $this->resource->Execute(
                        "SHOW COLUMNS `{$this->pathComponents[0]}`.`{$this->pathComponents[1]}`;"
                    );
                } catch (ADODB_Exception $e) {
                    throw new FileNotFoundException($path, $e->getCode(), $e);
                }
                break;
            case 3:
                break;
            default:
                break;
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamAdapter::stream_open()
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->openPath($path);
        $opened_path = $path;

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamAdapter::stream_read()
     */
    public function stream_read($count)
    {
        $ret = strlen($this->lastStatement) - $this->lastPosition;

        if ($ret == 0 && ($value = $this->entries->FetchRow())) {
            $value = array_shift($value);
            $this->resource->Execute("USE {$this->pathComponents[0]};");
            $this->lastStatement = $this->resource->Execute("SHOW CREATE TABLE `{$value}`;")->FetchRow()['Create Table'] . ';';
            $this->lastPosition = 0;
            $ret = strlen($this->lastStatement);
        }

        $out = substr($this->lastStatement, $this->lastPosition, $count);

        if ($ret >= $count) {
            $this->lastPosition += $count;
        } else if (!$this->entries->EOF) {
            $this->lastPosition += $ret;
            $out .= "\r\n" . $this->stream_read($count - $ret - 2);
        } else {
            $this->lastStatement = '';
        }

        return $out;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamAdapter::stream_eof()
     */
    public function stream_eof()
    {
        return ($this->lastPosition + 1) >= strlen($this->lastStatement) && $this->entries->EOF;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamAdapter::stream_close()
     */
    public function stream_close()
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamAdapter::stream_flush()
     */
    public function stream_flush()
    {
    }
}
