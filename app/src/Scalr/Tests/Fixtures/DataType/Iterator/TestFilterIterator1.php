<?php
namespace Scalr\Tests\Fixtures\DataType\Iterator;

use Scalr\DataType\Iterator\AbstractFilter;

class TestFilterIterator1 extends AbstractFilter
{
    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\Iterator\AbstractFilter::accept()
     */
    public function accept()
    {
        return $this->current() % 2 == 0;
    }
}