/* Copyright (c) 2017,2018 Aron Heinecke
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * Execute on upgrade from 0.1.1
 */
ALTER TABLE `member_names` ADD `updated` datetime;
UPDATE `member_names` SET `updated` = `date`;
ALTER TABLE `member_names` ADD KEY `updated` (`updated`);
ALTER TABLE `member_names` DROP KEY `date`;
ALTER TABLE `member_names` MODIFY `updated` datetime NOT NULL;

/*
 * Execute on upgrade from 0.1.2
 * please also run clantool checkdb
 */
CREATE TABLE `missing_entries` (
 `date` datetime NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*
 * Exceute on upgrade from 0.1.2/0.1.3
 * just performance improvement
 */
ALTER TABLE member_names ADD KEY(`updated`,`id`);
ALTER TABLE missing_entries ADD `member` bit(1) DEFAULT TRUE;
ALTER TABLE missing_entries MODIFY COLUMN `member` bit(1) NOT NULL;

/*
 * Exceute on upgrade from 0.1.4
 * performance improvement
 */
ALTER TABLE member_addition ADD KEY(`name`);

/*
 * Execute on upgrade from 0.1.5
 * adding caution table
 */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*
 * Execute on upgrad from 0.3.0
 * adding TS identity check
 */
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
