<?php
require_once __DIR__ . '/../src/prepend.inc.php';

$db = Scalr::getDb();

set_time_limit(0);

$dump = @file_get_contents("https://my.scalr.net/storage/shared-roles.php?v2=1&scalr_id=".SCALR_ID);
$roles = @json_decode($dump, true);
if (count($roles) < 1)
    die("Unable to import shared roles: {$dump}");

try {
	foreach ($roles as $role) {
        $chk2 = $db->GetRow("SELECT name, origin FROM roles WHERE id=? LIMIT 1", array($role['id']));
        if ($chk2['name'] && ($chk2['name'] != $role['name'] || $chk2['origin'] != $role['origin'])) {
            print "Role ID #{$role['id']} for role '{$role['name']}' taken by role '{$chk2['name']}'\n";
            continue;
        }

		$chk = $db->GetOne("SELECT id FROM roles WHERE origin=? AND id=? LIMIT 1", array($role['origin'], $role['id']));
		if (!$chk) {
			$db->Execute("INSERT INTO roles SET
				`id` = ?,
				`name` = ?,
				`origin` = ?,
				`client_id` = ?,
			    `cat_id` = ?,
				`env_id` = ?,
				`description` = ?,
				`behaviors` = ?,
				`generation` = ?,
				`os` = ?,
			    `os_family` = ?,
			    `os_generation` = ?,
			    `os_version` = ?
			", array(
				$role['id'], $role['name'], $role['origin'], $role['client_id'], $role['cat_id'], $role['env_id'], $role['description'],
				$role['behaviors'],
				$role['generation'], $role['os'], $role['os_family'], $role['os_generation'], $role['os_version']
			));
		} else {
			$role['id'] = $chk;
			$db->Execute("DELETE FROM role_images WHERE role_id = ?", array($role['id']));
			$db->Execute("DELETE FROM role_security_rules WHERE role_id =?", array($role['id']));
			$db->Execute("DELETE FROM role_properties WHERE role_id =?", array($role['id']));
			$db->Execute("DELETE FROM role_parameters WHERE role_id = ?", array($role['id']));
			$db->Execute("DELETE FROM role_behaviors WHERE role_id =?", array($role['id']));
		}

		foreach ($role['role_security_rules'] as $r3) {
			$db->Execute("INSERT INTO role_security_rules SET
				`role_id` = ?,
				`rule` = ?
			", array($r3['role_id'], $r3['rule']));
		}

		foreach ($role['role_properties'] as $r5) {
		    try {
				$db->Execute("INSERT INTO role_properties SET
					`role_id` = ?,
					`name` = ?,
					`value` = ?
				", array($r5['role_id'], $r5['name'], $r5['value']));
            } catch (Exception $e) {}
		}

		foreach ($role['role_parameters'] as $r6) {
			$db->Execute("INSERT INTO role_parameters SET
				`role_id` = ?,
				`name` = ?,
				`type` = ?,
				`isrequired` = ?,
				`defval` = ?,
				`allow_multiple_choice` = ?,
				`options` = ?,
				`hash` = ?,
				`issystem` = ?
			", array($r6['role_id'], $r6['name'], $r6['type'], $r6['isrequired'], $r6['defval'], $r6['allow_multiple_choice'], $r6['options'], $r6['hash'], $r6['issystem']));
		}

        foreach ($role['role_images'] as $r7) {
			try {
				$db->Execute("INSERT INTO role_images SET
					`role_id` = ?,
					`cloud_location` = ?,
					`image_id` = ?,
					`platform` = ?
				", array($r7['role_id'], $r7['cloud_location'], $r7['image_id'], $r7['platform']));

                if ($r7['image']) {
                    $hash = $r7['image']['hash'];
                    unset($r7['image']['hash']);
                    $software = $r7['image']['software'];
                    unset($r7['image']['software']);

                    $db->Execute('DELETE FROM images WHERE hash = UNHEX(?)', [$hash]);
                    $db->Execute('INSERT INTO images (hash, ' . implode(',', array_keys($r7['image'])) . ') VALUES(UNHEX(?), ' . implode(',', array_fill(0, count($r7['image']), '?')) .
                        ')', array_merge([$hash], array_values($r7['image'])));

                    $db->Execute('DELETE FROM image_software WHERE image_hash = UNHEX(?)', [$hash]);

                    if (count($software)) {
                        foreach ($software as $soft) {
                            $db->Execute('INSERT INTO image_software (image_hash, name, version) VALUES(UNHEX(?), ?, ?)', [
                                $hash,
                                $soft['name'],
                                $soft['version']
                            ]);
                        }
                    }
                }
			} catch (Exception $e) {}
		}

		foreach ($role['role_behaviors'] as $r8) {
		    try {
				$db->Execute("INSERT INTO role_behaviors SET
					`role_id` = ?,
					`behavior` = ?
				", array($r8['role_id'], $r8['behavior']));
		    } catch (Exception $e) {}
		}
    }
} catch (Exception $e) {
    $db->RollbackTrans();
    var_dump($e->getMessage());
    exit();
}

$db->CommitTrans();
