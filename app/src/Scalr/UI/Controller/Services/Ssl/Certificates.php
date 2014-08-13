<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Services_Ssl_Certificates extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'certId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);
        $this->response->page('ui/services/ssl/certificates/view.js');
    }

    public function getList()
    {
        return $this->db->GetAll('SELECT id, name FROM services_ssl_certs WHERE env_id = ?', array($this->getEnvironmentId()));
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);
        $this->request->defineParams(array(
            'certs' => array('type' => 'json')
        ));

        foreach ($this->getParam('certs') as $certId) {
            $cert = new Scalr_Service_Ssl_Certificate();
            $cert->loadById($certId);
            $this->user->getPermissions()->validate($cert);
            $cert->delete();
        }

        $this->response->success();
    }

    public function editAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);

        $cert = new Scalr_Service_Ssl_Certificate();
        $cert->loadById($this->getParam('certId'));
        $this->user->getPermissions()->validate($cert);

        $this->response->page('ui/services/ssl/certificates/create.js', array(
            'cert' => array(
                'id' => $cert->id,
                'name' => $cert->name,
                'privateKey' => $cert->privateKey ? 'Private key uploaded' : '',
                'privateKeyPassword' => $cert->privateKeyPassword ? '******' : '',
                'certificate' => $cert->certificate ? $cert->getCertificateName() : '',
                'caBundle' => $cert->caBundle ? $cert->getCaBundleName() : ''
            )
        ));
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);
        $this->response->page('ui/services/ssl/certificates/create.js');
    }

    public function xSaveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_SERVICES_SSL);

        $cert = new Scalr_Service_Ssl_Certificate();
        $flagNew = false;
        if ($this->getParam('id')) {
            $cert->loadById($this->getParam('id'));
            $this->user->getPermissions()->validate($cert);
        } else {
            $cert->envId = $this->getEnvironmentId();
            $flagNew = true;
        }

        if (! $this->getParam('name'))
            throw new Scalr_Exception_Core('Name can\'t be empty');

        $cert->name = $this->getParam('name');

        if (!empty($_FILES['privateKey']['tmp_name'])) {
            $cert->privateKey = file_get_contents($_FILES['privateKey']['tmp_name']);
        } else if ($this->getParam('privateKeyClear')) {
            $cert->privateKey = '';
        }

        if ($this->getParam('privateKeyPassword')) {
            if (! $cert->privateKey) {
                $this->request->addValidationErrors('privateKeyPassword', 'Private key password requires private key');
            } else {
                if ($this->getParam('privateKeyPassword') != '******')
                    $cert->privateKeyPassword = $this->getParam('privateKeyPassword');
            }
        }

        if (!empty($_FILES['certificate']['tmp_name'])) {
            $cr = file_get_contents($_FILES['certificate']['tmp_name']);
            if (! openssl_x509_parse($cr, false)) {
                $this->request->addValidationErrors('certificate', 'Not valid certificate');
            } else {
                $cert->certificate = $cr;
            }
        } else if ($this->getParam('certificateClear')) {
            $cert->certificate = '';
        }

        if (!empty($_FILES['caBundle']['tmp_name'])) {
            $bn = file_get_contents($_FILES['caBundle']['tmp_name']);
            if (! openssl_x509_parse($bn, false)) {
                $this->request->addValidationErrors('caBundle', 'Not valid certificate chain');
            } else {
                $cert->caBundle = $bn;
            }
        } else if ($this->getParam('caBundleClear')) {
            $cert->caBundle = '';
        }

        if (! $this->request->isValid()) {
            $this->response->data($this->request->getValidationErrors());
            $this->response->failure();
        } else {
            $cert->save();
            $this->response->success('Certificate was successfully saved');
            if ($flagNew) {
                $this->response->data(array('cert' => array(
                    'id' => (string)$cert->id,
                    'name' => $cert->name
                )));
            }
        }
    }

    public function xListCertificatesAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json')
        ));

        $sql = "SELECT id, name, ssl_pkey AS privateKey, ssl_pkey_password AS privateKeyPassword, ssl_cert AS certificate, ssl_cabundle AS caBundle FROM `services_ssl_certs` WHERE env_id = ? AND :FILTER:";
        $response = $this->buildResponseFromSql2($sql, array('id', 'name'), array('id', 'name'), array($this->getEnvironmentId()));

        foreach ($response['data'] as &$row) {
            $row['privateKey'] = !!$row['privateKey'];
            $row['privateKeyPassword'] = !!$row['privateKeyPassword'];

            if ($row['certificate']) {
                $info = openssl_x509_parse($row['certificate'], false);
                $row['certificate'] = $info['name'] ? $info['name'] : 'uploaded';
            }

            if ($row['caBundle']) {
                $info = openssl_x509_parse($row['caBundle'], false);
                $row['caBundle'] = $info['name'] ? $info['name'] : 'uploaded';
            }
        }

        $this->response->data($response);
    }
}
