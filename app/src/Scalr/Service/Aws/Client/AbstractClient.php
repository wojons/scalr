<?php
namespace Scalr\Service\Aws\Client;

use Scalr\Service\Aws\Event\SendRequestEvent;
use Scalr\Service\Aws\Event\EventType;

/**
 * AbstractClient
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     19.03.2013
 */
abstract class AbstractClient
{

    /**
     * Aws instance
     *
     * @var \Scalr\Service\Aws
     */
    private $aws;

    /**
     * Last API call
     *
     * @var string
     */
    protected $lastApiCall;

    /**
     * The name of the AWS service
     *
     * This is used in the signature v4
     *
     * @var string
     */
    private $serviceName;

    /**
     * Sets aws instance
     *
     * @param   \Scalr\Service\Aws $aws AWS intance
     * @return  ClientInterface
     */
    public function setAws(\Scalr\Service\Aws $aws = null)
    {
        $this->aws = $aws;

        return $this;
    }

    /**
     * Sets service name in the lower case
     *
     * It is used to authenticate signature v4
     *
     * @param    string     $service  The name of the AWS service to authenticate signature v4
     * @return   ClientInterface
     */
    public function setServiceName($service)
    {
        $this->serviceName = $service;

        return $this;
    }

    /**
     * Gets service name in the lower case
     *
     * It is used to authenticate signature v4
     *
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * Gets AWS instance
     * @return  \Scalr\Service\Aws Returns an AWS intance
     */
    public function getAws()
    {
        return $this->aws;
    }

    /**
     * Increments the quantity of the processed queries during current client instance
     */
    protected function _incrementQueriesQuantity()
    {
        $this->aws->queriesQuantity++;
        $eventObserver = $this->aws->getEventObserver();
        if (isset($eventObserver) && $eventObserver->isSubscribed(EventType::EVENT_SEND_REQUEST)) {
            $eventObserver->fireEvent(new SendRequestEvent(array(
                'requestNumber' => $this->aws->queriesQuantity,
                'apicall'       => isset($this->lastApiCall) ? $this->lastApiCall : null,
            )));
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientInterface::getQueriesQuantity()
     */
    public function getQueriesQuantity()
    {
        return $this->aws->queriesQuantity;
    }
}