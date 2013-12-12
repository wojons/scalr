<?php

namespace Scalr\Upgrade;

use Scalr\Upgrade\Entity\AbstractUpgradeEntity;

/**
 * UpdateIncompleteIterator class
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (11.10.2013)
 * @package  Scalr\Upgrade
 */
class UpdateIncompleteIterator extends \FilterIterator
{
    /**
     * Filter option
     *
     * @var string
     */
    private $appearsAfter;

    /**
     * Constructor
     *
     * @param   \Iterator $iterator     Inner Iterator
     * @param   int       $appearsAfter The unix timestamp of file modification time
     *                                  after which the update should appear in the result list
     */
    public function __construct(\Iterator $iterator, $appearsAfter)
    {
        parent::__construct($iterator);
        $this->appearsAfter = $appearsAfter;
        if (empty($this->appearsAfter) || !is_numeric($appearsAfter)) {
            throw new \InvalidArgumentException(sprintf(
                'Second parameter is expected to be valid number, "%s" given for class "%s".', $appearsAfter, get_class($this)
            ));
        }
    }

	/**
     * {@inheritdoc}
     * @see FilterIterator::accept()
     */
    public function accept()
    {
        /* @var $current \Scalr\Upgrade\AbstractUpdate */
        $current = $this->getInnerIterator()->current();

        if ($current->version !== null) {
            //If version of the software more than applicable version of the update latter should be ignored.
            $ret = version_compare(SCALR_VERSION, $current->version, '<');
        } else {
            $ret = true;
        }

        //It filters either pending or failed updates or those which are the most recent.
        $ret = $ret && ($current->getStatus() != AbstractUpgradeEntity::STATUS_OK || $current->fileInfo->getMTime() > $this->appearsAfter);

        //Forthcoming updates should be postponed
        $ret = $ret && ($current->released <= gmdate('Y-m-d H:i:s'));

        return $ret;
    }
}