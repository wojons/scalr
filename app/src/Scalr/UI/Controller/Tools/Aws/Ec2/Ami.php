<?php

use Scalr\Model\Entity\Image;

class Scalr_UI_Controller_Tools_Aws_Ec2_Ami extends Scalr_UI_Controller
{

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/ec2/ami/view.js', []);
    }

    /**
     * @param string $cloudLocation
     */
    public function xListAction($cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $existedImages = [];
        foreach (Image::find([
            ['platform'      => SERVER_PLATFORMS::EC2],
            ['cloudLocation' => $cloudLocation],
            ['envId'         => $this->getEnvironmentId()]
        ]) as $i) {
            /* @var $i Image */
            $existedImages[$i->id] = $i;
        }

        $images = [];
        foreach ($aws->ec2->image->describe(null, 'self') as $im) {
            $i = [
                'id' => $im->imageId,
                'imageName' => $im->name,
                'imageState' => $im->imageState,
                'imageVirt' => $im->virtualizationType,
                'imageIsPublic' => $im->isPublic
            ];

            if (isset($existedImages[$im->imageId])) {
                $i['status'] = 'sync';
                $i['name'] = $existedImages[$im->imageId]->name;
                unset($existedImages[$im->imageId]);
            } else {
                $i['status'] = 'none';
            }

            $images[] = $i;
        }

        foreach ($existedImages as $i) {
            /* @var $i Image */
            $images[] = [
                'name' => $i->name,
                'id' => $i->id,
                'status' => 'missed'
            ];
        }

        $this->response->data(['data' => $images]);
    }
}
