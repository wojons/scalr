<?php

namespace Scalr\Tests\Functional\Ui\Controller;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Roles class.
 *
 * @author   Roman Kolodnitskyi   <r.kolodnitskyi@scalr.com>
 * @since    24.06.2015
 */
class RolesTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * @test
     * @covers \Scalr_UI_Controller_Roles::xListRolesAction()
     */
    public function testXListRoles()
    {
        $uri = 'roles/xListRoles';
        $content = $this->request($uri);

        $this->assertResponseDataHasKeys(
            [
                'name'  => $this->logicalNot($this->isEmpty()),
                'behaviors' => $this->isType('array'),
                'id'    => $this->logicalNot($this->isEmpty()),
                'accountId',
                'envId',
                'status'    => $this->matchesRegularExpression('/^(Not used|In use)$/'),
                'scope'    => $this->matchesRegularExpression('/^(scalr|environment)$/'),
                'os'    => $this->logicalNot($this->isEmpty()),
                'osId'  => $this->logicalNot($this->isEmpty()),
                'osFamily'  => $this->logicalNot($this->isEmpty()),
                'dtAdded',
                'dtLastUsed',
                'platforms' => $this->isType('array'),
                'client_name'
            ],
            $content,
            true
        );

        $content = $this->request($uri, ['osFamily' => 'centos', 'query' => 'ap']);
        if (count($content['data'])) {
            $this->assertResponseDataHasKeys(
                [
                    'name'  => $this->matchesRegularExpression('/(ap)/'),
                    'osFamily'  => $this->equalTo('centos')
                ],
                $content,
                true
            );
        }
    }

    /**
     * @test
     * @covers \Scalr_UI_Controller_Roles::xGetListAction()
     */
    public function testXGetListAction()
    {
        $uri = 'roles/xGetList';
        $content = $this->request($uri);

        if ($content) {
            $this->assertResponseDataHasKeys(
                [
                    'role_id'   => $this->logicalNot($this->isEmpty()),
                    'name'  => $this->logicalNot($this->isEmpty()),
                    'behaviors',
                    'origin'    => $this->matchesRegularExpression('/^(SHARED|CUSTOM)$/'),
                    'cat_name',
                    'cat_id',
                    'osId'  => $this->logicalNot($this->isEmpty()),
                    'images',
                    'variables',
                    'description'
                ],
                $content,
                true,
                'roles'
            );

            $images = $content['roles'][0]['images'];

            if (is_array($images)) {
                $arr = array_values(array_values($images)[0])[0];

                $this->assertArrayHasKey('id', $arr);
                $this->assertArrayHasKey('architecture', $arr);
                $this->assertArrayHasKey('type', $arr);
            }
        }
    }
}
