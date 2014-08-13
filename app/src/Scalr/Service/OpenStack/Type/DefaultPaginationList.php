<?php
namespace Scalr\Service\OpenStack\Type;

use Scalr\Service\OpenStack\Services\AbstractService;

/**
 * DefaultPaginationList
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (21.01.2014)
 */
class DefaultPaginationList extends AbstractPaginationList
{
    /**
     * Constructor
     *
     * @param   AbstractService  $service  The openstack service associated with the response
     * @param   string           $subject  The name of the property in the response to treat as the list of results
     * @param   array            $array    Array of the objects.
     * @param   array            $links    optional Array of the links to the pages
     */
    public function __construct(AbstractService $service, $subject, $array = array(), $links = null)
    {
        parent::__construct($service, $subject, $array, $links);
    }
}