CREATE TABLE `owners` (
                          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                          `name` varchar(255) NOT NULL,
                          `factor` int NOT NULL,
                          `denominator` int NOT NULL,
                          `created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE='InnoDB' COLLATE 'utf8_general_ci';