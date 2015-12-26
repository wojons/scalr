<?php

use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupIngressRequestData;
use Scalr\UI\Request\JsonData;
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Aws_Rds_Sg extends Scalr_UI_Controller
{
    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_RDS);
    }

    /**
     * Forwards the controller to the default action
     */
    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * Gets AWS Client for the current environment
     *
     * @param  string $cloudLocation Cloud location
     * @return \Scalr\Service\Aws Returns Aws client for current environment
     */
    protected function getAwsClient($cloudLocation)
    {
        return $this->environment->aws($cloudLocation);
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/rds/sg/view.js', array(
            'locations'	=> self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false)
        ));
    }

    /**
     * List security groups
     *
     * @param string $cloudLocation          Cloud location
     * @param string $name          optional Filter by name
     */
    public function xListAction($cloudLocation, $name = null)
    {
        $sGroups = $this->getAwsClient($cloudLocation)->rds->dbSecurityGroup->describe($name)->toArray(true);
        $response = $this->buildResponseFromData($sGroups, ['DBSecurityGroupName']);
        $this->response->data($response);
    }

    /**
     * Creates security group
     *
     * @param string $cloudLocation              Cloud location
     * @param string $dbSecurityGroupName        Security group name
     * @param string $dbSecurityGroupDescription Security group description
     */
    public function xCreateAction($cloudLocation, $dbSecurityGroupName, $dbSecurityGroupDescription)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->getAwsClient($cloudLocation)->rds->dbSecurityGroup->create($dbSecurityGroupName, $dbSecurityGroupDescription);
        $this->response->success("DB security group successfully created");
    }

    /**
     * Deletes security group
     *
     * @param string $cloudLocation Cloud location
     * @param string $dbSgName      Security group name
     */
    public function xDeleteAction($cloudLocation, $dbSgName)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->getAwsClient($cloudLocation)->rds->dbSecurityGroup->delete($dbSgName);
        $this->response->success("DB security group successfully removed");
    }

    /**
     * Gets security group rules for editing
     *
     * @param string $cloudLocation Cloud location
     * @param string $dbSgName      Security group name
     */
    public function editAction($cloudLocation, $dbSgName)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);
        /* @var $group \Scalr\Service\Aws\Rds\DataType\DBSecurityGroupData */
        $group = $this->getAwsClient($cloudLocation)->rds->dbSecurityGroup->describe($dbSgName)->get(0);

        $ipRules = $groupRules = [];
        if (!empty($group->iPRanges) && count($group->iPRanges)) {
            $ipRules = $group->iPRanges->toArray(true);
        }
        if (!empty($group->eC2SecurityGroups) && count($group->eC2SecurityGroups)) {
            $groupRules = $group->eC2SecurityGroups->toArray(true);
        }

        $rules = ['ipRules' => $ipRules, 'groupRules' => $groupRules];

        $this->response->page('ui/tools/aws/rds/sg/edit.js', ['rules' => $rules, 'description' => $group->dBSecurityGroupDescription]);
    }

    /**
     * Saves the rules
     *
     * @param string   $cloudLocation Cloud location
     * @param string   $dbSgName      Security group name
     * @param JsonData $rules         Rules
     */
    public function xSaveAction($cloudLocation, $dbSgName, JsonData $rules)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);
        /* @var $group \Scalr\Service\Aws\Rds\DataType\DBSecurityGroupData */
        $group = $aws->rds->dbSecurityGroup->describe($dbSgName)->get(0);

        $sgRules = [];

        if (!empty($group->iPRanges)) {
            foreach ($group->iPRanges as $r) {
                $r = $r->toArray(true);
                $r['id'] = md5($r['CIDRIP']);
                $sgRules[$r['id']] = $r;
            }
        }
        if (!empty($group->eC2SecurityGroups)) {
            foreach ($group->eC2SecurityGroups as $r) {
                $r = $r->toArray(true);
                $r['id'] = md5($r['EC2SecurityGroupName'] . $r['EC2SecurityGroupOwnerId']);
                $sgRules[$r['id']] = $r;
            }
        }

        foreach ($sgRules as $id => $r) {
            $found = false;
            foreach ($rules as $rule) {
                if ($rule['Type'] == 'CIDR IP') {
                    $rid = md5($rule['CIDRIP']);
                } else {
                    $rid = md5($rule['EC2SecurityGroupName'] . $rule['EC2SecurityGroupOwnerId']);
                }

                if ($id == $rid) {
                    $found = true;
                }
            }

            if (!$found) {
                $request = new DBSecurityGroupIngressRequestData($dbSgName);
                if ($r['CIDRIP']) {
                    $request->cIDRIP = $r['CIDRIP'];
                } else {
                    $request->eC2SecurityGroupName = $r['EC2SecurityGroupName'];
                    $request->eC2SecurityGroupOwnerId = $r['EC2SecurityGroupOwnerId'];
                }
                $aws->rds->dbSecurityGroup->revokeIngress($request);
                unset($request);
            }
        }

        foreach ($rules as $rule){
            if ($rule['Status'] == 'new') {
                $request = new DBSecurityGroupIngressRequestData($dbSgName);
                if ($rule['Type'] == 'CIDR IP') {
                    $request->cIDRIP = $rule['CIDRIP'];
                } else {
                    $request->eC2SecurityGroupName = $r['EC2SecurityGroupName'];
                    $request->eC2SecurityGroupOwnerId = $r['EC2SecurityGroupOwnerId'];
                }
                $aws->rds->dbSecurityGroup->authorizeIngress($request);
                unset($request);
            }
        }

        $this->response->success("DB security group successfully updated");
    }
}
