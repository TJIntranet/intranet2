DROP TABLE IF EXISTS CEEBMap;
CREATE TABLE CEEBMap (
	CEEB MEDIUMINT(8) NOT NULL DEFAULT 0,
	PRIMARY KEY (CEEB),
	CollegeName VARCHAR(64) DEFAULT NULL
);
