<?php

namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\ElbException;
use Scalr\Service\Aws\Elb\AbstractElbDataType;

/**
 * AccessLog
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class AccessLogData extends AbstractElbDataType
{
    /**
     * Specifies whether access log is enabled for the load balancer.
     *
     * @var bool
     */
    public $enabled;

    /**
     * The interval for publishing the access logs. You can specify an interval of either 5 minutes or 60 minutes.
     *
     * @var int
     */
    public $emitInterval;

    /**
     * The name of the Amazon S3 bucket where the access logs are stored.
     *
     * @var string
     */
    public $s3BucketName;

    /**
     * The logical hierarchy you created for your Amazon S3 bucket, for example my-bucket-prefix/prod.
     * If the prefix is not provided, the log is placed at the root level of the bucket.
     *
     * @var string
     */
    public $s3BucketPrefix;

    /**
     * Constructor
     *
     * @param   bool  $enabled    Specifies whether connection draining is enabled for the load balancer.
     */
    public function __construct($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Elb.AbstractElbDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        if ($this->enabled === null) {
            throw new ElbException(get_class($this) . ' has not been initialized with properties values yet.');
        }
    }

}