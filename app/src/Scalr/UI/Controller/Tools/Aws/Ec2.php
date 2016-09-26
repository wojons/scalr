<?php

class Scalr_UI_Controller_Tools_Aws_Ec2 extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
        if (!in_array(SERVER_PLATFORMS::EC2, $enabledPlatforms))
            throw new Exception("You need to enable EC2 platform for current environment");

        return true;
    }

    /**
     * @param string $cloudLocation
     */
    public function xListPlacementGroupsAction($cloudLocation)
    {
        $ec2 = $this->getEnvironment()->aws($cloudLocation)->ec2;
        $retval = array();
        /* @var $pg \Scalr\Service\Aws\Ec2\DataType\PlacementGroupData  */
        foreach ($ec2->placementGroup->describe() as $pg) {
            $retval[] = array('id' => $pg->groupName, 'groupName' => $pg->groupName);
        }

        $this->response->data(array(
            'data' => $retval
        ));
    }

    /**
     * @param string $cloudLocation
     */
    public function createPlacementGroupAction($cloudLocation)
    {
        $this->response->page('ui/tools/aws/ec2/placementgroups/create.js', array());
    }

    /**
     * @param string $cloudLocation
     * @param string $groupName
     */
    public function xCreatePlacementGroupAction($cloudLocation, $groupName)
    {
        $ec2 = $this->getEnvironment()->aws($cloudLocation)->ec2;

        $ec2->placementGroup->create($groupName);


        $this->response->data(array('group' => array(
            'id' => $groupName,
            'groupName' => $groupName
        )));
    }

}
