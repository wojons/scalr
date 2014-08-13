<?php
namespace Scalr\Tests\Fixtures\DataType\Iterator;

use Scalr\DataType\Iterator\Filter;

class TestFilterIterator1 extends Filter
{
    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\Iterator\Filter::accept()
     */
    public function accept()
    {
        return $this->current() % 2 == 0;
    }
}