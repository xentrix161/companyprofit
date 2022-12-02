CREATE TABLE `owners_in_companies` (
                                       `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                       `owner_id` int(11) NOT NULL,
                                       `company_id` int(11) NOT NULL,
                                       `created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
                                       FOREIGN KEY (`owner_id`) REFERENCES `owners` (`id`),
                                       FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE='InnoDB' COLLATE 'utf8_general_ci';