<?php

use Scalr\Modules\PlatformFactory;

class Scalr_UI_Controller_Tools_Aws extends Scalr_UI_Controller
{
    public static function getAwsLocations()
    {
        $locations = array();

        foreach (PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2)->getLocations($this->environment) as $key => $loc)
            $locations[] = array($key, $loc);

        return $locations;
    }

    public function autoSnapshotSettingsAction()
    {
        $object_type = '';
        if($this->getParam('type') == 'ebs')
            $object_type = AUTOSNAPSHOT_TYPE::EBSSnap;

        if($this->getParam('type') == 'rds')
            $object_type = AUTOSNAPSHOT_TYPE::RDSSnap;

        $infos = $this->db->GetRow("SELECT * FROM autosnap_settings WHERE objectid = ? AND object_type = ? AND env_id = ? LIMIT 1",
            array(
                $this->getParam('objectId'),
                $object_type,
                 $this->getEnvironmentId()
            ));
        $this->response->page('ui/tools/aws/autoSnapshotSettings.js', array('settings' => $infos));
    }

    public function xSaveAutoSnapshotSettingsAction()
    {
        $object_type = '';
        if($this->getParam('type') == 'ebs')
            $object_type = AUTOSNAPSHOT_TYPE::EBSSnap;

        if($this->getParam('type') == 'rds')
            $object_type = AUTOSNAPSHOT_TYPE::RDSSnap;

        if ($this->getParam('enabling'))
        {
            $infos = $this->db->GetRow("SELECT * FROM autosnap_settings WHERE objectid = ? AND object_type = ? AND env_id = ? LIMIT 1",
            array(
                $this->getParam('objectId'),
                $object_type,
                 $this->getEnvironmentId()
            ));
            if($infos)
            {
                $this->db->Execute("UPDATE autosnap_settings SET
                    period		= ?,
                    rotate		= ?
                    WHERE clientid = ? AND objectid = ? AND object_type = ?",
                array(
                    $this->getParam('period'),
                    $this->getParam('rotate'),
                    $this->user->getAccountId(),
                    $this->getParam('objectId'),
                    $object_type
                ));
                $this->response->success('Auto-snapshots successfully updated');
            }
            else
            {
                $this->db->Execute("INSERT INTO autosnap_settings SET
                    clientid 	= ?,
                    period		= ?,
                    rotate		= ?,
                    region		= ?,
                    objectid	= ?,
                    object_type	= ?,
                    env_id		= ?",
                array(
                    $this->user->getAccountId(),
                    $this->getParam('period'),
                    $this->getParam('rotate'),
                    $this->getParam('cloudLocation'),
                    $this->getParam('objectId'),
                    $object_type,
                    $this->getEnvironmentId()
                ));
                $this->response->success('Auto-snapshots successfully enabled');
            }
        }
        else
        {
            $this->db->Execute("DELETE FROM autosnap_settings WHERE
                objectid	= ? AND
                object_type	= ? AND
                env_id		= ?",
             array(
                 $this->getParam('objectId'),
                 $object_type,
                 $this->getEnvironmentId()
            ));
            $this->response->success('Auto-snapshots successfully deleted');
        }
    }

    /**
     * Checks security groups governance policy
     *
     * @param Scalr\UI\Request\JsonData   $vpcSecurityGroups
     * @param string  $serviceName Service name (rds, elb ...)
     * @return bool|string Returns error message if access to some data restricted. True otherwise.
     * @throws Scalr_Exception_Core
     */
    public function checkSecurityGroupsPolicy($vpcSecurityGroups, $serviceName = false)
    {
        $governance = new Scalr_Governance($this->getEnvironmentId());
        $value = $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::getEc2SecurityGroupPolicyNameForService($serviceName), '');

        if (!empty($value)) {
            if (!empty($vpcSecurityGroups)) {
                foreach ($vpcSecurityGroups as $vpcSecurityGroup) {
                    if (empty($vpcSecurityGroup['id'])) {
                        $notFoundGroups[] = strtolower($vpcSecurityGroup['name']);
                    }
                    $vpcSecurityGroupNames[strtolower($vpcSecurityGroup['name'])] = $vpcSecurityGroup['id'];
                }
            }

            if (!empty($value['value']) && !empty($vpcSecurityGroupNames)) {
                if (!empty($notFoundGroups)) {
                    $s = count($notFoundGroups) > 1 ? 's' : '';
                    $es = $s ? '' : "e$s";
                    $they = $s ? "they" : 'it';

                    return sprintf("A Security Group Policy is active in this Environment, and requires that you attach the following Security Group%s to your instance: %s, but %s do%s not exist in current VPC.",
                        $s, implode(', ', $notFoundGroups), $they, $es
                    );
                }
            }
            if (!empty($vpcSecurityGroupNames)) {
                $sgRequiredPatterns = \Scalr_Governance::prepareSecurityGroupsPatterns($value['value']);
                $sgOptionalPatterns = $value['allow_additional_sec_groups'] ? \Scalr_Governance::prepareSecurityGroupsPatterns($value['additional_sec_groups_list']) : [];
                
                $missingGroups = [];
                foreach ($sgRequiredPatterns as $patternName => $sgRequiredPattern) {
                    $sgGroupExists = true;
                    if (!isset($vpcSecurityGroupNames[$patternName])) {
                        $sgGroupExists = false;
                        if (isset($sgRequiredPattern['regexp'])) {
                            foreach ($vpcSecurityGroupNames as $sgGroupName => $sgGroupId) {
                                if (preg_match($sgRequiredPattern['regexp'], $sgGroupName) === 1) {
                                    $sgGroupExists = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (!$sgGroupExists) {
                        $missingGroups[] = $sgRequiredPattern['value'];
                    }
                }
                
                if (!empty($missingGroups)) {
                    return sprintf("A Security Group Policy is active in this Environment, and requires that you attach the following Security Groups to your instance: %s", implode(', ', $missingGroups));
                }

                if (empty($value['allow_additional_sec_groups']) || !empty($sgOptionalPatterns)) {
                    $hasNotAllowedGroups = false;
                    $notAllowedGroupName = null;
                    foreach ($vpcSecurityGroupNames as $sgGroupName => $sgGroupId) {
                        if (!empty($sgRequiredPatterns)) {
                            $hasNotAllowedGroups = !\Scalr_Governance::isSecurityGroupNameAllowed($sgGroupName, $sgRequiredPatterns);
                        } else {
                            $hasNotAllowedGroups = true;
                        }
                        
                        if ($hasNotAllowedGroups && !empty($sgOptionalPatterns)) {
                            $hasNotAllowedGroups = !\Scalr_Governance::isSecurityGroupNameAllowed($sgGroupName, $sgOptionalPatterns);
                        }
                        
                        if ($hasNotAllowedGroups) {
                            $notAllowedGroupName = $sgGroupName;
                            break;
                        }
                    }
                    if ($hasNotAllowedGroups) {
                        return sprintf("A Security Group Policy is active in this Environment, and you can't apply additional security groups to your instance (%s).", $notAllowedGroupName);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Checks vpc governance policy
     *
     * @param string                      $vpcId
     * @param Scalr\UI\Request\JsonData   $subnetIds
     * @param string                      $cloudLocation
     * @return bool|string Returns error message if access to some data restricted. True otherwise.
     * @throws Scalr_Exception_Core
     */
    public function checkVpcPolicy($vpcId, $subnetIds, $cloudLocation)
    {
        $governance = new Scalr_Governance($this->getEnvironmentId());
        $value = $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, '');

        if (!empty($value)) {
            if (!empty($value['value']) && empty($vpcId)) {
                return "A Vpc Policy is active in this Environment, all resources should be launched in a VPC.";
            }

            if (!empty($vpcId)) {
                if (!empty($cloudLocation) && !array_key_exists($cloudLocation, (array) $value['regions'])) {
                    return sprintf("A Vpc Policy is active in this Environment, access to %s region has been restricted by account owner.", $cloudLocation);
                }

                foreach ($value['regions'] as $region => $policy) {
                    if (!empty($policy['ids']) && !empty($cloudLocation) && $cloudLocation == $region && !in_array($vpcId, (array) $policy['ids'])) {
                        return sprintf("A Vpc Policy is active in this Environment, access to vpc %s has been restricted by account owner.", $vpcId);
                    }
                }

                foreach ($value['ids'] as $vpc => $restrictions) {
                    $subnetIds = (array) $subnetIds;
                    $missingSubnets = array_diff($subnetIds, $restrictions);
                    $s = count($missingSubnets) > 1 ? 's' : '';

                    if (!empty($restrictions) && is_array($restrictions) && $vpc == $vpcId && !empty($missingSubnets)) {
                        return sprintf("A Vpc Policy is active in this Environment, access to subnet%s %s has been restricted by account owner.",
                            $s, implode(', ', $missingSubnets)
                        );
                    }
                }
            }
        }

        return true;
    }

}
