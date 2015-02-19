<?php
require __DIR__ . "/../../src/prepend.inc.php";

$db = Scalr::getDb();

$setupInfo = $db->GetRow("SELECT * FROM upd.scalr_setups WHERE scalr_id = ? LIMIT 1", array($_GET['scalr_id']));

if (!$setupInfo)
    die("Unrecognized setup: update denied. Please use this form http://hub.am/1fDAc2B to be whitelisted.");

$v2 = (boolean) $_GET['v2'];

$rs20 = $db->Execute("SELECT * FROM roles WHERE env_id = '0' AND client_id = '0' AND generation='2'");
$result = array();
while ($role = $rs20->FetchRow()) {

    if ($role['os_family'] == 'ubuntu') {
        if ($role['os_version'] == '8.04' || $role['os_generation'] == '10.04')
            continue;
    }
    
    if ($role['os_family'] == 'centos') {
        if ($role['os_generation'] == '5')
            continue;
    }
    
    if ($role['os_family'] == 'windows') {
        if ($role['os_generation'] == '2003')
            continue;
    }
    
    $role['role_security_rules'] = $db->GetAll("SELECT * FROM role_security_rules WHERE role_id = ?", array($role['id']));
    $role['role_properties'] = $db->GetAll("SELECT * FROM role_properties WHERE role_id = ?", array($role['id']));
    $role['role_parameters'] = $db->GetAll("SELECT * FROM role_parameters WHERE role_id = ?", array($role['id']));
    $role['role_images'] = $db->GetAll("SELECT * FROM role_images WHERE role_id = ?", array($role['id']));
    $role['role_behaviors'] = $db->GetAll("SELECT * FROM role_behaviors WHERE role_id = ?", array($role['id']));

    $isOldMySQL = $db->GetOne("SELECT id FROM role_behaviors WHERE role_id = ? AND behavior='mysql' LIMIT 1", array($role['id']));

    if (!$isOldMySQL) {
        foreach ($role['role_images'] as &$image) {
            if ($v2) {
                $image['image'] = $db->GetRow('SELECT *, HEX(hash) AS hash FROM images WHERE platform = ? AND cloud_location = ? AND id = ? AND env_id IS NULL', [
                    $image['platform'],
                    $image['cloud_location'],
                    $image['image_id']
                ]);
                // temporary solution because of os-refactoring feature, remove in next OSS release
                unset($image['image']['os_id']);
                unset($image['image']['dt_last_used']);

                $image['image']['software'] = $db->GetAll('SELECT name, version FROM image_software WHERE image_hash = UNHEX(?)', [$image['image']['hash']]);
            } else {
                $image['architecture'] = NULL;
                $image['os_family'] = NULL;
                $image['os_name'] = NULL;
                $image['os_version'] = NULL;
                $image['agent_version'] = NULL;
            }
        }

        $result[] = $role;
    }
}

print json_encode($result);

exit();
