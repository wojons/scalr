<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Exception\ScalrException;
use Scalr\DataType\ScopeInterface;

/**
 * WebhookEndpoint entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_endpoints")
 */
class WebhookEndpoint extends AbstractEntity implements ScopeInterface
{

    const LEVEL_SCALR = 1;
    const LEVEL_ACCOUNT = 2;
    const LEVEL_ENVIRONMENT = 4;

    /**
     * The identifier of the webhook endpoint
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuid")
     * @var string
     */
    public $endpointId;

    /**
     * The level
     *
     * @Column(type="integer")
     * @var int
     */
    public $level;

    /**
     * The identifier of the client's account
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The identifier of the client's environment
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Endpoint url
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $url;

    /**
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuid",nullable=true)
     * @var string
     */
    public $validationToken;

    /**
     * @Column(type="boolean")
     * @var bool
     */
    public $isValid;

    /**
     * @Column(type="string")
     * @var string
     */
    public $securityKey;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->isValid = false;
        $this->securityKey = '';
    }

    /**
     * Validates url
     *
     * @return   boolean   Returns true if url endpoint passes validation.
     *                     It saves updated properties itself on success
     * @throws   \Scalr\Exception\ScalrException
     */
    public function validateUrl()
    {
        if (!$this->isValid && $this->endpointId) {
            $q = new \HttpRequest($this->url, HTTP_METH_GET);
            $q->addHeaders(array(
                'X-Scalr-Webhook-Enpoint-Id' => $this->endpointId,
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Date'         => gmdate('r'),
            ));
            $q->setOptions(array(
                'redirect'       => 10,
                'useragent'      => sprintf('Scalr client (http://scalr.com) PHP/%s pecl_http/%s', phpversion(), phpversion('http')),
                'verifypeer'     => false,
                'verifyhost'     => false,
                'timeout'        => 10,
                'connecttimeout' => 10,
            ));
            try {
                $message = $q->send();
                if ($message->getResponseCode() == 200) {
                    $code = trim($q->getResponseBody());
                    $h = $message->getHeader('X-Validation-Token');
                    $this->isValid = ($code == $this->validationToken) || ($h == $this->validationToken);
                    if ($this->isValid) {
                        $this->save();
                    }

                } else
                    throw new ScalrException(sprintf("Validation failed. Endpoint '%s' returned http code %s", strip_tags($this->url), $message->getResponseCode()));

            } catch (\HttpException $e) {
                throw new ScalrException(sprintf("Validation failed. Cannot connect to '%s'.", strip_tags($this->url)));
            }
        }
        return $this->isValid;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        switch ($this->level) {
            case self::LEVEL_ENVIRONMENT:
                return self::SCOPE_ENVIRONMENT;
            case self::LEVEL_ACCOUNT:
                return self::SCOPE_ACCOUNT;
            case self::LEVEL_SCALR:
                return self::SCOPE_SCALR;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unknown level type: %d in %s::%s",
                    $this->level, get_class($this), __FUNCTION__
                ));
        }
    }

    public function setScope($scope, $accountId, $envId)
    {
        switch ($scope) {
            case self::SCOPE_ENVIRONMENT:
                $this->level = self::LEVEL_ENVIRONMENT;
                $this->accountId = $accountId;
                $this->envId = $envId;
                break;
            case self::SCOPE_ACCOUNT:
                $this->level = self::LEVEL_ACCOUNT;
                $this->accountId = $accountId;
                break;
            case self::SCOPE_SCALR:
                $this->level = self::LEVEL_SCALR;
                break;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unknown scope: %d in %s::%s",
                    $scope, get_class($this), __FUNCTION__
                ));
        }
    }

}