DROP TABLE IF EXISTS test_data;
CREATE TABLE test_data(
id int(11) unsigned NOT NULL AUTO_INCREMENT,
meta text,
value int(11),
c_date DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
