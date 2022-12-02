CREATE TABLE `companies` (
                             `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                             `profit` float NOT NULL,
                             `created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE='InnoDB' COLLATE 'utf8_general_ci';