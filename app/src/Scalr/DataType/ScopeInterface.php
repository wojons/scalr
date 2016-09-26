<?php

namespace Scalr\DataType;

/**
 * ScopeInterface
 *
 * @author  Igor Savchenko
 * @since   5.4.0   (07.03.2015)
 */
interface ScopeInterface
{
    const SCOPE_SCALR       = 'scalr';
    const SCOPE_ACCOUNT     = 'account';
    const SCOPE_ENVIRONMENT = 'environment';
    const SCOPE_FARM        = 'farm';
    const SCOPE_ROLE        = 'role';
    const SCOPE_FARMROLE    = 'farmrole';
    const SCOPE_SERVER      = 'server';

    /**
     * Gets scope
     *
     * @return   string  Returns scope
     */
    public function getScope();
}