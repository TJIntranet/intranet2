<?php
	/**
	* The MySQL module for Iodine.
	* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
	* @copyright 2004 The Intranet 2 Development Team
	* @version 1.0
	* @since 1.0
	* @package mysql
	*/
	
	class MySQL {

		/**
		* Indicates that results should be greater than a value.
		*/
		const $GREATER_THAN = '>';
		/**
		* Indicates that results should be less than a value.
		*/
		const $LESS_THAN = '<';
		/**
		* Indicates that results should be equal to a value.
		*/
		const $EQUAL_TO = '=';
		/**
		* Indicates the sort order is descending.
		*/
		const $DESC = 'DESC';
		/**
		* Indicates the sort order is ascending.
		*/
		const $ASC = 'ASC';
		/**
		* Indicates an AND in a WHERE statement.
		*/
		const $AND = 'AND';
		/**
		* Indicates on OR in a WHERE statement.
		*/
		const $OR = 'OR';
		/**
		* Indicates a left parenthesis in a WHERE statement.
		*/
		const $LPAREN = '(';
		/**
		* Indicates a right parenthesis in a WHERE statement.
		*/
		const $RPAREN = ')';
		
		/**
		* The MySQL class constructor.
		* 
		* @access public
		*/
		function MySQL() {
			//TODO: Get config value here//
			$this->connect($blah, $blah2, $blah3);

		}

		/**
		* Converts/wraps a MySQL result object into an Intranet2 type.
		*
		* @access private
		* @param object $sql The MySQL resultset object.
		* @return object An Intranet2-MySQL result object.
		*/
		private function sql_to_result($sql) {
			//TODO:  Implement for real.  Decide on what results should be.
			return $sql;
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
			return mysql_pconnect($server, $user, $password);
		}
		
		/**
		* Select a MySQL database.
		*
		* @access protected
		* @param string $database The name of the database to select.
		*/
		protected function select_db($database) {
			mysql_select_db($database);
		}

		/**
		* Perform a preformatted MySQL query.  NOT FOR OUTSIDE USE!!!
		*
		* @param string $query The query string.
		*/
		protected function query($query) {
			return mysql_query($query);
		}

		protected function orderToString($ordering) {
			$str = "ORDER BY ";
			if (!$ordering) {
				return $str;
			}
			$first = true;
			foreach ($ordering as $item) {
				if ($first) {
					$first = false;
				} else {
					$str .= ',';
				}
				//$item = array(DESC,value), for example
				$ordertype = $item[0];
				$value = $item[1];
				$str .= "$value $ordertype";
			}
			return $str;
		}
		

		/**
		 * Converts a printf-style string and an array of values
		 * into a MySQL-type WHERE clause.
		 *
		 * @access protected
		 * @param string $format The format string.
		 * @param array $values An array of values.
		 */
		protected function whereToString($format,$values) {
			if (!$format || !$values) {
				return '';
			}
			$str = "WHERE ";
			/* Break the format string around &, |, (, ), =, >, or < signs 
			** except where they're escaped with \.
			*/
			$format = preg_split('/^([^\\][()&|=<>])$/',$format,-1,
				PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

			$parens_open = 0;
			$waiting_for_name = true;
			$got_equality = false;
			
			foreach ($format as $clause) {
				
				//Replace escaped sequences with their raw forms, and strip whitespace
				$clause = preg_replace('/^\s*\\([()&|=<>])\s*$/e',"'\\1'",$clause);
				
				/* Okay, now we have one of four things, either:
				** 	(a) A (, ), &, or | character alone
				** 	(b) A >, <, or = character alone
				**	(c) A column name
				** or 	(d) A value
				*/
				switch ($clause) {
					case $LPAREN: 
						$str .= " $LPAREN ";
						$parens_open++;
						break;
					case $RPAREN: 
						$str .= " $RPAREN ";
						$parens_open--;
						if ($parens_open < 0) {
							//TODO:  Roll over and die
						}
						break;
					case '&':
						if (!$waiting_for_name) {
							//TODO: fail
						}
						$waiting_for_name = true;
						$str .= " $AND ";
						break;
					case '|':
						if (!$waiting_for_name) {
							//TODO: throw error
						}
						$waiting_for_name = true;
						$str .= " $OR ";
						break;
					case $EQUAL_TO:
						if ($waiting_for_name || $got_equality) {
							//TODO: fail
						}
						$got_equality = true;
						$str .= " $EQUAL_TO ";
						break;
					case $LESS_THAN:
						if ($waiting_for_name || $got_equality) {
							//TODO: fail
						}
						$got_equality = true;
						$str .= " $LESS_THAN ";
						break;
					case $GREATER_THAN:
						if ($waiting_for_name || $got_equality) {
							//TODO: fail
						}
						$got_equality = true;
						$str .= " $GREATER_THAN ";
						break;
					default:
						if ($waiting_for_name) {
							//$clause should be a column name
							//TODO: prevent breaking MySQL here
							$str .= " $clause  ";
							$waiting_for_name = true;
							$got_inequality = false;
						} else {
							//$clause should be a value

							/* Catch %[character] and %[space] in their own little sections
							** with all the other stuff in between them.  This only grabs
							** one character.  I think that's all we need.
							*/
							$clause = preg_split('/([^\\]%[\S ])/',$clause,-1,PREG_SPLIT_DELIM_CAPTURE);
							
							foreach ($clause as $fragment) {
								if ($fragment{0} != '%') {
									$fragment = preg_replace('/\\%/','%',$fragment);
								} else {
									//This is a special formatty-thingy.  Let's fix it up.
									
									if (count($values) < 1) {
										//TODO: give the caller a piece of our mind.
									}
									
									$val = array_shift($values);	
									
									$char = $fragment[1];
									switch ($char) {
										case 'd':
											if (!is_int($val)) {
												//TODO: type error
											}
											$fragment = $val;
											break;
										case 's':
											if (!is_string($val)) {
												//TODO: type error
											}
											$fragment = "$val";
											break;
										case 'T':
											$fragment = "'CURRENT_TIMESTAMP'";
											break;
										case ' ':
											$fragment = ' ';
											break;
										case '%':
											//Hey, people will expect %% to create a %
											$fragment = '%';
											break;
										default:
											//TODO: throw a bad formatting error
											break;
									}
								}
								$fragment = addslashes($fragment);
								$str .= $fragment;
							}
							
							$waiting_for_name = true;
						}
						break;
				}
				
			}
		}

		/**
		* Issues a proporly formatted MySQL SELECT query, and returns the results.
		*
		* @param string $table The table to query.
		* @param array $columns The columns to select (all by default).
		* @param array $where The conditions on which to accept a row.  This should
		* be an associative array of two-element arrays consisting of a comparison operator
		* and a valueso that $where[$key] = array($comparitive,$value,$).
		* @param array $conditionals An array indicating how to match the $where argument's requirements.
		* It have appropriate grouping.  For the MySQL query segment
		* "WHERE name='david' AND (id='3' OR foo='bar') AND who='me'", the array would be:
		* array(AND,LPAREN,OR,RPAREN,AND).
		The following lines are from a discussion in an I2 meeting, not actual code.
		Example MySQL query: "SELECT * FROM blah WHERE mycol=`myval123` ORDER BY mykey ASC;"
		<cfquery datasource="blah">
		SELECT * FROM blah WHERE
		mycol=<cfqueryparam type='CFSQLPARAM_INT' value='myval123' />
		ORDER BY mykey ASC;;

		</cfquery>
		$I2_SQL->select("mytable", "*", "mycol=" . $I2_SQL::param('int','myval123'));
		$I2_SQL->select("mytable", "*", "mycol=`%d`", array("myval123"));
		
		* @param array $ordering The desired sort order of the resultset as an array of arrays
		* , with each subarray being array($ordertype,$column).
		*/
		function select($table, $columns = false, $where = false, $conditionals = false, $ordering = false) {
			/*
			** Build a (hopefully valid) MySQL query from the arguments.
			*/
			$q = "SELECT ";
			if (!$columns) {
				$q .= '*';
			} else {
				$first = true;
				foreach ($columns as $col) {
					if ($first) {
						$first = false;
					} else {
						$q .= ',';
					}
					$q .= $col;
				}
			}
			
			$q .= " FROM $table";
			
			if ($where) {
				$q .= " WHERE ";
				$first = true;
				foreach ($where as $key=>$subarray) {
					if ($first) {
						$first = false;
						while ($conditionals && $conditionals[0] == LPAREN) {
							$q .= array_shift($conditionals);
						}
					} else {
						if ($conditionals) {
							$poss = array_shift($conditionals);
							while ($poss == LPAREN || $poss = RPAREN) {
								$q .= $poss;
								//TODO: array bounds checking
								$poss = array_shift($conditionals);
							}
							$q .= " $poss ";
						}
					}
					$comptype = $subarray[0];
					$value = addslashes($subarray[1]); //Is addslashes() good enough?
					$q .= "$key $comptype '$value'";
				}
				while ($conditionals && ($poss = array_shift($conditionals))) {
					//if ($poss != RPAREN) { err('Bad parens.') }
					$q .= $poss;
				}
			}
			
			$q .= ' ';
			$q .= orderToString($ordering);

			//Glad that's over with.  Now, we query the database.
			
			return sql_to_result(query($q));
		}

		/**
		* Perform a MySQL INSERT query.
		*
		* @param string $table The table to insert into.
		* @param mixed $cols_to_vals An associative array of columns to values (or just columns if $values is provided).
		* @param array $values An array of values.
		* @return A result object.
		*/
		function insert($table, $cols, $values = false) {
			
			/*
			** If $values wasn't passed, break up $cols into $cols and $values. 
			*/
			if (!$values) {
				$values = array_values($cols);
				$cols = array_keys($cols);
			}

			/*
			** Build the query.
			*/
			
			$q = "INSERT INTO $table(";
			$first = true;
			foreach ($cols as $col) {
				if ($first) {
					$first = false;
				} else {
					$q .= ',';
				}
				$q .= $col;
			}
			$q .= ') VALUES(';
			$first = true;
			foreach ($values as $val) {
				if ($first) {
					$first = false;
				} else {
					$q .= ',';
				}
				$var = addslashes($val);
				$q .= "'$val'";
			}
			$q .= ')';

			return sql_to_result(query($q));
			
		}

		/**
		* Performs a MySQL UPDATE statement.
		*
		* @param string $table The table to update.
		* @param mixed $columns An associative array of columns to values, or an array of columns if $values is provided.
		* @param array $values An array of values.
		* @param array $where An associative array of arrays containing condition-match pairs, so that
		* $where[$key] = array($comparetype,$value).
		* TODO: document $conditionals.
		*/
		function update($table, $columns, $values = false, $where = false, $conditionals = false) {
			/*
			** If $values isn't present, expand $columns to $columns and $values.
			*/
			
			if (!$values) {
				foreach (array_values($columns) as $val) {
					$values = $val;
				}
				$columns = array_keys($columns);
			}

			/*
			** Build a query.
			*/

			$q = "UPDATE $table SET ";

			//if (array_len($columns) != array_len($values)) { error("uh-oh!"); }
			
			$first = true;
			for ($i = 0; $i < array_len($columns); $i++) {
				if ($first) {
					$first = false;
				} else {
					$q .= ',';
				}
				$val = addslashes($values[$i]);
				$q .= "$columns[$i] = '$val'";
			}

			//TODO: implement $where and $conditionals
			

			return sql_to_result(query($q));
		}
		
		/**
		* Perform a MySQL DELETE statement.  You must provide at least one condition.
		*
		* @param string $table The name of the table to delete from.
		* @param array $where An associative array of names to condition/value pairs,
		* so that $where[$column] = array($matchtype,$value).
		* TODO: document $conditionals.
		*/
		function delete($table, $where = false, $conditionals = false) {
			
			//if (!$where) { error("uh-oh!"); } 

			$q = "DELETE FROM $table WHERE ";
			
			//TODO: implement 
		}

	}

?>
