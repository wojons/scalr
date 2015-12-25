<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws\Rds\DataType;

class Scalr_UI_Controller_Tools_Aws_Rds_Pg extends Scalr_UI_Controller
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
        $this->response->page('ui/tools/aws/rds/pg/view.js', [
            'locations'	=> self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false)
        ]);
    }

    /**
     * List parameter groups
     *
     * @param string $cloudLocation Cloud location
     */
    public function xListAction($cloudLocation)
    {
        $groups = [];
        /* @var $pargroup \Scalr\Service\Aws\Rds\DataType\DBParameterGroupData */
        foreach ($this->getAwsClient($cloudLocation)->rds->dbParameterGroup->describe() as $pargroup){
            $groups[] = [
                'Engine'               => $pargroup->dBParameterGroupFamily,
                'DBParameterGroupName' => $pargroup->dBParameterGroupName,
                'Description'          => $pargroup->description,
            ];
        }

        $response = $this->buildResponseFromData($groups, ['Description', 'DBParameterGroupName']);

        $this->response->data($response);
    }

    /**
     * Create a parameter group
     *
     * @param string $cloudLocation        Cloud location
     * @param string $dbParameterGroupName Group name
     * @param string $EngineFamily         Group family
     * @param string $Description          Group description
     */
    public function xCreateAction($cloudLocation, $dbParameterGroupName, $EngineFamily, $Description)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->getAwsClient($cloudLocation)->rds->dbParameterGroup->create(new DataType\DBParameterGroupData(
            $dbParameterGroupName,
            $EngineFamily,
            $Description
        ));

        $this->response->success("DB parameter group successfully created");
    }

    /**
     * Delete parameter group
     *
     * @param string $cloudLocation Cloud location
     * @param string $name          Group name
     */
    public function xDeleteAction($cloudLocation, $name)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->getAwsClient($cloudLocation)->rds->dbParameterGroup->delete($name);
        $this->response->success("DB parameter group successfully removed");
    }

    /**
     * Get parameter groups for editing
     *
     * @param string $cloudLocation Cloud location
     * @param string $name          Group name
     */
    public function editAction($cloudLocation, $name)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $params = $aws->rds->dbParameterGroup->describeParameters($name);

        $groups = $aws->rds->dbParameterGroup->describe($name)->get(0);

        $items = [];
        /* @var $value ParameterData */
        foreach ($params as $value) {
            $value = $value->toArray();
            $value = array_combine(array_map('ucfirst', array_keys($value)), array_values($value));
            $value["Source"] = lcfirst(str_replace(" ", "", ucwords(str_replace("-", " ", $value["Source"]))));
            $itemField = new stdClass();
            if (strpos($value['AllowedValues'], ',') && $value['DataType'] != 'boolean') {
                $store = explode(',', $value['AllowedValues']);

                $itemField->xtype        = 'combo';
                $itemField->allowBlank   = true;
                $itemField->editable     = false;
                $itemField->queryMode    = 'local';
                $itemField->displayField = 'name';
                $itemField->valueField   = 'name';
                $itemField->store        = $store;
            } elseif ($value['DataType'] == 'boolean') {
                $itemField->xtype        = 'checkbox';
                $itemField->inputValue   = 1;
                $itemField->checked      = $value['ParameterValue'] == 1;
            } else {
                if ($value['IsModifiable'] === false) {
                    $itemField->xtype    = 'displayfield';
                } else {
                    $itemField->xtype    = 'textfield';
                }
            }
            $itemField->name        = $value['Source'] . '[' . $value['ParameterName'] . ']';
            $itemField->fieldLabel  = $value['ParameterName'];
            $itemField->value       = $value['ParameterValue'];
            $itemField->labelWidth  = 300;
            $itemField->width       = 790;
            $itemField->readOnly    = $value['IsModifiable'] === false && $itemField->xtype != 'displayfield';
            $itemField->submitValue = $value['IsModifiable'] !== false;

            $itemDesc = new stdClass();
            $itemDesc->xtype  = 'displayinfofield';
            $itemDesc->width  = 16;
            $itemDesc->margin = '0 0 0 5';
            $itemDesc->info   = $value['Description'];

            $item = new stdClass();
            $item->xtype  = 'fieldcontainer';
            $item->layout = 'hbox';
            $item->items  = [$itemField, $itemDesc];

            $items[$value['Source']][] = $item;
        }

        $this->response->page('ui/tools/aws/rds/pg/edit.js', ['params' => $items, 'group' => $groups]);
    }

    /**
     * Save parameter groups
     *
     * @param string $cloudLocation         Cloud location
     * @param string $name                  Group name
     * @param array  $system        optional
     * @param array  $user          optional
     * @param array  $engineDefault optional
     */
    public function xSaveAction($cloudLocation, $name, array $system = [], array $user = [], array $engineDefault = [])
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $params = $aws->rds->dbParameterGroup->describeParameters($name);

        $modifiedParameters = new DataType\ParameterList();
        $newParams = [];
        foreach ($system as $system => $f) {
            $newParams[] = new DataType\ParameterData($system, null, $f);
        }
        foreach ($engineDefault as $default => $f) {
            $newParams[] = new DataType\ParameterData($default, null, $f);
        }
        foreach ($user as $user => $f) {
            $newParams[] = new DataType\ParameterData($user, null, $f);
        }

        //This piece of code needs to be optimized.
        foreach ($newParams as $newParam) {
            /* @var $newParam DataType\ParameterData */
            foreach ($params as $param) {
                /* @var $param DataType\ParameterData */
                if ($param->parameterName == $newParam->parameterName) {
                    if ((empty($param->parameterValue) && !empty($newParam->parameterValue)) ||
                        (!empty($param->parameterValue) && empty($newParam->parameterValue)) ||
                        ($newParam->parameterValue !== $param->parameterValue &&
                        !empty($newParam->parameterValue) && !empty($param->parameterValue))
                    ) {
                        if ($param->applyType === 'static') {
                            $newParam->applyMethod = DataType\ParameterData::APPLY_METHOD_PENDING_REBOOT;
                        } else {
                            $newParam->applyMethod = DataType\ParameterData::APPLY_METHOD_IMMEDIATE;
                        }
                        $modifiedParameters->append($newParam);
                    }
                }
            }
        }

        $oldBoolean = [];
        foreach ($params as $param) {
            if ($param->dataType == 'boolean' && $param->parameterValue == 1) {
                if ($param->applyType == 'static') {
                    $param->applyMethod = DataType\ParameterData::APPLY_METHOD_PENDING_REBOOT;
                } else {
                    $param->applyMethod = DataType\ParameterData::APPLY_METHOD_IMMEDIATE;
                }
                $oldBoolean[] = $param;
            }
        }

        foreach ($oldBoolean as $old) {
            $found = false;
            foreach ($newParams as $newParam) {
                if ($old->parameterName == $newParam->parameterName) {
                    $found = true;
                }
            }

            if (!$found && $old->isModifiable) {
                $old->parameterValue = 0;
                $modifiedParameters->append($old);
            }
        }

        if (count($modifiedParameters)) {
            $aws->rds->dbParameterGroup->modify($name, $modifiedParameters);
        }

        $this->response->success("DB parameter group successfully updated");
    }

    /**
     * Reset parameters in the given group
     *
     * @param string $cloudLocation Cloud location
     * @param string $name          Group name
     */
    public function xResetAction($cloudLocation, $name)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $params = $aws->rds->dbParameterGroup->describeParameters($name);

        $modifiedParameters = new DataType\ParameterList();
        foreach ($params as $param) {
            if ($param->parameterValue && !empty($param->parameterValue)) {
                if ($param->applyType == 'static') {
                    $modifiedParameters->append(new DataType\ParameterData(
                        $param->parameterName,
                        DataType\ParameterData::APPLY_METHOD_PENDING_REBOOT,
                        $param->parameterValue
                    ));
                } else {
                    $modifiedParameters->append(new DataType\ParameterData(
                        $param->parameterName,
                        DataType\ParameterData::APPLY_METHOD_IMMEDIATE,
                        $param->parameterValue
                    ));
                }
            }
        }

        if (count($modifiedParameters)) {
            $aws->rds->dbParameterGroup->reset($name, $modifiedParameters);
        }

        $this->response->success("DB parameter group successfully reset to default");
    }

    /**
     * Gets list of engine families
     *
     * @param string $cloudLocation
     */
    public function xGetDBFamilyListAction($cloudLocation)
    {
        $engineFamilyList = [];

        foreach ($this->getAwsClient($cloudLocation)->rds->describeDBEngineVersions() as $version) {
            /* @var $version \Scalr\Service\Aws\Rds\DataType\DBEngineVersionData */
            $entry = [$version->dBParameterGroupFamily];

            if (!in_array($entry, $engineFamilyList)) {
                $engineFamilyList[] = $entry;
            }
        }

        $this->response->data(['engineFamilyList' => $engineFamilyList]);
    }
}
