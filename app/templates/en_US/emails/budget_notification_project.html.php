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
<table class="table-overspend" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;width: 80%">
<tr>
<?php if ($budget['budgetSpentPct'] >= 95) {
                $color = '#f5411b';
            } elseif ($budget['budgetSpentPct'] >= 75) {
                $color = '#fe9b23';
            } else {
                $color = '#57a831';
            }
            $estimateOverspend = round($forecastCost - $budget['budget']);
            $estimateOverspendPct = round(($forecastCost / $budget['budget']) * 100) - 100;
        ?>
        <td style="width:90%;border-collapse:collapse">
            <div class="title3" style="line-height:18px;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold">Budget used   <span style="color:<?=$color?>">$<?=number_format(round($budget['budgetSpent']), 0, '.', ',')?> (<?=round($budget['budgetSpentPct'])?>%)</span>
</div>
        </td>
        <td style="border-collapse:collapse"><div class="title3" style="line-height:18px;margin-left:-40px;white-space:nowrap;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold">Budget <span style="color:#1A487B">$<?=number_format(round($budget['budget']), 0, '.', ',')?></span>
</div></td>
    </tr>
<tr>
<td class="budget" style="border-collapse:collapse;border-right: 1px solid #e9f0f6;padding-top: 10px">
            <table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="bar" style="border-collapse:collapse;background: #caddec;color: #fff">
                <table style="width:<?=$budget['budgetSpentPct']?>%" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="background:<?=$color?>;border-collapse:collapse;height: 24px;line-height: 24px"><div style="overflow:hidden"></div></td></tr></table>
</td></tr></table>
</td>
        <td class="overspend" style="border-collapse:collapse;padding-top: 10px"> </td>
    </tr>
<tr>
<td class="budget" style="border-collapse:collapse;border-right: 1px solid #e9f0f6;padding-top: 10px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Quarter end estimate   <span style="color:#08aff0">$<?=number_format(round($forecastCost), 0, '.', ',')?> (<?=100+$estimateOverspendPct?>%)</span>
</div>
            <table style="width:100%;overflow:hidden" cellpadding="0" cellspacing="0"><tr><td class="bar" style="border-collapse:collapse;background: #caddec;color: #fff">
                <table style="width:<?=100+$estimateOverspendPct?>%" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="background:#08aff0;border-collapse:collapse;height: 24px;line-height: 24px"><div style="overflow:hidden"></div></td></tr></table>
</td></tr></table>
</td>
        <td class="overspend" style="border-collapse:collapse;padding-top: 10px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px"> </div>
            <?php if ($estimateOverspend>0) :?><table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="background:#08aff0;border-collapse:collapse;height: 24px;line-height: 24px"><div style="overflow:hidden"></div></td></tr></table>
<?php endif?>
</td>
    </tr>
<tr>
<td class="budget" style="border-collapse:collapse;border-right: 1px solid #e9f0f6;padding-top: 10px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">
                <?php if (round($estimateOverspend)!=0):?><?=(round($estimateOverspend) >= 0 ? 'Over' : 'Under')?>spend estimate   <span style="color:<?=($estimateOverspend >= 0 ? '#f5411b' : '#57a831')?>">$<?=number_format(round(abs($estimateOverspend)), 0, '.', ',')?> (<?=abs(round($estimateOverspendPct))?>%)</span>
</div>
                <?php else: ?>
                    Overspend estimate   $0
                <?php endif;?><?php if (round($estimateOverspend)<0) :?><table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="bar" style="background:#57a831;border-collapse:collapse;color: #fff">
                    <table style="width:<?=100 + round($estimateOverspendPct)?>%" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="background:#caddec;border-collapse:collapse;height: 24px;line-height: 24px"> </td></tr></table>
</td></tr></table>
<?php else:?><table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="bar" style="border-collapse:collapse;background: #caddec;color: #fff">
                    <table style="width:0px" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="border-collapse:collapse;height: 24px;line-height: 24px"> </td></tr></table>
</td></tr></table>
<?php endif?>
</td>
        <td class="overspend" style="border-collapse:collapse;padding-top: 10px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px"> </div>
            <?php if (round($estimateOverspend)>0) :?><table style="width:100%" cellpadding="0" cellspacing="0"><tr><td class="bar-inner" style="background:#f5411b;border-collapse:collapse;height: 24px;line-height: 24px"><div style="overflow:hidden"></div></td></tr></table>
<?php endif?>
</td>
    </tr>
</table>
<table class="table-trends" cellpadding="0" cellspacing="0" style="width: 100%"><tr>
<td style="border-collapse:collapse;text-align: center;width: 33%;padding: 18px 0 32px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Remaining</div>
            <div class="title2" style="font-size: 23px;color: #1A487B;font-weight: bold">$<?=number_format(round($budget['budgetRemain']), 0, '.', ',')?>
</div>
        </td>
        <td style="border-collapse:collapse;text-align: center;width: 33%;padding: 18px 0 32px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px"><?=$trends['rollingAverageMessage']?></div>
            <div class="title2" style="font-size: 23px;color: #1A487B;font-weight: bold">$<?=number_format(round($trends['rollingAverageDaily']), 0, '.', ',')?><span class="small" style="font-size: 80%"> per <?=$interval?></span>
</div>
        </td>
        <td style="border-collapse:collapse;text-align: center;width: 33%;padding: 18px 0 32px">
            <div class="title3" style="font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Exceed date</div>
            <div class="title2 red" style="color: #e62106!important;font-size: 23px;font-weight: bold"><?=$budget['estimateDate'] ? $budget['estimateDate'] : '&ndash;'?></div>
        </td>
    </tr></table>
<table style="width:100%" cellpadding="0" cellspacing="0">
<tr>
<td class="title3" style="width:240px;border-collapse:collapse;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Top 5 farms</td>
        <td class="title3" style="width:180px;border-collapse:collapse;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Spend</td>
        <td class="title3" style="border-collapse:collapse;font-size: 13px;color: #5997BF;text-transform: uppercase;font-weight: bold;line-height: 40px">Daily average</td>
    </tr>
<?php foreach ($farms as $farm):?><tr>
<td class="title4" style="padding:0 60px 0 0;border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px"><?=$farm['name'] ? $farm['name'] : 'Other farms'?></td>
            <td class="title4" style="border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px">$<?=number_format(round($farm['cost']), 0, '.', ',')?>
</td>
            <td class="title4" style="border-collapse:collapse;font-size: 13px;line-height: 24px;font-weight: bold;color: #1A487B;padding-bottom: 16px"><?=round($farm['median']) ? '$'.number_format(round($farm['median']), 0, '.', ',') : '&ndash;'?></td>
        </tr>
<?php endforeach?>
</table>
</td>
            </tr>
<tr>
<td id="page-footer" style="border-collapse:collapse;font-size: 12px;line-height: 18px;background: #eef4f8;text-align: center;height: 12px;padding: 16px;color: #0055CC"><a href="https://my.scalr.com#/analytics/dashboard" style="color: #0055CC;text-decoration: underline">View detailed statistics</a></td>
            </tr>
</table>
</body>
</html>
