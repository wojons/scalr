Quarterly report for vCPU utilization:
<?php if (empty($data)):?>
No stats have been collected yet.
<?php else:?>
<?php foreach ($data as $values):?>

<?=$values["header"]?>

<?php foreach($values["dataByPlatform"] as $percentile): ?>
<?=$percentile?>

<?php endforeach;?>
<?php endforeach;?>
<?php endif;?>

