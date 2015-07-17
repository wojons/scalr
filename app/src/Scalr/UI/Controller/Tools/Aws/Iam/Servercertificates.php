<?php

use Scalr\Acl\Acl;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;

class Scalr_UI_Controller_Tools_Aws_Iam_ServerCertificates extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_IAM);
    }

    public function createAction()
    {
        $this->response->page('ui/tools/aws/iam/serverCertificates/create.js');
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/iam/serverCertificates/view.js');
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'name' => array('type' => 'string')
        ));

        //FIXME This must be refactored to new Scalr\Service\Aws\Iam class
        $iamClient = Scalr_Service_Cloud_Aws::newIam(
            $this->getEnvironment()->getPlatformConfigValue(Ec2PlatformModule::ACCESS_KEY),
            $this->getEnvironment()->getPlatformConfigValue(Ec2PlatformModule::SECRET_KEY)
        );

        $iamClient->uploadServerCertificate(
            @file_get_contents($_FILES['certificate']['tmp_name']),
            @file_get_contents($_FILES['privateKey']['tmp_name']),
            $this->getParam('name'),
            (!empty($_FILES['certificateChain']['tmp_name'])) ? @file_get_contents($_FILES['certificateChain']['tmp_name']) : null
        );

        $this->response->success('Certificate successfully uploaded');
    }

    public function xListCertificatesAction()
    {
        //FIXME This needs to be refactored. We have to use new Scalr\Service\Aws\Iam library.
        $iamClient = Scalr_Service_Cloud_Aws::newIam(
            $this->getEnvironment()->getPlatformConfigValue(Ec2PlatformModule::ACCESS_KEY),
            $this->getEnvironment()->getPlatformConfigValue(Ec2PlatformModule::SECRET_KEY)
        );

        $rowz = array();
        $certs = $iamClient->listServerCertificates();

        foreach ($certs->ServerCertificateMetadataList as $item) {
            $rowz[] = array(
                'id'			=> $item->ServerCertificateId,
                'name'			=> $item->ServerCertificateName,
                'path'			=> $item->Path,
                'arn'			=> $item->Arn,
                'upload_date'	=> $item->UploadDate
            );
        }

        $this->response->data(array('data' => $rowz));
    }
}
