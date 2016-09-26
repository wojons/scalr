<?php

use Scalr\Acl\Acl;
use Scalr\Farm\FarmLease;
use Scalr\Model\Entity\SettingEntity;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Core_Governance_Lease extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GOVERNANCE_ENVIRONMENT);
    }

    public function historyAction()
    {
        $this->response->page('ui/core/governance/lease/history.js');
    }

    public function xHistoryRequestsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'DESC'))
        ));

        $sql = 'SELECT fl.*, f.name as farm_name, u1.email AS request_user_email, u2.email AS answer_user_email FROM farm_lease_requests fl
            LEFT JOIN farms f ON f.id = fl.farm_id
            LEFT JOIN account_users u1 ON fl.request_user_id = u1.id
            LEFT JOIN account_users u2 ON fl.answer_user_id = u2.id
            WHERE f.env_id = ?
        ';

        $response = $this->buildResponseFromSql2($sql, array('farm_id', 'request_time', 'request_days', 'status'), array(), array($this->getEnvironmentId()));
        foreach ($response["data"] as &$row) {
            $row['request_time'] = $row['request_time'] ? Scalr_Util_DateTime::convertTz($row['request_time']) : '';
        }

        $this->response->data($response);
    }

    public function xRequestsAction()
    {
        $data = $this->db->GetAll('
            SELECT fl.*, farms.name, fs.value AS terminate_date FROM farm_lease_requests fl
            LEFT JOIN farms ON farms.id = fl.farm_id
            LEFT JOIN farm_settings fs ON fl.farm_id = fs.farmid
            WHERE fl.status = ? AND fs.name = ? AND farms.env_id = ? ORDER BY fs.value',
            array(FarmLease::STATUS_PENDING, Entity\FarmSetting::LEASE_TERMINATE_DATE, $this->getEnvironmentId()));

        $this->response->data(array('data' => $data));
    }

    public function xRequestResultAction()
    {
        $this->request->defineParams(array(
            'requests' => array('type' => 'json'), 'decision'
        ));

        if (! in_array($this->getParam('decision'), array(FarmLease::STATUS_APPROVE, FarmLease::STATUS_DECLINE)))
            throw new Scalr_Exception_Core('Wrong status');

        foreach ($this->getParam('requests') as $id) {
            $req = $this->db->GetRow('SELECT * FROM farm_lease_requests WHERE id = ? LIMIT 1', array($id));
            if ($req) {
                $dbFarm = DBFarm::LoadByID($req['farm_id']);
                $this->user->getPermissions()->validate($dbFarm);

                $this->db->Execute('UPDATE farm_lease_requests SET status = ?, answer_comment = ?, answer_user_id = ? WHERE id = ?',
                    array($this->getParam('decision'), $this->getParam('comment'), $this->user->getId(), $id));

                try {
                    $mailer = Scalr::getContainer()->mailer;
                    if ($dbFarm->ownerId) {
                        $user = Entity\Account\User::findPk($dbFarm->ownerId);

                        if (\Scalr::config('scalr.auth_mode') == 'ldap') {
                            $email = $user->getSetting(Entity\Account\User\UserSetting::NAME_LDAP_EMAIL);
                            if (!$email) {
                                $email = $user->email;
                            }
                        } else {
                            $email = $user->email;
                        }

                        $mailer->addTo($email);
                    } else {
                        $mailer = null;
                    }
                } catch (Exception $e) {
                    $mailer = null;
                }

                if ($this->getParam('decision') == FarmLease::STATUS_APPROVE) {
                    if ($req['request_days'] > 0) {
                        $dt = $dbFarm->GetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE);
                        $dt = new DateTime($dt);
                        $dt->add(new DateInterval('P' . $req['request_days'] . 'D'));
                        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE, $dt->format('Y-m-d H:i:s'));
                        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_NOTIFICATION_SEND, null);

                        if ($mailer)
                            $mailer->sendTemplate(
                                SCALR_TEMPLATES_PATH . '/emails/farm_lease_non_standard_approve.eml',
                                array(
                                    '{{farm_name}}' => $dbFarm->Name,
                                    '{{user_name}}' => $this->user->getEmail(),
                                    '{{comment}}' => $this->getParam('comment'),
                                    '{{date}}' => $dt->format('M j, Y'),
                                    '{{envName}}' => $dbFarm->GetEnvironmentObject()->name,
                                    '{{envId}}' => $dbFarm->GetEnvironmentObject()->id
                                )
                            );
                    } else {
                        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_STATUS, '');
                        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE, '');
                        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_NOTIFICATION_SEND, '');

                        if ($mailer)
                            $mailer->sendTemplate(
                                SCALR_TEMPLATES_PATH . '/emails/farm_lease_non_standard_forever.eml',
                                array(
                                    '{{farm_name}}' => $dbFarm->Name,
                                    '{{user_name}}' => $this->user->getEmail(),
                                    '{{comment}}' => $this->getParam('comment'),
                                    '{{envName}}' => $dbFarm->GetEnvironmentObject()->name,
                                    '{{envId}}' => $dbFarm->GetEnvironmentObject()->id
                                )
                            );
                    }
                } else {
                    $dt = new DateTime($dbFarm->GetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE));
                    SettingEntity::increase(SettingEntity::LEASE_DECLINED_REQUEST);

                    if ($mailer)
                        $mailer->sendTemplate(
                            SCALR_TEMPLATES_PATH . '/emails/farm_lease_non_standard_decline.eml',
                            array(
                                '{{farm_name}}' => $dbFarm->Name,
                                '{{user_name}}' => $this->user->getEmail(),
                                '{{date}}' => $dt->format('M j, Y'),
                                '{{comment}}' => $this->getParam('comment'),
                                '{{envName}}' => $dbFarm->GetEnvironmentObject()->name,
                                '{{envId}}' => $dbFarm->GetEnvironmentObject()->id
                            )
                        );
                }
            }
        }

        $this->response->success();
    }
}
