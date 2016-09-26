<?php

namespace Scalr\Upgrade;

/**
 * UpdateCollection
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (11.10.2013)
 */
class UpdateCollection extends \ArrayObject
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(array());
    }

    /**
     * Sorts updates by release timestamp
     */
    public function sort()
    {
        $this->uasort(function(AbstractUpdate $a, AbstractUpdate $b){
            return ($a->released < $b->released) ? -1 : 1;
        });
    }

    /**
     * Gets an incomplete iterator
     * @param   int    $unixtimestamp The file modification time unix timestamp which
     *                                used to filter collection. All files appears after
     *                                this time will be added to result set.
     * @return  UpdateIncompleteIterator Returns iterator
     */
    public function getIncompleteIterator($unixtimestamp)
    {
        return new UpdateIncompleteIterator(parent::getIterator(), $unixtimestamp);
    }


    /**
     * Gets a pending updates
     *
     * @param   int    $unixtimestamp The file modification time unix timestamp which
     *                                used to filter collection. All files appears after
     *                                this time will be added to result set.
     * @return  UpdateCollection      Returns update collection
     */
    public function getPendingUpdates($unixtimestamp)
    {
        $pending = new UpdateCollection();
        foreach ($this->getIncompleteIterator($unixtimestamp) as $update) {
            //Only updates that need to be applied
            $pending[$update->getUuidHex()] = $update;
        };
        $pending->sort();
        return $pending;
    }
}