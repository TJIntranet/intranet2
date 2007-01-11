DROP TABLE IF EXISTS events;
CREATE TABLE events(
	id INTEGER UNSIGNED NOT NULL DEFAULT NULL AUTO_INCREMENT UNIQUE,
	PRIMARY KEY(id),
	title VARCHAR(255) NOT NULL,
	description TEXT NOT NULL,
	amount DECIMAL(5, 2) NOT NULL,
	startdt DATETIME NOT NULL,
	enddt DATETIME NOT NULL,
	public TINYINT(1) NOT NULL DEFAULT 1
);
