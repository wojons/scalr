<?php

namespace Scalr\Acl\Resource;

/**
 * Resource Mode Interface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.08.2015
 */
interface ModeInterface
{

    /**
     * Gets ACL Resource identifier the Mode corresponds to
     *
     * @return  int Returns ACL Resource identifier the Mode corresponds to
     */
    public function getResourceId();

    /**
     * Defines possible ID / name pairs for the mode
     *
     * The less mode identifier value than more priority this mode has against others.
     * If User is assigned to more than one Account Roles with different Resouce modes,
     * lesser mode identifier value will be applied.
     *
     * @return ModeDescritpion[]  Returns possible ID / name pairs for the mode
     */
    public function getMapping();

    /**
     * Gets default value for the Mode
     *
     * @return    int  Returns default for the mode
     */
    public function getDefault();
}