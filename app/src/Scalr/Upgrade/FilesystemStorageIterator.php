<?php
namespace Scalr\Upgrade;

use \RegexIterator;
use \FilesystemIterator;

/**
 * FilesystemStorageIterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (18.10.2013)
 */
class FilesystemStorageIterator extends RegexIterator
{
    /**
     * Current regular expression
     *
     * @var string
     */
    private $regex;

	/**
     * Constructor
     */
    public function __construct($path)
    {
        $this->regex = '/^[\da-f]{32}$/i';
        parent::__construct(new FilesystemIterator($path), $this->regex);
    }

	/**
     * {@inheritdoc}
     * @see RegexIterator::accept()
     */
    public function accept()
    {
        /* @var $fileInfo \SplFileInfo */
        $fileInfo = $this->getInnerIterator()->current();
        return $fileInfo->isFile() && preg_match($this->regex, $fileInfo->getFilename());
    }
}
