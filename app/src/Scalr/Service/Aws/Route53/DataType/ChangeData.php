<?php
namespace Scalr\Service\Aws\Route53\DataType;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Route53\AbstractRoute53DataType;
use DateTime;

/**
 * ChangeData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ChangeData extends AbstractRoute53DataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * Unique identifier for the change batch request
     *
     * @var string
     */
    public $changeId;

    /**
     * PENDING | INSYNC
     *
     * @var string
     */
    public $status;

    /**
     * Date and time in Coordinated Universal Time format
     *
     * @var DateTime
     */
    public $submittedAt;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Route53.AbstractRoute53DataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->changeId === null) {
            throw new Route53Exception(sprintf('changeId has not been initialized for the "%s" yet!', get_class($this)));
        }
    }

}