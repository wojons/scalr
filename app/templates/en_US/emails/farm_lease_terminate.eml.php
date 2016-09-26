[subject][Scalr] Farm is scheduled for termination: <?= $farm ?>[/subject]

This is a reminder that, as per your company's lease management policy, the "<?= $farm ?>" Farm has been scheduled for termination.

The "<?= $farm ?>" Farm will be terminated on <?= $terminateDate ?>.

If you do not want the "<?= $farm ?>" Farm to be terminated, you can request a lease extension through the "Extended Information" menu for this Farm.

<?php if ($showOwnerWarning): ?>You received this email because farm "<?= $farm ?>" doesn’t have owner.

<?php endif; ?>
Regards,
The Scalr Team
