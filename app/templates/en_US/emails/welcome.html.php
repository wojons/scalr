<html style="font-family:georgia">

<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <title>Thank you for selecting Scalr <?= $firstName ?> and welcome</title>
   <!-- [subject]Your Scalr Account[/subject] -->
</head>

<body>

	<p>Thank you for selecting Scalr <?= $firstName ?> and welcome.
	   We’re excited to be working with you, and want to make your onboarding process delightful.</p>

	<p style="color:#C30000"><b>Password</b></p>
	<p>For your security, we automatically generated a strong password (don't forget to change it!): <b><?= $password ?></b></p>

	<p style="color:#C30000"><b>First steps</b></p>
	<p>
		<ol>
			<li><a href="<?= $siteUrl ?>">Login</a> to your Scalr account using your email address and the password above.</li>
			<li>Add your cloud credentials to link Scalr with your cloud provider.</li>
			<li><a href="<?= $siteUrl ?>/#/farms/build">Create</a> your first farm and begin to set up your infrastructure!</li>
		</ol>
	</p>

	<p style="color:#C30000"><b>Resources and support</b></p>
    <p>We suggest exploring and bookmarking the following:
        <ul>
<?php if ($isUrl): ?>
        	<li><a href="<?= supportUrl ?>">Support</a> for any support needs</li>
<?php elseif (preg_match('/\.scalr\.com$/', $siteUrl)): ?>
        	<li><a href="http://support.scalr.com">Support.scalr.com</a> for any support needs</li>
<?php else: ?>
        	<li><a href="https://groups.google.com/forum/#!forum/scalr-discuss">Scalr</a> discussion group</li>
<?php endif; ?>
            <li><a href="<?= $wikiUrl ?>">Scalr</a> documentation</li>
            <li><a href="https://www.youtube.com/user/scalrvideos">Scalr Youtube</a> video tutorials</li>
        </ul>
    </p>

    <p>Thanks for selecting Scalr for your cloud management needs.
       We’re looking forward to working together.
       Should you have any other questions, feel free to reach us at <a href="mailto:sales@scalr.com">sales@scalr.com</a>.
       We’re here to help.</p>

	<p>Cheers,<br/>
	Team Scalr</p>
</body>

</html>