[subject]<?= $cloudName ?> has been added to your Scalr install[/subject]

User <?= $userEmail ?> has configured Scalr to utilize resources in <?= $cloudName ?>.
<?php if ($isEc2): ?>
Scalr Cost Analytics will default to official pricing to track spend for this cloud.
You can customize pricing by visiting the Pricing List.
<?= $linkToPricing ?>
<?php elseif ($isSupported): ?>
Scalr Cost Analytics cannot track spend for this cloud until the Pricing List has been configured.
Please visit this page and set pricing immediately.
<?= $linkToPricing ?>
<?php else: ?>
Scalr Cost Analytics cannot track spend for this cloud. Please visit the Pricing List to confirm this alert.
<?php endif; ?>


Regards,
The Scalr Team