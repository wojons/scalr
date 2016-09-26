<?php

namespace Scalr\DataType;

/**
 * AccessPermissionsInterface
 *
 * @author  Vitaliy Demidov
 * @since   5.4.0   (18.03.2015)
 */
interface AccessPermissionsInterface
{
    /**
     * Check whether the user has access permissions to the specified object.
     *
     * It should check only Entity level access permissions, NOT ACL
     *
     * @param     \Scalr\Model\Entity\Account\User $user
     *            The User Entity
     *
     * @param     \Scalr\Model\Entity\Account\Environment $environment optional
     *            The Environment Entity if request is from Environment scope
     *
     * @param     bool $modify optional
     *            Whether it should check MODIFY permission. By default it checks READ permission.
     *
     * @return    bool Returns TRUE if the user has access to the specified object
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null);
}