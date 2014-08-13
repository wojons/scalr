<?php
require __DIR__ . '/src/prepend.inc.php';

use Scalr\Model\Entity\WebhookHistory;
use Scalr\Model\Entity\WebhookEndpoint;
use Scalr\Model\Entity\WebhookConfig;

try {
    $webhookId = $_SERVER['HTTP_X_SCALR_WEBHOOK_ID'];
    $signature = $_SERVER['HTTP_X_SIGNATURE'];
    $date = $_SERVER['HTTP_DATE'];

    $history = WebhookHistory::findPk($webhookId);
    if (!$history)
        throw new Exception("Bad request (1)");

    $endpoint = WebhookEndpoint::findPk($history->endpointId);
    $webhook = WebhookConfig::findPk($history->webhookId);

    $canonicalString = $history->payload . $date;
    $validSignature = hash_hmac('SHA1', $canonicalString, $endpoint->securityKey);

    if ($signature != $validSignature)
        throw new Exception("Bad request (2)");

    $payload = json_decode($history->payload);

    $text = "
========= Event ========\n
EVENT_NAME: {$payload->eventName}
EVENT_ID: {$payload->eventId}

========= Farm ========\n
FARM_ID: {$payload->data->SCALR_EVENT_FARM_ID}
FARM_NAME: {$payload->data->SCALR_EVENT_FARM_NAME}

========= Role ========\n
FARM_ROLE_ID: {$payload->data->SCALR_EVENT_FARM_ROLE_ID}
ROLE_NAME: {$payload->data->SCALR_FARM_ROLE_ALIAS}

========= Server =========\n
SERVER_ID: {$payload->data->SCALR_EVENT_SERVER_ID}
CLOUD_SERVER_ID: {$payload->data->SCALR_EVENT_CLOUD_SERVER_ID}
PUBLIC_IP: {$payload->data->SCALR_EVENT_INTERNAL_IP}
PRIVATE_IP: {$payload->data->SCALR_EVENT_EXTERNAL_IP}
CLOUD_LOCATION: {$payload->data->SCALR_EVENT_CLOUD_LOCATION}
CLOUD_LOCATION_ZONE: {$payload->data->SCALR_EVENT_CLOUD_LOCATION_ZONE}
";
    $subject = "{$payload->data->SCALR_EVENT_FARM_NAME}: {$payload->eventName} on {$payload->data->SCALR_EVENT_SERVER_ID} ({$payload->data->SCALR_EVENT_EXTERNAL_IP})";

    $mailer = Scalr::getContainer()->mailer
            ->setFrom('no-reply@scalr.com', 'Scalr')
            ->setMessage($text)
            ->setSubject($subject);

    $emails = explode(",", $webhook->postData);
    foreach ($emails as $email) {
        if ($email)
            $mailer->send($email);
    }

} catch (Exception $e) {
    header("HTTP/1.0 500 Error");
    die($e->getMessage());
}
