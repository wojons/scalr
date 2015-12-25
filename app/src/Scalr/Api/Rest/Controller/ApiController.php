<?php

namespace Scalr\Api\Rest\Controller;

use DomainException;
use Scalr\Api\Rest\ApiApplication;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\Pagination;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\DataType\ScopeInterface;

/**
 * Api base Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (12.02.2015)
 */
class ApiController extends AbstractController
{

    const QUERY_PARAM_PAGE_NUM = 'pageNum';

    const QUERY_PARAM_MAX_RESULTS = 'maxResults';

    /**
     * Application instance
     *
     * @var  \Scalr\Api\Rest\ApiApplication
     */
    protected $app;

    /**
     * Common query parameters
     *
     * @var array
     */
    private $commonQueryParameters = [];

    /**
     * Sets API Application instance
     *
     * @param    ApiApplication    $app  API Application instance
     * @return   ApiController
     */
    public function setApplication($app)
    {
        if (!($app instanceof ApiApplication)) {
            throw new \InvalidArgumentException(sprintf("Argument must be Scalr\\Api\\Rest\\ApiApplication instance."));
        }

        $this->app = $app;

        return $this;
    }

    /**
     * Gets Application
     *
     * @return ApiApplication
     */
    public function getApplication()
    {
        return $this->app;
    }

    public function __call($method, $args)
    {
        if (method_exists($this, $method . 'Action')) {
            //Processes common query parameters such as pageNum or maxResults
            $this->processCommonQueryParameters();

            $this->response->setContentType("application/json", "utf-8");

            $result = call_user_func_array([$this, $method . 'Action'], $args);

            if (is_object($result) || is_array($result)) {
                $this->response->setBody(json_encode($result));
                return true;
            } else {
                return $result;
            }
        } else {
            throw new \Exception(sprintf("Method %sAction() does not exist in the %s controller", $method, get_class($this)));
        }
    }

    /**
     * Processes and validates common query parameters from the request
     */
    private function processCommonQueryParameters()
    {
        $this->commonQueryParameters[self::QUERY_PARAM_MAX_RESULTS] = (int) $this->request->get(self::QUERY_PARAM_MAX_RESULTS, 100);
        $this->commonQueryParameters[self::QUERY_PARAM_PAGE_NUM] = (int) $this->request->get(self::QUERY_PARAM_PAGE_NUM, 1);

        if ($this->commonQueryParameters[self::QUERY_PARAM_PAGE_NUM] < 1) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Page number should be more than 0.');
        }

        if ($this->commonQueryParameters[self::QUERY_PARAM_MAX_RESULTS] < 1 || $this->commonQueryParameters[self::QUERY_PARAM_MAX_RESULTS] > 100) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Limit should be a positive number less then or equal to 100.');
        }
    }

    /**
     * Gets GET request parameter
     *
     * If common query parameter is used
     *
     * @param    string $name    The name of the parameter
     * @param    string $default optional The default value of the parameter
     *
     * @return   string|array   Returns the value of the param. If the name is not specified it returns
     *                          all query params
     */
    final public function params($name = null, $default = null)
    {
        if ($name === null) {
            return $this->commonQueryParameters + $this->request->get();
        } else {
            return array_key_exists($name, $this->commonQueryParameters) ? $this->commonQueryParameters[$name] :
                   $this->request->get($name, $default);
        }
    }

    /**
     * Gets common query parameters
     *
     * @return  array Returns array of common query parameters looks like [name => value]
     */
    public function getCommonQueryParams()
    {
        return $this->commonQueryParameters;
    }

    /**
     * Gets the maximum records per page
     *
     * @return   int Returns maximum records per page
     */
    public function getMaxResults()
    {
        return $this->commonQueryParameters[self::QUERY_PARAM_MAX_RESULTS];
    }

    /**
     * Gets the number of the page
     *
     * @return   int Returns the number of the page
     */
    public function getPageNum()
    {
        return $this->commonQueryParameters[self::QUERY_PARAM_PAGE_NUM];
    }

    /**
     * Gets the page offset
     *
     * @return   int  Returns page offset
     */
    public function getPageOffset()
    {
        return ($this->getPageNum() - 1) * $this->getMaxResults();
    }

    /**
     * Gets result envelope for the response
     *
     * @param     mixed         $data       The response data
     * @return    ResultEnvelope Returns result envelope object depends on specified data
     */
    public function result($data)
    {
        $envelope = new ResultEnvelope();
        $envelope->data = $data;

        return $envelope;
    }

    /**
     * Gets the list result envelope for the response
     *
     * @param     mixed         $data       The list of the objects which should be responded with
     * @param     string|null   $foundRows  optional The number of the found rows to inject Pagination into envelope
     * @return    ListResultEnvelope Returns list result
     */
    public function resultList($data, $foundRows = null)
    {
        $envelope = new ListResultEnvelope();
        $envelope->pagination = $this->getPagination($foundRows);
        $envelope->data = $data;

        return $envelope;
    }

    /**
     * Generates link using specified query params
     *
     * @param    array     $params  The query params
     * @param    bool      $sort    optional Should it sort params array to be canonical
     * @return   string
     */
    private function generateLink(&$params, $sort = false)
    {
        if ($sort) {
            ksort($params);
        }

        return $this->request->getPath() . '?' . http_build_query($params, null, '&', PHP_QUERY_RFC3986);
    }


    /**
     * Gets pagination object
     *
     * @param    int    $foundRows  The number of records found
     * @return   Pagination  Returns pagination object
     */
    public function getPagination($foundRows)
    {
        $pagination = new Pagination();

        $maxResults = $this->getMaxResults();

        if (!empty($foundRows) && $foundRows > $maxResults) {
            $params = $this->params();

            $pageNum = $this->getPageNum();

            $params[self::QUERY_PARAM_PAGE_NUM] = 1;

            $pagination->first = $this->generateLink($params, true);

            $params[self::QUERY_PARAM_PAGE_NUM] = $lastPage = ceil($foundRows / $maxResults);
            $pagination->last = $this->generateLink($params);

            if ($pageNum > 1) {
                if ($pageNum == 2) {
                    $pagination->prev = $pagination->first;
                } else {
                    $params[self::QUERY_PARAM_PAGE_NUM] = $pageNum - 1;
                    $pagination->prev = $this->generateLink($params);
                }
            }

            if ($pageNum != $lastPage) {
                if ($pageNum == $lastPage - 1) {
                    $pagination->next = $pagination->last;
                } else {
                    $params[self::QUERY_PARAM_PAGE_NUM] = $pageNum + 1;
                    $pagination->next = $this->generateLink($params);
                }
            }
        }

        return $pagination;
    }

    /**
     * Gets a new Instance of the adapter
     *
     * @param    string    $name  The name of the adapter
     * @return   ApiEntityAdapter Returns the instance of the specified adapter
     */
    public function adapter($name)
    {
        //The same version Adapter should be used as the version of the controller
        $adapterClass = preg_replace('/(\\\\V\d+(?:beta\d*)?\\\\)(.+)$/', '\\1Adapter\\' . ucfirst($name) . 'Adapter', get_class($this));
        return new $adapterClass($this);
    }

    /**
     * Gets authenticated user
     *
     * @return \Scalr\Model\Entity\Account\User
     */
    public function getUser()
    {
        return $this->app->getUser();
    }

    /**
     * Gets Environment from the current request
     *
     * @return \Scalr\Model\Entity\Account\Environment
     */
    public function getEnvironment()
    {
        return $this->app->getEnvironment();
    }

    /**
     * Checks whether the authenticated user either is authorized to the specified object or has permission to ACL Role
     *
     * hasPermissions(object $obj, bool $modify = false)
     * hasPermissions(int $roleId, string $permissionId = null)
     *
     * @return bool            Returns TRUE if the authenticated user has access or FALSE otherwise
     * @throws \BadMethodCallException
     */
    public function hasPermissions()
    {
        return call_user_func_array([$this->app, 'hasPermissions'], func_get_args());
    }

    /**
     * Checks whether the authenticated user either is authorized to the specified object or has permission to ACL Role
     *
     * checkPermissions(object $obj, bool $modify = false, $errorMessage='')
     * checkPermissions(int $roleId, string $permissionId = null, $errorMessage='')
     *
     * @throws ApiInsufficientPermissionsException
     */
    public function checkPermissions(...$args)
    {
        $this->app->checkPermissions(...$args);
    }

    /**
     * Gets bare id from request object
     *
     * @param object $object    Request object
     * @param string $item      Item name (image, role)
     * @return mixed
     */
    public static function getBareId($object, $item)
    {
        if (is_array($object)) {
            $object = (object) $object;
        }

        if (!empty($object->{$item}->id)) {
            return $object->{$item}->id;
        } else if (!empty($object->{$item})) {
            $property = $object->{$item};
            if (is_array($property) && !empty($property['id'])) {
                return $property['id'];
            } else if (!(is_array($property) || is_object($property))) {
                return $object->{$item};
            }
        }

        return null;
    }

    /**
     * Gets current API request scope
     *
     * @return string Returns scope
     */
    public function getScope()
    {
        return $this->app->getScope();
    }

    /**
     * Checks whether the authenticated user either is authorized has permission to ACL Role
     *
     * @param   string  $resourceMnemonic            ACL resource name
     * @param   string  $permissionMnemonic optional ACL permission name
     *
     * @throws ApiInsufficientPermissionsException
     * @throws DomainException
     */
    public function checkScopedPermissions($resourceMnemonic, $permissionMnemonic = null)
    {
        $resourceConst = 'Scalr\Acl\Acl::RESOURCE_' . strtoupper($resourceMnemonic) . '_' . strtoupper($this->getScope());
        $permissionConst = $permissionMnemonic ? 'Scalr\Acl\Acl::PERM_' . strtoupper($resourceMnemonic). '_' . strtoupper($this->getScope()) . '_' . strtoupper($permissionMnemonic) : NULL;

        if (!defined($resourceConst)) {
            throw new DomainException("ACL Constant {$resourceConst} was not found for method checkScopedPermissions");
        }

        if ($permissionConst && !defined($permissionConst)) {
            throw new DomainException("ACL Constant {$permissionConst} was not found for method checkScopedPermissions");
        }

        $resource = constant($resourceConst);
        $permission = $permissionConst ? constant($permissionConst) : NULL;

        $this->checkPermissions($resource, $permission);
    }

    /**
     * Gets criteria corresponding current API request scope
     *
     * @param   string  $scope  optional Scope override
     *
     * @return  array   Returns criteria
     * @throws  ApiErrorException
     */
    public function getScopeCriteria($scope = null, $strictToScope = false)
    {
        switch ($scope ?: $this->getScope()) {
            case ScopeInterface::SCOPE_ENVIRONMENT:
                if ($strictToScope) {
                    $criteria = [
                        ['accountId' => $this->getUser()->accountId],
                        ['envId' => $this->getEnvironment()->id]
                    ];
                } else {
                    $criteria = [
                        ['$or' => [['accountId' => $this->getUser()->accountId], ['accountId' => null]]],
                        ['$or' => [['envId' => $this->getEnvironment()->id], ['envId' => null]]]
                    ];
                }
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                if ($strictToScope) {
                    $criteria = [
                        ['accountId' => $this->getUser()->accountId],
                        ['envId'     => null]
                    ];
                } else {
                    $criteria = [
                        ['$or' => [['accountId' => $this->getUser()->accountId], ['accountId' => null]]],
                        ['envId'     => null]
                    ];
                }
                break;

            case ScopeInterface::SCOPE_SCALR:
                $criteria = [['envId' => null], ['accountId' => null]];
                break;

            default:
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
        }

        return $criteria;
    }

    /**
     * AuditLogger wrapper
     *
     * @param  string  $event      Event name, aka tag
     * @param  mixed   $extra      optional Array of additionally provided information
     * @param  mixed   $extra,...  optional
     * @return boolean Whether operation was successful
     */
    public function auditLog($event, ...$extra)
    {
        return $this->getContainer()->auditlogger->auditLog($event, ...$extra);
    }
}