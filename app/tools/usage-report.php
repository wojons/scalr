<?php
require_once __DIR__ . '/../src/prepend.inc.php';

use Scalr\Util\PhpTemplate;

$opt = getopt('', ['csv::']);

$format = array_key_exists('csv', $opt) && $opt['csv'] === false ? 'csv' : 'txt';

$quarterDays = [90, 91, 92, 92];

$data = [];

$allPlatforms = \SERVER_PLATFORMS::getList();

$res = \Scalr::getDb()->Execute("
    SELECT `time`, `platform`, `value`, QUARTER(`time`) AS quarter, YEAR(`time`) AS year
    FROM platform_usage
    ORDER BY `time` ASC
");

while($row = $res->FetchRow()) {
    $quarter = "Q" . $row["quarter"] . " " . $row["year"];

    if (!array_key_exists($quarter, $data)) {
        $data[$quarter] = [
            "quarter"        => $row["quarter"] - 1,
            "year"           => $row["year"],
            "days"           => [],
            "dataByPlatform" => [],
        ];
    }

    if (!array_key_exists($row["platform"], $data[$quarter]["dataByPlatform"])) {
        $data[$quarter]["dataByPlatform"][$row["platform"]] = [];
    }

    $data[$quarter]["dataByPlatform"][$row["platform"]][] = $row["value"];
    $data[$quarter]["days"][substr($row["time"], 0, 10)] = true;
}

if ($format == 'csv') {
    $csvRowTemplate = ['year' => '', 'quarter' => '', 'partial' => ''];
}

foreach ($data as $quarter => &$set) {
    ksort($set["dataByPlatform"]);

    foreach ($set["dataByPlatform"] as $platform => &$report) {
        $report = \Scalr_Util_Arrays::percentile($report, 90, true);

        if ($format == 'csv' && !array_key_exists($platform, $csvRowTemplate)) {
            $csvRowTemplate[$platform] = 0;
        }
    }
}

foreach ($data as $quarter => &$set) {
    $uniqueDates = count($set["days"]);
    $partial = ($quarterDays[$set["quarter"]] + ($set["quarter"] === 0 && (($set["year"] % 4 === 0 && $set["year"] % 100 !== 0) || ($set["year"] % 400 === 0)) ? 1 : 0) === $uniqueDates) ? false : $uniqueDates;

    if ($format == 'csv') {
        $csvData[] = array_merge(
            $csvRowTemplate,
            ['year' => $set["year"], 'quarter' => $set["quarter"] + 1, 'partial' => empty($partial) ? '' : $partial],
            $set["dataByPlatform"]
        );
    } else {
        foreach ($set["dataByPlatform"] as $platform => &$report) {
            $platformName = array_key_exists($platform, $allPlatforms) ? $allPlatforms[$platform] : $platform;

            if (empty($report)) {
                $report = str_pad($platformName . ":", 32) . "0";
            } else {
                $report = str_pad($platformName . ":", 34) . str_replace("0", " ", sprintf("%05d", $report));
            }
        }

        $set["header"] = str_pad(
            "===== " . $quarter . ($partial !== false ? " (Partial: " . $partial . " day" . ($partial > 1 ? "s" : "") . ")" : "") . " =====",
            39,
            "=",
            STR_PAD_BOTH
        );
    }
}

if ($format == 'csv') {
    fputcsv(STDOUT, array_keys($csvRowTemplate));

    foreach ($csvData as $csvRow) {
        fputcsv(STDOUT, $csvRow);
    }
} else {
    echo PhpTemplate::load(APPPATH . "/templates/reports/usage_report.txt.php", ["data" => $data]);
}