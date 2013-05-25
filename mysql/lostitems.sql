DROP TABLE IF EXISTS lostitems;
CREATE TABLE lostitems(
	id BIGINT(20) UNSIGNED DEFAULT NULL,
	title VARCHAR(255) DEFAULT NULL,
	ownerID MEDIUMINT(8) UNSIGNED DEFAULT NULL,
	posted TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	text TEXT
);