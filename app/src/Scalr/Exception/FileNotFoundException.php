<?php

namespace Scalr\Exception;

/**
 * FileNotFound exception
 *
 * @author N.V.
 */
class FileNotFoundException extends ScalrException
{

    protected $path;

    public function __construct($path = null, $message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }
}
