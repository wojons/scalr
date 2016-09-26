<?php

namespace Scalr\Tests\Functional\Ui\Controller\Services\Ssl;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Services_Ssl_Certificates.
 *
 * @author Roman Kolodnitskyi <r.kolodnitskyi@scalr.com>
 * @since  18.06.2015
 */
class CertificatesTest extends WebTestCase
{
    private $testCertName;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->testCertName = 'tescert' . $this->getInstallationId();
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Services_Ssl_Certificates::xListCertificatesAction
     */
    public function testXListCertificatesAction()
    {
        $response = $this->request('/services/ssl/certificates/xListCertificates');

        $this->assertResponseDataHasKeys(
            [
                'id',
                'name'  => $this->logicalNot($this->isEmpty()),
                'privateKey',
                'privateKeyPassword'
            ],
            $response,
            true
        );

        foreach ($response['data'] as $cert) {
            if ($cert['name'] == $this->testCertName) {
                $this->request('/services/ssl/certificates/xRemove', ['certs' => json_encode([$cert['id']])]);
            }
        }

    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Services_Ssl_Certificates::xSaveAction
     * @depends testXListCertificatesAction
     */
    public function testXSave()
    {
        //try create invalid cert (without uploaded files) and receive errors
        $response = $this->request(
            '/services/ssl/certificates/xSave',
            [
                'name'  => $this->testCertName,
                'caBundleClear' => 1,
                'privateKeyPassword'    => 'password',
            ],
            'POST',
            [],
            [
                'privateKey'    => '/home/koloda/tmp/ssl_changes.txt'
            ]
        );

        $this->assertArrayHasKey('errors', $response);
    }
}
