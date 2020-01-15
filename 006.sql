ALTER TABLE users
ADD COLUMN password_reset_attempts int(3) unsigned default 0;
