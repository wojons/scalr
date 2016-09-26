<?php
require __DIR__ . "/../../src/prepend.inc.php";

$db = Scalr::getDb();

$setupInfo = $db->GetRow("SELECT * FROM upd.scalr_setups WHERE scalr_id = ? LIMIT 1", array($_GET['scalr_id']));

if (!$setupInfo)
    die("Unrecognized setup: update denied. Please use this form http://hub.am/1fDAc2B to be whitelisted.");

$v2 = (boolean) $_GET['v2'];
$addMongoRole = (boolean) $_GET['addMongoRole'];

$rs20 = $db->Execute("SELECT * FROM roles WHERE env_id IS NULL AND client_id IS NULL AND generation='2' AND is_deprecated='0' AND os_id NOT IN (
    SELECT id FROM os WHERE (family='centos' AND generation='5') OR (family='ubuntu' AND generation IN ('8.04','10.04')) OR (family='windows' AND generation='2003')
)");
$result = array();
while ($role = $rs20->FetchRow()) {
    $role['role_security_rules'] = $db->GetAll("SELECT * FROM role_security_rules WHERE role_id = ?", array($role['id']));
    $role['role_properties'] = $db->GetAll("SELECT * FROM role_properties WHERE role_id = ?", array($role['id']));
    $role['role_images'] = $db->GetAll("SELECT * FROM role_images WHERE role_id = ?", array($role['id']));
    $role['role_behaviors'] = $db->GetAll("SELECT * FROM role_behaviors WHERE role_id = ?", array($role['id']));

    $isOldMySQL = $db->GetOne("SELECT id FROM role_behaviors WHERE role_id = ? AND behavior='mysql' LIMIT 1", array($role['id']));
    $isMongoDB = $db->GetOne("SELECT id FROM role_behaviors WHERE role_id = ? AND behavior='mongodb' LIMIT 1", array($role['id']));

    if (!$isOldMySQL && (!$isMongoDB || $addMongoRole) && !$role['is_deprecated']) {
        foreach ($role['role_images'] as $i => $image) {
            if ($v2) {
                $role['role_images'][$i]['image'] = $db->GetRow('SELECT *, HEX(hash) AS hash FROM images WHERE platform = ? AND cloud_location = ? AND id = ? AND env_id IS NULL', [
                    $image['platform'],
                    $image['cloud_location'],
                    $image['image_id']
                ]);

                // temporary solution because of os-refactoring feature, remove in next OSS release
                // ugly hack for testing purposes
                if ($v2 != 2) {
                    unset($role['role_images'][$i]['image']['os_id']);
                    unset($role['role_images'][$i]['image']['dt_last_used']);
                }


                $role['role_images'][$i]['image']['software'] = $db->GetAll('SELECT name, version FROM image_software WHERE image_hash = UNHEX(?)', [$image['image']['hash']]);

                //DO NOT provide OLD/Deprecated 32 bit images
                if ($role['role_images'][$i]['image']['architecture'] == 'i386')
                    unset($role['role_images'][$i]);

            } else {
                $image['architecture'] = NULL;
                $image['os_family'] = NULL;
                $image['os_name'] = NULL;
                $image['os_version'] = NULL;
                $image['agent_version'] = NULL;
            }
        }

        if (count($role['role_images']) > 0) {
            //For backward compatibility
            if (empty($role['env_id'])) {
                $role['env_id'] = 0;
            }

            if (empty($role['client_id'])) {
                $role['client_id'] = 0;
            }

            $result[] = $role;
        }
    }
}

print json_encode($result);

exit();
