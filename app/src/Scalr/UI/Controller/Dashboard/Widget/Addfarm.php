<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Iterator\SharedProjectsFilterIterator;
use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;

class Scalr_UI_Controller_Dashboard_Widget_Addfarm extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'local'
        );
    }

    public function getContent($params = array())
    {
        $this->request->restrictFarmAccess(null, Acl::PERM_FARMS_MANAGE);
        
        $projects = [];
        if ($this->getContainer()->analytics->enabled && $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)) {
            $costCenter = $this->getContainer()->analytics->ccs->get($this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID));

            $currentYear = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y');
            $quarters = new Quarters(SettingEntity::getQuarters());
            $currentQuarter = $quarters->getQuarterForDate(new \DateTime('now', new \DateTimeZone('UTC')));

            if ($costCenter instanceof CostCentreEntity) {
                $projectsIterator = new SharedProjectsFilterIterator($costCenter->getProjects(), $costCenter->ccId, $this->user, $this->getEnvironment());

                foreach ($projectsIterator as $item) {
                    $quarterBudget = QuarterlyBudgetEntity::findOne([['year' => $currentYear], ['subjectType' => QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT], ['subjectId' => $item->projectId], ['quarter' => $currentQuarter]]);
                    $projects[] = array(
                        'projectId'     => $item->projectId,
                        'name'          => $item->name,
                        'budgetRemain'  => (!is_null($quarterBudget) && $quarterBudget->budget > 0)
                                            ? max(0, round($quarterBudget->budget - $quarterBudget->cumulativespend))
                                            : null,
                    );
                }
                //$costCentreName = $costCenter->name;
                $isLocked = $costCenter->getProperty(CostCentrePropertyEntity::NAME_LOCKED);
                $accountCcs = AccountCostCenterEntity::findOne([['accountId' => $this->environment->clientId], ['ccId' => $costCenter->ccId]]);

                if ($isLocked || !($accountCcs instanceof AccountCostCenterEntity)) {
                    $costCentreLocked = 1;
                } else {
                    $costCentreLocked = 0;
                }

            } else {
                $costCentreName = '';
                $costCentreLocked = 0;
            }

        }

        return [
            'costCenterLocked'  => $costCentreLocked,
            'projects' => $projects
        ];
    }
}
