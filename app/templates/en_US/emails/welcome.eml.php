[subject]<?= $creatorName ?> has created a Scalr account for you.[/subject]

Welcome to Scalr!
=================

Hi <?= $clientFirstname ?>!

<?= $creatorName ?> has created a Scalr account for you.

Scalr is used by <?= $creatorName ?> and your colleagues to efficiently
manage cloud infrastructure.


Log in to Scalr
===============

You can access Scalr by pointing your browser to: <?= $siteUrl ?>

Your username is your email address: <?= $email ?>

Your password is: <?= $password ?>


Getting your questions answered
===============================

Scalr Wiki
----------

Regardless of whether you're a Scalr veteran or a new user, the Scalr
Wiki is the most complete and up-to-date resource on Scalr.

<?= $wikiUrl ?>

<?php if ($isUrl) : ?>
Scalr Discussion Group
----------------------

You can find and engage with fellow Scalr users at the Scalr Google
Group.

<?= $supportUrl ?>


Support Forum
-------------

If you are using Hosted Scalr (that is, you access Scalr via
http://my.scalr.com), you can get in touch with the Scalr team via our
support portal:

http://support.scalr.net
<?php endif; ?>


We hope you'll enjoy using Scalr as much as we enjoy building it,

The Scalr Team