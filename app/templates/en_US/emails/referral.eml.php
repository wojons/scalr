[subject]<?= $creatorName ?> has invited you to use Scalr.[/subject]

Hi <?= $clientFirstname ?>!
<?= $creatorName ?> has invited you to use Scalr.
<?= $creatorName ?> and your colleagues are using Scalr to efficiently manage cloud infrastructure and would like you to join them.

Getting Started:
===============

Log in to Scalr:

You can access Scalr by pointing your browser to: <?= $siteUrl ?>

Your username is your email address: <?= $email ?>

Your password is: <?= $password ?>


Resources and Support
=====================
We suggest exploring and bookmarking the following:

<?php if ($isUrl): ?>
<?= $supportUrl ?> for any support needs
<?php elseif (preg_match('/\.scalr\.com$/', $siteUrl)): ?>
http://support.scalr.com for any support needs
<?php else: ?>
You can find and engage with fellow Scalr users at the Scalr Google
Group. https://groups.google.com/forum/#!forum/scalr-discuss
<?php endif; ?>

Scalr documentation <?= $wikiUrl ?>


Scalr Youtube video tutorials https://www.youtube.com/user/scalrvideos

Thanks for selecting Scalr for your cloud management needs. We’re looking forward to working together.
Should you have any other questions, feel free to reach us at sales@scalr.com.
We’re here to help.

Cheers,
Team Scalr