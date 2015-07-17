<?php
namespace Scalr\Service\Aws\Kms\DataType;

use Scalr\Service\Aws\Kms\AbstractKmsDataType;
use DateTime;
use DateTimeZone;

/**
 * Kms KeyMetadataData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    22.06.2015
 */
class KeyMetadataData extends AbstractKmsDataType
{

    /**
     * Unique identifier for the key.
     *
     * Length constraints: Minimum length of 1. Maximum length of 256.
     * Required: Yes
     *
     * @var string
     */
    public $keyId;

    /**
     * Account ID number.
     *
     * Required: No
     *
     * @var string
     */
    public $awsAccountId;

    /**
     * Key ARN (Amazon Resource Name).
     *
     * Length constraints: Minimum length of 20. Maximum length of 2048.
     * Required: No
     *
     * @var string
     */
    public $arn;

    /**
     * Date the key was created.
     *
     * Required: No
     *
     * @var DateTime
     */
    public $creationDate;

    /**
     * The description of the key.
     *
     * Length constraints: Minimum length of 0. Maximum length of 8192.
     * Required: No
     *
     * @var string
     */
    public $description;

    /**
     * Value that specifies whether the key is enabled.
     *
     * Required: No
     *
     * @var bool
     */
    public $enabled;

    /**
     * A value that specifies what operation(s) the key can perform.
     *
     * Required: No
     *
     * @var string
     */
    public $keyUsage;

    /**
     * Constructor
     *
     * @param   string   $keyId  Unique identifier for the key.
     */
    public function __construct($keyId = null)
    {
        $this->keyId = (string)$keyId;
    }
}