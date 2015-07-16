CREATE TABLE `packages` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `aftership_id` varchar(255) DEFAULT NULL,
  `tracking` varchar(255) DEFAULT NULL,
  `carrier` varchar(255) DEFAULT NULL,
  `method` varchar(255) DEFAULT NULL,
  `shipped` datetime DEFAULT NULL,
  `delivery` datetime DEFAULT NULL,
  `delivery_confirmed` tinyint(1) DEFAULT '0',
  `status` varchar(255) DEFAULT NULL,
  `shipper` varchar(255) DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `contents` text,
  `checkpoints` text,
  PRIMARY KEY (`idx`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
