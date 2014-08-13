<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Image;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Images extends Scalr_UI_Controller
{
    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/images/view.js');
    }

    /**
     * @param string $query
     * @param string $platform
     * @param string $cloudLocation
     * @param JsonData $sort
     * @param int $start
     * @param int $limit
     * @throws Exception
     */
    public function xListAction($query = null, $platform = null, $cloudLocation = null, JsonData $sort, $start = 0, $limit = 20)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        $criteria = [];
        $criteria[] = ['envId' => $this->getEnvironmentId()];

        if ($query) {
            $querySql = '%' . $query . '%';
            $criteria[] = [
                '$or' => [
                    [ 'id' => [ '$like' => $querySql ]]
                ]
            ];
        }

        if ($platform) {
            $criteria[] = ['platform' => $platform];
        }

        if ($cloudLocation) {
            $criteria[] = ['cloudLocation' => $cloudLocation];
        }

        $result = Image::find($criteria, \Scalr\UI\Utils::convertOrder($sort, ['id' => 'ASC'], ['id', 'platform', 'cloudLocation', 'os']), $limit, $start, true);
        $data = [];
        foreach ($result as $image) {
            /* @var Image $image */
            $s = get_object_vars($image);
            $s['status'] = $image->isUsed() ? 'In use' : 'Not used';
            $data[] = $s;
        }

        $this->response->data([
            'total' => $result->totalNumber,
            'data' => $data
        ]);
    }

    /**
     * @param $osFamily
     * @param $osVersion
     */
    public function xGetRoleImagesAction($osFamily, $osVersion)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        $data = [];

        foreach (Image::find([
            ['envId' => $this->getEnvironmentId(true)],
            ['osFamily' => $osFamily],
            ['osVersion' => $osVersion]
        ]) as $image) {
            /* @var Image $image */
            $data[] = [
                'platform' => $image->platform,
                'cloudLocation' => $image->cloudLocation,
                'id' => $image->id,
                'architecture' => $image->architecture,
                'source' => $image->source,
                'createdByEmail' => $image->createdByEmail
            ];
        }

        $this->response->data(['images' => $data]);
    }
}
