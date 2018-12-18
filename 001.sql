DROP TABLE IF EXISTS user_contacts;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS records;
DROP TABLE IF EXISTS invitations;
DROP TABLE IF EXISTS updates;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id int(11) unsigned NOT NULL AUTO_INCREMENT,
	given_name varchar(255) DEFAULT '',
    family_name varchar(255) DEFAULT '',
    password varchar(255) NOT NULL,
    email varchar(255) DEFAULT NULL,
    phone varchar(16) NOT NULL,
	emails text DEFAULT '',
	phones text DEFAULT '',
    addresses text DEFAULT '',
    c_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    m_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_verified TINYINT(1) DEFAULT 0,
	follow_approval TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE contacts (
    user int(11) unsigned NOT NULL,
    contact int(11) unsigned NOT NULL,
	local_id varchar(255) DEFAULT NULL,
	status enum('requested', 'pending', 'accepted'),
	info_shared text,
	last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
	meta text,
    PRIMARY KEY (user, contact),
    KEY fk_user_contacts_user (user),
    KEY fk_user_contacts_contact (contact),
    CONSTRAINT fk_user_contacts_user FOREIGN KEY (user) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_contacts_contact FOREIGN KEY (contact) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE records (
	id int(11) unsigned NOT NULL AUTO_INCREMENT,
	record_type enum('follow_request', 'follow_accept', 'info_update', 'roladex_invite'),
	origin_user int(11) unsigned NOT NULL,
	target_user int(11) unsigned NOT NULL,
	c_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	v_date TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
	viewed_by_target TINYINT(11) DEFAULT 0,
	meta text DEFAULT NULL,
	PRIMARY KEY (id),
	KEY fk_records_origin_user (origin_user),
	KEY fk_records_target_user (target_user),
	CONSTRAINT fk_records_origin_user FOREIGN KEY (origin_user) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_records_target_user FOREIGN KEY (target_user) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE invitations (
	inviter int(11) unsigned NOT NULL,
	invitee_phone varchar(255) NOT NULL,
	c_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (inviter, invitee_phone),
	KEY fk_invitations_inviter (inviter),
	CONSTRAINT fk_invitations_inviter FOREIGN KEY (inviter) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE updates (
	id int(11) unsigned NOT NULL AUTO_INCREMENT,
	user int(11) unsgined NOT NULL;
	meta text,
	c_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	KEY fk_updates_user (user),
	CONSTRAINT fk_updates_user FOREIGN KEY (user) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
	
