/* Copyright (c) 2016, 2018 Aron Heinecke
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * This file is parsed & compiled into clanntol-backend
 */
CREATE TABLE `clan` (
 `date` datetime NOT NULL,
 `wins` int(11) NOT NULL,
 `losses` int(11) NOT NULL,
 `draws` int(11) NOT NULL,
 `members` int(11) NOT NULL,
 PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member` (
 `id` int(11) NOT NULL,
 `date` datetime NOT NULL,
 `exp` int(10) unsigned NOT NULL,
 `cp` int(11) NOT NULL,
 PRIMARY KEY (`id`,`date`) USING BTREE,
 KEY `id` (`id`),
 KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member_names` (
 `id` int(11) NOT NULL,
 `name` varchar(12) NOT NULL, /* account name */
 `date` datetime NOT NULL,
 `updated` datetime NOT NULL,
 UNIQUE KEY `id` (`id`,`name`),
 KEY `name` (`name`),
 KEY `updated` (`updated`),
 KEY `updated_2` (`updated`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `missing_entries` (
 `date` datetime NOT NULL PRIMARY KEY,
 `member` bit(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member_addition` (
 `id` int(11) NOT NULL,
 `name` varchar(25) NOT NULL, /* person first name */
 `vip` bit(1) NOT NULL,
 `comment` text,
 `diff_comment` VARCHAR(70),
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `membership_cause` (
 `nr` INT NOT NULL,
 `kicked` bit(1) NOT NULL,
 `cause` varchar(200),
 PRIMARY KEY (`nr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `membership` (
 `nr` INT NOT NULL AUTO_INCREMENT,
 `id` int(11) NOT NULL,
 `from` date NOT NULL,
 `to` date,
 PRIMARY KEY (`nr`),
 UNIQUE KEY `idf` (`id`,`from`),
 KEY `id` (`id`),
 KEY `from` (`from`),
 KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ts_relation` (
 `id` int(11) NOT NULL,
 `client_id` int(11) NOT NULL,
 PRIMARY KEY (`id`,`client_id`),
 KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `second_acc` (
 `id` int(11) NOT NULL,
 `id_sec` int(11) NOT NULL,
 PRIMARY KEY (`id`,`id_sec`),
 KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `afk` (
 `id` int(11) NOT NULL,
 `from` date NOT NULL,
 `to` date NOT NULL,
 `added` datetime NOT NULL,
 `cause` text,
 PRIMARY KEY (`id`,`from`,`to`),
 KEY `id` (`id`),
 KEY `from` (`from`),
 KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `caution` (
  `id` int(11) NOT NULL,
  `from` date NOT NULL,
  `to` date NOT NULL,
  `added` datetime NOT NULL,
  `cause` text,
  PRIMARY KEY (`id`,`from`),
  KEY `id` (`id`),
  KEY `from` (`from`),
  KEY `to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `member_trial` (
 `id` int(11) NOT NULL,
 `from` date NOT NULL,
 `to` date,
 PRIMARY KEY (`id`,`from`),
 KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `log` (
 `date` datetime NOT NULL,
 `msg` text NOT NULL,
 KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
 `key` VARCHAR(50) NOT NULL,
 `value` VARCHAR(250) NOT NULL,
 PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `unknown_ts_ids` (
  `client_id` int(11) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ignore_ts_ids` (
  `client_id` int(11) NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW `unknown_ts_unignored` AS
SELECT `t`.`client_id` from `unknown_ts_ids` t where `t`.`client_id` NOT IN (
    select `ignore_ts_ids`.`client_id` from `ignore_ts_ids`
);

/* module for ranked */

CREATE TABLE `ranks` (
  `usn` int(11) NOT NULL,
  `season` int(11) NOT NULL,
  `mode` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  `subrank` int(11) NOT NULL,
  PRIMARY KEY (`usn`,`season`,`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `mode_names` (
  `mode` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rank_names` (
  `rank` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW `ranked` AS
SELECT mn.name as player_name,usn,season,r.mode,r.rank,subrank,
    n.name as mode_name,rn.name as rank_name from ranks r
JOIN mode_names n ON r.mode = n.mode
JOIN rank_names rn ON r.rank = rn.rank
JOIN member_names mn ON r.usn = mn.id AND mn.updated = (
    SELECT MAX(updated) FROM member_names mnl WHERE mnl.id = r.usn
);
