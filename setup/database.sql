CREATE TABLE IF NOT EXISTS `accounts` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'auto increment for in database use',
  `id` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'uuid for external use',
  `mail` varchar(255) NOT NULL DEFAULT '' COMMENT 'account email',
  `display` varchar(255) NOT NULL COMMENT 'display name',
  `identifier` varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT 'temporary identifier for login',
  `since` datetime DEFAULT NULL COMMENT 'datetime until identifier is valid',
  `notify` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'if activated you will be notified of invites and messages',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='your user accounts';

CREATE TABLE IF NOT EXISTS `chats` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'internal usage id',
  `organisation` bigint(20) unsigned NOT NULL COMMENT 'organisation this was posted in',
  `target` bigint(20) unsigned NOT NULL COMMENT 'user this is for',
  `created` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'when was this written - cleanup after 6h',
  `creator` bigint(20) unsigned NOT NULL COMMENT 'who wrote this',
  `content` longblob NOT NULL COMMENT 'AES encoded data',
  `iv` varbinary(255) NOT NULL COMMENT 'RSA encoded vector for AES',
  `key` varbinary(255) NOT NULL COMMENT 'RSA encoded key for AES',
  PRIMARY KEY (`aid`),
  KEY `organization_target` (`organisation`,`target`),
  KEY `creator` (`creator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `configurations` (
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `folders` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'internally used id',
  `id` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'uuid for external use',
  `name` varchar(255) NOT NULL COMMENT 'foldername - unencrypted',
  `owner` bigint(20) unsigned NOT NULL COMMENT 'owner of the folder',
  `type` enum('Account','Organisation') NOT NULL DEFAULT 'Account' COMMENT 'What kind of owner it is',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `name_owner` (`name`,`owner`),
  KEY `FK_folder_accounts` (`owner`) USING BTREE,
  KEY `owner_type` (`owner`,`type`),
  CONSTRAINT `FK_folder_accounts` FOREIGN KEY (`owner`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='a folder to organize logins and notes';

CREATE TABLE IF NOT EXISTS `invites` (
  `aid` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'internally used id',
  `id` char(36) NOT NULL DEFAULT '' COMMENT 'uuid used in the invite',
  `mail` varchar(255) NOT NULL DEFAULT '' COMMENT 'email to invite',
  `secret` char(255) NOT NULL DEFAULT '' COMMENT 'one-time password',
  `inviter` bigint(20) unsigned NOT NULL COMMENT 'inviting user',
  `invitee` bigint(20) unsigned DEFAULT NULL COMMENT 'invited user',
  `created` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'time of creation',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `mail_inviter` (`mail`,`inviter`),
  KEY `mail_secret` (`mail`,`secret`),
  KEY `FK_invites_accounts` (`inviter`),
  KEY `FK_invites_accounts_2` (`invitee`),
  CONSTRAINT `FK_invites_accounts` FOREIGN KEY (`inviter`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `FK_invites_accounts_2` FOREIGN KEY (`invitee`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='invites between users to this platform';

CREATE TABLE IF NOT EXISTS `knowns` (
  `owner` bigint(20) unsigned NOT NULL COMMENT 'owner account id',
  `target` bigint(20) unsigned NOT NULL COMMENT 'target account id',
  `note` longblob NOT NULL COMMENT 'an AES encrypted note about the target user',
  `iv` longblob NOT NULL COMMENT 'RSA encrypted iv for the AES encryption',
  `key` longblob NOT NULL COMMENT 'RSA encrypted key for the AES encryption',
  PRIMARY KEY (`owner`,`target`) USING BTREE,
  KEY `FK_knownusers_accounts_2` (`target`) USING BTREE,
  CONSTRAINT `knowns_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `knowns_ibfk_2` FOREIGN KEY (`target`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='notes on users you encountered';

CREATE TABLE IF NOT EXISTS `logins` (
  `aid` bigint(20) unsigned NOT NULL COMMENT 'internal id',
  `id` char(36) NOT NULL COMMENT 'external id',
  `domain` longblob NOT NULL COMMENT 'for mapping',
  `login` longblob NOT NULL COMMENT 'encrypted login name',
  `pass` longblob NOT NULL COMMENT 'encrypted password',
  `folder` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'the folder this secret is in',
  `account` bigint(20) unsigned NOT NULL COMMENT 'owning user',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `id_account` (`id`,`account`),
  KEY `FK_logins_accounts` (`account`),
  KEY `FK_logins_folders` (`folder`),
  CONSTRAINT `FK_logins_accounts` FOREIGN KEY (`account`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `FK_logins_folders` FOREIGN KEY (`folder`) REFERENCES `folders` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='your login data';

CREATE TABLE IF NOT EXISTS `memberships` (
  `organisation` bigint(20) unsigned NOT NULL COMMENT 'organisation to be long to',
  `account` bigint(20) unsigned NOT NULL COMMENT 'account in organisation',
  `role` enum('Owner','Administrator','Member','Reader','Proposed') NOT NULL DEFAULT 'Proposed' COMMENT 'rights in this organisation',
  PRIMARY KEY (`organisation`,`account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='your status in a group';

CREATE TABLE IF NOT EXISTS `messages` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id` char(36) NOT NULL,
  `owner` bigint(20) unsigned NOT NULL COMMENT 'owner account id',
  `target` bigint(20) unsigned NOT NULL COMMENT 'target account id',
  `note` longblob NOT NULL COMMENT 'an AES encrypted note about the target user',
  `iv` longblob NOT NULL COMMENT 'RSA encrypted iv for the AES encryption',
  `key` longblob NOT NULL COMMENT 'RSA encrypted key for the AES encryption',
  `created` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'time of creation',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `id` (`id`),
  KEY `FK_knownusers_accounts` (`owner`),
  KEY `FK_knownusers_accounts_2` (`target`),
  CONSTRAINT `FK_knownusers_accounts` FOREIGN KEY (`owner`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `FK_knownusers_accounts_2` FOREIGN KEY (`target`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='a simple on site messaging system';

CREATE TABLE IF NOT EXISTS `notes` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id for internal use',
  `id` char(36) NOT NULL COMMENT 'uuid for external use',
  `content` longblob NOT NULL COMMENT 'AES encrypted content',
  `name` longblob NOT NULL COMMENT 'RSA encrypted name',
  `account` bigint(20) unsigned NOT NULL COMMENT 'account id of the owner',
  `folder` bigint(20) unsigned NOT NULL COMMENT 'folder the note belongs to',
  `iv` varbinary(255) NOT NULL COMMENT 'RSA encoded iv for decryption',
  `key` varbinary(255) NOT NULL COMMENT 'RSA encrypted key for decryption',
  PRIMARY KEY (`aid`),
  UNIQUE KEY `id` (`account`,`id`) USING BTREE,
  KEY `FK_notes_folders` (`folder`),
  CONSTRAINT `FK__accounts` FOREIGN KEY (`account`) REFERENCES `accounts` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `FK_notes_folders` FOREIGN KEY (`folder`) REFERENCES `folders` (`aid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='secret notes';

CREATE TABLE IF NOT EXISTS `organisations` (
  `aid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`aid`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='organizations share secrets among their members';