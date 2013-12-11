<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\NetworkService;
use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * RoutersHandler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    26.08.2013
 *
 * @method  array|object list()
 *          list(string $routerId = null, \Scalr\Service\OpenStack\Services\Network\Type\ListRoutersFilter|array $filter = null, array $fields = null)
 *          Gets the routers list (GET /routers[/router-id])
 *          This operation returns a list of routers to which the tenant has access.
 *          Default policy settings return only those routers that are owned by the tenant who submits the request,
 *          unless the request is submitted by an user with administrative rights.
 *          Users can control which attributes should be returned by using the fields query parameter.
 *          Additionally, results can be filtered by using query string parameters.
 *
 * @method  object create()
 *          create(\Scalr\Service\OpenStack\Services\Network\Type\CreateRouter|array $request)
 *          Create Router action (POST /routers)
 *          This operation creates a new logical router.
 *          When it is created, a logical router does not have any internal interface.
 *          In other words, it is not associated to any subnet.
 *          The user can optionally specify an external gateway for a router at create time;
 *          a router's external gateway must be plugged into an external network,
 *          that is to say a network for which the extended field router:external is set to true.
 *
 * @method  bool delete()
 *          delete(string $routerId)
 *          Delete Router action (DELETE /routers/router-id)
 *          This operation removes a logical router;
 *          The operation will fail if the router still has some internal interfaces.
 *          Users must remove all router interfaces before deleting the router,
 *          by removing all internal interfaces through remove router interface operation.
 *
 * @method  object update()
 *          update(string $routerId, object|array $options)
 *          Update Router action (PUT /routers/router-id)
 *          This operation updates a logical router. Beyond the name and the administrative state,
 *          the only parameter which can be updated with this operation is the external gateway.
 *          Please note that this operation does not allow to update router interfaces.
 *          To this aim, the add router interface and remove router interface should be used.
 *
 * @method  object addInterface()
 *          addInterface(string $routerId, string $subnetId = null, string $portId = null)
 *          Add Router Interface action (PUT /routers/router-id/add_router_interface)
 *          This operation attaches a subnet to an internal router interface.
 *          Either a subnet identifier or a port identifier must be passed in the request body.
 *          If both are specified, a 400 Bad Request error is returned.
 *          When the subnet_id attribute is specified in the request body,
 *          the subnet's gateway ip address is used to create the router interface;
 *          otherwise, if port_id is specified, the IP address associated with the port is used
 *          for creating the router interface.
 *          It is worth remarking that a 400 Bad Request error is returned if several IP addresses are associated with the specified port,
 *          or if no IP address is associated with the port;
 *          also a 409 Conflict is returned if the port is already used.
 *
 * @method  object removeInterface()
 *          removeInterface(string $routerId, string $subnetId = null, string $portId = null)
 *          Remove Router Interface action (PUT /routers/router-id/remove_router_interface)
 *          This operation removes an internal router interface, thus detaching a subnet from the router.
 *          Either a subnet identifier (subnet_id) or a port identifier (port_id) should be passed in the request body;
 *          this will be used to identify the router interface to remove.
 *          If both are specified, the subnet identifier must correspond to the one of the first ip address on the port specified by the port identifier;
 *          Otherwise a 409 Conflict error will be returned.
 *          The response will contain information about the affected router and interface.
 *          A 404 Not Found error will be returned either if the router or the subnet/port do not exist or are not visible to the user.
 *          As a consequence of this operation, the port connecting the router with the subnet is removed from the subnet's network.
 */
class RoutersHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'            => 'listRouters',
            'create'          => 'createRouter',
            'delete'          => 'deleteRouter',
            'update'          => 'updateRouter',
            'addInterface'    => 'addRouterInterface',
            'removeInterface' => 'removeRouterInterface',
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.AbstractServiceHandler::getService()
     * @return  NetworkService
     */
    public function getService()
    {
        return parent::getService();
    }
}