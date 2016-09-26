<?php
namespace Scalr\System\Zmq\Cron;

/**
 * Error payload
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (12.09.2014)
 */
class ErrorPayload extends AbstractPayload
{
    public $message = '';

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractPayload::__sleep()
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), ['message']);
    }
}