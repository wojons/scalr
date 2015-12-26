<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity;
use \Scalr\Service\Aws;

class Scalr_UI_Controller_Tools_Aws_Iam_ServerCertificates extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_IAM);
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_IAM, Acl::PERM_AWS_IAM_MANAGE);

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
        $this->request->restrictAccess(Acl::RESOURCE_AWS_IAM, Acl::PERM_AWS_IAM_MANAGE);

        $this->request->defineParams(array(
            'name' => array('type' => 'string')
        ));

        $this->environment->aws(Aws::REGION_US_EAST_1)->iam->serverCertificate->upload(
            @file_get_contents($_FILES['certificate']['tmp_name']),
            @file_get_contents($_FILES['privateKey']['tmp_name']),
            $this->getParam('name'),
            (!empty($_FILES['certificateChain']['tmp_name'])) ? @file_get_contents($_FILES['certificateChain']['tmp_name']) : null
        );

        $this->response->success('Certificate successfully uploaded');
    }

    public function xListCertificatesAction()
    {
        $certificatesList = $this->environment->aws(Aws::REGION_US_EAST_1)->iam->serverCertificate->describe();
        $rows = array();
        foreach ($certificatesList as $item) {
            $rows[] = array(
                'id'            => $item->serverCertificateId,
                'name'          => $item->serverCertificateName,
                'path'          => $item->path,
                'arn'           => $item->arn,
                'upload_date'   => $item->uploadDate->format('Y-m-d\TH:i:s\Z')
            );
        }
        $this->response->data($this->buildResponseFromData($rows));
    }
}
