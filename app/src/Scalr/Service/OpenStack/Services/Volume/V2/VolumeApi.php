<?php

namespace Scalr\Service\OpenStack\Services\Volume\V2;

use \Scalr\Service\OpenStack\Services\Volume\V1\VolumeApi as VolumeApiV1;

/**
 * Volume API v2 placeholder
 *
 * @author N.V.
 */
class VolumeApi extends VolumeApiV1
{

    /**
     * {@inheritdoc}
     * @see VolumeApiV1::createVolume()
     */
    public function createVolume($size, $name = null, $desc = null, $snapshotId = null, $type = null, array $metadata = null,
                                 $availabilityZone = null)
    {
        $result = null;
        $volume = array(
            'size' => (int) $size,
        );
        if ($name !== null) {
            $volume['name'] = (string) $name;
        }
        if ($desc !== null) {
            $volume['description'] = (string) $desc;
        }
        if ($snapshotId !== null) {
            $volume['snapshot_id'] = (string) $snapshotId;
        }
        if ($type !== null) {
            $volume['volume_type'] = (string) $type;
        }
        if ($metadata !== null) {
            $volume['metadata'] = $metadata;
        }
        if ($availabilityZone !== null) {
            $volume['availability_zone'] = $availabilityZone;
        }
        $options = array(
            "volume" => $volume,
        );
        $response = $this->getClient()->call(
            $this->service, '/volumes', $options, 'POST'
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->volume;
        }
        return $result;
    }
}