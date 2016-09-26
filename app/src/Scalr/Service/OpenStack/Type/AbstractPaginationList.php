<?php
namespace Scalr\Service\OpenStack\Type;

use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Service\OpenStack\Services\AbstractService;

/**
 * AbstractPaginationList
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (21.01.2014)
 */
abstract class AbstractPaginationList extends AbstractList implements PaginationInterface
{

    /**
     * Next page
     *
     * @var object
     */
    private $next;

    /**
     * Previous page
     *
     * @var object
     */
    private $previous;

    /**
     * The openstack service associated with the response
     *
     * @var AbstractService
     */
    private $service;

    /**
     * The name of the property in the response to treat as the list of results
     *
     * @var string
     */
    private $subject;

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
        $this->service = $service;
        $this->subject = $subject;
        parent::__construct($array);
        if (!empty($links) && (is_array($links) || $links instanceof \Traversable)) {
            foreach ($links as $link) {
                if (isset($link->rel) && isset($link->href)) {
                    if ($link->rel == 'next' || $link->rel == 'previous') {
                        $this->{$link->rel} = $this->_parseHref($link->href);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\OpenStack\Type\AbstractList::getClass()
     */
    public function getClass()
    {
        //This method may be overridden
        return null;
    }

    /**
     * Gets requested page
     *
     * @param   object    $target
     * @return  AbstractPaginationList  Returns result set on success or throws an exception
     * @throws  RestClientException
     */
    private function _getRequestedPage($target)
    {
        $class = get_class($this);
        $response = $this->service->getOpenStack()->getClient()->call($target->base, $target->path, null, 'GET');
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (!empty($result->{$this->subject . '_links'})) {
                $links = $result->{$this->subject . '_links'};
            } else {
                $links = null;
            }
        } else {
            throw new \Exception('Something goes wrong. Exception is expected to have thrown in hasError() method.');
        }

        return new $class($this->service, $this->subject, $result->{$this->subject}, $links);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\OpenStack\Type\PaginationInterface::getNextPage()
     */
    public function getNextPage()
    {
        return !empty($this->next) ? $this->_getRequestedPage($this->next) : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\OpenStack\Type\PaginationInterface::getPreviousPage()
     */
    public function getPreviousPage()
    {
        return !empty($this->previous) ? $this->_getRequestedPage($this->previous) : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\OpenStack\Type\PaginationInterface::hasNextPage()
     */
    public function hasNextPage()
    {
        return !is_null($this->next);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\OpenStack\Type\PaginationInterface::hasPreviousPage()
     */
    public function hasPreviousPage()
    {
        return !is_null($this->previous);
    }

    /**
     * Parses href
     *
     * @param   string    $href  A
     * @return  object    Returns object
     */
    private function _parseHref($href)
    {
        $endpoint = rtrim($this->service->getEndpointUrl(), '/');
        //some providers gets whrong url schema in href
        $e = parse_url($endpoint);
        $a = parse_url($href);

        $ret = new \stdClass();
        $ret->base = $e['scheme'] . '://'
          . (isset($e['user']) ? ($e['user'] . (isset($e['pass']) ? ':' . urlencode($e['pass']) : '')) . '@' : '')
          . $e['host'] . (isset($e['port']) ? ':' . $e['port'] : '');
        $ret->path = $a['path']
          . (isset($a['query']) ? '?' . $a['query'] : '')
          . (isset($a['fragment']) ? '#' . $a['fragment'] : '');
        $ret->components = $a;

        return $ret;
    }
}