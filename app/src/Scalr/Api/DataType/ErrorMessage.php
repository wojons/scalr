<?php

namespace Scalr\Api\DataType;

/**
 * ErrorMessage object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (02.03.2015)
 */
class ErrorMessage extends AbstractDataType
{

    /**
     * Bad request
     *
     * HTTP Status Code: 400
     * Example Message: Your request wasn't understood by the API.
     */
    const ERR_BAD_REQUEST = "BadRequest";

    /**
     * Platform is not enabled
     *
     * HTTP Status Code: 409
     * Example Message: Ec2 platform is not enabled
     */
    const ERR_NOT_ENABLED_PLATFORM = 'NotEnabledPlatform';

    /**
     * Not implemented
     *
     * HTTP Status Code: 501
     * Example Message: This functionality has not been implemented yet
     */
    const ERR_NOT_IMPLEMENTED = 'NotImplemented';

    /**
     * Operating System Mismatch
     *
     * HTTP Status Code: 409
     * Example Message: OS mismatch between Role and Image
     */
    const ERR_OS_MISMATCH = "OperatingSystemMismatch";

    /**
     * Unacceptable Image Status
     *
     * HTTP Status Code: 409
     * Example Message: You can't add image img-3456 because of its status: deleted
     */
    const ERR_UNACCEPTABLE_IMAGE_STATUS = "UnacceptableImageStatus";

    /**
     * Internal Server error
     * HTTP Status Code 500
     */
    const ERR_INTERNAL_SERVER_ERROR = 'InternalServerError';

    /**
     * Invalid Structure
     *
     * HTTP Status Code: 400
     * Example Message: Your request is structurally incorrect, and was not understood by the API.
     */
    const ERR_INVALID_STRUCTURE = 'InvalidStructure';

    /**
     * Invalid value
     *
     * HTTP Status Code: 400
     * Example Message: Your request was understood by the API, but included data that is not acceptable.
     */
    const ERR_INVALID_VALUE = 'InvalidValue';

    /**
     * Bad Authentication
     *
     * HTTP Status Code: 401
     * Example Message: Your request authentication failed to validate.
     */
    const ERR_BAD_AUTHENTICATION = 'BadAuthentication';

    /**
     * Insufficient Permissions
     *
     * HTTP Status Code: 403
     * Example Message: Your request requires permissions you do not have.
     */
    const ERR_PERMISSION_VIOLATION = 'PermissionViolation';

    /**
     * Invalid endpoint
     *
     * HTTP Status Code: 404
     * Example Message: The route you are trying to access does not exist
     */
    const ERR_ENDPOINT_NOT_FOUND = 'EndpointNotFound';

    /**
     * Unicity Violation
     *
     * HTTP Status Code: 409
     * Example Message: The changes you are trying to make violate a unicity constraint.
     */
    const ERR_UNICITY_VIOLATION = 'UnicityViolation';

    /**
     * Scope Violation
     *
     * HTTP Status Code: 403
     * Example Message: Your request should be made in a different Scope.
     */
    const ERR_SCOPE_VIOLATION = 'ScopeViolation';

    /**
     * Object In Use
     *
     * HTTP Status Code: 409
     * Example Message: The changes you are trying to make aren't possible while this object is in use.
     */
    const ERR_OBJECT_IN_USE = 'ObjectInUse';

    /**
     * Object Not Found
     *
     * HTTP Status Code: 404
     * Example Message: The URL you are trying to access does not exist.
     */
    const ERR_OBJECT_NOT_FOUND = 'ObjectNotFound';

    /**
     * Machine-readable error code
     *
     * @var string
     */
    public $code;

    /**
     * A human readable error message
     *
     * @var string
     */
    public $message;

    /**
     * Constructor
     * @param   string $code    optional A machine-readable API error
     * @param   string $message optional A human-readable API error message
     */
    public function __construct($code = null, $message = null)
    {
        $this->code = $code ?: '';
        $this->message = $message ?: '';
    }
}