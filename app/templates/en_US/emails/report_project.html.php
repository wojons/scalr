<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<base href="https://scalr.com">
<title>Message Title</title>
</head>
<body style="-webkit-text-size-adjust:none;margin:0;padding:0;font: 16px 'open_sansbold', Arial, sans-serif;line-height: 1.429;color: #1A487B;width:100% !important">
        <table id="background-table" cellpadding="0" cellspacing="0" style="height: 100% !important;margin: 0 auto;padding: 0;width: 750px;background-color: #fff;border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt">
<tr>
<td id="page-header" style="border-collapse:collapse">
                    <table width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="text-align:left;padding-left:32px;border-collapse:collapse;font-size: 22px;line-height: 25px;padding-top: 20px;padding-bottom: 20px;color: #fff;background: #A8CCDF"><?=$name?></td>
                            <td style="text-align:right;padding-right:32px;border-collapse:collapse;font-size: 22px;line-height: 25px;padding-top: 20px;padding-bottom: 20px;color: #fff;background: #A8CCDF"><?=$date?></td>
                        </tr></table>
</td>
<td style="border-collapse:collapse">
            </td>
</tr>
<tr>
<td id="page-content" style="border-collapse:collapse;padding: 32px;border-left: 1px solid #e1f0fa;border-right: 1px solid #e1f0fa;overflow:hidden;width: 100%">
<table class="table-totals" cellpadding="0" cellspacing="0" style="width: 100%">
<tr>
<td style="border-collapse:collapse;width: 50%;padding: 0 0 24px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Total spent</div>
            <span class="title2" style="font-size: 23px;color: #1A487B;font-weight: bold">$<?=number_format(round($totals['cost']), 0, '.', ',')?></span>
            <?php if ($totals['growth'] !=0):?>
                  <span class="label-growth" style="background:<?=round($totals['growth']) >= 0 ? '#f76040' : '#2ba446'?>;color: #fff;padding: 0 6px;font-size: 13px;border-radius: 2px">
                                <?=round($totals['growth']) >= 0 ? '+' : '–'?>
                                <?php if (round($totals['growthPct'])!=0):?><?=round($totals['growthPct'])?>% ($<?=number_format(abs(round($totals['growth'])), 0, '.', ',')?>)
                                <?php else:?>
                                    $<?=number_format(abs(round($totals['growth'])), 0, '.', ',')?><?php endif;?></span>
            <?php endif?>
</td>
        <td style="border-collapse:collapse;width: 50%;padding: 0 0 24px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">
<?=ucfirst($forecastPeriod)?> end estimate</div>
            <div class="title2" style="font-size: 23px;color: #1A487B;font-weight: bold"><span class="small" style="font-size: 80%"><?=is_null($totals['forecastCost']) ? 'n/a' : '$'.number_format(round($totals['forecastCost']), 0, '.', ',')?></span></div>
        </td>
    </tr>
<tr>
<td style="border-collapse:collapse;width: 50%;padding: 0 0 24px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Prev. <?=$period?>
</div>
            <div class="title2" style="font-size: 23px;color: #1A487B;font-weight: bold"><span class="small" style="font-size: 80%">$<?=number_format(round($totals['prevCost']), 0, '.', ',')?></span></div>
        </td>
        <td style="border-collapse:collapse;width: 50%;padding: 0 0 24px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px"><?=$totals['trends']['rollingAverageMessage'] ? $totals['trends']['rollingAverageMessage'] : 'Average'?></div>
            <div class="title2" style="font-size: 23px;color: #1A487B;font-weight: bold"><span class="small" style="font-size: 80%">$<?=number_format(round($totals['trends']['rollingAverageDaily']), 0, '.', ',')?><span class="small" style="font-size: 80%"> per <?=$interval?></span></span></div>
        </td>
    </tr>
</table>
<?php if ($totals['budget']['budget']):?><?php if ($totals['budget']['budgetSpentPct'] >= 95) {
        $color = '#f5411b';
    } elseif ($totals['budget']['budgetSpentPct'] >= 75) {
        $color = '#fe9b23';
    } else {
        $color = '#57a831';
    }
?>
<br><table style="width:100%" cellpadding="0" cellspacing="0"><tr>
<td style="border-collapse:collapse">
            <span class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Q<?=$totals['budget']['quarter']?> budget</span>   <span class="title4" style="font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B"><span style="color:<?=$color?>">$<?=number_format(round($totals['budget']['budgetSpent']), 0, '.', ',')?></span> of $<?=number_format(round($budget['budget']), 0, '.', ',')?></span>
        </td>
        <td style="text-align:right;border-collapse:collapse">
<span class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Remaining</span> <span class="title4" style="font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B">$<?=number_format(round($totals['budget']['budgetRemain']), 0, '.', ',')?></span>
</td>
    </tr></table>
<table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="bar" style="border-collapse:collapse;background: #caddec;color: #fff">
    <table style="width:<?=$totals['budget']['budgetSpentPct']?>%" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="background:<?=$color?>;border-collapse:collapse;height: 24px;line-height: 24px"><div style="overflow:hidden"></div></td></tr></table>
</td></tr></table>
<?php endif?><br><table style="width:100%" cellpadding="0" cellspacing="0">
<tr>
<td class="title3" colspan="3" style="border-collapse:collapse;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Clouds</td>
    </tr>
<?php foreach ($totals['clouds'] as $cloud):?><tr>
<td class="title4" style="padding:0 60px 0 0;border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px"><?=$cloud['name']?></td>
            <td class="title4" style="border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px">$<?=number_format(round($cloud['cost']), 0, '.', ',')?>
</td>
            <td class="title4" style="border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px">
                <?php if(round($cloud['growth'])!=0):?><span class="label-growth" style="background:<?=round($cloud['growth']) <= 0 ? '#2ba446' : '#f76040'?>;color: #fff;padding: 0 6px;font-size: 13px;border-radius: 2px">
                        <?=round($cloud['growth']) <= 0 ? '&ndash;' : '+'?><?php if (round($cloud['growthPct'])!=0) :?><?=round($cloud['growthPct'])?>% ($<?=number_format(abs(round($cloud['growth'])), 0, '.', ',')?>)
                        <?php else:?>
                            $<?=number_format(abs(round($cloud['growth'])), 0, '.', ',')?><?php endif;?></span>
                <?php endif;?>
</td>
        </tr>
<?php endforeach?><tr>
<td class="title3" colspan="3" style="border-collapse:collapse;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Top 5 farms</td>
    </tr>
<?php foreach ($totals['farms'] as $farm):?><tr>
<td class="title4" style="padding:0 60px 0 0;border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px"><?=$farm['name']?></td>
            <td class="title4" style="border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px">$<?=number_format(round($farm['cost']), 0, '.', ',')?>
</td>
            <td class="title4" style="border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px">
                <?php if(round($farm['growth'])!=0):?><span class="label-growth" style="background:<?=round($farm['growth']) <= 0 ? '#2ba446' : '#f76040'?>;color: #fff;padding: 0 6px;font-size: 13px;border-radius: 2px">
                        <?=round($farm['growth']) <= 0 ? '&ndash;' : '+'?><?php if (round($farm['growthPct'])!=0) :?><?=round($farm['growthPct'])?>% ($<?=number_format(abs(round($farm['growth'])), 0, '.', ',')?>)
                        <?php else:?>
                            $<?=number_format(abs(round($farm['growth'])), 0, '.', ',')?><?php endif;?></span>
                <?php endif;?>
</td>
        </tr>
<?php endforeach?>
</table>
</td>
            </tr>
<tr>
<td id="page-footer" style="border-collapse:collapse;font-size: 12px;line-height: 18px;background: #eef4f8;text-align: center;height: 12px;padding: 16px;color: #0055CC">
                    <table style="width:100%" cellpadding="0" cellspacing="0"><tr>
<td style="width:50%;border-collapse:collapse"><a href="<?=$reportUrl?>" style="color: #0055CC;text-decoration: underline">Permalink to this report</a></td>
                            <td style="border-collapse:collapse"><a href="https://my.scalr.com#/analytics/dashboard" style="color: #0055CC;text-decoration: underline">View detailed statistics</a></td>
                        </tr></table>
</td>
            </tr>
</table>
</body>
</html>
