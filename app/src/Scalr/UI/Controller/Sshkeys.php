<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Utils;
use Scalr\Model\Entity\SshKey;
use Scalr\Model\Entity\Farm;

class Scalr_UI_Controller_Sshkeys extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'sshKeyId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_SECURITY_SSH_KEYS);
    }

    /**
     * Covert object to array (without public/private keys)
     *
     * @param SshKey $key
     * @return array
     */
    public function getSshKeyObject($key)
    {
        /* @var $farm Farm */
        return [
            'id' => $key->id,
            'type' => $key->type,
            'cloudKeyName' => $key->cloudKeyName,
            'platform' => $key->platform,
            'cloudLocation' => $key->cloudLocation,
            'farmId' => $key->farmId,
            'farmName' => ($key->farmId && ($farm = Farm::findPk($key->farmId))) ? $farm->name : '',
            'status' => $key->isUsed() ? 'In use' : 'Not used'
        ];
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/sshkeys/view.js', array(
            'platforms' => (new SshKey())->getEnvironmentPlatforms($this->getEnvironmentId())
        ));
    }

    /**
     * Download private key
     *
     * @param int $sshKeyId
     * @param int $farmId
     * @param string $platform
     * @param string $cloudLocation
     * @param bool $formatPpk
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     * @throws Exception
     */
    public function downloadPrivateAction($sshKeyId = null, $farmId = null, $platform = null, $cloudLocation = null, $formatPpk = false)
    {
        /* @var $sshKey SshKey */
        if ($sshKeyId) {
            $sshKey = SshKey::findPk($sshKeyId);
        } else {
            $sshKey = (new SshKey())->loadGlobalByFarmId(
                $this->getEnvironmentId(),
                $platform,
                $cloudLocation,
                $farmId
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

        $this->request->checkPermissions($sshKey);

        $extension = $formatPpk ? 'ppk' : 'pem';
        $fileName = ($sshKey->cloudLocation) ? "{$sshKey->cloudKeyName}.{$sshKey->cloudLocation}.{$extension}" : "{$sshKey->cloudKeyName}.{$extension}";
        $key = $formatPpk ? $sshKey->getPuttyPrivateKey() : $sshKey->privateKey;

        $this->response->setHeader('Pragma', 'private');
        $this->response->setHeader('Cache-control', 'private, must-revalidate');
        $this->response->setHeader('Content-type', 'plain/text');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        $this->response->setHeader('Content-Length', strlen($key));
        $this->response->setResponse($key);
    }

    /**
     * Download public key
     *
     * @param int $sshKeyId
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     * @throws Exception
     */
    public function downloadPublicAction($sshKeyId)
    {
        /* @var $sshKey SshKey */
        $sshKey = SshKey::findPk($sshKeyId);
        if (!$sshKey)
            throw new Exception("SSH key not found in database");

        $this->request->checkPermissions($sshKey);

        if (!$sshKey->publicKey)
            $sshKey->generatePublicKey();

        if ($sshKey->cloudLocation)
            $fileName = "{$sshKey->cloudKeyName}.{$sshKey->cloudLocation}.pub";
        else
            $fileName = "{$sshKey->cloudKeyName}.pub";

        $this->response->setHeader('Pragma', 'private');
        $this->response->setHeader('Cache-control', 'private, must-revalidate');
        $this->response->setHeader('Content-type', 'plain/text');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="'.$fileName.'"');
        $this->response->setHeader('Content-Length', strlen($sshKey->publicKey));
        $this->response->setResponse($sshKey->publicKey);
    }

    /**
     * Remove SSH keys
     *
     * @param   JsonData    $sshKeyId   json array of sshKeyId to remove
     */
    public function xRemoveAction(JsonData $sshKeyId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_SSH_KEYS, Acl::PERM_SECURITY_SSH_KEYS_MANAGE);

        $errors = [];
        $processed = [];

        foreach ($sshKeyId as $id) {
            try {
                /* @var $sshKey SshKey */
                $sshKey = SshKey::findPk($id);
                if ($sshKey) {
                    $this->request->checkPermissions($sshKey, true);

                    if ($sshKey->type == SshKey::TYPE_GLOBAL) {
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

                        $processed[] = $sshKey->id;
                    } else {
                        //TODO:
                    }
                } else {
                    $errors[] = sprintf('SshKey [%d] was not found', $id);
                }
            } catch (\Scalr\Service\OpenStack\Exception\OpenStackException $e) {
                if (strstr($e->getMessage(), 'not found') || strstr($e->getMessage(), 'not be found')) {
                    $sshKey->delete();
                    $processed[] = $sshKey->id;
                } else {
                    $errors[] = $e->getMessage();
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->response->data(['processed' => $processed]);

        if (count($errors)) {
            $this->response->warning((count($processed) ? "SSH key(s) successfully removed, but some errors occurred:\n" : '') . implode("\n", $errors));
        } else {
            $this->response->success('SSH key(s) successfully removed');
        }
    }

    /**
     * Regenerate SSH key
     *
     * @param   int     $sshKeyId
     * @throws  Exception
     */
    public function regenerateAction($sshKeyId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_SSH_KEYS, Acl::PERM_SECURITY_SSH_KEYS_MANAGE);

        $env = $this->getEnvironment();

        /* @var $sshKey SshKey */
        $sshKey = SshKey::findPk($sshKeyId);
        $this->request->checkPermissions($sshKey, true);

        if ($sshKey->type == SshKey::TYPE_GLOBAL) {
            if ($sshKey->platform == SERVER_PLATFORMS::EC2) {
                $aws = $env->aws($sshKey->cloudLocation);
                $aws->ec2->keyPair->delete($sshKey->cloudKeyName);
                $result = $aws->ec2->keyPair->create($sshKey->cloudKeyName);
                $oldKey = $sshKey->publicKey;

                if (!empty($result->keyMaterial)) {
                    $sshKey->privateKey = $result->keyMaterial;
                    $pubKey = $sshKey->generatePublicKey();
                    if (!$pubKey) {
                        throw new Exception("Keypair generation failed");
                    }

                    $sshKey->publicKey = $pubKey;
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
     * @param   string      $query
     * @param   string      $sshKeyId
     * @param   int         $farmId
     * @param   string      $platform
     * @param   string      $cloudLocation
     * @param   JsonData    $sort
     * @param   int         $start
     * @param   int         $limit
     */
    public function xListAction($query = null, $sshKeyId = null, $farmId = null, $platform = null, $cloudLocation = null, JsonData $sort, $start = 0, $limit = 20)
    {
        $criteria = [[
            'envId' => $this->getEnvironmentId()
        ]];

        if ($this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_SERVERS)) {
            if (!$this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
                $criteria[] = [
                    'farmId' => [
                        '$ne' => NULL
                    ]
                ];
            }
        } else {
            $farms = $this->db->GetCol(
                "SELECT id FROM farms f WHERE env_id = ? AND " . $this->request->getFarmSqlQuery(Acl::PERM_FARMS_SERVERS),
                [$this->getEnvironmentId()]
            );

            if ($this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE)) {
                $criteria[] = [
                    '$or' => [[
                        'farmId' => NULL
                    ], [
                        'farmId' => [
                            '$in' => $farms
                        ]
                    ]]
                ];
            } else {
                if (count($farms)) {
                    $criteria[] = [
                        'farmId' => [
                            '$in' => $farms
                        ]
                    ];
                } else {
                    // user doesn't have access to any farm. try to find better solution
                    $criteria[] = [ 'farmId' => -1 ];
                }
            }
        }

        if ($sshKeyId) {
            $criteria[] = [
                'id' => $sshKeyId
            ];
        }

        if ($farmId) {
            $criteria[] = [
                'farmId' => $farmId
            ];
        }

        if ($query) {
            $querySql = '%' . $query . '%';
            $criteria[] = [
                '$or' => [
                    [ 'cloudKeyName' => [ '$like' => $querySql ]]
                ]
            ];
        }

        if ($platform) {
            $criteria[] = ['platform' => $platform];
            if ($cloudLocation) {
                $criteria[] = ['cloudLocation' => $cloudLocation];
            }
        }

        $result = SshKey::find($criteria, null, Utils::convertOrder($sort, ['id' => true], ['id', 'cloudKeyName', 'platform', 'cloudLocation']), $limit, $start, true);
        $data = [];
        foreach ($result as $key) {
            /* @var $key SshKey */
            $data[] = $this->getSshKeyObject($key);
        }

        $this->response->data([
            'total' => $result->totalNumber,
            'data' => $data
        ]);
    }
}
