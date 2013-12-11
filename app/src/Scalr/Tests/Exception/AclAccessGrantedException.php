<?php
namespace Scalr\Tests\Exception;

use Scalr\Exception\ScalrException;

/**
 * AclAccessGrantedException
 *
 * This exception is used in phpunit to recognize
 * if access is granted.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     16.08.2013
 */
class AclAccessGrantedException extends ScalrException
{
    //The message is used in the tests.
    const MESSAGE = 'Access granted.';

	/**
     * {@inheritdoc}
     * @see Exception::__construct()
     */
    public function __construct($message = null, $code = null, $previous = null)
    {
        parent::__construct(self::MESSAGE . ($message ? ' ' . $message : ''));
    }
}