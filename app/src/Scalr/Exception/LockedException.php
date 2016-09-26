<?php

namespace Scalr\Exception;

use Exception;
use InvalidArgumentException;
use Scalr\Model\Entity\Account\User;

/**
 * Locked Exception
 *
 * @author N.V.
 */
class LockedException extends ScalrException
{

    /**
     * Id of the User that add a lock
     *
     * @var int
     */
    private $lockedBy;

    /**
     * Locked object
     *
     * @var mixed
     */
    private $object;

    /**
     * LockedException
     *
     * @param   int       $lockedBy          Id of User that add a lock to the object
     * @param   mixed     $object   optional Locked object
     * @param   string    $comment  optional Lock comment
     * @param   int       $code     optional The Exception code
     * @param   Exception $previous optional The previous exception used for the exception chaining
     */
    public function __construct($lockedBy, $object = null, $comment = '', $code = 0, Exception $previous = null)
    {
        if (is_array($object)) {
            throw new InvalidArgumentException("Second argument can not be an array");
        }

        $this->lockedBy = $lockedBy;
        $this->object = $object;

        /* @var  $user User */
        $user = User::findPk($lockedBy);

        $userName = empty($user) ? $lockedBy : $user->email;

        if (!empty($comment)) {
            $comment = " with comment: '{$comment}'";
        }

        if (is_object($object)) {
            $nameParts = explode('\\', get_class($object));
            $object = array_pop($nameParts);
        }

        parent::__construct((empty($object) ? "Locked" : "{$object} locked" ) . " by {$userName}{$comment}", $code, $previous);
    }

    /**
     * Gets the identifier of the User who locked the object
     *
     * @return  int Returns the identifier of the User who locked the object
     */
    public function getLockedBy()
    {
        return $this->lockedBy;
    }

    /**
     * Gets the object that is locked by the User
     *
     * @return  mixed   Returns the object that is locked
     */
    public function getLockedObject()
    {
        return $this->object;
    }
}