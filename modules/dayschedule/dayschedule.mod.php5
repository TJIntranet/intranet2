<?php
/**
* the DaySchedule module, which shows a daily and week schedule,
* as well as the current class period you're in during a school day.
* @copyright 2013 The Intranet Development Team
* @package modules
* @subpackage DaySchedule
*/

class DaySchedule extends Module {
	
	/**
	* The TJ CalendarWiz iCal URL, which is used to find out
	* what type of day it is.
	**/
	private static $iCalURL = 'https://www.calendarwiz.com/CalendarWiz_iCal.php?crd=tjhsstcalendar';
	// for when CalendarWiz breaks things
	//private static $iCalURL = 'http://174.122.109.75/CalendarWiz_iCal.php?crd=tjhsstcalendar';

	/**
	* In seconds, how long the cached iCal should be saved.
	* Currently 1 hour.
	**/
	private static $cache_length = 3600;

	/**
	* Values of the SUMMARY iCal field, which are then mapped
	* to IDs for the type of day (as defined in $default_day_schedules)
	* Custom summaries should be stored in MySQL
	**/
	private static $summaries = array(
		"Anchor Day" => "anchor",
		"Blue Day" => "blue",
		"Red Day" => "red",
		"JLC Blue" => "jlc",
		"Tele-Learn Day (Anchor)" => "telelearn",
		"School Closed" => "schoolclosed",
		"No School" => "noschool"
	);

	/**
	* Maps a schedule type to a pretty name
	* (e.g. jlc to JLC Blue Day and not JLC Blue)
	* Custom summaries should be in MySQL
	**/
	private static $pretty_summaries = array(
		// "jlc" => "JLC Blue Day"
	);
	
	/**
	* Defines days that should use a schedule
	* other than the one defined in CalendarWiz
	**/
	private static $override_schedules = array(
	//	"20131127" => "telelearnshort"
	);
	/**
	* The default schedule information (name, periods) for default days
	* Custom schedules should be stored in MySQL!
	**/
	private static $schedules = array(
		"blue" => array(
			array("Period 1", "8:30", "10:05"),
			array("Period 2", "10:15", "11:45"),
			array("Lunch", "11:45", "12:30"),
			array("Period 3", "12:30", "2:05"),
			array("Break", "2:05", "2:20"),
			array("Period 4", "2:20", "3:50")
		),
		"red" => array(
			array("Period 5", "8:30", "10:05"),
			array("Period 6", "10:15", "11:45"),
			array("Lunch", "11:45", "12:30"),
			array("Period 7", "12:30", "2:05"),
			array("Break", "2:05", "2:20"),
			array("Period 8A", "2:20", "3:00"),
			array("Period 8B", "3:10", "3:50")
		),
		"anchor" => array(
			array("Period 1", "8:30", "9:15"),
			array("Period 2", "9:25", "10:05"),
			array("Period 3", "10:15", "10:55"),
			array("Period 4", "11:05", "11:45"),
			array("Lunch", "11:45", "12:35"),
			array("Period 5", "12:35", "1:15"),
			array("Period 6", "1:25", "2:05"),
			array("Period 7", "2:15", "2:55"),
			array("Break", "2:55", "3:10"),
			array("Period 8B", "3:10", "3:50")
		),
		"jlc" => array(
			array("JLC", "8:00", "8:55"),
			array("Period 1", "9:00", "10:28"),
			array("Period 2", "10:37", "12:05"),
			array("Lunch", "12:05", "12:45"),
			array("Period 3", "12:45", "2:13"),
			array("Break", "2:13", "2:22"),
			array("Period 4", "2:22", "3:50")
		),
		"error" => array('error' => 'No schedule information available.'),
		"noschool" => array(),
		"schoolclosed" => array()
	);

	/**
	* A to-be-filled array containing the types of days for each day
	**/
	private static $dayTypes = array();

	/**
	* A to-be-filled array containing the summary for each day
	**/
	private static $daySummaries = array();

	/**
	* Contains the fetched ics file
	**/
	private static $icsStr = "";

	/**
	* A to-be-filled array containing the parsed ics file
	**/
	private static $icsArr = array();

	/**
	* The template arguments
	**/
	private static $args = array(
		"alljs"=>false,
		"allcss"=>false
	);

	/**
	* The displayed name of the module (required)
	**/
	function get_name() {
		return "Day Schedule";
	}

	/**
	* General initilization
	**/
	public static function init() {
		global $I2_QUERY;
		self::$args['iframe'] = isset($I2_QUERY['iframe']);
		self::$args['afd'] = isset($I2_QUERY['afd']);
		self::$args['alljs'] = isset($I2_QUERY['alljs'])||self::$args['iframe'];
		self::$args['allcss'] = isset($I2_QUERY['allcss'])||self::$args['iframe'];
		/* set the day that we are querying */
		if(isset($I2_QUERY['date'])) {
			self::$args['date'] = $I2_QUERY['date'];
		} else {
			self::$args['date'] = date('Ymd');
		}
		self::check_show_next_day();
		/* load the custom sql entries */
		self::fetch_custom_entries();

		/* get the parsed iCal */
		$ical = self::convert_to_array(self::fetch_ical());
		/* find the day types */
		self::find_day_types($ical);
	}

	/**
	* Get $args (used for login page)
	* @return Array the template arguments
	**/
	public static function get_args() {
		return self::$args;
	}

	/**
	* Do initialization for the login page
	**/
	public static function init_login() {
		self::init();
		self::gen_day_args();
	}

	/**
	* Initialization for the pane goes here
	**/
	function init_pane() {
		global $I2_ARGS, $I2_USER;
		if(isset($I2_ARGS[1]) && $I2_ARGS[1] == 'admin') {
			if($I2_USER->is_group_member('admin_all') || $I2_USER->is_group_member('admin_dayschedule')) {
				return "".$this->admin();
			} else {
				throw new I2Exception("You aren't an admin.");
			}
		}
		self::init();
		return "Day Schedule";
	}

	/**
	* Displaying of the pane goes here
	**/
	function display_pane($disp) {
		if(isset(self::$args['template'])) {
			$disp->disp(self::$args['template'], self::$args);
			return;
		}
		self::gen_day_args();
		$disp->disp('pane.tpl', self::$args);
	}

	/**
	* Initialization of the intrabox
	**/
	function init_box() {
		self::init();
		self::$args['type'] = 'box';
		return "Day Schedule";
	}

	/**
	* Displaying of the intrabox
	**/
	function display_box($disp) {
		self::gen_day_args();
		$disp->disp('pane.tpl', self::$args);

	}

	/**
	* Used for accessing data over the Iodine "API"
	**/
	function api() {
		global $I2_API, $I2_ARGS;
		self::init();
		self::gen_day_args();
		$schedule = self::$args['schedule'];


		$I2_API->writeElement('dayname', self::$args['dayname']);
		$I2_API->writeElement('summary', self::$args['summary']);

		$I2_API->startElement('date');
		$I2_API->writeAttribute('tomorrow', date('Ymd', strtotime('+1 day', strtotime(self::$args['date']))));
		$I2_API->writeAttribute('yesterday', date('Ymd', strtotime('-1 day', strtotime(self::$args['date']))));
		$I2_API->text(self::$args['date']);
		$I2_API->endElement();
		
		$I2_API->startElement('schedule');
		$I2_API->writeAttribute('date', self::$args['date']);
		$i = 1;
		foreach($schedule as $s) {
			$I2_API->startElement('period');
			$I2_API->writeAttribute('num', $i++);
			$I2_API->writeElement('name', $s[0]);

			$I2_API->startElement('times');
			$I2_API->writeAttribute('start', $s[1]);
			$I2_API->writeAttribute('end', $s[2]);
			$I2_API->text($s[1].' - '.$s[2]);
			$I2_API->endElement();

			$I2_API->endElement();
		}
		$I2_API->endElement();

	}


    function ajax_json_day() {
        self::gen_day_args();
        $schedule = self::$args['schedule'];
        $json = array();
        $json['dayname'] = self::$args['dayname'];
        $json['summary'] = self::$args['summary'];

        $json['date'] = array(
            'tomorrow' => date('Ymd', strtotime('+1 day', strtotime(self::$args['date']))),
            'yesterday' => date('Ymd', strtotime('-1 day', strtotime(self::$args['date']))),
            'today' => self::$args['date']
        );

        $json['schedule'] = array(
            'date' => self::$args['date'],
            'period' => array()
        );
        $i = 1;
        foreach($schedule as $s) {
            $json['schedule']['period'][] = array(
                'num' => $i++,
                'name' => $s[0],
                'times' => array(
                    'start' => $s[1],
                    'end' => $s[2],
                    'times' => $s[1].' - '.$s[2]
                )
            );
        }
        return $json;
    }

    /**
	* Used for accessing data on the client side through AJAX
	**/
	function ajax() {
		global $I2_FS_ROOT, $I2_ARGS, $I2_QUERY;
		$disp = new Display('dayschedule');
		
		/**
		* Do initialization which is needed for the following code
		* to work (specifically fetching the custom SQL and iCal)
		**/
		
		self::init_pane();
        if(isset($I2_ARGS[2]) && $I2_ARGS[2] == 'json_exp') {
            set_time_limit(2); // in case some idiot has $start > $end..
            $json = array();
            $start = $I2_QUERY['start'];
            $end = $I2_QUERY['end'];
            if(!isset($I2_QUERY['start']) || !isset($I2_QUERY['end'])) die(json_encode(array("Invalid")));
            $date = $start;
            while($date != $end) {
                self::$args['date'] = $date;
                $json[$date] = self::ajax_json_day();
                $date = date('Ymd', strtotime(isset($I2_QUERY['incr']) ? $I2_QUERY['incr'] : '+1 day', strtotime(self::$args['date'])));
            }
            header("Content-type: application/json");
            if(isset($I2_QUERY['callback'])) {
                echo $I2_QUERY['callback']."(".json_encode($json).");";
            } else {
                echo json_encode($json);
            }
        } else if(isset($I2_ARGS[2]) && $I2_ARGS[2] == 'json') {
			$json = self::ajax_json_day();
            header("Content-type: application/json"); 
            if(isset($I2_QUERY['callback'])) {
				echo $I2_QUERY['callback']."(".json_encode($json).");";
			} else {
			    echo json_encode($json);
            }
		} else if(isset($I2_ARGS[2]) && $I2_ARGS[2] == 'week') {
			$week = array();
			$d = isset($I2_QUERY['days']) ? $I2_QUERY['days'] : 5;
			for($i=0; $i<$d; $i++) {
				self::gen_day_args();
				$week[] = $disp->fetch($I2_FS_ROOT . 'templates/dayschedule/pane.tpl', self::$args, false);
				/* make date be the next day */
				self::increment_date('+1 day');
			}
			echo json_encode($week);
        } else if(isset($I2_ARGS[2]) && $I2_ARGS[2] == 'exp') {
            $week = array();
            $d = isset($I2_QUERY['days']) ? $I2_QUERY['days'] : 5;
            for($i=0; $i<$d; $i++) {
                self::gen_day_args();
                $week[] = $disp->fetch($I2_FS_ROOT . 'templates/dayschedule/pane.tpl', self::$args, false);
                self::increment_date(isset($I2_QUERY['incr']) ? $I2_QUERY['incr'] : '+1 day');
            }
            echo "<style>.day-left,.day-right{display:none}table.outer>tbody>tr>td,table.outer>tr>td{padding:10px}</style><table class='outer'><tr>";
            $i = 0;
            foreach($week as $wk) {
                echo "<td>$wk</td>";
                $i++;
                if(isset($I2_QUERY['split']) && $i % (int)$I2_QUERY['split'] == 0) {
                    echo "</tr><tr>";
                }
            }
            echo "</tr></table>";
		} else {
			self::gen_day_args();
			if(isset($I2_ARGS[2]) && $I2_ARGS[2] == 'box') self::$args['type'] = 'box';
			echo $disp->fetch($I2_FS_ROOT . 'templates/dayschedule/pane.tpl', self::$args, false);
		}

	}

	/**
	* Get the default or pretty name of a summary
	* (e.x. get "Anchor Day" from "anchor", and
	* "JLC Blue Day" from "jlc")
	* @attr String $daytype the ugly name
	* @return String the pretty name
	**/
	private static function get_display_summary($daytype) {
		if(isset(self::$pretty_summaries[$daytype])) {
			return self::$pretty_summaries[$daytype];
		} else {
			return null;
			// return array_search($daytype, self::$summaries);
		}
	}

	/**
	* Check if tomorrow's schedule should be shown
	* Currently, this is after 4PM
	**/
	private static function check_show_next_day() {
		global $I2_QUERY;
		$hr = (int)date('G');
		// if it's after 4
		// never do this if there's a custom date
		if($hr >= 16 && !isset($I2_QUERY['date'])) {
			self::increment_date('+1 day');
		}
	}

	/**
	* Increment the args['date'] variable by a strtotime expression
	* e.x. increment_date('+1 day')
	* @attr String $inc the strtotime expression
	**/
	private static function increment_date($inc) {
		self::$args['date'] = date('Ymd', strtotime($inc, strtotime(self::$args['date'])));
	}

	/**
	* Calculate the schedule for today and return the array
	* which is passed to $disp
	* @return Array the template arguments
	**/
	private static function gen_day_args() {
		$day = self::$args['date'];
		$daytype = self::find_day_type($day);
		self::$args['dayname'] = date('l, F j', strtotime($day));
		self::$args['summaryid'] = $daytype;
		self::$args['summary'] = self::get_display_summary($daytype);
		if(self::$args['summary'] == null) {
			if(isset(self::$daySummaries[$day])) {
				self::$args['summary'] = self::$daySummaries[$day];
			} else {
				self::$args['summary'] = array_search($daytype, self::$summaries);
			}
		}
		self::$args['schedule'] = isset(self::$schedules[$daytype]) ? self::$schedules[$daytype] : self::$schedules['error'];
	}

	/**
    * Downloads a file.
    *
    * @param string $url The file to download.
    * @return string The contents of the file or FALSE in case of failure.
    */
    private static function curl_file_get_contents($url) {
            $c = curl_init();
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_URL, $url);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
            $contents = curl_exec($c);
            curl_close($c);
            return isset($contents) ? $contents : FALSE;
	}

	/**
	* Fetch the iCal file from CalendarWiz
	* @return String the iCal files's contents
	**/
	private static function fetch_ical() {
		global $I2_CACHE, $I2_QUERY;
        self::$icsStr = unserialize($I2_CACHE->read(get_class(), 'ical'));
        $cache_date = unserialize($I2_CACHE->read(get_class(), 'ical_date'));
        if(self::$icsStr === false || isset($I2_QUERY['fetch_ical']) || (time() > ($cache_date + self::$cache_length))) {
        	d('Reloading dayschedule cache', 4);
			self::$icsStr = self::curl_file_get_contents(self::$iCalURL.'&'.time());
        	$I2_CACHE->store(get_class(), 'ical', serialize(self::$icsStr));
        	$I2_CACHE->store(get_class(), 'ical_date', serialize(time()));
        }
		return self::$icsStr;
	}

	/**
	* Convert the iCal file to a PHP-readable array, saves to self::$icsArr
	* @attr String $icsFile the contents of an iCal file
	* @return Array with the iCal files' contents
	**/
	private static function convert_to_array($icsFile) {
	    $icsData = explode("BEGIN:", $icsFile);
	    $icsDates = []; $icsDatesMeta = [];
	    foreach($icsData as $key => $value) {
	        $icsDatesMeta[$key] = explode("\n", $value);
	    }
	    foreach($icsDatesMeta as $key => $value) {
	        foreach($value as $subKey => $subValue) {
	        	if ($subValue != "") {
		            if ($key != 0 && $subKey == 0) {
	                    // $icsDates[$key]["BEGIN"] = $subValue;
	                } else {
	                    $subValueArr = explode(":", $subValue, 2);
	                    if(in_array($subValueArr[0], array("DTSTART", "DTEND", "DTSTART;VALUE=DATE", "DTEND;VALUE=DATE", "SUMMARY", "CATEGORIES"))) {
		                    $icsDates[$key][$subValueArr[0]] = $subValueArr[1];
		                }
		            }
	            }
	        }
	    }
	    self::$icsArr = $icsDates;
	    return $icsDates;
	}

	/**
	* Convert the iCal array (from convert_to_array) to an array
	* assigning a day to it's day type. Stores this result in self::$dayTypes
	* @attr Array $arr from convert_to_array
	**/
	private static function find_day_types($arr) {
		$ret = array();
		$sum = array();
		foreach($arr as $item) {
			if(in_array(trim($item['SUMMARY']), array_keys(self::$summaries))) {
				if(isset($item['DTSTART;VALUE=DATE'])) {
					$day = $item['DTSTART;VALUE=DATE'];
				} else if(isset($item['DTSTART'])) {
					$day = substr($item['DTSTART'], 0, 8);
				} else continue;
				$ret[trim($day)] = self::$summaries[trim($item['SUMMARY'])];
				$sum[trim($day)] = trim($item['SUMMARY']);
			}
		}
		foreach(self::$override_schedules as $day=>$name) {
			$ret[$day] = $name;
		}
		self::$dayTypes = $ret;
		self::$daySummaries = $sum;
		return $ret;
	}

	/**
	* Check if there is an overriding schedule
	* @attr String the date in format YYYYMMDD
	**/
	private static function is_overriding_schedule($day) {
		return isset(self::$override_schedules[$day]);
	}

	/**
	* Return the type of day for one day
	* @attr String the date in format YYYYMMDD
	**/
	private static function find_day_type($day) {
		return isset(self::$dayTypes[$day]) ? self::$dayTypes[$day] : "noschool";
	}

	/**
	* The administration interface.
	**/
	private static function admin() {
		global $I2_ARGS;
		self::init();
		self::$args['template'] = 'admin.tpl';
		$page = isset($I2_ARGS[2]) ? $I2_ARGS[2] : 'home';
		self::$args['page'] = $page;
		if($page == 'home') {
			/**
			* The administration homepage.
			**/
			return "Administration";
		} else if($page == 'summaries') {
			/**
			* The summary edit page
			**/
			self::$args['summaries'] = self::$summaries;
			self::$args['pretty'] = self::$pretty_summaries;
			return "Administration: Summaries";
		} else if($page == 'summariesadd') {
			/**
			* Add a summary
			**/
			if(isset($_POST['daytype'], $_POST['daydesc'])) {
				$s = self::add_summary($_POST['daytype'], $_POST['daydesc']);
			}
			redirect("dayschedule/admin/summaries?update_schedule");
		} else if($page == 'summariesedit') {
			/**
			* Edit a summary
			**/
			if(isset($_POST['daytype'], $_POST['daydesc'])) {
				$s = self::edit_summary($_POST['daytype'], $_POST['daydesc']);
			}
			redirect("dayschedule/admin/summaries?update_schedule");
		} else if($page == 'schedules') {
			/**
			* The schedule edit page
			**/
		}
		return "Administration";
	}

	private static function e($value) { 
		$return = '';for($i = 0; $i < strlen($value); ++$i) {$char = $value[$i];$ord = ord($char);if($char !== "'" && $char !== "\"" && $char !== '\\' && $ord >= 32 && $ord <= 126)$return .= $char;else$return .= '\\x' . dechex($ord);}return $return;
	}
	/**
	* Add custom summary information to the database
	* @attr String $daytype Day type
	* @attr String $daydesc the SUMMARY field from iCal
	**/
	private static function add_summary($daytype, $daydesc) {
		global $I2_SQL;
		return $I2_SQL->raw_query("INSERT INTO `dayschedule_custom_summaries` (`daytype`, `daydesc`) VALUES " .
						   "('" . self::e($daytype) . "', '" . self::e($daydesc) . "');");
	}

	private static function edit_summary($daytype, $daydesc) {
		global $I2_SQL;
		return $I2_SQL->raw_query("UPDATE `dayschedule_custom_summaries` SET daydesc='" . self::e($daydesc) . "' WHERE daytype='" . self::e($daytype) . "';");
	}

	/**
	* Add pretty summary information to the database
	* @attr String $daytype Day type
	* @attr String $daydesc the description
	**/
	private static function add_pretty_summary($daytype, $daydesc) {global $I2_SQL;
		return $I2_SQL->raw_query("INSERT INTO `dayschedule_pretty_summaries` (`daytype`, `daydesc`) VALUES " .
						   "('" . mysql_real_escape_string($daytype) . "', '" . mysql_real_escape_string($daydesc) . "');");
	}

	/**
	* Add custom schedule information to the database
	* @attr String $daytype Day type
	* @attr Array $arr Array containing the schedule, to be converted to JSON
	* information, for example:
	* [["Period 1", "8:30", "10:05"], ["Period 2", "10:15", "11:45"]]
	**/
	private static function add_schedule($daytype, $arr) {
		$json = json_encode($arr);
		return $I2_SQL->raw_query("INSERT INTO `dayschedule_custom_schedules` (`daytype`, `json`) VALUES " .
						   "('" . mysql_real_escape_string($daytype) . "', '" . mysql_real_escape_string($json) . "');");
	}

	/**
	* Fetch the custom schedules and summaries stored in SQL
	* and append them to the current schedules and summaries
	**/
	private static function fetch_custom_entries() {
		global $I2_SQL, $I2_CACHE, $I2_QUERY;

		
		$custom_summaries = unserialize($I2_CACHE->read(get_class(), 'custom_summaries'));
		$pretty_summaries = unserialize($I2_CACHE->read(get_class(), 'pretty_summaries'));
		$custom_schedules = unserialize($I2_CACHE->read(get_class(), 'custom_schedules'));
		$override_schedules = unserialize($I2_CACHE->read(get_class(), 'override_schedules'));
        $cache_date = unserialize($I2_CACHE->read(get_class(), 'custom_date'));

        if(($custom_summaries === false || $pretty_summaries === false || $custom_schedules === false) ||
           (isset($I2_QUERY['regenerate_schedule'])) ||
           (time() > ($cache_date + self::$cache_length))
        ) {
        	d('Regenerating SQL cache', 4);
			$custom_summaries = $I2_SQL->query('SELECT * FROM dayschedule_custom_summaries')->fetch_all_arrays(MYSQLI_ASSOC);
			$pretty_summaries = $I2_SQL->query('SELECT * FROM dayschedule_pretty_summaries')->fetch_all_arrays(MYSQLI_ASSOC);
			$custom_schedules = $I2_SQL->query('SELECT * FROM dayschedule_custom_schedules')->fetch_all_arrays(MYSQLI_ASSOC);
			$override_schedules = $I2_SQL->query('SELECT * FROM dayschedule_override_schedules')->fetch_all_arrays(MYSQLI_ASSOC);
			$I2_CACHE->store(get_class(), 'custom_summaries', serialize($custom_summaries));
			$I2_CACHE->store(get_class(), 'pretty_summaries', serialize($pretty_summaries));
			$I2_CACHE->store(get_class(), 'custom_schedules', serialize($custom_schedules));
			$I2_CACHE->store(get_class(), 'override_schedules', serialize($override_schedules));
        	$I2_CACHE->store(get_class(), 'custom_date', serialize(time()));
		}
		foreach($custom_summaries as $s) {
			self::$summaries[$s['daydesc']] = $s['daytype'];
		}

		foreach($pretty_summaries as $s) {
			self::$pretty_summaries[$s['daytype']] = $s['daydesc'];
		}

		foreach($custom_schedules as $s) {
			self::$schedules[$s['daytype']] = json_decode($s['json']);
            // If daytype is a date, set it as an override schedule automatically
            if(strlen($s['daytype']) == 8 && is_numeric($s['daytype'])) {
                self::$override_schedules[$s['daytype']] = $s['daytype'];
            }
		}
		foreach($override_schedules as $v) {
			self::$override_schedules[$v["dayname"]] = $v["daytype"];
		}
		//self::$override_schedules = $override_schedules;
		
		
	}
}

?>
