CREATE TABLE `users` (
  `login` char(50) collate ascii_bin NOT NULL,
  `hash` char(32) collate ascii_bin NOT NULL,
  PRIMARY KEY  (`login`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii COLLATE=ascii_bin;