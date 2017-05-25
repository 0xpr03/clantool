/* Copyright (c) 2016, Aron Heinecke
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
CREATE TABLE `clan` (
 `date` datetime NOT NULL,
 `wins` int(11) NOT NULL,
 `losses` int(11) NOT NULL,
 `draws` int(11) NOT NULL,
 `members` int(11) NOT NULL,
 PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `member` (
 `id` int(11) NOT NULL,
 `date` datetime NOT NULL,
 `exp` int(10) unsigned NOT NULL,
 `cp` int(11) NOT NULL,
 PRIMARY KEY (`id`,`date`) USING BTREE,
 KEY `id` (`id`),
 KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `member_names` (
 `id` int(11) NOT NULL,
 `name` varchar(12) NOT NULL,
 `date` datetime NOT NULL,
 UNIQUE KEY `id` (`id`,`name`),
 KEY `name` (`name`),
 KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
