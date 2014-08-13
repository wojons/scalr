<?php

namespace Scalr\Model\Objects;

/**
 * BinaryStream
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (12.03.2014)
 */
class BinaryStream
{
    /**
     * @var resource
     */
    public $stream;

    /**
     * Constructor
     * @param   binary   $data  Binary string
     */
    public function __construct($data)
    {
        if ($data !== null) {
            $this->stream = fopen('data://text/plain;base64,' . base64_encode($data), 'r');
        }
    }

    final private function __clone()
    {
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }
    }

    /**
     * Gets hexadecimal value
     *
     * @return  string
     */
    public function hex()
    {
        return is_resource($this->stream) ? bin2hex(stream_get_contents($this->stream, -1, 0)) : null;
    }

    public function __toString()
    {
        return is_resource($this->stream) ? stream_get_contents($this->stream, -1, 0) : '';
    }
}