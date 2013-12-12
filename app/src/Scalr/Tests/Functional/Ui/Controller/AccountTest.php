<?php

namespace Scalr\Tests\Functional\Ui\Controller;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Account class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    12.08.2013
 */
class AccountTest extends WebTestCase
{

    /**
     * @test
     */
    public function testGetAccountTeamsList()
    {
        $uri = '/account/xGetData';
        $content = $this->request($uri, array(
            'stores' => '["account.teams"',
        ));
    }
}