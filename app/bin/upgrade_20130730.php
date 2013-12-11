#!/usr/bin/env php
<?php

require_once __DIR__ . '/../src/prepend.inc.php';

set_time_limit(0);

$ScalrUpdate = new Update20130729();
$ScalrUpdate->Run();

class Update20130729
{
    public function Run()
    {
        $container = Scalr::getContainer();
        $db = $container->adodb;

        $script = <<<EOL
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

CREATE  TABLE IF NOT EXISTS `acl_roles` (
  `role_id` INT(11) NOT NULL,
  `name` VARCHAR(255) NULL DEFAULT NULL ,
  PRIMARY KEY (`role_id`) ,
  UNIQUE INDEX `name_UNIQUE` (`name` ASC)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci
COMMENT = 'Global ACL roles';

CREATE  TABLE IF NOT EXISTS `acl_role_resources` (
  `role_id` INT(11) NOT NULL ,
  `resource_id` INT(11) NOT NULL ,
  `granted` TINYINT(4) NULL DEFAULT NULL ,
  PRIMARY KEY (`resource_id`, `role_id`) ,
  INDEX `fk_aa1q8565e63a6f2d299_idx` (`role_id` ASC) ,
  CONSTRAINT `fk_3a1qafb85e63a6f2d276` FOREIGN KEY (`role_id` ) REFERENCES `acl_roles` (`role_id` )
  ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci
COMMENT = 'Grants access permissions to resources.';

CREATE  TABLE IF NOT EXISTS `acl_role_resource_permissions` (
  `role_id` INT(11) NOT NULL ,
  `resource_id` INT(11) NOT NULL ,
  `perm_id` VARCHAR(64) NOT NULL ,
  `granted` TINYINT(4) NULL DEFAULT NULL ,
  PRIMARY KEY (`role_id`, `resource_id`, `perm_id`) ,
  INDEX `fk_b0bc67f70c85735d0da2_idx` (`role_id` ASC, `resource_id` ASC) ,
  CONSTRAINT `fk_8a0eafb8ae6ea4f2d276`
    FOREIGN KEY (`role_id` , `resource_id` )
    REFERENCES `acl_role_resources` (`role_id` , `resource_id` )
    ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci
COMMENT = 'Grants privileges to unique permissions';

CREATE  TABLE IF NOT EXISTS `acl_account_roles` (
  `account_role_id` VARCHAR(20) NOT NULL ,
  `account_id` INT(11) NULL DEFAULT NULL ,
  `role_id` INT(11) NOT NULL ,
  `name` VARCHAR(255) NULL DEFAULT NULL ,
  PRIMARY KEY (`account_role_id`) ,
  INDEX `base_role_id` (`role_id` ASC) ,
  INDEX `account_id` (`account_id` ASC) ,
  CONSTRAINT `fk_acl_account_roles_clients1`
    FOREIGN KEY (`account_id` )
    REFERENCES `clients` (`id` )
    ON DELETE CASCADE,
  CONSTRAINT `fk_acl_account_roles_acl_roles1`
    FOREIGN KEY (`role_id` )
    REFERENCES `acl_roles` (`role_id` )
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci
COMMENT = 'Account level roles override global roles';

CREATE  TABLE IF NOT EXISTS `acl_account_role_resources` (
  `account_role_id` VARCHAR(20) NOT NULL ,
  `resource_id` INT(11) NOT NULL ,
  `granted` TINYINT(4) NULL DEFAULT NULL ,
  PRIMARY KEY (`account_role_id`, `resource_id`) ,
  INDEX `fk_e81073f7212f04f8db61_idx` (`account_role_id` ASC) ,
  CONSTRAINT `fk_2a0b54035678ca90222b`
    FOREIGN KEY (`account_role_id` )
    REFERENCES `acl_account_roles` (`account_role_id` )
    ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE  TABLE IF NOT EXISTS `acl_account_role_resource_permissions` (
  `account_role_id` VARCHAR(20) NOT NULL ,
  `resource_id` INT(11) NOT NULL ,
  `perm_id` VARCHAR(64) NOT NULL ,
  `granted` TINYINT(4) NULL DEFAULT NULL ,
  PRIMARY KEY (`account_role_id`, `resource_id`, `perm_id`) ,
  INDEX `fk_f123f4826415e04bfa12_idx` (`account_role_id` ASC, `resource_id` ASC) ,
  CONSTRAINT `fk_a98e2a51b27453360594`
    FOREIGN KEY (`account_role_id` , `resource_id` )
    REFERENCES `acl_account_role_resources` (`account_role_id` , `resource_id` )
    ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

CREATE  TABLE IF NOT EXISTS `account_team_user_acls` (
  `account_team_user_id` INT(11) NOT NULL ,
  `account_role_id` VARCHAR(20) NOT NULL ,
  PRIMARY KEY (`account_team_user_id`, `account_role_id`) ,
  INDEX `fk_9888fac48291b3452f82_idx` (`account_team_user_id` ASC) ,
  INDEX `fk_d7ced79d114b481574f8_idx` (`account_role_id` ASC) ,
  CONSTRAINT `fk_241cb20f77a84d2a9a95`
    FOREIGN KEY (`account_team_user_id` )
    REFERENCES `account_team_users` (`id` )
    ON DELETE CASCADE,
  CONSTRAINT `fk_4c65ed7fea80e0f3792d`
    FOREIGN KEY (`account_role_id` )
    REFERENCES `acl_account_roles` (`account_role_id` )
    ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_general_ci;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
EOL;

        $time = microtime(true);

        foreach (preg_split('/;/', $script) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt == '') continue;
            $db->Execute($stmt);
        }

        $db->Execute("ALTER TABLE `elastic_ips` CHANGE `allocation_id` `allocation_id` VARCHAR(36) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;");

        print "Done.\n";

        $t = round(microtime(true) - $time, 2);

        printf("Upgrade process took %0.2f seconds\n\n", $t);
    }
}
