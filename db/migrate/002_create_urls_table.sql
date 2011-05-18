CREATE TABLE IF NOT EXISTS `urls` (
  `key` char(10) character set ascii collate ascii_bin NOT NULL,
  `base_url` char(150) character set ascii collate ascii_bin NOT NULL,
  `full_url` char(255) character set ascii collate ascii_bin NOT NULL,
  `create_date` datetime default NULL,
  PRIMARY KEY  (`key`),
  KEY `create_date` (`create_date`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii COLLATE=ascii_bin;