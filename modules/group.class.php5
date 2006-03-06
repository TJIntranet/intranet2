<?php
/**
* Just contains the definition for the class {@link Groups}.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2005 The Intranet 2 Development Team
* @package modules
* @subpackage Group
* @filesource
*/

/**
* The module that runs groups
* @package modules
* @subpackage Group
*/
class Group {

	/**
	* Commonly accessed administrative groups.
	*/
	private static $admin_groups = NULL;
	private static $admin_all = NULL;
	private static $admin_eighth = NULL;
	private static $admin_ldap = NULL;
	private static $admin_mysql = NULL;

	/**
	* groupname to GID mapping
	*/
	private static $gid_map;

	public static function admin_all() {
		if(self::$admin_all === NULL) {
			self::$admin_all = new Group('admin_all');
		}
		return self::$admin_all;
	}
	
	public static function admin_groups() {
		if(self::$admin_groups === NULL) {
			self::$admin_groups = new Group('admin_groups');
		}
		return self::$admin_groups;
	}

	public static function admin_eighth() {
		if(self::$admin_eighth === NULL) {
			self::$admin_eighth = new Group('admin_eighth');
		}
		return self::$admin_eighth;
	}

	public static function admin_ldap() {
		if (self::$admin_ldap === NULL) {
			self::$admin_ldap = new Group('admin_ldap');
		}
		return self::$admin_ldap;
	}

	public static function admin_mysql() {
		if (self::$admin_mysql === NULL) {
			self::$admin_mysql = new Group('admin_mysql');
		}
		return self::$admin_mysql;
	}

	private $mygid;
	private $myname;

	public function __get($var) {
		global $I2_SQL;
		switch($var) {
			case 'gid':
				return $this->mygid;
			case 'name':
				return $this->myname;
			case 'special':
				return ($this->mygid < 0);
			case 'members':
				return $this->get_members();
			case 'members_obj':
				return User::id_to_user($this->get_members());
		}
	}

	public function __construct($group) {
		global $I2_SQL;

		if (self::$gid_map === NULL) {
			self::$gid_map = array();
			 $res = $I2_SQL->query('SELECT name,gid FROM groups');
			 while ($row = $res->fetch_array(Result::ASSOC)) {
			 	self::$gid_map[$row['name']] = $row['gid'];
			 }
		}

		if(is_numeric($group)) {
		// Numeric $group passed; figure out group name
			$name = $I2_SQL->query('SELECT name FROM groups WHERE gid=%d', $group)->fetch_single_value();
			try {
				if($name) {
					$this->mygid = $group;
					$this->myname = $name;
				}
				elseif($name = self::get_special_group($group)) {
					$this->mygid = $group;
					$this->myname = $name;
				}
				else {
					throw new I2Exception("Nonexistent group id $group given to the Group module");
				}
			} catch(I2Exception $e) {
				throw new I2Exception("Nonexistent group id $group given to the Group module");
			}
		}
		else {
		// Non-numeric $group passed; figure out GID
			if(isSet(self::$gid_map[$group])) {
				$this->mygid = self::$gid_map[$group];
				$this->myname = $group;
			}
			else {
				try {
					$this->mygid = self::get_special_group($group);
					$this->myname = $group;
				} catch (I2Exception $e) {
					throw new I2Exception("Nonexistent group $group given to the Group module");
				}
			}
		}
	}

	public function get_members() {
		global $I2_SQL;

		return flatten($I2_SQL->query('SELECT uid FROM group_user_map WHERE gid=%d',$this->mygid)->fetch_all_arrays(Result::NUM));
	}
	
	/**
	* Gets all groups.
	*
	* @return Array An containing all of the Group objects.
	*/
	public static function get_all_groups($module = NULL) {
		global $I2_SQL;
		$prefix = "%";
		if($module) {
			$prefix = strtolower($module) . "_%";
		}
		$ret = array();
		foreach($I2_SQL->query('SELECT gid FROM groups WHERE name LIKE %s', $prefix) as $row) {
			$ret[] = new Group($row[0]);
		}
		return $ret;
	}

	/**
	* Gets the name of every group
	*
	* @return Result A Result containing each group's name.
	*/
	public static function get_all_group_names() {
		global $I2_SQL;
		return $I2_SQL->query('SELECT name FROM groups');
	}

	public function add_user($user) {
		global $I2_SQL;

		if(is_numeric($user)) {
			$user = new User($user);
		}
		if($this->special) {
			throw I2Exception("Attempted to add user {$user->uid} to invalid group {$this->mygid}");
		}
		return $I2_SQL->query('INSERT INTO group_user_map (gid,uid) VALUES(%d,%d)',$this->mygid,$user->uid);
	}

	public function remove_user(User $user) {
		global $I2_SQL;

		if($this->special) {
			throw I2Exception("Attempted to remove user {$user->uid} from invalid group {$this->mygid}");
		}
		
		return $I2_SQL->query('DELETE FROM group_user_map WHERE uid=%d AND gid=%d',$user->uid,$this->mygid);
	}

	public function remove_all_members() {
		global $I2_SQL;

		if($this->special) {
			throw I2Exception("Attempted to remove all users from invalid group {$this->mygid}");
		}

		return $I2_SQL->query('DELETE FROM group_user_map WHERE gid=%d', $this->mygid);
	}

	public function grant_admin(User $user) {
		global $I2_SQL;

		if($this->special) {
			throw I2Exception("Attempted to grant admin privileges to user {$user->uid} for invalid group {$this->mygid}");
		}
		
		return $I2_SQL->query('UPDATE group_user_map SET is_admin=1 WHERE uid=%d AND gid=%d', $user->uid, $this->mygid);
	}
	
	public function revoke_admin(User $user) {
		global $I2_SQL;

		if($this->special) {
			throw I2Exception("Attempted to revoke admin privileges from user {$user->uid} for invalid group {$this->mygid}");
		}

		return $I2_SQL->query('UPDATE group_user_map SET is_admin=0 WHERE uid=%d AND gid=%d', $user->uid, $this->mygid);
	}

	/**
	* Determine whether a user is a member of this group.
	*
	* Returns whether or not $user is a member of the group. If $user is ommitted, or NULL, the currently logged-in user is checked.
	*
	* @param User $user The user to check, or $I2_USER if unspecified.
	* @return bool TRUE if the user is a member of the group, FALSE otherwise.
	*/
	public function has_member($user=NULL) {
		global $I2_SQL;

		if($user===NULL) {
			$user = $GLOBALS['I2_USER'];
		}

		// If the user is in admin_all, they're also admin_anything
		if (substr($this->name, 0, 6) == 'admin_'  && $this->name != 'admin_all' && self::admin_all()->has_member($user)) {
			return TRUE;
		}

		// Check for 'special' groups
		if( $this->special ) {
			$specs = self::get_special_groups($user);
			return in_array($this->mygid, $specs);
		}
		
		// Standard DB check
		$res = $I2_SQL->query('SELECT gid FROM group_user_map WHERE uid=%d AND gid=%d', $user->uid, $this->mygid);
		if( $res->num_rows() > 0) {
			return TRUE;
		}

		return FALSE;
	}

	public function set_group_name($name) {
		global $I2_SQL;
		return $I2_SQL->query('UPDATE groups SET name=%s WHERE gid=%d',$name,$this->mygid);
	}

	/**
	* Gets the groups of which a user is a member.
	*
	*
	* @param int $uid The userID of which to fetch the groups.
	* @return array The group IDs of groups for the given user.
	*/
	public static function get_user_groups(User $user, $include_special = TRUE) {
		global $I2_SQL;
		$ret = array();
		
		$res = $I2_SQL->query('SELECT gid FROM group_user_map WHERE uid=%d',$user->uid);
		
		foreach($res as $row) {
			$ret[] = new Group($row['gid']);
		}
		if($include_special && $user->grade) {
			$ret[] = new Group("grade_{$user->grade}");
		}
		return $ret;
	}

	/**
	* Gets the admin groups of which a user is a member.
	*
	*
	* @param int $uid The userID of which to fetch the groups.
	* @return array The group IDs of admin groups for the given user.
	*/
	public static function get_admin_groups(User $user) {
		$groups = self::get_user_groups($user, FALSE);
		$ret = array();
		
		foreach($groups as $group) {
			if($group->is_admin($user)) {
				$ret[] = $group;
			}
		}
		return $ret;
	}
	/**
	*
	*/
	public function delete_group() {
		global $I2_SQL;
		
		if(!self::admin_groups()->has_member($GLOBALS['I2_USER']) && !(self::admin_eighth()->has_member($GLOBAL['I2_USER']) && substr($this->name, 0, 7) == "eighth_")) {
			throw new I2Exception('User is not authorized to delete groups.');
		}

		$I2_SQL->query('DELETE FROM group_user_map WHERE gid=%d;', $this->mygid);
		$I2_SQL->query('DELETE FROM groups WHERE gid=%d;', $this->mygid);
	}

	/**
	*
	*/
	public static function add_group($name) {
		global $I2_SQL;

		if(!self::admin_groups()->has_member($GLOBALS['I2_USER']) && !(self::admin_eighth()->has_member($GLOBALS['I2_USER']) && substr($name, 0, 7) == "eighth_")) {
			throw new I2Exception('User is not authorized to create groups.');
		}

		$res = $I2_SQL->query('SELECT gid FROM groups WHERE name=%s;',$name);
		if($res->num_rows() > 0) {
			throw new I2Exception("Tried to create group with name `$name`, which already exists as gid `{$res->fetch_single_value()}`");
		}

		$I2_SQL->query('INSERT INTO groups (name) VALUES (%s);',$name);
	}

	/**
	* Gets special group information.
	*
	* Given a special group name, returns the negative GID. Given a negative
	* GID, returns the group name.
	*
	* @param $group mixed Either a string, the group name, or an int, the
	*		GID.
	* @return mixed Either a string, the group name, or an int, the GID.
	*/
	private static function get_special_group($group) {
		switch($group) {
			case -9:
			case '-9':
				return 'grade_9';
			case -10:
			case '-10':
				return 'grade_10';
			case -11:
			case '-11':
				return 'grade_11';
			case -12:
			case '-12':
				return 'grade_12';
			case 'staff':
				return 'grade_staff';
			case 'grade_9':
				return -9;
			case 'grade_10':
				return -10;
			case 'grade_11':
				return -11;
			case 'grade_12':
				return -12;
			case 'grade_staff':
				return -8;
			case -8:
			case '-8':
				return 'grade_staff';
		}
		throw new I2Exception('Invalid special group '.$group.' passed to get_special_group');
	}

	public function is_admin(User $user) {
		global $I2_SQL;

		$res = $I2_SQL->query('SELECT is_admin FROM group_user_map WHERE uid=%d AND gid=%d;',$user->uid,$this->gid);
		if($res->num_rows() < 1) {
			// admin_all members get admin access to all groups, I believe
			if(self::admin_all()->has_member($user)) {
				return TRUE;
			}
			// They're not even a member of the group, so... not an admin
			return FALSE;
		}
		if($res->fetch_single_value()) {
			return TRUE;
		}
		return FALSE;
	}

	public function get_name() {
		return 'Group';
	}
}
?>
