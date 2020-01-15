DROP TABLE IF EXISTS reset_codes;
CREATE TABLE reset_codes (
	id int(11) unsigned NOT NULL AUTO_INCREMENT,
	user int(11) unsigned NOT NULL,
	code int(11) unsigned NOT NULL,
	c_date DATETIME DEFAULT CURRENT_TIMESTAMP,
	is_active tinyint(1) DEFAULT 1,
	PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
