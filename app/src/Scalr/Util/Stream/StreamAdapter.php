<?php

namespace Scalr\Util\Stream;

use Exception;
use Scalr\Exception\NotYetImplementedException;
use Scalr\Exception\NotSupportedException;

/**
 * Class StreamAdapter
 * @package Scalr\Util\Stream
 */
abstract class StreamAdapter implements StreamWrapper
{

    /**
     * stream context with any options
     * @var array
     */
    public $context = [ ];

    /**
     * stream
     * @var resource
     */
    protected $resource;

    /**
     * read/write position in stream
     *
     * Remember to update the read/write position of the stream (by the number of bytes that were successfully read/write).
     * The read/write position of the stream should be updated according to the offset and whence.
     *
     * @var int
     */
    protected $position = 0;

    /**
     * Current stream path
     *
     * @var string
     */
    protected $path;

    /**
     * Register a stream wrapper according to its scheme and class.
     * Must called prior the opening of first stream under this scheme
     */
    public static function registerStreamWrapper()
    {
        if (in_array(static::SCHEME, stream_get_wrappers())) {
            stream_wrapper_unregister(static::SCHEME);
        }

        stream_register_wrapper(static::SCHEME, get_called_class());
    }

    protected static function getType($path)
    {
        return 0;
    }

    protected static function getPrivileges($path)
    {
        return 0;
    }

    /**
     * Generate stats placeholders
     *
     * @param   string $path
     * @return  array
     */
    protected static function getStat($path)
    {
        $stats = [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => static::getType($path) | static::getPrivileges($path),
            'nlink'   => 0,
            'uid'     => function_exists('posix_getuid') ? posix_getuid() : 0,
            'gid'     => function_exists('posix_getgid') ? posix_getgid() : 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => -1,
            'blocks'  => -1
        ];

        return array_values($stats) + $stats;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::dir_closedir()
     */
    public function dir_closedir()
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::dir_opendir()
     */
    public function dir_opendir($path, $options)
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::dir_readdir()
     */
    public function dir_readdir()
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::dir_rewinddir()
     */
    public function dir_rewinddir()
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::mkdir()
     */
    public function mkdir($path, $mode, $options)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::rename()
     */
    public function rename($path_from, $path_to)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::rmdir()
     */
    public function rmdir($path, $options)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_cast()
     */
    public function stream_cast($cast_as)
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_close()
     */
    public function stream_close()
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_eof()
     */
    public function stream_eof()
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_flush()
     */
    public function stream_flush()
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_lock()
     */
    public function stream_lock($operation)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_metadata()
     */
    public function stream_metadata($path, $option, $value)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_open()
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_read()
     */
    public function stream_read($count)
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_seek()
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_set_option()
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_stat()
     */
    public function stream_stat()
    {
        return static::getStat($this->path);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_tell()
     */
    public function stream_tell()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_truncate()
     */
    public function stream_truncate($new_size)
    {
        throw new NotYetImplementedException();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::stream_write()
     */
    public function stream_write($data)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::unlink()
     */
    public function unlink($path)
    {
        throw new NotSupportedException("Not implemented yet!");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Util\Stream\StreamWrapper::url_stat()
     */
    public function url_stat($path, $flags)
    {
        if ($flags & STREAM_URL_STAT_QUIET) {
            try {
                return static::getStat($path);
            } catch (Exception $e) {
                return false;
            }
        }

        return static::getStat($path);
    }
}
