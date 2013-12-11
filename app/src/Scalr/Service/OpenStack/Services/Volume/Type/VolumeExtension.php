<?php

namespace Scalr\Service\OpenStack\Services\Volume\Type;

use Scalr\Service\OpenStack\Type\StringType;

/**
 * VolumeExtension
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    26.08.2012
 */
class VolumeExtension extends StringType
{
    const EXT_QUOTA_CLASSES                = 'os-quota-class-sets';
    const EXT_HOSTS                        = 'os-hosts';
    const EXT_VOLUME_HOST_ATTRIBUTE        = 'os-vol-host-attr';
    const EXT_VOLUME_IMAGE_METADATA        = 'os-vol-image-meta';
    const EXT_VOLUME_TENANT_ATTRIBUTE      = 'os-vol-tenant-attr';
    const EXT_QUOTAS                       = 'os-quota-sets';
    const EXT_TYPES_MANAGE                 = 'os-types-manage';
    const EXT_CREATE_VOLUME_EXTENSION      = 'os-image-create';
    const EXT_VOLUME_ACTIONS               = 'os-volume-actions';
    const EXT_TYPES_EXTRA_SPECS            = 'os-types-extra-specs';
    const EXT_BACKUPS                      = 'backups';
    const EXT_SERVICES                     = 'os-services';
    const EXT_ADMIN_ACTIONS                = 'os-admin-actions';
    const EXT_EXTENDED_SNAPSHOT_ATTRIBUTES = 'os-extended-snapshot-attributes';

    public static function getPrefix()
    {
        return 'EXT_';
    }
}