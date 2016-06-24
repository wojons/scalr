<?php

use Scalr\Modules\Platforms\Azure\AzurePlatformModule;
use Scalr\Stats\CostAnalytics\Entity\ReportPayloadEntity;
use Scalr\Util\Api\Describer;
use Scalr\Util\Api\Mutators\AllowedCloudsSubstractor;
use Scalr\Util\Api\Mutators\AnalyticsSubtractor;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

/**
 * Class Scalr_UI_Controller_Public
 *
 * Special guest controller for public links.
 * CSRF protection MUST be implemented itself in the action.
 */
class Scalr_UI_Controller_Public extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return true;
    }

    /**
     * View report action
     *
     * @param string $uuid          Report uuid
     * @param string $secretHash    Report secret hash
     * @throws Scalr_UI_Exception_NotFound
     */
    public function reportAction($uuid, $secretHash)
    {
        $data = ReportPayloadEntity::findOne([['uuid' => $uuid], ['secret' => hex2bin($secretHash)]]);

        if (empty($data) || !property_exists($data, 'payload')) {
            throw new Scalr_UI_Exception_NotFound();
        }

        $this->response->page('ui/public/report.js', json_decode($data->payload, true), [], ['ui/analytics/analytics.css', 'ui/admin/analytics/admin.css', 'ui/public/report.css']);
    }

    /**
     * Describes API specifications
     *
     * @param   string  $version    API version
     * @param   string  $service    API service
     */
    public function describeApiSpecAction($version, $service) {
        $describer = new Describer($version, $service, \Scalr::getContainer()->config());

        $describer->mutate(new AnalyticsSubtractor())
                  ->mutate(new AllowedCloudsSubstractor())
                  ->describe($this->response);
    }

    /**
     * Decode params from href depending on type of OS
     *
     * Possible formats (branch is optioanl):
     * for windows: /{repo}/[{branch}/]install_scalarizr.ps1
     * for linux: /{repo}/{platform}/[{branch}]/install_scalarizr.sh
     *
     * @param   array   $params
     * @param   bool    $isLinux
     * @return  array
     */
    protected function parseInstallScriptArgs($params, $isLinux = true)
    {
        $result = [
            'repo' => '',
            'platform' => '',
            'repoUrls' => []
        ];

        $repo = array_shift($params);
        $platform = $isLinux ? array_shift($params) : '';
        array_pop($params); // pop script name from the end of href
        $branch = implode('/', $params);

        $repos = $this->getContainer()->config('scalr.scalarizr_update.' . ($branch ? 'devel_repos' : 'repos'));

        if (in_array($repo, array_keys($repos))) {
            if ($branch) {
                // strip illegal chars
                $branch = preg_replace('/[^A-Za-z\/0-9_.-]/', '', $branch);
                if ($repo !== 'snapshot') {
                    // for snapshot don't cut "." (dot)
                    $branch = str_replace(array(".", '/'), array('', '-'), $branch);
                }
            }

            $repoUrls = $repos[$repo];

            if ($branch) {
                foreach ($repoUrls as $key => &$url) {
                    $url = sprintf($url, $branch);
                }
            }

            if ($isLinux) {
                if (in_array($platform, $this->getContainer()->config('scalr.allowed_clouds'))) {
                    if (PlatformFactory::isOpenstack($platform)) {
                        $platform = SERVER_PLATFORMS::OPENSTACK;
                    } else if (PlatformFactory::isCloudstack($platform)) {
                        $platform = SERVER_PLATFORMS::CLOUDSTACK;
                    }

                    $result['platform'] = $platform;
                    $result['repo'] = $repo;
                    $result['repoUrls'] = $repoUrls;
                }
            } else {
                $result['repo'] = $repo;
                $result['repoUrls'] = $repoUrls;
            }
        }

        return $result;
    }

    public function windowsAction()
    {
        $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');
        $result = $this->parseInstallScriptArgs($this->unusedPathChunks, false);

        $translate = [
            '{{winRepoUrl}}' => $result['repoUrls']['win_repo_url'],
            '{{jsonParserUrl}}' => $baseUrl . '/storage/Newtonsoft.Json.dll'
        ];

        $body = file_get_contents(APPPATH . '/templates/services/install_scalarizr/windows.ps1');
        $body = str_replace(array_keys($translate), array_values($translate), $body);

        $this->response->setResponse($body);
        $this->response->setHeader('Content-Type', 'text/text', true);
        $this->response->setHeader('Content-Disposition', 'inline; filename="install_scalarizr.ps1', true);
    }

    public function linuxAction()
    {
        $result = $this->parseInstallScriptArgs($this->unusedPathChunks, true);

        $translate = [
            '{{platform}}' => $result['platform'],
            '{{badPlatform}}' => $result['platform'] ? '' : 'True',
            '{{repo}}' => $result['repo'],
            '{{badRepo}}' => $result['repo'] ? '' : 'True',
            '{{rpmRepoUrl}}' => $result['repoUrls']['rpm_repo_url'],
            '{{debRepoUrl}}' => $result['repoUrls']['deb_repo_url']
        ];

        $body = file_get_contents(APPPATH . '/templates/services/install_scalarizr/linux.sh');
        $body = str_replace(array_keys($translate), array_values($translate), $body);

        $this->response->setResponse($body);
        $this->response->setHeader('Content-Type', 'text/text', true);
        $this->response->setHeader('Content-Disposition', 'inline; filename="install_scalarizr.sh', true);
    }

    /**
     * xAzureTokenAction. Redirects to azure environment configuration
     *
     * @param string $code                  optional
     * @param string $error                 optional
     * @param string $error_description     optional
     */
    public function xAzureTokenAction($code = null, $error = null, $error_description = null)
    {
        $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');

        if (!$code) {
            $error_description = $error_description ?: 'Unknown error';
            $this->response->setRedirect("{$baseUrl}/#/public/azure?error={$error_description}");
            return;
        }

        if ($this->user) {
            $envs = $this->user->getEnvironments();
            $environment = null;

            foreach ($envs as $env) {
                $e = \Scalr_Environment::init()->loadById($env['id']);
                $ccProps = $e->keychain(SERVER_PLATFORMS::AZURE)->properties;

                $authCode = $ccProps[Entity\CloudCredentialsProperty::AZURE_AUTH_CODE];
                $step = $ccProps[Entity\CloudCredentialsProperty::AZURE_AUTH_STEP];

                if (empty($authCode) && $step == 1) {
                    $environment = $e;
                    break;
                }
            }

            if (isset($environment)) {
                $ccProps->saveSettings([
                    Entity\CloudCredentialsProperty::AZURE_ACCESS_TOKEN => false,
                    Entity\CloudCredentialsProperty::AZURE_ACCESS_TOKEN_EXPIRE => false,
                ]);

                if (!empty($code)) {
                    $azure = $environment->azure();
                    $ccProps->saveSettings([Entity\CloudCredentialsProperty::AZURE_AUTH_CODE => $code]);

                    try {
                        $azure->getAccessTokenByAuthCode($code);
                    } catch (Exception $e) {
                        $cloudCredentials = $environment->keychain(SERVER_PLATFORMS::AZURE);
                        $tenantName = $cloudCredentials->properties[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME];
                        $cloudCredentials->delete();
                        $message = "Failed to get access to tenant {$tenantName}";
                        $this->response->setRedirect("{$baseUrl}/#/public/azure?error={$message}");
                        return;
                    }

                    $ccProps->saveSettings([Entity\CloudCredentialsProperty::AZURE_AUTH_STEP => 2]);

                    $this->response->setRedirect($baseUrl . '#/account/environments/' . $environment->id . '/clouds?platform=' . SERVER_PLATFORMS::AZURE);
                    return;
                }
            } else {
                $this->response->setRedirect("{$baseUrl}/#/public/azure?error=Unknown environment");
                return;
            }

            $this->response->setRedirect("{$baseUrl}/#/public/azure?error=Unknown error");
        } else {
            $this->response->setRedirect("{$baseUrl}/#/public/azure?code={$code}");
        }
    }

    /**
     * SAML 2.0 Auth test endpoint
     *
     * FIXME remove termporary saml2 acs endpoint
     */
    public function samlAction()
    {
        /* FIXME We'll enable \Scalr::config('scalr.auth_mode') !== 'saml' when it is production ready
        if (\Scalr::config('scalr.auth_mode') !== 'saml') {
            $this->response->setHttpResponseCode(404);
            return;
        }
        */

        @session_start();

        //This is necessary for test container as OneLogin_Saml2_Utils::getSelfHost() method relies on HTTP_HOST / SERVER_PORT
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];

        $auth = $this->getContainer()->saml;

        $body = '';

        if (isset($_GET['sso'])) {
            $auth->login();
        } else if (isset($_GET['slo'])) {
            $auth->logout(
                null,
                [],
                (isset($_SESSION['samlNameId']) ? $_SESSION['samlNameId'] : null),
                (isset($_SESSION['samlSessionIndex']) ? $_SESSION['samlSessionIndex'] : null)
            );
        } else if (isset($_GET['acs'])) {
            $auth->processResponse();

            $errors = $auth->getErrors();

            if (!empty($errors)) {
                $body .= '<p>' . implode(', ', $errors) . '</p>';
            }

            if (!$auth->isAuthenticated()) {
                $body .= "<p>Not authenticated</p>";
                $this->response->body = $body;
                return;
            }

            $_SESSION['samlUserdata'] = $auth->getAttributes();
            $_SESSION['samlNameId'] = $auth->getNameId();
            $_SESSION['samlSessionIndex'] = $auth->getSessionIndex();

            if (isset($_POST['RelayState']) && OneLogin_Saml2_Utils::getSelfURL() != $_POST['RelayState']) {
                $auth->redirectTo($_POST['RelayState']);
                return;
            }
        } else if (isset($_GET['sls'])) {
            $auth->processSLO();

            $errors = $auth->getErrors();

            if (empty($errors)) {
                $body .= '<p>Sucessfully logged out</p>';
            } else {
                $body .= '<p>' . implode(', ', $errors) . '</p>';
            }
        } else if (isset($_GET['metadata'])) {
            $settings = $auth->getSettings();

            // Now we only validate SP settings
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);

            if (empty($errors)) {
                $this->response->setHeader('Content-Type', 'text/xml');
                $this->response->body = $metadata;
                return;
            } else {
                throw new OneLogin_Saml2_Error(
                    'Invalid SP metadata: ' . implode(', ', $errors),
                    OneLogin_Saml2_Error::METADATA_SP_INVALID
                );
            }
        }

        if (isset($_SESSION['samlUserdata'])) {
            if (!empty($_SESSION['samlUserdata'])) {
                $attributes = $_SESSION['samlUserdata'];
                $body .= '<style type="text/css">'
                      . '  th, td { border: 1px solid black; padding: 2px 4px; }'
                      . '  ul { padding: 1px 2px; margin: 0px; }'
                      . '  ul li { list-style-type: none; }'
                      . '</style>';
                $body .= 'Scalr requires following attributes:<br>';
                $body .= '<table><thead><th>Name</th><th>Values</th></thead><tbody>';

                $body .= '<tr><td>Email</td><td><ul><li>' . htmlentities($_SESSION['samlNameId']) . '</li></ul></td></tr>';
                $body .= '<tr><td>Groups</td><td><ul><li>' . (!empty($_SESSION['samlUserdata']['Groups']) ? join(', ', array_map('htmlentities', (array)$_SESSION['samlUserdata']['Groups'])) : '<b color="red">not provided</b>') . '</li></ul></td></tr>';
                $body .= '</tbody></table>';

                $body .= "<br><br>";
                $body .= 'Your Identity Provider responded with attributes:<br>';
                $body .= '<table><thead><th>Name</th><th>Values</th></thead><tbody>';
                $body .= '<tr><td>Email</td><td><ul><li>' . htmlentities($_SESSION['samlNameId']) . '</li></ul></td></tr>';
                foreach ($attributes as $attributeName => $attributeValues) {
                    $body .= '<tr><td>' . htmlentities($attributeName) . '</td><td><ul>';
                    foreach ($attributeValues as $attributeValue) {
                        $body .= '<li>' . htmlentities($attributeValue) . '</li>';
                    }
                    $body .= '</ul></td></tr>';
                }
                $body .= '</tbody></table>';
            } else {
                $body .= "<p>You don't have any attribute</p>";
            }

            $body .= '<p><a href="?slo">single logout</a></p>';
        } else {
            $body .= '<p><a href="?sso">single sign on</a></p>';
        }

        $this->response->body = $body;
    }

    public function azureAction()
    {
        $this->response->page('ui/public/azure.js');
    }
}
