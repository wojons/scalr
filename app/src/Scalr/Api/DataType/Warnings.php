<?php

namespace Scalr\Api\DataType;

/**
 * Warnings object
 *
 * @author  Andrii Penchuk <a.penchuk@scalr.com>
 * @since   5.6.14 (06.11.2015)
 */
class Warnings extends AbstractDataType implements \JsonSerializable
{
    /**
     * An array of objects of type ApiMessage.
     * @var ApiMessage[]
     */
    public $warnings = [];

    /**
     * Appends ApiMessage into warning array
     *
     * @param string|null  $code    optional Machine-readable representation of the message
     * @param string|null  $message optional Human-readable representation of the message
     */
    public function appendWarnings($code = null, $message = null)
    {
        $this->warnings[] = new ApiMessage($code, $message);
    }

    /**
     * {@inheritdoc}
     * @see JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return $this->warnings;
    }

}