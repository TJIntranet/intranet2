DROP TABLE IF EXISTS nags;
CREATE TABLE nags(
	nid MEDIUMINT UNSIGNED NOT NULL DEFAULT NULL AUTO_INCREMENT,
	PRIMARY KEY(nid),
	name TEXT NOT NULL,
	locations MEDIUMTEXT NOT NULL
);
