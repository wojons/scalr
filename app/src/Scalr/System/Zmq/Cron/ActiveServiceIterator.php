<?php
namespace Scalr\System\Zmq\Cron;

use Scalr\DataType\Iterator\AbstractFilter;
use Iterator;

/**
 * Active service iterator
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (15.09.2014)
 */
class ActiveServiceIterator extends AbstractFilter
{

    /**
	 * Constructor
	 */
    public function __construct()
    {
        parent::__construct(new ServiceIterator());
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\Iterator\AbstractFilter::accept()
     */
    public function accept()
    {
        return $this->current()->config()->enabled;
    }
}