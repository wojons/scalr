<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Http\Request;
use Scalr\Acl\Acl;
use Scalr\Service\Aws;
use Scalr\Exception\NotEnabledPlatformException;

/**
 * User/Version-1/Images API Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (03.03.2015)
 */
class Images extends ApiController
{
    /**
     * Retrieves the list of the images
     */
    public function describeAction()
    {
        $this->checkPermissions(Acl::RESOURCE_FARMS_IMAGES);

        $platformFilter = $this->params('cloudPlatform');
        $regionFilter = $this->params('cloudLocation');

        if (!empty($regionFilter) && empty($platformFilter)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Both 'cloudPlatform' and 'cloudLocation' filters should be provided with request.");
        }

        return $this->adapter('image')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Gets default search criteria according environment scope
     *
     * @return array Returns array of the search criteria
     */
    private function getDefaultCriteria()
    {
        return [[ '$or' => [['envId' => $this->getEnvironment()->id], ['envId' => null]] ]];
    }

    /**
     * Gets specified Image taking into account both scope and authentication token
     *
     * @param      string    $imageId                    The unique identifier of the Image (UUID)
     * @param      bool      $restrictToEnvironmentScope optional Whether it should additionally check the Image corresponds to Environment scope
     * @return     Entity\Image Returns the Image Entity on success
     * @throws     ApiErrorException
     */
    public function getImage($imageId, $restrictToEnvironmentScope = false)
    {
        $criteria = $this->getDefaultCriteria();
        $criteria[] = ['hash' => strtolower($imageId)];

        $image = Entity\Image::findOne($criteria);
        /* @var $image Entity\Image */

        if (!$image) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Image either does not exist or is not owned by your environment.");
        }

        if ($restrictToEnvironmentScope && ($image->getScope() !== $image::SCOPE_ENVIRONMENT || $image->envId !== $this->getEnvironment()->id)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION,
                "The image is not either from the environment scope or owned by your environment."
            );
        }

        return $image;
    }

    /**
     * Checks whether Image does exist in the user's scope
     *
     * @param    string     $cloudImageId   The identifier of the image on the Cloud
     * @param    string     $platform       The cloud platform
     * @param    string     $cloudLocation  The cloud location
     * @return   bool Returns TRUE if image exists in the user's scope
     * @throws   ApiErrorException
     */
    public function getImageByCloudId($cloudImageId, $platform, $cloudLocation)
    {
        $criteria = array_merge($this->getDefaultCriteria(), [
            ['id' => $cloudImageId],
            ['platform' => $platform],
            ['cloudLocation' => $cloudLocation]
        ]);

        $image = Entity\Image::findOne($criteria);

        if (!$image) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Image either does not exist or is not owned by your environment.");
        }

        return $image;
    }

    /**
     * Fetches detailed info about one image
     *
     * @param    string $imageId Unique identifier of the image (uuid)
     * @return   \Scalr\Api\DataType\ResultEnvelope
     * @throws   ApiErrorException
     */
    public function fetchAction($imageId)
    {
        $this->checkPermissions(Acl::RESOURCE_FARMS_IMAGES);

        return $this->result($this->adapter('image')->toData($this->getImage($imageId)));
    }

    /**
     * Register an Image in the Environment
     */
    public function registerAction()
    {
        $this->checkPermissions(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_CREATE);

        $object = $this->request->getJsonBody();

        $imageAdapter = $this->adapter('image');

        //Pre validates the request object
        $imageAdapter->validateObject($object, Request::METHOD_POST);

        if (isset($object->scope) && $object->scope !== ScopeInterface::SCOPE_ENVIRONMENT) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope");
        }

        //Read only property. It is needed before toEntity() call to set envId and accountId properties properly
        $object->scope = ScopeInterface::SCOPE_ENVIRONMENT;

        /* @var $image Entity\Image */
        //Converts object into Role entity
        $image = $imageAdapter->toEntity($object);

        $image->hash = null;
        $image->source = Entity\Image::SOURCE_MANUAL;
        $image->status = Entity\Image::STATUS_ACTIVE;

        $imageAdapter->validateEntity($image);

        if (!$image->getEnvironment()->isPlatformEnabled($image->platform)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                "Platform '%s' is not enabled.", $image->platform
            ));
        }

        if (!$image->checkImage()) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE,
                "Unable to find the requested image on the cloud, or it is not usable by your account"
            );
        }

        if (!empty($object->name)) {
            $image->name = $object->name;
        }

        //Saves entity
        $image->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($imageAdapter->toData($image));
    }

    /**
     * Change image attributes. Only the name be can changed!
     *
     * @param  string $imageId Unique identifier of the image (uuid)
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function modifyAction($imageId)
    {
        $this->checkPermissions(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        $object = $this->request->getJsonBody();

        $imageAdapter = $this->adapter('image');

        //Pre validates the request object
        $imageAdapter->validateObject($object, Request::METHOD_PATCH);

        if (isset($object->scope) && $object->scope !== ScopeInterface::SCOPE_ENVIRONMENT) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope");
        }

        //We have to do additional check instead of $this->checkPermissions() because we don't allow to modify SCALR level Image here
        $image = $this->getImage($imageId, true);

        //Copies all alterable properties to fetched Role Entity
        $imageAdapter->copyAlterableProperties($object, $image);

        //Re-validates an Entity
        $imageAdapter->validateEntity($image);

        //Saves verified results
        $image->save();

        return $this->result($imageAdapter->toData($image));
    }

    /**
     * De-registers an Image from this Environment
     *
     * @param   string $imageId Unique identifier of the image (uuid)
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws  ApiErrorException
     * @throws  \Scalr\Exception\ModelException
     */
    public function deregisterAction($imageId)
    {
        $this->checkPermissions(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        //We only allow to delete images that are from the environment scope
        $image = $this->getImage($imageId, true);

        if ($image->getUsed()) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE,
                "The Image is used by some Role or Server."
            );
        }

        $image->delete();

        return $this->result(null);
    }

    /**
     * Copies image to different location
     *
     * @param   string $imageId Unique identifier of the image (uuid)
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws  ApiErrorException
     */
    public function copyAction($imageId)
    {
        $this->checkPermissions(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        $object = $this->request->getJsonBody();

        if (empty($object->cloudLocation) || empty($object->cloudPlatform)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Invalid body");
        }

        $locations = Aws::getCloudLocations();

        if (!in_array($object->cloudLocation, $locations)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid region");
        }

        if ($object->cloudPlatform !== 'ec2') {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Only Ec2 cloud platform is supported");
        }

        $image = $this->getImage($imageId, true);

        $imageAdapter = $this->adapter('image');
        //Re-validates an Entity
        $imageAdapter->validateEntity($image);

        if ($image->cloudLocation == $object->cloudLocation) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Destination region is the same as source one');
        }

        try {
            $newImage = $image->migrateEc2Location($object->cloudLocation, $this->getUser());
        } catch (NotEnabledPlatformException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_NOT_ENABLED_PLATFORM, $e->getMessage());
        }

        $this->response->setStatus(202);

        return $this->result($imageAdapter->toData($newImage));
    }

}