DROP TABLE IF EXISTS eighth_absentees;
CREATE TABLE eighth_absentees (
	bid MEDIUMINT UNSIGNED NOT NULL,

	userid MEDIUMINT UNSIGNED NOT NULL,

	PRIMARY KEY(bid,userid)
);
