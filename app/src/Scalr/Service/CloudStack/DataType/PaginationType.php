<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * PaginationType
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  string              $page     The page number of the response
 * @property  string              $pagesize The maximum number of items you want in the response body.
 *
 * @method    string              getPage()            getPage()           Gets a page.
 * @method    PaginationType      setPage()            setPage($val)       Sets a page.
 * @method    string              getPagesize()        getPagesize()       Gets pagesize.
 * @method    PaginationType      setPagesize()        setPagesize($val)   Sets pagesize.
 */
class PaginationType extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('page', 'pagesize');

    /**
     * Convenient constructor
     *
     * @param   string     $page     optional The page
     * @param   string     $pagesize optional The page size
     */
    public function __construct($page = null, $pagesize = null)
    {
        if ($page !== null) $this->setPage($page);
        if ($pagesize !== null) $this->setPagesize($pagesize);
    }
}