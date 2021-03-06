DROP TABLE IF EXISTS poll_responses;
CREATE TABLE poll_responses (
		  pid MEDIUMINT(8) UNSIGNED NOT NULL,
		  qid TINYINT(4) UNSIGNED NOT NULL, 
		  aid SMALLINT(4) UNSIGNED NOT NULL,
		  PRIMARY KEY(pid,qid,aid),
		  answer MEDIUMTEXT NOT NULL DEFAULT ''
);
