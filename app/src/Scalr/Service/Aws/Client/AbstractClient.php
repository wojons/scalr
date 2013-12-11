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