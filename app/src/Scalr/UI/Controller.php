<?php

use Scalr\Exception\FileNotFoundException;
use Scalr\UI\Request\ObjectInitializingInterface;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Model\Entity\Account\User;
use Scalr\Util\CryptoTool;

class Scalr_UI_Controller
{
    /**
     * @var \ADODB_mysqli
     */
    public $db;

    /**
     * @var Scalr_UI_Request
     */
    public $request;

    /**
     * @var Scalr_UI_Response
     */
    public $response;

    /**
     * @var Scalr_Account_User
     */
    public $user;

    /**
     * @var Scalr_Environment
     */
    protected $environment;

    /**
     * @var CryptoTool
     */
    private $crypto;

    public $cryptoKey;

    /**
     * DI Container
     *
     * @var \Scalr\DependencyInjection\Container
     */
    private $container;

    protected $sortParams = [];

    /**
     * @var string
     */
    public $uiCacheKeyPattern;

    public $unusedPathChunks = [];

    public function __construct()
    {
        $this->request = Scalr_UI_Request::getInstance();
        $this->response = Scalr_UI_Response::getInstance();
        $this->user = $this->request->getUser();
        $this->environment = $this->request->getEnvironment();
        $this->container = Scalr::getContainer();
        $this->db = Scalr::getDb();
    }

    public function init()
    {
    }

    /**
     * @return CryptoTool
     */
    protected function getCrypto()
    {
        if (!$this->crypto) {
            $this->crypto = \Scalr::getContainer()->crypto;
        }

        return $this->crypto;
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

    /**
     * Returns identifier of the current Environment which is selected by the User.
     *
     * If environment hasn't been set it will throw an exception unless silent is turned on.
     *
     * @param   bool    $silent optional On false throw Exception
     * @return  int     Returns identifier of the Environment
     * @throws  Scalr_Exception_Core
     */
    public function getEnvironmentId($silent = false)
    {
        if ($this->environment) {
            return $this->environment->id;
        } else if ($silent) {
            return null;
        } else {
            throw new Scalr_Exception_Core("No environment defined for current session.");
        }
    }

    /**
     * Gets current environment
     *
     * @return  Scalr_Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @deprecated
     * @param string $key
     * @param bool $rawValue if true returns rawValue (not stripped) only once, don't save in cache
     * @return mixed
     */
    public function getParam($key, $rawValue = false)
    {
        return $this->request->getParam($key, $rawValue);
    }

    /**
     * Restricts access to controller's actions
     *
     * @return boolean Returns true if user has access.
     */
    public function hasAccess()
    {
        if ($this->user) {
            // check admin, non-admin
            if (! $this->user->isAdmin()) {
                // check controller in permissions
                return true;
            } else
                return false;
        } else
            return false;
    }

    /**
     * Restricts access based on mnemonic constants
     * Method interprets $resourceMnemonic as RESOURCE_$resourceMnemonic_$scope,
     * $permissionMnemonic as PERM_$resourceMnemonic_$scope_$permissionMnemonic
     * For example, call(ROLES, MANAGE) on account scope will check RESOURCE_ROLES_ACCOUNT, PERM_ROLES_ACCOUNT_MANAGE
     *
     * @param   string  $resourceMnemonic               Name of resource
     * @param   string  $permissionMnemonic optional    Name of permission
     * @throws  Scalr_Exception_InsufficientPermissions
     * @throws  Scalr_Exception_Core
     */
    public function restrictAccess($resourceMnemonic, $permissionMnemonic = null)
    {
        if ($this->user->isScalrAdmin()) {
            // we don't have permissions on scalr scope
            return;
        }

        $resourceConst = 'Scalr\Acl\Acl::RESOURCE_' . strtoupper($resourceMnemonic) . '_' . strtoupper($this->request->getScope());
        $permissionConst = $permissionMnemonic ? 'Scalr\Acl\Acl::PERM_' . strtoupper($resourceMnemonic). '_' . strtoupper($this->request->getScope()) . '_' . strtoupper($permissionMnemonic) : NULL;

        if (! defined($resourceConst)) {
            throw new Scalr_Exception_Core("ACL Constant {$resourceConst} was not found for method restrictAccess");
        }

        if ($permissionConst && !defined($permissionConst)) {
            throw new Scalr_Exception_Core("ACL Constant {$permissionConst} was not found for method restrictAccess");
        }

        $resource = constant($resourceConst);
        $permission = $permissionConst ? constant($permissionConst) : NULL;

        $this->request->restrictAccess($resource, $permission);
    }

    /**
     * Check whether the user has access permissions to the specified object
     *
     * @param   object     $object
     * @param   boolean    $modify
     * @return  bool                Returns TRUE if the authenticated user has access or FALSE otherwise
     * @throws  Scalr_Exception_Core
     */
    public function hasPermissions($object, $modify = null)
    {
        if (! ($object instanceof AccessPermissionsInterface)) {
            throw new Scalr_Exception_Core('Object is not an instance of AccessPermissionsInterface');
        }

        return $object->hasAccessPermissions(User::findPk($this->user->getId()), $this->getEnvironment(), $modify);
    }

    /**
     * Check whether the user has access permissions to the specified object
     *
     * @param   object    $object
     * @param   bool      $modify
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function checkPermissions($object, $modify = null)
    {
        if (! $this->hasPermissions($object, $modify)) {
            throw new Scalr_Exception_InsufficientPermissions('Access denied');
        }
    }

    protected function sort($item1, $item2)
    {
        foreach ($this->sortParams as $cond) {
            $field = $cond['property'];
            if (is_int($item1[$field]) || is_float($item1[$field])) {
                $result = ($item1[$field] == $item2[$field]) ? 0 : (($item1[$field] < $item2[$field]) ? -1 : 1);
            } else {
                $result = strcasecmp($item1[$field], $item2[$field]);
            }
            if ($result != 0)
                return $cond['direction'] == 'DESC' ? $result : ($result > 0 ? -1: 1);
        }

        return 0;
    }

    /**
     * Parse and validate input parameter sort (json-encoded array), and return as array
     * [[property, direction], ...]
     *
     * @return  array   Array of sort orders [[property => '', direction => '']]
     * @throws  Scalr_Exception_Core
     */
    protected function getSortOrder()
    {
        $sort = $this->request->getRequestParam('sort');
        $sort = json_decode($sort, true);
        $result = [];

        if (is_array($sort)) {
            foreach ($sort as $s) {
                if (preg_match('/^[a-z\d_]+$/i', $s['property'])) {
                    $result[] = [
                        'property'  => $s['property'],
                        'direction' => strtoupper($s['direction']) == 'DESC' ? 'DESC' : 'ASC'
                    ];
                }
            }
        }

        return $result;
    }

    protected function buildResponseFromData(array $data, $filterFields = [], $ignoreLimit = false)
    {
        $this->request->defineParams([
            "start" => ["type" => "int", "default" => 0],
            "limit" => ["type" => "int", "default" => 20]
        ]);

        if ($this->getParam("query") && count($filterFields) > 0) {
            $query = trim($this->getParam("query"));
            $data = array_filter($data, function ($value) use ($filterFields, $query) {
                foreach (array_intersect(array_keys($value), $filterFields) as $field) {
                    if (stristr($value[$field], $query)) {
                        return true;
                    }
                }
                return false;
            });
        }

        $response["total"] = count($data);

        $sortParams = $this->getSortOrder();
        if (count($sortParams)) {
            $this->sortParams = $sortParams;
            @usort($data, [$this, "sort"]);
        }

        if (!$ignoreLimit) {
            $data = array_slice($data, $response["total"] > $this->getParam("start") ? $this->getParam("start") : 0, $this->getParam("limit"));
        }

        $response["data"] = array_values($data);
        $response["success"] = true;

        return $response;
    }

    protected function buildResponseFromSql2($sql, $sortFields = [], $filterFields = [], $args = [], $noLimit = false)
    {
        if ($this->getParam('query') && ! empty($filterFields)) {
            $filter = $this->db->qstr('%' . trim($this->getParam('query')) . '%');
            foreach ($filterFields as &$field) {
                $fs = explode('.', $field);
                foreach ($fs as &$f) {
                    $f = "`{$f}`";
                }
                $field = implode('.', $fs) . " LIKE " . $filter;
            }
            $sql = str_replace(':FILTER:', '(' . implode(' OR ', $filterFields) . ')', $sql);
        } else {
            $sql = str_replace(':FILTER:', '1=1', $sql);
        }

        if (!$noLimit) {
            $response['total'] = $this->db->GetOne('SELECT COUNT(*) FROM (' . $sql. ') c_sub', $args);
        }

        $sortParams = $this->getSortOrder();
        if (count($sortParams)) {
            $sortSql = [];
            foreach ($sortParams as $param) {
                if (in_array($param['property'], $sortFields)) {
                    $sortSql[] = "`{$param['property']}` {$param['direction']}";
                }
            }

            if (!empty($sortSql)) {
                $sql .= " ORDER BY " . implode(',', $sortSql);
            }
        }

        if (! $noLimit) {
            $start = intval($this->getParam('start'));
            if ($start > $response["total"] || $start < 0) {
                $start = 0;
            }

            $limit = intval($this->getParam('limit'));
            if ($limit < 1 || $limit > 100) {
                $limit = 100;
            }
            $sql .= " LIMIT $start, $limit";
        }

        $response['data'] = $this->db->GetAll($sql, $args);
        $response['success'] = true;

        return $response;
    }

    /**
     * @deprecated
     * @param string $sql
     * @param array  $filterFields
     * @param string $groupSQL
     * @param bool|true $simpleQuery
     * @param bool|false $noLimit
     * @return mixed
     */
    protected function buildResponseFromSql($sql, $filterFields = array(), $groupSQL = "", $simpleQuery = true, $noLimit = false)
    {
        $this->request->defineParams(array(
            'start' => array('type' => 'int', 'default' => 0),
            'limit' => array('type' => 'int', 'default' => 20)
        ));

        if (is_array($groupSQL)) {
            return $this->buildResponseFromSql2($sql, $filterFields, $groupSQL, is_array($simpleQuery) ? $simpleQuery : array(), $noLimit);
        }

        if ($this->getParam('query') && count($filterFields) > 0) {
            $filter = $this->db->qstr('%' . trim($this->getParam('query')) . '%');
            foreach($filterFields as $field) {
                if ($simpleQuery)
                    $likes[] = "`{$field}` LIKE {$filter}";
                else
                    $likes[] = "{$field} LIKE {$filter}";
            }
            $sql .= " AND (";
            $sql .= implode(" OR ", $likes);
            $sql .= ")";
        }

        if ($groupSQL)
            $sql .= "{$groupSQL}";

        if (!$noLimit) {
            $response['total'] = $this->db->GetOne('SELECT COUNT(*) FROM (' . $sql. ') c_sub');
        }

        // @TODO replace with simple code (legacy code)
        $s = $this->getParam('sort');
        if (! is_array($s)) {
            $s = json_decode($this->getParam('sort'), true);
        }

        if (is_array($s)) {
            $sorts = array();
            if (count($s) && (!isset($s[0]) || !is_array($s[0])))
                $s = array($s);

            foreach ($s as $param) {
                $sort = preg_replace("/[^A-Za-z0-9_]+/", "", $param['property']);
                $dir = (in_array(strtolower($param['direction']), array('asc', 'desc'))) ? $param['direction'] : 'ASC';

                if ($sort && $dir)
                    $sorts[] = "`{$sort}` {$dir}";
            }

            if (count($sorts) > 0) {
                $sql .= " ORDER BY " . implode($sorts, ',');
            }
        } else if ($this->getParam('sort')) {
            $sort = preg_replace("/[^A-Za-z0-9_]+/", "", $this->getParam('sort'));
            $dir = (in_array(strtolower($this->getParam('dir')), array('asc', 'desc'))) ? $this->getParam('dir') : 'ASC';
            $sql .= " ORDER BY `{$sort}` {$dir}";
        }

        if (! $noLimit) {
            $start = intval($this->getParam('start'));
            if ($start > $response["total"])
                $start = 0;

            $limit = intval($this->getParam('limit'));
            $sql .= " LIMIT $start, $limit";
        }

        //$response['sql'] = $sql;
        $response["success"] = true;
        $response["data"] = $this->db->GetAll($sql);

        return $response;
    }

    public function call($pathChunks = array(), $permissionFlag = true)
    {
        $arg = array_shift($pathChunks);

        try {
            $subController = self::loadController($arg, get_class($this), true);
        } catch (Scalr_UI_Exception_NotFound $e) {
            $subController = null;
        }

        if ($subController) {
            $this->addUiCacheKeyPatternChunk($arg);
            $subController->uiCacheKeyPattern = $this->uiCacheKeyPattern;
            $subController->call($pathChunks, $permissionFlag);

        } else if (($action = $arg . 'Action') && method_exists($this, $action)) {
            $this->addUiCacheKeyPatternChunk($arg);
            $this->response->setHeader('X-Scalr-Cache-Id', $this->uiCacheKeyPattern);

            if (!$permissionFlag)
                throw new Scalr_Exception_InsufficientPermissions();

            $this->unusedPathChunks = $pathChunks;
            $this->callActionMethod($action);

        } else if (count($pathChunks) > 0) {
            $constName = get_class($this) . '::CALL_PARAM_NAME';
            if (defined($constName)) {
                $const = constant($constName);
                $this->request->setParams(array($const => $arg));
                $this->addUiCacheKeyPatternChunk('{' . $const . '}');
            } else {
                // TODO notice
            }

            $this->call($pathChunks, $permissionFlag);

        } else if (method_exists($this, 'defaultAction') && $arg == '') {
            $this->response->setHeader('X-Scalr-Cache-Id', $this->uiCacheKeyPattern);

            if (!$permissionFlag)
                throw new Scalr_Exception_InsufficientPermissions();

            $this->callActionMethod('defaultAction');

        } else {
            throw new Scalr_UI_Exception_NotFound();
        }
    }

    public function callActionMethod($method)
    {
        if ($this->request->getRequestType() == Scalr_UI_Request::REQUEST_TYPE_API) {
            $apiMethodCheck = false;
            if (method_exists($this, 'getApiDefinitions')) {
                $api = $this::getApiDefinitions();
                $m = str_replace('Action', '', $method);
                if (in_array($m, $api)) {
                    $apiMethodCheck = true;
                }
            }

            if (! $apiMethodCheck)
                throw new Scalr_UI_Exception_NotFound();
        }

        $reflection = new ReflectionMethod($this, $method);
        if ($reflection->getNumberOfParameters()) {
            $params = array();

            $comment = $reflection->getDocComment();
            $matches = array();
            $types = array();
            if (preg_match_all('/^\s+\*\s+@param\s+(.*)\s+\$([A-Za-z0-9_]+)*.*$/m', $comment, $matches)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $matches[1][$i] = strtolower(trim($matches[1][$i]));
                    if (in_array($matches[1][$i], array('bool', 'boolean', 'int', 'integer', 'float', 'string', 'array'))) {
                        $types[trim($matches[2][$i])] = $matches[1][$i];
                    }
                }
            }
            // TODO: else: make some warning to log, otherwise we don't know when type-casting is not working

            foreach ($reflection->getParameters() as $parameter) {
                $className = $parameter->getClass() ? $parameter->getClass()->name : NULL;
                $value = $this->request->getRequestParam($parameter->name);
                $hasValue = $this->request->hasParam($parameter->name);

                if ($className) {
                    if (is_subclass_of($className, 'Scalr\UI\Request\ObjectInitializingInterface')) {
                        /* @var $className ObjectInitializingInterface */
                        $params[] = $className::initFromRequest($className == 'Scalr\UI\Request\FileUploadData' ? $this->request->getFileName($parameter->name) : $value, $parameter->name);
                    } else {
                        throw new Scalr\Exception\Http\BadRequestException(sprintf('%s is invalid class in argument', $className));
                    }
                } else {
                    $type = $types[$parameter->name] ? $types[$parameter->name] : 'string';

                    if ($hasValue) {
                        if (in_array($type, ['bool', 'boolean'])) {
                            if (is_numeric($value)) {
                                $value = !empty($value);
                            } else if (is_string($value)) {
                                $value = $value !== '' && strtolower($value) !== 'false';
                            } else {
                                $value = (bool)$value;
                            }
                        } else if ($type == 'array') {
                            // do not strip value
                            settype($value, $type);
                        } else {
                            $value = $this->request->stripValue($value);
                            settype($value, $type);
                        }
                    } else {
                        if ($parameter->isDefaultValueAvailable()) {
                            $value = $parameter->getDefaultValue();
                        } else {
                            throw new Exception(sprintf('Missing required argument: %s', $parameter->name));
                        }
                    }

                    $params[] = $value;
                }
            }

            call_user_func_array(array($this, $method), $params);

        } else {
            $this->{$method}();
        }
    }

    public function addUiCacheKeyPatternChunk($chunk)
    {
        $this->uiCacheKeyPattern .= "/{$chunk}";
    }

    static public function handleRequest($pathChunks)
    {
        $startTime = microtime(true);

        if ($pathChunks[0] == '')
            $pathChunks = array('guest');

        try {
            $user = Scalr_UI_Request::getInstance()->getUser();
            if (!$user && !($pathChunks[0] == 'guest' || $pathChunks[0] == 'public')) {
                throw new Scalr_Exception_InsufficientPermissions();
            }

            $controller = self::loadController(array_shift($pathChunks), 'Scalr_UI_Controller', true);
            $class = get_class($controller);

            $controller->uiCacheKeyPattern = '';
            if ($user &&
                $user->getAccountId() &&
                $user->getAccount()->status != Scalr_Account::STATUS_ACTIVE &&
                $user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER &&
                $class != 'Scalr_UI_Controller_Account2' &&
                $class != 'Scalr_UI_Controller_Core' &&
                $class != 'Scalr_UI_Controller_Dashboard' &&
                $class != 'Scalr_UI_Controller_Guest' &&
                $class != 'Scalr_UI_Controller_Public'
            ) {
                // suspended account, user = owner, replace controller with billing or allow billing action/guest action
                throw new Exception('Your account has been suspended.');
            } else {
                $r = explode('_', $class);
                $controller->addUiCacheKeyPatternChunk(strtolower((array_pop($r))));
                $controller->call($pathChunks);
            }

        } catch (Scalr_UI_Exception_AccessDenied $e) {
            Scalr_UI_Response::getInstance()->setHttpResponseCode(403);

        } catch (Scalr_Exception_InsufficientPermissions $e) {
            if (is_object($user))
                Scalr_UI_Response::getInstance()->failure($e->getMessage());
            else {
                Scalr_UI_Response::getInstance()->setHttpResponseCode(403);
            }

        } catch (Scalr_UI_Exception_NotFound $e) {
            Scalr_UI_Response::getInstance()->setHttpResponseCode(404);

        } catch (ADODB_Exception $e) {
            \Scalr::logException($e);
            Scalr_UI_Response::getInstance()->debugException($e);
            Scalr_UI_Response::getInstance()->failure('Database error');
        } catch (FileNotFoundException $e) {
            Scalr_UI_Response::getInstance()->failure(sprintf("File '%s' not found", $e->getPath()));
            Scalr_UI_Response::getInstance()->setHttpResponseCode(404);
        } catch (Exception $e) {
            $rawHtml = get_class($e) == 'Scalr_Exception_LimitExceeded';

            Scalr_UI_Response::getInstance()->debugException($e);
            Scalr_UI_Response::getInstance()->failure($e->getMessage(), $rawHtml);
        }

        Scalr_UI_Response::getInstance()->setHeader("X-Scalr-ActionTime", microtime(true) - $startTime);
    }

    /**
     * Create controller object from current class
     *
     * @param   bool    $checkPermissions
     * @return  Scalr_UI_Controller
     */
    static public function controller($checkPermissions = false)
    {
        $class = get_called_class();
        $classSplitted = explode('_', $class);
        $controller = array_pop($classSplitted);
        $prefix = implode('_', $classSplitted);

        return self::loadController($controller, $prefix, $checkPermissions);
    }

    /**
     *
     * @return Scalr_UI_Controller
     * @throws Scalr_UI_Exception_NotFound
     * @throws Scalr_Exception_InsufficientPermissions
     */
    static public function loadController($controller, $prefix = 'Scalr_UI_Controller', $checkPermissions = false)
    {
        if (preg_match("/^[a-z0-9]+$/i", $controller)) {
            $controller = ucwords(strtolower($controller));

            // support versioning
            if ($prefix == 'Scalr_UI_Controller' && $controller == 'Account') {
                $request = Scalr_UI_Request::getInstance();
                if ($request->getRequestType() == Scalr_UI_Request::REQUEST_TYPE_UI) {
                    $controller = 'Account2';
                } else if ($request->getRequestType() == Scalr_UI_Request::REQUEST_TYPE_API && $request->requestApiVersion == '2') {
                    $controller = 'Account2';
                }
            }

            $className = "{$prefix}_{$controller}";

            if (file_exists(SRCPATH . '/' . str_replace('_', '/', $prefix) . '/' . $controller . '.php') && class_exists($className)) {
                $o = new $className();
                $o->init();
                if (!$checkPermissions || $o->hasAccess())
                    return $o;
                else
                    throw new Scalr_Exception_InsufficientPermissions();
            }
        }

        throw new Scalr_UI_Exception_NotFound(isset($className) ? $className : '');
    }

    /**
     * Gets DI Container
     *
     * @return \Scalr\DependencyInjection\Container Returns DI Container
     */
    public function getContainer()
    {
        return $this->container;
    }
}
