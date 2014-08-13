[subject]A new cloud platform has been added to your Scalr install. Please check or set the pricing list for this cloud[/subject]

User <?= $userEmail ?> has configured Scalr to utilize resources in <?= $cloudName ?>.
<?php if ($isPublicCloud): ?>
By default Scalr will use the official pricing list to track and calculate spend for this cloud.
However, you can customize pricing by modifying the Pricing List in Scalr Cost Analytics.
Please visit this page to confirm pricing.
<?php else: ?>
Scalr cannot record and calculate spend for this cloud until the Pricing List has been set in Scalr Cost Analytics.
Please visit this page as soon as possible, so that Scalr can begin tracking cloud spend.
<?php endif; ?>
<?= $linkToPricing ?>


Regards,
The Scalr Team