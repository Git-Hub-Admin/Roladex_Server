DROP TABLE IF EXISTS verification_numbers;

CREATE TABLE verification_numbers (
	id int(11) unsigned NOT NULL AUTO_INCREMENT,
	phone varchar(16) NOT NULL,
	user int(11) unsigned NOT NULL,
	code int(11) unsigned NOT NULL,
	c_date DATETIME DEFAULT CURRENT_TIMESTAMP,
	is_active tinyint(1) DEFAULT 1,
	PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
