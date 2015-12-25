<?php
require_once __DIR__ . '/../src/prepend.inc.php';

use Scalr\Util\PhpTemplate;

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

foreach ($data as $quarter => &$set) {
    ksort($set["dataByPlatform"]);

    foreach ($set["dataByPlatform"] as $platform => &$report) {
        $percentile = \Scalr_Util_Arrays::percentile($report, 90, true);

        if (empty($percentile)) {
            $report = str_pad($allPlatforms[$platform] . ":", 32) . "0";
        } else {
            $report = str_pad($allPlatforms[$platform] . ":", 34) . str_replace("0", " ", sprintf("%05d", $percentile));
        }
    }

    $uniqueDates = count($set["days"]);

    $partial = ($quarterDays[$set["quarter"]] + ($set["quarter"] === 0 && (($set["year"] % 4 === 0 && $set["year"] % 100 !== 0) || ($set["year"] % 400 === 0)) ? 1 : 0) === $uniqueDates) ? false : $uniqueDates;

    $set["header"] = str_pad(
        "===== " . $quarter . ($partial !== false ? " (Partial: " . $partial . " day" . ($partial > 1 ? "s" : "") . ")" : "") . " =====",
        39,
        "=",
        STR_PAD_BOTH
    );
}

echo PhpTemplate::load(APPPATH . "/templates/reports/usage_report.txt.php", ["data" => $data]);
