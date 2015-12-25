<?php

use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity;

/**
 * Class Scalr_UI_Controller_Services_Ssl_Certificates.
 */
class Scalr_UI_Controller_Services_Ssl_Certificates extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'certId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function viewAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);
        $this->response->page(
            'ui/services/ssl/certificates/view.js',
            [],
            ['ui/services/ssl/certificates/create.js']
        );
    }

    /**
     * @param JsonData $certs
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xRemoveAction(JsonData $certs)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL, Acl::PERM_SERVICES_SSL_MANAGE);

        $errors = [];
        $processed = [];

        foreach ($certs as $certId) {
            try {
                $cert = Entity\SslCertificate::findPk($certId);

                if (!$cert) {
                    throw new Scalr_UI_Exception_NotFound();
                }

                $this->user->getPermissions()->validate($cert);
                $cert->delete();
                $processed[] = $certId;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->response->data(['processed' => $processed]);
        if (count($errors)) {
            $this->response->warning("Certificates(s) successfully removed, but some errors occurred:\n".implode("\n", $errors));
        } else {
            $this->response->success('Certificates(s) successfully removed');
        }
    }

    /**
     * @param int $certId
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function editAction($certId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL, Acl::PERM_SERVICES_SSL_MANAGE);

        /* @var \Scalr\Model\Entity\SslCertificate $cert */
        $cert = Entity\SslCertificate::findPk($certId);

        if (!$cert) {
            throw new Scalr_UI_Exception_NotFound();
        }

        $this->user->getPermissions()->validate($cert);

        $this->response->page('ui/services/ssl/certificates/create.js', [
            'cert' => $cert->getInfo(),
        ]);
    }

    /**
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL, Acl::PERM_SERVICES_SSL_MANAGE);
        $this->response->page('ui/services/ssl/certificates/create.js');
    }

    /**
     * Save certificate (create new or update existing).
     *
     * @param string $name
     * @param int    $id                 optional
     * @param int    $privateKeyClear    optional
     * @param int    $certificateClear   optional
     * @param int    $caBundleClear      optional
     * @param string $privateKeyPassword optional
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     * @throws \Scalr\Exception\ModelException
     */
    public function xSaveAction($name, $id = null, $privateKeyClear = null, $certificateClear = null, $caBundleClear = null, $privateKeyPassword = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL, Acl::PERM_SERVICES_SSL_MANAGE);

        $flagNew = false;
        if ($id) {
            /* @var \Scalr\Model\Entity\SslCertificate $cert */
            $cert = Entity\SslCertificate::findPk($id);

            if (!$cert) {
                throw new Scalr_UI_Exception_NotFound();
            }

            $this->user->getPermissions()->validate($cert);
        } else {
            $cert = new Entity\SslCertificate();
            $cert->envId = $this->getEnvironmentId();
            $flagNew = true;
        }

        $cert->name = $name;
        if (!$cert->name) {
            $this->request->addValidationErrors('name', 'Name can\'t be empty');
        }
        
        $criteria = [
            ['name' => $cert->name],
            ['envId' => $cert->envId]
        ];

        if ($id) {
            $criteria[] = ['id' => ['$ne' => $id]];
        }
        
        if (Entity\SslCertificate::findOne($criteria)) {
            $this->request->addValidationErrors('name', 'Name must be unique.');
        }

        if (!empty($_FILES['privateKey']['tmp_name'])) {
            $cert->privateKey = file_get_contents($_FILES['privateKey']['tmp_name']);
        } elseif ($privateKeyClear) {
            $cert->privateKey = null;
        }

        if ($privateKeyPassword) {
            if (!$cert->privateKey) {
                $this->request->addValidationErrors('privateKeyPassword', 'Private key password requires private key');
            } else {
                if ($privateKeyPassword != '******') {
                    $cert->privateKeyPassword = $privateKeyPassword;
                }
            }
        } else {
            $cert->privateKeyPassword = null;
        }

        if (!empty($_FILES['certificate']['tmp_name'])) {
            $cr = file_get_contents($_FILES['certificate']['tmp_name']);
            if (!openssl_x509_parse($cr, false)) {
                $this->request->addValidationErrors('certificate', 'Not valid certificate');
            } else {
                $cert->certificate = $cr;
            }
        } elseif ($certificateClear) {
            $cert->certificate = null;
        }

        if (!empty($_FILES['caBundle']['tmp_name'])) {
            $bn = file_get_contents($_FILES['caBundle']['tmp_name']);
            if (!openssl_x509_parse($bn, false)) {
                $this->request->addValidationErrors('caBundle', 'Not valid certificate chain');
            } else {
                $cert->caBundle = $bn;
            }
        } elseif ($caBundleClear) {
            $cert->caBundle = null;
        }

        if (!$cert->certificate) {
            $this->request->addValidationErrors('certificate', 'Certificate cannot be empty.');
        }

        if (!$cert->privateKey) {
            $this->request->addValidationErrors('privateKey', 'Private key cannot be empty.');
        }

        if (!$this->request->isValid()) {
            $this->response->data($this->request->getValidationErrors());
            $this->response->failure();
        } else {
            $cert->save();

            if ($id) {
                try {
                    // Update existing servers
                    $res = $this->db->Execute("SELECT farm_roleid FROM farm_role_settings WHERE name='nginx.proxies' AND value LIKE '%ssl_certificate_id\":\"{$cert->id}%'");
                    while ($f = $res->FetchRow()) {
                        $dbFarmRole = DBFarmRole::LoadByID($f['farm_roleid']);
                        $servers = $dbFarmRole->GetServersByFilter(['status' => SERVER_STATUS::RUNNING]);
                        foreach ($servers as $server) {
                            $msg = new Scalr_Messaging_Msg_SSLCertificateUpdate();
                            $msg->id = $cert->id;
                            $msg->certificate = $cert->certificate;
                            $msg->cacertificate = $cert->caBundle;
                            $msg->privateKey = $cert->privateKey;

                            $server->SendMessage($msg, false, true);
                        }
                    }
                    // Update apache server
                } catch (Exception $e) {
                }
            }

            $this->response->success('Certificate was successfully saved');

            if ($flagNew) {
                $this->response->data(['cert' => $cert->getInfo()]);
            }
        }
    }

    /**
     * @throws Scalr_Exception_Core
     */
    public function xListCertificatesAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);

        $criteria = [['envId' => $this->getEnvironmentId()]];
        $certs = Entity\SslCertificate::find($criteria);
        $certInfos = [];

        /* @var \Scalr\Model\Entity\SslCertificate $cert */
        foreach ($certs as $cert) {
            $certInfos[] = $cert->getInfo();
        }

        $this->response->data(['data' => $certInfos]);
    }
}
