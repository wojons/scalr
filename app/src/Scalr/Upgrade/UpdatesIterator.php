<?php
namespace Scalr\Upgrade;

use \RegexIterator;
use \FilesystemIterator;

/**
 * UpdatesIterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (18.10.2013)
 */
class UpdatesIterator extends RegexIterator
{
    /**
     * Current regular expression.
     * Ensure php-5.3 compability.
     *
     * @var string
     */
    private $regex;

    /**
     * Constructor
     */
    public function __construct($path)
    {
        $this->regex = '/^(Update[\d]{14})((?i)\.php)$/';
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
