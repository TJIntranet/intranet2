<?php
/**
* Just contains the definition for the class {@link EighthActivity}.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2005 The Intranet 2 Development Team
* @package modules
* @subpackage Eighth
* @filesource
*/

/**
* The module that holds the definition for an eighth period activity.
* @package modules
* @subpackage Eighth
*/

class EighthActivity {

	private $data = array();
	const CANCELLED = 1;
	const PERMISSIONS = 2;
	const CAPACITY = 4;
	const STICKY = 8;
	const ONEADAY = 16;
	const PRESIGN = 32;

	/**
	* The constructor for the {@link EighthActivity} class
	*
	* @access public
	* @param int $activityid The activity ID.
	* @param int $blockid The block ID for an activity, NULL in general.
	*/
	public function __construct($activityid, $blockid = NULL) {
		global $I2_SQL;
		if ($activityid != NULL && $activityid != '') {
			$this->data = $I2_SQL->query('SELECT * FROM eighth_activities WHERE aid=%d', $activityid)->fetch_array(Result::ASSOC);
			$this->data['sponsors'] = (!empty($this->data['sponsors']) ? explode(',', $this->data['sponsors']) : array());
			$this->data['rooms'] = (!empty($this->data['rooms']) ? explode(',', $this->data['rooms']) : array());
			if($blockid) {
				$additional = $I2_SQL->query("SELECT bid,sponsors AS block_sponsors,rooms AS block_rooms,cancelled,comment,advertisement,attendancetaken FROM eighth_block_map WHERE bid=%d AND activityid=%d", $blockid, $activityid)->fetch_array(MYSQL_ASSOC);
				$this->data = array_merge($this->data, $additional);
				$this->data['block_sponsors'] = (!empty($this->data['block_sponsors']) ? explode(",", $this->data['block_sponsors']) : array());
				$this->data['block_rooms'] = (!empty($this->data['block_rooms']) ? explode(",", $this->data['block_rooms']) : array());
				$this->data['block'] = new EighthBlock($blockid);
			}
		}
	}

	public static function add_member_to_activity($aid, User $user, $force = FALSE, $blockid = NULL) {
			  $act = new EighthActivity($aid);
			  return $act->add_member($user,$force,$blockid);
	}

	/**
	* Adds a member to the activity.
	*
	* @access public
	* @param int $user The student's user object.
	* @param boolean $force Force the change.
	* @param int $blockid The block ID to add them to.
	*/
	public function add_member(User $user, $force = FALSE, $blockid = NULL) {
		global $I2_SQL,$I2_USER,$I2_LOG;
		$userid = $user->uid;
		/*
		** Users need to be able to add themselves to an activity
		*/
		if (!($I2_USER->uid == $userid || Eighth::is_admin())) {
			/*
			** Trigger an error: check_admin() WILL fail.
			*/
			Eighth::check_admin();
		}
		if ($force) {
			Eighth::check_admin();
		}
		if($blockid == NULL) {
			$blockid = $this->data['bid'];
		}
		$ret = 0;
		$capacity = $this->__get('capacity');
		if($capacity != -1 && $this->__get('member_count') >= $capacity) {
			$ret |= EighthActivity::CAPACITY;
		}
		if($this->cancelled) {
			$ret |= EighthActivity::CANCELLED;
		}
		if($this->data['restricted'] && !in_array($userid, $this->get_restricted_members())) {
			$ret |= EighthActivity::PERMISSIONS;
		}
		$otheractivityid = EighthSchedule::get_activities_by_block($userid, $this->data['bid']);
		$otheractivity = new EighthActivity($otheractivityid);
		if ($otheractivity && $otheractivity->sticky) {
				$ret |= EighthActivity::STICKY;
		}
		if ($otheractivity && $otheractivityid == $this->data['aid'] && $this->oneaday) {
			$ret |= EighthActivity::ONEADAY;
		}
		if($this->presign && time() > strtotime($this->block->date)-60*60*24*2) {
			$ret |= EighthActivity::PRESIGN;
		}
		if(!$ret || $force) {
			$bid = $this->data['bid'];
			$query = 'REPLACE INTO eighth_activity_map (aid,bid,userid) VALUES (%d,%d,%d)';
			$args = array($this->data['aid'],$blockid,$userid);
			$result = $I2_SQL->query_arr($query, $args);
			if (!$otheractivityid) {
				$inverse = 'DELETE FROM eighth_activity_map WHERE aid=%d AND bid=%d AND userid=%d';
				$invargs = $args;
			} else {
				$inverse = 'REPLACE INTO eighth_activity_map (aid,bid,userid) VALUES(%d,%d,%d)';
				$invargs = array($otheractivityid,$bid,$userid);
			}
			$I2_LOG->log_file('Changing activity');
			Eighth::push_undoable($query,$args,$inverse,$invargs,'User Schedule Change');
			if(mysql_error()) {
				$ret = -1;
			}
		}
		if($force && $ret != -1) {
			return 0;
		}
		return $ret;
	}

	public function num_members() {
		global $I2_SQL;
		return $I2_SQL->query('SELECT COUNT(bid) FROM eighth_activity_map WHERE aid=%d',$this->data['aid']);
	}

	public static function add_members_to_activity($aid,$userids,$force = FALSE, $blockid = NULL) {
			  $act = new EighthActivity($aid);
			  return $act->add_members($userids,$force,$blockid);
	}

	/**
	* Add multiple members to the activity.
	*
	* @access private
	* @param array $userids The students' user IDs.
	* @param int $blockid The block ID to add them to.
	*/
	public function add_members($userids, $force = FALSE, $blockid = NULL) {
		Eighth::start_undo_transaction();
		foreach($userids as $userid) {
			$this->add_member(new User($userid), $force, $blockid);
		}
		Eighth::end_undo_transaction();
	}

	public static function remove_member_from_activity($aid, User $user, $blockid = NULL) {
			  $act = new EighthActivity($aid);
			  return $act->remove_member($user,$blockid);
	}

	/**
	* Removes a member from the activity.
	*
	* @access public
	* @param int $userid The student's user object.
	* @param int $blockid The block ID to remove them from.
	*/
	public function remove_member(User $user, $blockid = NULL) {
		global $I2_SQL;
		$userid = $user->uid;
	
		/*
		** Users need to be able to remove themselves from an activity
		*/
		if (!($I2_USER->uid == $userid || Eighth::is_admin())) {
			/*
			** Trigger an error: check_admin() WILL fail.
			*/
			Eighth::check_admin();
		}
		if($blockid == NULL) {
			$blockid = $this->data['bid'];
		}
		$queryarg = array($this->data['aid'], $blockid, $userid);
		$query = 'DELETE FROM eighth_activity_map WHERE aid=%d AND bid=%d AND userid=%d';
		$invquery = 'REPLACE INTO eighth_activity_map (aid,bid,userid) VALUES(%d,%d,%d)';
		$invarg = $queryarg;
		$I2_SQL->query_arr($query, $queryarg);
		Eighth::push_undoable($query,$queryarg,$invquery,$invarg,'Remove Student From Activity');
	}

	
	public static function remove_members_from_activity($aid, $userids, $blockid = NULL) {
			  $act = new EighthActivity($aid);
			  $act->remove_members($userids,$blockid);
	}

	/**
	* Removes multiple members from the activity.
	*
	* @access public
	* @param array $userid The students' user IDs.
	* @param int $blockid The block ID to remove them from.
	*/
	public function remove_members($userids, $blockid = NULL) {
		Eighth::start_undo_transaction();
		foreach($userids as $userid) {
			$this->remove_member(new User($userid), $blockid);
		}
		Eighth::end_undo_transaction();
	}

	/**
	* Gets the members of the activity.
	*
	* @access public
	* @param int $blockid The block ID to get the members for.
	*/
	public function get_members($blockid = NULL) {
		global $I2_SQL;
		if($blockid == NULL) {
			if($this->data['bid']) {
				return flatten($I2_SQL->query("SELECT userid FROM eighth_activity_map WHERE bid=%d AND aid=%d", $this->data['bid'], $this->data['aid'])->fetch_all_arrays(Result::NUM));
			}
			else {
				return array();
			}
		}
		else {
			return flatten(
				$I2_SQL->query('SELECT userid FROM eighth_activity_map WHERE bid=%d AND aid=%d', $blockid, $this->data['aid'])
					->fetch_all_arrays(Result::NUM));
		}
	}

	public static function remove_all_from_activity($aid, $blockid = NULL) {
			  $act = new EighthActivity($aid);
			  $act->remove_all($blockid);
	}

	/**
	* Removes all members from the activity.
	*
	* @access public
	* @param int $blockid The block ID to remove them from.
	*/
	public function remove_all($blockid = NULL) {
		global $I2_SQL;
		Eighth::check_admin();
		if($blockid == NULL) {
			$blockid = $this->data['bid'];
		}
		$result = $I2_SQL->query('SELECT userid FROM eighth_activity_map WHERE aid=%d AND bid=%d', $this->data['aid'], $blockid);
		Eighth::start_undo_transaction();
		$query = 'DELETE FROM eighth_activity_map WHERE aid=%d AND bid=%d';
		$undoquery = 'DELETE FROM eighth_activity_map WHERE aid=%d AND bid=%d AND userid=%d';
		$queryarg = array($this->data['aid'], $blockid);
		$invquery = 'REPLACE INTO eighth_activity_map (aid,bid,userid) VALUES(%d,%d,%d)';
		while ($userid = $result->fetch_single_value()) {
			Eighth::push_undoable($undoquery,$queryarg+$userid,$invquery,$queryarg+$userid,'Remove All [student]');
		}
		$I2_SQL->query_arr($query, $queryarg);
		Eighth::end_undo_transaction();
	}

	public static function add_restricted_member_to_activity($aid, User $user) {
			  $act = new EighthActivity($aid);
			  $act->add_restricted_member($user);
	}

	/**
	* Adds a member to the restricted activity.
	*
	* @access public
	* @param User $user The students's user object.
	*/
	public function add_restricted_member(User $user) {
		global $I2_SQL;
		Eighth::check_admin();
		$query = 'INSERT INTO eighth_activity_permissions (aid,userid) VALUES (%d,%d)';
		$queryarg = array($this->data['aid'],$user->uid);
		$I2_SQL->query_arr($query, $queryarg);
		$invquery = 'DELETE FROM eighth_activity_permissions WHERE aid=%d AND userid=%d';
		Eighth::push_undoable($query,$queryarg,$invquery,$queryarg,'Add Student to Restricted Activity');
	}

	public static function add_restricted_members_to_activity($aid, $users) {
			  $act = new EighthActivity($aid);
			  $act->add_restricted_members($users);
	}

	/**
	* Adds multiple members to the restricted activity.
	*
	* @access public
	* @param array $userids The students' user objects or IDs.
	*/
	public function add_restricted_members($users) {
		Eighth::start_undo_transaction();
		foreach($users as $user) {
			$this->add_restricted_member(new User($user));
		}
		Eighth::end_undo_transaction();
	}

	public static function remove_restricted_member_from_activity($aid, User $user) {
			  $act = new EighthActivity($aid);
			  $act->remove_restricted_member($user);
	}

	/**
	* Removes a member from the restricted activity.
	*
	* @access public
	* @param int $user The students's user object.
	*/
	public function remove_restricted_member(User $user) {
		global $I2_SQL;
		Eighth::check_admin();
		$query = 'DELETE FROM eighth_activity_permissions WHERE aid=%d AND userid=%d';
		$queryarg = array($this->data['aid'],$user->uid);
		$I2_SQL->query_arr($query,$queryarg);
		$invquery = 'INSERT INTO eighth_activity_permissions (aid,userid) VALUES(%d,%d)';
		Eighth::push_undoable($query,$queryarg,$invquery,$queryarg,'Remove Student from Restricted Activity');
	}

	public static function remove_restricted_members_from_activity($aid, $users) {
			  $act = new EighthActivity($aid);
			  $act->remove_restricted_members($users);
	}

	/**
	* Removes multiple members from the restricted activity.
	*
	* @access public
	* @param array $users The students' user objects.
	*/
	public function remove_restricted_members($users) {
		EighthActivity::start_undo_transaction();
		foreach($users as $user) {
			$this->remove_restricted_member(new User($user));
		}
		EighthActivity::end_undo_transaction();
	}

	public static function remove_restricted_all_from_activity($aid) {
		$act = new EighthActivity($aid);
		$act->remove_restricted_all();
	}

	/**
	* Removes all members from the restricted activity.
	*
	* @access public
	*/
	public function remove_restricted_all() {
		global $I2_SQL;
		Eighth::check_admin();
		Eighth::start_undo_transaction();
		$old = $I2_SQL->select('SELECT userid FROM eighth_activity_permissions WHERE aid=%d',$this->data['aid']);
		$query = 'DELETE FROM eighth_activity_permissions WHERE aid=%d';
		$queryarg = array($this->data['aid']);
		$result = $I2_SQL->query($query, $queryarg);
		Eighth::push_undoable();
		Eighth::end_undo_transaction();
	}

	/**
	* Gets the members of the restricted activity.
	*
	* @access public
	*/
	public function get_restricted_members() {
		global $I2_SQL;
		return flatten($I2_SQL->query('SELECT userid FROM eighth_activity_permissions WHERE aid=%d'
			, $this->data['aid'])->fetch_all_arrays(Result::NUM));
	}

	/**
	* Adds a sponsor to the activity.
	*
	* @access public
	* @param int $sponsorid The ssponsor ID.
	*/
	public function add_sponsor($sponsorid) {
		Eighth::check_admin();
		if(!in_array($sponsorid, $this->data['sponsors'])) {
			$this->data['sponsors'][] = $sponsorid;
			$this->__set('sponsors', $this->data['sponsors']);
		}
	}

	/**
	* Removes a sponsor from the activity.
	*
	* @access public
	* @param int $sponsorid The sponsor ID.
	*/
	public function remove_sponsor($sponsorid) {
		Eighth::check_admin();
		if(in_array($sponsorid, $this->data['sponsors'])) {
			unset($this->data['sponsors'][array_search($sponsorid, $this->data['sponsors'])]);
			$this->__set('sponsors', $this->data['sponsors']);
		}
	}
	
	/**
	* Adds a room to the activity.
	*
	* @access public
	* @param int $roomid The room ID.
	*/
	public function add_room($roomid) {
		Eighth::check_admin();
		if(!in_array($roomid, $this->data['rooms'])) {
			$this->data['rooms'][] = $roomid;
			$this->__set('rooms', $this->data['rooms']);
		}
	}

	/**
	* Removes a room from the activity.
	*
	* @access public
	* @param int $roomid The room ID.
	*/
	public function remove_room($roomid) {
		Eighth::check_admin();
		if(in_array($roomid, $this->data['rooms'])) {
			unset($this->data['rooms'][array_search($roomid, $this->data['rooms'])]);
			$this->__set('rooms', $this->data['rooms']);
		}
	}
	
	/**
	* Gets all the available activities.
	*
	* @access public
	* @param int $blockid The room ID.
	*/
	public static function get_all_activities($blockids = NULL, $restricted = FALSE) {
		global $I2_SQL;
		if($blockids == NULL) {
			return self::id_to_activity(flatten($I2_SQL->query('SELECT aid FROM eighth_activities ' . ($restricted ? 'WHERE restricted=1 ' : '') . 'ORDER BY name')->fetch_all_arrays(Result::NUM)));
		}
		else {
			if(!is_array($blockids)) {
				settype($blockids, 'array');
			}
			return self::id_to_activity($I2_SQL->query('SELECT aid,bid FROM eighth_activities LEFT JOIN eighth_block_map ON (eighth_activities.aid=eighth_block_map.activityid) WHERE bid IN (%D) ' . ($restricted ? 'AND restricted=1 ' : '') . 'GROUP BY aid ORDER BY name', $blockids)->fetch_all_arrays(Result::NUM));
		}
	}

	/**
	* Adds an activity to the list.
	*
	* @access public
	* @param string $name The name of the activity.
	* @param array $sponsors The activity's sponsors.
	* @param array $rooms The activity's rooms.
	* @param string $description The description of the activity.
	* @param bool $restricted If this is a restricted activity.
	*/
	public static function add_activity($name, $sponsors = array(), $rooms = array(), $description = '', 
			$restricted = FALSE, $sticky = FALSE, $bothblocks = FALSE, $presign = FALSE, $aid = NULL) {
		Eighth::check_admin();
		global $I2_SQL;
		if(!is_array($sponsors)) {
			$sponsors = array($sponsors);
		}
		if(!is_array($rooms)) {
			$rooms = array($rooms);
		}
		$result = NULL;
		if ($aid === NULL) {
			$query = "REPLACE INTO eighth_activities (name,sponsors,rooms,description,restricted,sticky,bothblocks,presign) 
				VALUES (%s,'%D','%D',%s,%d,%d,%d,%d)";
			$queryarg = array($name, $sponsors, $rooms, $description, ($restricted?1:0),($sticky?1:0),($bothblocks?1:0),($presign?1:0));
			$result = $I2_SQL->query_arr($query,$queryarg);
			$invquery = 'DELETE FROM eighth_activities WHERE aid=%d';
			$newid = $result->get_insert_id();
			$invarg = array($newid);
			Eighth::push_undoable($query,$queryarg,$invquery,$invarg,'Create Activity');
		} else {
			$old = $I2_SQL->query('SELECT * FROM eighth_activities WHERE aid=%d',$aid)->fetch_array(MySQL::ASSOC);
			$query = "REPLACE INTO eighth_activities 
				(name,sponsors,rooms,description,restricted,sticky,bothblocks,presign,aid) 
				VALUES (%s,'%D','%D',%s,%d,%d,%d,%d,%d)";
			$queryarg = array($name, $sponsors, $rooms, $description, ($restricted?1:0),($sticky?1:0),($bothblocks?1:0),($presign?1:0),$aid);
			$result = $I2_SQL->query_arr($query,$queryarg);
			$invarg = array($old['name'],$old['sponsors'],$old['rooms'],$old['description'],$old['restricted'],$old['sticky'],$old['bothblocks'],$old['presign'],$old['aid']);
			Eighth::push_undoable($query,$queryarg,$query,$invarg);
		}
		return $result->get_insert_id();
	}

	/**
	* Removes an activity from the list.
	*
	* @access public
	* @param int $activityid The activity ID.
	*/
	public static function remove_activity($activityid) {
		global $I2_SQL;
		Eighth::check_admin();
		Eighth::start_undo_transaction();
		$old = $I2_SQL->query('SELECT * FROM eighth_activities WHERE aid=%d', $activityid)->fetch_array(Result::ASSOC);
		$query = 'DELETE FROM eighth_activities WHERE aid=%d';
		$queryarg = array($activityid);
		$I2_SQL->query_arr($query,$queryarg);
		$invquery = "REPLACE INTO eighth_activities 
				(name,sponsors,rooms,description,restricted,sticky,bothblocks,presign,aid) 
				VALUES (%s,'%D','%D',%s,%d,%d,%d,%d,%d)";
		$invarg = array($old['name'],$old['sponsors'],$old['rooms'],$old['description'],$old['restricted'],$old['sticky'],$old['bothblocks'],$old['presign'],$old['aid']);
		Eighth::push_undoable($query,$queryarg,$invquery,$invarg,'Delete Activity');
		$people = $I2_SQL->query('SELECT bid,userid FROM eighth_activity_map WHERE aid=%d',$activityid);
		$defaid = i2config_get('default_aid',999,'eighth');
		/*
		** Move all affected students into the default activity
		*/
		while ($row = $people->fetch_array(Result::ASSOC)) {
			$query = 'REPLACE INTO eighth_activity_map (aid,bid,userid) VALUES(%d,%d,%d)';
			$queryarg = array($defaid,$row['bid'],$row['userid']);
			$I2_SQL->query_arr($query,$queryarg);
			$invarg = array($activityid,$row['bid'],$row['userid']);
			Eighth::push_undoable($query,$queryarg,$query,$invarg,'Delete Activity [displace student]');
		}
		Eighth::end_undo_transaction();
	}

	/**
	* Removes this activity from the list.
	*
	* @access public
	*/
	public function remove() {
		$this->remove_activity($this->data['aid']);
		$this->data = array();
	}

	/**
	* The magic __get function.
	*
	* @access public
	* @param string $name The name of the field to get.
	*/
	public function __get($name) {
		global $I2_SQL;
		if(array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		else if($name == 'members' && $this->data['bid']) {
			return $this->get_members();
		}
		else if($name == 'members_obj' && $this->data['bid']) {
			return User::sort_users($this->get_members());
		}
		else if($name == 'absentees' && $this->data['bid']) {
			return EighthSchedule::get_absentees($this->data['bid'], $this->data['aid']);
		}
		else {
			switch($name) {
				case 'comment_short':
					return substr($this->data['comment'], 0, 15);
				case 'name_r':
					return $this->data['name'] . ($this->data['restricted'] ? ' (R)' : '')
														. ($this->data['bothblocks'] ? ' (BB)' : '')
														. ($this->data['sticky'] ? ' (S)' : '');
				case 'sponsors_comma':
					$sponsors = EighthSponsor::id_to_sponsor($this->data['sponsors']);
					$temp_sponsors = array();
					foreach($sponsors as $sponsor) {
						$temp_sponsors[] = $sponsor->name;
					}
					return implode(', ', $temp_sponsors);
				case 'block_sponsors_comma':
					$sponsors = EighthSponsor::id_to_sponsor($this->data['block_sponsors']);
					$temp_sponsors = array();
					foreach($sponsors as $sponsor) {
						$temp_sponsors[] = $sponsor->name;
					}
					return implode(', ', $temp_sponsors);
				case 'block_sponsors_comma_short':
					$sponsors = EighthSponsor::id_to_sponsor($this->data['block_sponsors']);
					$temp_sponsors = array();
					foreach($sponsors as $sponsor) {
						$temp_sponsors[] = substr($sponsor->fname, 0, 1) . ". {$sponsor->lname}";
					}
					return implode(', ', $temp_sponsors);
				case 'rooms_comma':
					$rooms = EighthRoom::id_to_room($this->data['rooms']);
					$temp_rooms = array();
					foreach($rooms as $room) {
						$temp_rooms[] = $room->name;
					}
					return implode(', ', $temp_rooms);
				case 'block_rooms_comma':
					$rooms = EighthRoom::id_to_room($this->data['block_rooms']);
					$temp_rooms = array();
					foreach($rooms as $room) {
						$temp_rooms[] = $room->name;
					}
					return implode(', ', $temp_rooms);
				case 'restricted_members':
					return $this->get_restricted_members();
				case 'restricted_members_obj':
					return User::id_to_user($this->get_restricted_members());
				case 'capacity':
					if (!isSet($this->data['block_rooms']) || count($this->data['block_rooms']) == 0) {
						return -1;
					}
					return $I2_SQL->query('SELECT SUM(capacity) FROM eighth_rooms WHERE rid IN (%D)', 
							$this->data['block_rooms'])->fetch_single_value();
				case 'member_count':
					return count($this->get_members());
			}
		}
	}

	/**
	* The magic __set function.
	*
	* @access public
	* @param string $name The name of the field to set.
	* @param mixed $value The value to assign to the field.
	*/
	public function __set($name, $value) {
		global $I2_SQL;
		Eighth::check_admin();
		if($name == 'name') {
			$oldname = $I2_SQL->query('SELECT name FROM eighth_activities WHERE aid=%d',$this->data['aid'])->fetch_single_value();
			$query = 'UPDATE eighth_activities SET name=%s WHERE aid=%d';
			$queryarg = array($value, $this->data['aid']);
			$result = $I2_SQL->query_arr($query,$queryarg);
			$invarg = array($oldname, $this->data['aid']);
			Eighth::push_undoable($query,$queryarg,$query,$invarg,'Change Activity Name');
			$this->data['name'] = $value;
		}
		else {
			switch($name) {
				case 'sponsors':
				case 'rooms':
					if(!is_array($value)) {
						$value = array($value);
					}
					$oldval = $I2_SQL->query("SELECT $name FROM eighth_activities WHERE aid=%d",$this->data['aid'])->fetch_single_value();
					$query = "UPDATE eighth_activities SET $name='%D' WHERE aid=%d";
					$queryarg = array($value, $this->data['aid']);
					$result = $I2_SQL->query_arr($query,$queryarg);
					$this->data[$name] = $value;
					$invarg = array($oldval,$this->data['aid']);
					$name = ucFirst($name);
					Eighth::push_undoable($query,$queryarg,$query,$invarg,"Change Activity $name");
					return;
				case 'description':
					$oldval = $I2_SQL->query("SELECT $name FROM eighth_activities WHERE aid=%d",$this->data['aid'])->fetch_single_value();
					$query = 'UPDATE eighth_activities SET description=%s WHERE aid=%d';
					$queryarg = array($value, $this->data['aid']);
					$result = $I2_SQL->query_arr($query,$queryarg);
					$this->data['description'] = $value;
					$invarg = array($oldval,$this->data['aid']);
					Eighth::push_undoable($query,$queryarg,$query,$invarg,'Change Activity Description');
					return;
				case 'restricted':
				case 'oneaday':
				case 'bothblocks':
				case 'sticky':
				case 'presign':
					if ($this->data[$name] == $value) {
						//Nothing to do
						return;
					}
					$query = "UPDATE eighth_activities SET $name=%d WHERE aid=%d";
					$queryarg = array((int)$value, $this->data['aid']);
					$result = $I2_SQL->query_arr($query,$queryarg);
					$this->data[$name] = $value;
					// Invert value - we checked for equality earlier
					$invarg = array($value?0:1,$this->data['aid']);
					$name = ucFirst($name);
					Eighth::push_undoable($query,$queryarg,$query,$invarg,"Change $name Status");
					return;
				case 'cancelled':
					if ($this->data['cancelled'] == $value) {
						return;
					}
					$query = 'UPDATE eighth_block_map SET cancelled=%d WHERE bid=%d AND activityid=%d';
					$queryarg = array((int)$value, $this->data['bid'], $this->data['aid']);
					$result = $I2_SQL->query_arr($query,$queryarg); 					
					$this->data['cancelled'] = $value;
					$invarg = array($value ? 0 : 1, $this->data['bid'], $this->data['aid']);
					Eighth::push_undoable($query,$queryarg,$query,$invarg,'Cancel Activity');
					return;
				case 'comment':
				case 'advertisement':
					$oldval = $I2_SQL->query("SELECT $name FROM eighth_block_map WHERE bid=%d AND activityid=%d",$this->data['bid'],$this->data['aid'])->fetch_single_value();
					$query = "UPDATE eighth_block_map SET $name=%s WHERE bid=%d AND activityid=%d";
					$queryarg = array($value, $this->data['bid'], $this->data['aid']);
					$result = $I2_SQL->query_arr($query, $queryarg);
					$this->data[$name] = $value;
					$invarg = array($oldval, $this->data['bid'], $this->data['aid']);
					$name = ucFirst($name);
					Eighth::push_undoable($query,$queryarg,$query,$invarg,"Change Activity $name");
					return;
				case 'attendancetaken':
					if ($this->data['attendancetaken'] == $value) {
						return;
					}
					$query = 'UPDATE eighth_block_map SET attendancetaken=%d WHERE bid=%d AND activityid=%d';
					$queryarg = array((int)$value, $this->data['bid'], $this->data['aid']);
					$result = $I2_SQL->query_arr($query,$queryarg);
					$this->data['attendancetaken'] = $value;
					$invarg = array($value?0:1,$this->data['bid'],$this->data['aid']);
					Eighth::push_undoable($query,$queryarg,$query,$invarg,'Set Attendance-Taken Bit');
					return;
			}
		}
	}

	/**
	* Converts an array of activity IDs into {@link EighthActivity} objects;
	*
	* @access public
	* @param int $activityids The activity IDs.
	*/
	public static function id_to_activity($activityids) {
		$ret = array();
		foreach($activityids as $activityid) {
			if(is_array($activityid)) {
				$ret[] = new EighthActivity($activityid[0], $activityid[1]);
			}
			else {
				$ret[] = new EighthActivity($activityid);
			}
		}
		return $ret;
	}

	public static function cancel($blockid, $activityid) {
		global $I2_SQL;
		Eighth::check_admin();
		$I2_SQL->query("UPDATE eighth_block_map SET cancelled=1 WHERE bid=%d AND activityid=%d", $blockid, $activityid);
	}
}

?>
