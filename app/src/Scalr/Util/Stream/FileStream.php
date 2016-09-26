<?php

namespace Scalr\Util\Stream;

use SplFileObject;

/**
 * Class FileStream
 * @package Scalr\Util\Stream
 */
class FileStream extends SplFileObject
{

    const WRAPPERS_PACKAGE = '\Scalr\Util\Stream\Wrappers';
    const WRAPPER_SUFFIX = 'StreamWrapper';

    /**
     * Construct a new file object.
     * Automatically plugin missing wrappers.
     *
     * @see fopen() for a list of allowed modes.
     *
     * @param string $file_name        The file to read.
     * @param string $open_mode        The mode in which to open the file.
     * @param bool   $use_include_path Whether to search in the include_path for filename.
     * @param null   $context          A valid context resource created with stream_context_create().
     */
    public function __construct($file_name, $open_mode = 'r', $use_include_path = false, $context = null)
    {
        $scheme = parse_url($file_name, PHP_URL_SCHEME);
        if ($scheme && !in_array($scheme, stream_get_wrappers())) {
            /* @var $wrapper StreamAdapter */
            $wrapper = static::WRAPPERS_PACKAGE . '\\' . ucfirst($scheme) . static::WRAPPER_SUFFIX;
            $wrapper::registerStreamWrapper();
        }

        if($context === null) {
            parent::__construct($file_name, $open_mode, $use_include_path);
        } else {
            parent::__construct($file_name, $open_mode, $use_include_path, $context);
        }

    }
}
