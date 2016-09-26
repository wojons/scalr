<?php
namespace Scalr\System\Zmq\Cron;

use Iterator;
use DirectoryIterator;
use Exception;
use Countable;

/**
 * Crontab services/routines interator
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (15.09.2014)
 */
class ServiceIterator implements Iterator, Countable
{

    /**
     * Static cache
     *
     * @var array
     */
    private static $_cache;

    /**
     * Collection of the services
     *
     * @var  array
     */
    private $collection;

    /**
     * Internal position in the iterator
     *
     * @var   int
     */
    private $position = 0;

    /**
     * The number of the services
     *
     * @var   int
     */
    private $count = 0;

    /**
     * constructor
     */
    public function __construct()
    {
        $this->collection = self::load();
        $this->count = count($this->collection);
    }

    /**
     * Loads collection of the services and maintains it in the cache
     *
     * @return   array  Returns collection of the services
     */
    private static function load()
    {
        if (empty(self::$_cache['collection'])) {
            self::$_cache['collection'] = [];

            //Path to services/routines classes
            $path = __DIR__ . '/Task';

            foreach (new DirectoryIterator($path) as $info) {
                /* @var $info \SplFileInfo */
                if (!$info->isFile() || substr($info->getFilename(), - 4) != '.php') {
                    continue;
                }

                $class = __NAMESPACE__ . '\\Task\\' . substr($info->getFilename(), 0, - 4);

                //Initializes service object
                $task = new $class();

                if ($task instanceof TaskInterface) {
                    //It's a task class
                    self::$_cache['collection'][] = $task;
                }
            }
        }

        return self::$_cache['collection'];
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     * @return  TaskInterface  Returns Task
     */
    public function current()
    {
        return $this->collection[$this->position];
    }

    /**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        $this->position++;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->collection[$this->position]);
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return $this->count;
    }
}