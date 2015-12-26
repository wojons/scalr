<?php
namespace Scalr\Tests\Functional\Api\V2\Iterator;

use RecursiveFilterIterator;
use RecursiveArrayIterator;

/**
 * Class ApiDataRecursiveFilterIterator
 * Filtering data for api call
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (15.12.2015)
 */
class ApiDataRecursiveFilterIterator extends RecursiveFilterIterator
{

    /**
     * Array of the parts of the path
     *
     * @var array
     */
    protected $paths;

    /**
     * Current part of the path
     *
     * @var string
     */
    protected $part;

    /**
     * @var string
     */
    protected $type;

    /**
     * Rejected scope for each type
     *
     * @var array
     */
    protected static $notAllowedScope = [
        'user'    => ['scalr', 'account'],
        'account' => ['scalr']
    ];

    /**
     * ApiDataRecursiveFilterIterator constructor.
     *
     * @param RecursiveArrayIterator $recursiveIter recursive iterator for Api data
     * @param array $parts array of the parts of the path
     */
    public function __construct(RecursiveArrayIterator $recursiveIter, $parts, $type = null)
    {
        $this->parts = $parts;
        $this->part = array_shift($this->parts);
        $this->type = $type;
        parent::__construct($recursiveIter);
    }

    /**
     * {@inheritdoc}
     * @see RecursiveFilterIterator::accept()
     */
    public function accept()
    {
        $result = false;
        $this->prepareData();
        if ($this->getInnerIterator()->key() === $this->part) {
            $result = true;
        }

        if (preg_match('#{\w*}#', $this->part)) {
            $result = true;
            //check scope
            $obj = $this->current();
            if (!is_null($this->type) && array_key_exists('scope', $obj) && in_array($obj['scope'],
                    self::$notAllowedScope[$this->type])
            ) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @see RecursiveFilterIterator::getChildren()
     */
    public function getChildren()
    {
        return new self($this->getInnerIterator()->getChildren(), $this->parts, $this->type);
    }

    /**
     * If has not path parts cut or create Api data
     */
    public function prepareData()
    {
        if (count($this->parts) === 1 && preg_match('#{\w*}#', $this->part)) {
            $this->getInnerIterator()->offsetSet($this->key(), [$this->parts[0] => null]);
        }

        if (empty($this->parts)) {
            if (!preg_match('#{\w*}#', $this->part) && $this->getInnerIterator()->key() !== $this->part) {
                $this->getInnerIterator()->offsetSet($this->part, null);
            } else {
                $this->getInnerIterator()->offsetSet($this->key(), null);
            }
        }
    }
}