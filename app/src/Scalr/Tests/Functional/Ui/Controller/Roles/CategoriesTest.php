<?php

namespace Scalr\Tests\Functional\Ui\Controller\Roles;

use Scalr\Tests\WebTestCase;
use Scalr\Model\Entity\RoleCategory;

/**
 * Functional test for the Scalr_UI_Controller_Roles_Categories.
 */
class CategoriesTest extends WebTestCase
{
    private $testCategoryName;
    private $invalidCategoryName;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->testCategoryName = 'testrolecategory';
        $this->invalidCategoryName = 'Invalid-category!';
    }

    private function checkCategoriesList($response)
    {
        $this->assertResponseDataHasKeys(
            [
                'id',
                'name'  => $this->matchesRegularExpression('/^' . RoleCategory::NAME_REGEXP . '$/')
            ],
            $response,
            true,
            'data'
        );

        foreach ($response['data'] as $category) {
            if ($category['name'] == $this->testCategoryName || $category['name'] == $this->invalidCategoryName) {
                $this->request('/roles/categories/xGroupActionHandler', ['ids' => json_encode([ $category['id']])], 'POST');
            }
        }
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Roles_Categories::xGetListAction
     */
    public function testXListAction()
    {
        $response = $this->request('/roles/categories/xList');
        $this->checkCategoriesList($response);
    }

    /**
     * @test
     * @covers Scalr_UI_Controller_Roles_Categories::xSaveAction
     * @covers Scalr_UI_Controller_Roles_Categories::xGroupActionHandler
     */
    public function testXSaveAction()
    {
        //in first case try to create category with invalid name and receive error
        $response = $this->request(
            '/roles/categories/xSave',
            [
                'name'  => $this->invalidCategoryName
            ],
            'POST'
        );

        $this->assertArrayHasKey('errors', $response);

        if ($response['success'] && isset($response['category'])) {
            $this->request('/roles/categories/xGroupActionHandler', ['ids' => json_encode([$response['category']['id']])], 'POST');
            $this->assertTrue(false, 'Role Category with invalid name creates success without validation errors.');
        }

        //then create valid category
        $response = $this->request(
            '/roles/categories/xSave',
            [
                'name'  => $this->testCategoryName
            ],
            'POST'
        );

        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('successMessage', $response);
        $this->assertArrayHasKey('category', $response);

        $id = $response['category']['id'];

        //then try to create category with same name and receive error
        $response = $this->request(
            '/roles/categories/xSave',
            [
                'name'  => $this->testCategoryName
            ],
            'POST'
        );

        $this->assertArrayHasKey('errors', $response);

        if ($response['success'] && isset($response['category'])) {
            $this->request('/roles/categories/xGroupActionHandler', ['ids' => json_encode([$response['category']['id']])], 'POST');
            $this->assertTrue(false, 'Role Category with duplicate name creates success without validation errors.');
        }

        //and finally remove this category
        $response = $this->request('/roles/categories/xGroupActionHandler', ['ids' => json_encode([$id])], 'POST');
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('successMessage', $response);
    }
}
