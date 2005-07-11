<?php
/**
* Contains the definition for the class {@link MySQL}.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2004 The Intranet 2 Development Team
* @version $Id: mysql.class.php5,v 1.19 2005/07/11 07:06:08 adeason Exp $
* @package core
* @subpackage MySQL
* @filesource
*/

/**
* The MySQL module for Iodine.
* @package core
* @subpackage MySQL
* @see Result
*/
class MySQL {

	/**
	* The mysql_pconnect() link
	*/
	private $link;
	
	/**
	* Represents a SELECT query.
	*/
	const SELECT = 1;
	/**
	* Represents an INSERT query.
	*/
	const INSERT = 2;
	/**
	* Represents an UPDATE query.
	*/
	const UPDATE = 3;
	/**
	* Represents a DELETE query.
	*/
	const DELETE = 4;

	/**
	* A string representing all custom printf tags for mysql queries which require an argument. Each character represents a different tag.
	*/
	const TAGS_ARG = 'adsi';

	/**
	* A string representing all custom printf tags for mysql queries which do not require an argument. Each character represents a different tag.
	*/
	const TAGS_NOARG = 'V%';
		
	
	/**
	* The MySQL class constructor.
	* 
	* @access public
	*/
	function __construct() {
		$this->connect(i2config_get('server','','mysql'), i2config_get('user','','mysql'), i2config_get('pass','','mysql'));
		$this->select_db('iodine');
	}

	/**
	* Connects to a MySQL database server.
	*
	* @access protected
	* @param string $server The MySQL server location/name.
	* @param string $user The MySQL username.
	* @param string $password The MySQL password.
	*/
	protected function connect($server, $user, $password) {
		d("Connecting to mysql server $server as $user");
		$this->link = mysql_pconnect($server, $user, $password);
		return $this->link;
	}
	
	/**
	* Select a MySQL database.
	*
	* @access protected
	* @param string $database The name of the database to select.
	*/
	protected function select_db($database) {
		mysql_select_db($database, $this->link);
	}

	/**
	* Perform a preformatted MySQL query.
	*
	* @param string $query The query string.
	* @return resource A raw mysql result resourse.
	*/
	protected function raw_query($query) {
		global $I2_ERR;
		$r = mysql_query($query, $this->link);
		if ($err = mysql_error($this->link)) {
			throw new I2Exception('MySQL error: '.$err);
			return false;
		}
		return $r;
	}

	/**
	* Printf-style MySQL query function.
	*
	* This takes a string and args. The string is the actual MySQL query
	* with optional printf-style markers to indicate values that should be
	* checked (or formatted in a certain way). Any other arguments after
	* that are the printf-style arguments. For example:
	*
	* <code>
	* query('SELECT * FROM mytable WHERE id=%d', $the_id);
	* </code>
	*
	* Will essentially execute the query
	* 'SELECT * FROM mytable WHERE id=`$the_id`' except it will check that
	* $the_id is a valid integer.
	*
	* The printf-style tags implemented are:
	* <ul>
	* <li>%a - A string which only contains alphanumeric characters</li>
	* <li>%d or %i - An integer, or an integer in a string</li>
	* <li>%s - A string, which will be quoted, and escapes all necessary
	* characters for use in a mysql statement</li>
	* <li>%V - Outputs the current Iodine version</li>
	* <li>%% - Outputs a literal '%'</li>
	* </ul>
	*
	* @access public
	* @param string $query The printf-ifyed query you want to run.
	* @param mixed $args,... Arguments for printf tags.
	* @return Result The results of the query run.
	*/
	public function query($query) {
		global $I2_ERR,$I2_LOG;

		$argc = func_num_args()-1;
		$argv = func_get_args();
		array_shift($argv);
		
		/* matches Iodine custom printf-style tags */
		if( preg_match_all(
			'/(?<!%)%['.self::TAGS_ARG.self::TAGS_NOARG.']/',
			$query,
			$tags,
			PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE )
		) {
			foreach ($tags[0] as $tag) {
				/*$tag[0] is the string, $tag[1] is the offset*/
				
				/* tags that require an argument */
				if ( strpos(self::TAGS_ARG, $tag[0][1]) ) {
					if($argc < 1) {
						throw new I2Exception('Insufficient arguments to mysql query string');
					}
					$arg = array_shift($argv);
					$argc--;
				}

				/* Now substitute the tag depending on which tag
				was matched. $arg is the argument, if the tag
				needs one, and $replacement is the string to
				replace the tag with*/
				switch($tag[0][1]) {
					/* 'argument' tags first */
					
					/*alphanumeric string*/
					case 'a':
						if ( !ctype_alnum($arg) ) {
							throw new I2Exception('String `'.$arg.'` contains non-alphanumeric characters, and was passed as an %a string in a mysql query');
							$replacement = '';
						}
					case 's':
						$replacement = '\''.mysql_real_escape_string($arg).'\'';
						break;

					/* integer*/
					case 'd':
					case 'i':
						if (	is_int($arg) ||
							ctype_digit($arg) ||
							(ctype_digit(substr($arg,1)) && $arg[0]=='-') //negatives
						) {
							$replacement = ''.$arg;
						}
						else {
							throw new I2Exception('The string `'.$arg.'` is not an integer, but was passed as %d or %i in a mysql query');
							$replacement = '0';
						}
						break;
					
					/* Non-argument tags below here */
					
					/*Iodine version string*/
					case 'V':
						$replacement = 'TJHSST Intranet2 Iodine version '.I2_VERSION;
						break;
					case '%':
						$replacement = '%';
						break;
					
					/* sanity check */
					default:
						$I2_ERR->fatal_error('Internal error, undefined mysql printf tag `%'.$tag[0][1].'`', TRUE);
				}

				$query = substr_replace($query,$replacement,$tag[1],2);
			}
		}

		/* Get query type by examining the query string up to the first
		space */
		switch( strtoupper(substr($query, 0, strpos($query, ' '))) ) {
			case 'SELECT':
				$perm = 'r';
				$query_t = MYSQL::SELECT;
				break;
			case 'UPDATE':
				$perm = 'w';
				$query_t = MYSQL::UPDATE;
				break;
			case 'DELETE':
				$perm = 'd';
				$query_t = MYSQL::DELETE;
				break;
			case 'INSERT':
				$perm = 'i';
				$query_t = MYSQL::INSERT;
				break;
			default:
				throw new I2Exception('Attempted MySQL query of unauthorized command `'.substr($query, 0, strpos($query, ' ')).'`');
		}

		return new Result($this->raw_query($query),MYSQL::SELECT);
	}
	
	/**
	* Determines whether a certain column is in a certain table.
	*
	* @param string $table The mysql table where the column might be.
	* @param string $col The name of the column you are searching for.
	* @return bool TRUE if $col is in table $table, FALSE otherwise.
	*/
	public function column_exists($table, $col) {
		foreach(mysql_fetch_array($this->raw_query('DESCRIBE '.$table.';'), MYSQL_ASSOC) as $field) {
			if( $field['Field'] = $col ) {
				return TRUE;
			}
		}
		return FALSE;
	}
}

?>
