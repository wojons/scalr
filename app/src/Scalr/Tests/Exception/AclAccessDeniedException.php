<?php
namespace Scalr\Tests\Exception;

use Scalr\Exception\ScalrException;

/**
 * AclAccessDeniedException
 *
 * This exception is used in phpunit to recognize
 * if access is denied.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     19.08.2013
 */
class AclAccessDeniedException extends ScalrException
{
    //The message is used in the tests.
    const MESSAGE = 'Access denied.';

	/**
     * {@inheritdoc}
     * @see Exception::__construct()
     */
    public function __construct($message = null, $code = null, $previous = null)
    {
        parent::__construct(self::MESSAGE . ($message ? ' ' . $message : ''));
    }
}