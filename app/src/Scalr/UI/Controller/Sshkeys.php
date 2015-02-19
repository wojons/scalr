<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Sshkeys extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'sshKeyId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_SECURITY_SSH_KEYS);
    }

    public function viewAction()
    {
        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, array('id' => 0, 'name' => 'All farms'));

        $this->response->page('ui/sshkeys/view.js', array('farms' => $farms));
    }

    /**
     * Doenload private key
     *
     * @param int $sshKeyId
     * @param int $farmId
     * @param string $platform
     * @param string $cloudLocation
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function downloadPrivateAction($sshKeyId = null, $farmId = null, $platform = null, $cloudLocation = null)
    {
        if ($sshKeyId) {
            $sshKey = Scalr_SshKey::init()->loadById($sshKeyId);
        } else {
            $sshKey = Scalr_SshKey::init()->loadGlobalByFarmId(
                $this->getEnvironmentId(),
                $farmId,
                $cloudLocation,
                $platform
            );

            if (!$sshKey && $platform == SERVER_PLATFORMS::EC2) {
                $governance = new \Scalr_Governance($this->getEnvironmentId());
                $keyName = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_KEYPAIR);
                if ($keyName) {
                    throw new Exception(
                        "The SSH Key was not found. Note that SSH Key Governance is active, so Scalr does not automatically create SSH Keys for your Amazon EC2 Servers."
                    );
                }
            }
        }

        if (!$sshKey)
            throw new Exception('SSH key not found in database');
        
        $this->user->getPermissions()->validate($sshKey);
        $retval = $sshKey->getPrivate();

        if ($sshKey->cloudLocation)
            $fileName = "{$sshKey->cloudKeyName}.{$sshKey->cloudLocation}.private.pem";
        else
            $fileName = "{$sshKey->cloudKeyName}.private.pem";

        $this->response->setHeader('Pragma', 'private');
        $this->response->setHeader('Cache-control', 'private, must-revalidate');
        $this->response->setHeader('Content-type', 'plain/text');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        $this->response->setHeader('Content-Length', strlen($retval));

        $this->response->setResponse($retval);
    }

    /**
     * Download public key
     *
     * @param int $sshKeyId
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function downloadPublicAction($sshKeyId)
    {
        $sshKey = Scalr_SshKey::init()->loadById($sshKeyId);
        $this->user->getPermissions()->validate($sshKey);

        $retval = $sshKey->getPublic();
        if (!$retval)
            $retval = $sshKey->generatePublicKey();

        if ($sshKey->cloudLocation)
            $fileName = "{$sshKey->cloudKeyName}.{$sshKey->cloudLocation}.public.pem";
        else
            $fileName = "{$sshKey->cloudKeyName}.public.pem";

        $this->response->setHeader('Pragma', 'private');
        $this->response->setHeader('Cache-control', 'private, must-revalidate');
        $this->response->setHeader('Content-type', 'plain/text');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        $this->response->setHeader('Content-Length', strlen($retval));

        $this->response->setResponse($retval);
    }

    /**
     * Remove SSH keys
     *
     * @param JsonData $sshKeyId json array of sshKeyId to remove
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xRemoveAction(JsonData $sshKeyId)
    {
        $errors = [];

        foreach ($sshKeyId as $id) {
            try {
                $sshKey = Scalr_SshKey::init()->loadById($id);
                $this->user->getPermissions()->validate($sshKey);

                if ($sshKey->type == Scalr_SshKey::TYPE_GLOBAL) {
                    if ($sshKey->platform == SERVER_PLATFORMS::EC2) {
                        $aws = $this->getEnvironment()->aws($sshKey->cloudLocation);
                        $aws->ec2->keyPair->delete($sshKey->cloudKeyName);
                        $sshKey->delete();
                    } elseif (PlatformFactory::isOpenstack($sshKey->platform)) {
                        $os = $this->getEnvironment()->openstack($sshKey->platform, $sshKey->cloudLocation);
                        $os->servers->keypairs->delete($sshKey->cloudKeyName);
                        $sshKey->delete();
                    } else {
                        $sshKey->delete();
                    }
                } else {
                    //TODO:
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (count($errors))
            $this->response->warning("SSH key(s) successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        else
            $this->response->success('SSH key(s) successfully removed');
    }


    /**
     * Regenerate SSH key
     *
     * @param int $sshKeyId
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function regenerateAction($sshKeyId)
    {
        $env = $this->getEnvironment();

        $sshKey = Scalr_SshKey::init()->loadById($sshKeyId);
        $this->user->getPermissions()->validate($sshKey);

        if ($sshKey->type == Scalr_SshKey::TYPE_GLOBAL) {
            if ($sshKey->platform == SERVER_PLATFORMS::EC2) {
                $aws = $env->aws($sshKey->cloudLocation);
                $aws->ec2->keyPair->delete($sshKey->cloudKeyName);
                $result = $aws->ec2->keyPair->create($sshKey->cloudKeyName);

                if (!empty($result->keyMaterial)) {
                    $sshKey->setPrivate($result->keyMaterial);
                    $pubKey = $sshKey->generatePublicKey();
                    if (!$pubKey) {
                        throw new Exception("Keypair generation failed");
                    }
                    $oldKey = $sshKey->getPublic();

                    $sshKey->setPublic($pubKey);
                    $sshKey->save();

                    $dbFarm = DBFarm::LoadByID($sshKey->farmId);
                    $servers = $dbFarm->GetServersByFilter(array('platform' => SERVER_PLATFORMS::EC2, 'status' => array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT, SERVER_STATUS::PENDING)));
                    foreach ($servers as $dbServer) {
                        if ($dbServer->GetCloudLocation() == $sshKey->cloudLocation) {
                            $msg = new Scalr_Messaging_Msg_UpdateSshAuthorizedKeys(array($pubKey), array($oldKey));
                            $dbServer->SendMessage($msg);
                        }
                    }

                    $this->response->success();
                }
            } else {
                //TODO: regenerate ssh key for the different platforms
            }
        } else {
            //TODO:
        }
    }

    /**
     * Get list of roles for listView
     */
    public function xListSshKeysAction()
    {
        $this->request->defineParams(array(
            'sshKeyId' => array('type' => 'int'),
            'farmId'   => array('type' => 'int'),
            'sort'     => array('type' => 'json')
        ));

        $sql = "
            SELECT k.id, count(fr.id) AS status, f.name AS farmName
            FROM ssh_keys k
            LEFT JOIN farms f ON k.farm_id = f.id
            LEFT JOIN farm_roles fr ON k.farm_id = fr.farmid AND k.platform = fr.platform AND (k.cloud_location = fr.cloud_location OR k.cloud_location = '')
            WHERE k.env_id = ?
            AND :FILTER:
        ";
        $params = array($this->getEnvironmentId());

        if ($this->getParam('sshKeyId')) {
            $sql .= " AND k.id = ?";
            $params[] = $this->getParam('sshKeyId');
        }

        if ($this->getParam('farmId')) {
            $sql .= " AND k.farm_id = ?";
            $params[] = $this->getParam('farmId');
        }

        $sql .= ' GROUP BY k.id';

        $response = $this->buildResponseFromSql(
            $sql,
            array('k.id', 'k.type', 'k.cloud_location', 'status'),
            array('k.cloud_key_name', 'k.cloud_location', 'k.farm_id', 'k.id'),
            $params
        );

        foreach ($response["data"] as &$row) {
            $sshKey = Scalr_SshKey::init()->loadById($row['id']);
            $row = array(
                'id'				=> $sshKey->id,
                'type'				=> ($sshKey->type == Scalr_SshKey::TYPE_GLOBAL) ? "{$sshKey->type} ({$sshKey->platform})" : $sshKey->type,
                'cloud_key_name'	=> $sshKey->cloudKeyName,
                'farm_id'		    => $sshKey->farmId,
                'cloud_location'    => $sshKey->cloudLocation,
                'status'            => $row['status'] ? 'In use' : 'Not used',
                'farmName'          => $row['farmName']
            );
        }

        $this->response->data($response);
    }
}
