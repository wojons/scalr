<?php

namespace Scalr\Service\Azure\DataType;

/**
 * SubscriptionData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class SubscriptionData extends AbstractDataType
{
    /**
     * Resource identifier.
     * Uniquely identifies the subscription within the current API Management service instance.
     * The value is a valid relative URL in the format of subscriptions/{sid} where {sid} is a subscription identifier.
     * This property is read-only.
     *
     * @var string
     */
    public $id;

    /**
     * The user resource identifier of the subscription owner.
     * The value is a valid relative URL in the format of users/{uid} where {uid} is a user identifier.
     *
     * @var string
     */
    public $userId;

    /**
     * The product resource identifier of the subscribed product.
     * The value is a valid relative URL in the format of products/{pid} where {pid} is a product identifier.
     *
     * @var string
     */
    public $productId;

    /**
     * The state of the subscription. Possible states are: active|suspended|submitted|rejected|cancelled|expired
     *
     * @var string
     */
    public $state;

    /**
     * Subscription created date, in ISO 8601 format: 2014-06-24T16:25:00Z.
     *
     * @var string
     */
    public $createdDate;

    /**
     * Subscription activation date, in ISO 8601 format: 2014-06-24T16:25:00Z.
     *
     * @var string
     */
    public $startDate;

    /**
     * The date the subscription was cancelled, in ISO 8601 format: 2014-06-24T16:25:00Z.
     *
     * @var string
     */
    public $endDate;

    /**
     * The primary subscription key. Maximum length is 256 characters.
     *
     * @var string
     */
    public $primaryKey;

    /**
     * The secondary subscription key. Maximum length is 256 characters.
     *
     * @var string
     */
    public $secondaryKey;

    /**
     * Optional subscription comment added by an administrator.
     *
     * @var string
     */
    public $stateComment;

}