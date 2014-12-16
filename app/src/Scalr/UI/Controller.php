<?php

use Scalr\UI\Request\ObjectInitializingInterface;

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
     * @var Scalr_Util_CryptoTool
     */
    private $crypto;

    /**
     * DI Container
     *
     * @var \Scalr\DependencyInjection\Container
     */
    private $container;

    protected $sortParams = array();

    /**
     * @var string
     */
    public $uiCacheKeyPattern;

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
     * @return Scalr_Util_CryptoTool
     */
    protected function getCrypto()
    {
        if (! $this->crypto) {
            $this->crypto = new Scalr_Util_CryptoTool(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, 24, 8);
            $this->crypto->setCryptoKey(@file_get_contents(dirname(__FILE__)."/../../../etc/.cryptokey"));
        }

        return $this->crypto;
    }

    /**
     * @param bool $silent optional On false throw Exception
     * @return mixed
     * @throws Scalr_Exception_Core
     */
    public function getEnvironmentId($silent = false)
    {
        if ($this->environment) {
            return $this->environment->id;
        } else {
            if ($silent)
                return NULL;
            else
                throw new Scalr_Exception_Core("No environment defined for current session");
        }
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @deprecated
     * @param $key
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

    protected function sort($item1, $item2)
    {
        foreach ($this->sortParams as $cond) {
            $field = $cond['property'];
            if (is_int($item1[$field]) || is_float($item1[$field])) {
                $result = ($item1[$field] == $item2[$field]) ? 0 : (($item1[$field] < $item2[$field]) ? -1 : 1);
            } else {
                $result = strcmp($item1[$field], $item2[$field]);
            }
            if ($result != 0)
                return $cond['direction'] == 'DESC' ? $result : ($result > 0 ? -1: 1);
        }

        return 0;
    }

    protected function buildResponseFromData(array $data, $filterFields = array())
    {
        $this->request->defineParams(array(
            'start' => array('type' => 'int', 'default' => 0),
            'limit' => array('type' => 'int', 'default' => 20)
        ));

        if ($this->getParam('query') && count($filterFields) > 0) {
            $query = trim($this->getParam('query'));
            foreach ($data as $k => $v) {
                $found = false;
                foreach ($filterFields as $field)
                {
                    if (stristr($v[$field], $query)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found)
                    unset($data[$k]);
            }
        }

        $response['total'] = count($data);

        $s = $this->getParam('sort');
        if (! is_array($s)) {
            $s = json_decode($this->getParam('sort'), true);
        }

        $sortParams = array();
        if (is_array($s)) {
            if (count($s) && isset($s[0]) && !is_array($s[0]))
                $s = array($s);

            foreach ($s as $param) {
                if (is_array($param)) {
                    $sort = preg_replace("/[^A-Za-z0-9_]+/", "", $param['property']);
                    $dir = (in_array(strtolower($param['direction']), array('asc', 'desc'))) ? $param['direction'] : 'ASC';

                    $sortParams[] = array('property' => $sort, 'direction' => $dir);
                }
            }
        } else if ($this->getParam('sort')) {
            $sort = preg_replace("/[^A-Za-z0-9_]+/", "", $this->getParam('sort'));
            $dir = (in_array(strtolower($this->getParam('dir')), array('asc', 'desc'))) ? $this->getParam('dir') : 'ASC';

            $sortParams[] = array('property' => $sort, 'direction' => $dir);
        }

        if (count($sortParams)) {
            $this->sortParams = $sortParams;
            usort($data, array($this, 'sort'));
        }

        $data = (count($data) > $this->getParam('limit')) ? array_slice($data, $this->getParam('start'), $this->getParam('limit')) : $data;

        $response["success"] = true;
        $response['data'] = array_values($data);

        return $response;
    }

    protected function buildResponseFromSql2($sql, $sortFields = array(), $filterFields = array(), $args = array(), $noLimit = false)
    {
        if ($this->getParam('query') && count($filterFields) > 0) {
            $filter = $this->db->qstr('%' . trim($this->getParam('query')) . '%');
            foreach($filterFields as $field) {
                $fs = explode('.', $field);
                foreach($fs as &$f) {
                    $f = "`{$f}`";
                }
                $field = implode('.', $fs);
                $likes[] = "{$field} LIKE {$filter}";
            }
            $sql = str_replace(':FILTER:', '(' . implode(' OR ', $likes) . ')', $sql);
        } else {
            $sql = str_replace(':FILTER:', 'true', $sql);
        }

        if (!$noLimit) {
            $response['total'] = $this->db->GetOne('SELECT COUNT(*) FROM (' . $sql. ') c_sub', $args);
        }

        if (is_array($this->getParam('sort'))) {
            $sort = $this->getParam('sort');
            $sortSql = array();
            if (count($sort) && (!isset($sort[0]) || !is_array($sort[0])))
                $sort = array($sort);

            foreach ($sort as $param) {
                $property = preg_replace('/[^A-Za-z0-9_]+/', '', $param['property']);
                $direction = (in_array(strtolower($param['direction']), array('asc', 'desc'))) ? $param['direction'] : 'asc';

                if (in_array($property, $sortFields))
                    $sortSql[] = "`{$property}` {$direction}";
            }

            if (count($sortSql))
                $sql .= ' ORDER BY ' . implode($sortSql, ',');
        }

        if (! $noLimit) {
            $start = intval($this->getParam('start'));
            if ($start > $response["total"] || $start < 0)
                $start = 0;

            $limit = intval($this->getParam('limit'));
            if ($limit < 1 || $limit > 100)
                $limit = 100;
            $sql .= " LIMIT $start, $limit";
        }

        $response['success'] = true;
        $response['data'] = $this->db->GetAll($sql, $args);

        return $response;
    }

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
                        /* @var ObjectInitializingInterface $className */
                        $params[] = $className::initFromRequest($className == 'Scalr\UI\Request\FileUploadData' ? $this->request->getFileName($parameter->name) : $value);
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
                $class != 'Scalr_UI_Controller_Billing' &&
                $class != 'Scalr_UI_Controller_Core' &&
                $class != 'Scalr_UI_Controller_Guest' &&
                $class != 'Scalr_UI_Controller_Public' &&
                $class != 'Scalr_UI_Controller_Environments'
            ) {
                // suspended account, user = owner, replace controller with billing or allow billing action/guest action
                $controller = self::loadController('Billing', 'Scalr_UI_Controller', true);
                $r = explode('_', get_class($controller));
                $controller->addUiCacheKeyPatternChunk(strtolower((array_pop($r))));
                $controller->call();
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
            Scalr_UI_Response::getInstance()->debugException($e);
            try {
                $db = Scalr::getDb();
                $user = Scalr_UI_Request::getInstance()->getUser();

                $db->Execute('INSERT INTO ui_errors (tm, file, lineno, url, short, message, browser, account_id, user_id) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cnt = cnt + 1', array(
                    $e->getFile(),
                    $e->getLine(),
                    $_SERVER['REQUEST_URI'],
                    substr($e->getMessage(), 0, 255),
                    $e->getMessage() . "\n" . $e->getTraceAsString(),
                    $_SERVER['HTTP_USER_AGENT'],
                    $user ? $user->getAccountId() : '',
                    $user ? $user->getId() : ''
                ));

                Scalr_UI_Response::getInstance()->failure('Database error (1)');

            } catch (Exception $e) {
                Scalr_UI_Response::getInstance()->failure('Database error (2)');
            }

        } catch (Exception $e) {
            $rawHtml = false;
            if (get_class($e) == 'Scalr_Exception_LimitExceeded')
                $rawHtml = true;

            Scalr_UI_Response::getInstance()->debugException($e);
            Scalr_UI_Response::getInstance()->failure($e->getMessage(), $rawHtml);
        }

        Scalr_UI_Response::getInstance()->setHeader("X-Scalr-ActionTime", microtime(true) - $startTime);
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
