<?php
/**
* Just contains the definition for the class {@link Poll}.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2005 The Intranet 2 Development Team
* @package modules
* @subpackage Polls
* @filesource
*/

/**
* The class that keeps track of a poll
* @package modules
* @subpackage Polls
*/
class Poll {

	private $mypid;
	private $myname;

	private $myvisibility;
	private $mystartdt;
	private $myenddt;

	private $myintroduction;
	private $myquestions;

	private $mygroups;

	public function __get($var) {
		global $I2_SQL;
		switch($var) {
			case 'pid':
				return $this->mypid;
			case 'name':
				return $this->myname;
			case 'visible':
				return $this->myvisibility;
			case 'startdt':
				return $this->mystartdt;
			case 'enddt':
				return $this->myenddt;
			case 'introduction':
				return $this->myintroduction;
			case 'questions':
				return $this->myquestions;
			case 'groups':
				return $this->mygroups;
			case 'groupnames':
				$groupnames = array();
				foreach ($this->mygroups as $grp) {
					$groupnames[] = $grp->name;
				}
				return $groupnames;
			case 'groupids':
				$groupids = array();
				foreach ($this->mygroups as $grp) {
					$groupids[] = $grp->gid;
				}
				return $groupids;
		}
	}

	public function __construct($pid) {
		global $I2_SQL;

		$pollinfo = $I2_SQL->query('SELECT name, introduction, visible, startdt, enddt FROM polls WHERE pid=%d', $pid)->fetch_array();

		$this->mypid = $pid;
		$this->myname = $pollinfo['name'];
		$this->myvisibility = $pollinfo['visible'];
		$this->myintroduction = $pollinfo['introduction'];
		$this->mystartdt = $pollinfo['startdt'];
		$this->myenddt = $pollinfo['enddt'];

		$this->myquestions = array();
		foreach($I2_SQL->query('SELECT qid FROM poll_questions WHERE pid=%d ORDER BY qid ASC', $pid)->fetch_col('qid') as $qid) {
			$this->myquestions[] = new PollQuestion($pid, $qid);
		}
		
		$this->mygroups = array();
		foreach ($I2_SQL->query('SELECT gid FROM group_poll_map WHERE pid=%d', $pid)->fetch_col('gid') as $gid) {
			$this->mygroups[] = new Group($gid);
		}
	}

	/**
	* Sets the name of this poll.
	*
	* @param string $name The new name of the poll.
	*/
	public function set_name($name) {
		global $I2_SQL;

		$this->myname = $name;

		$I2_SQL->query('UPDATE polls SET name=%s WHERE pid=%d', $name, $this->mypid);
	}

	/**
	* Sets the introduction to this poll.
	*
	* @param string $intro The new introduction to the poll.
	*/
	public function set_introduction($intro) {
		global $I2_SQL;

		$this->myintroduction = $intro;

		$I2_SQL->query('UPDATE polls SET introduction=%s WHERE pid=%d', $intro, $this->mypid);
	}
	
	/**
	* Sets whether a poll is visible to the general Intranet public.
	*
	* @param boolean $isVisible How the visibility of the poll will be set.
	*/
	public function set_visibility($isVisible) {
		global $I2_SQL;

		$this->myvisibility = $isVisible;

		$I2_SQL->query('UPDATE polls SET visible=%s WHERE pid=%d', $isVisible, $this->mypid);
	}

	/**
	* Sets the groups that can vote in this poll.
	*
	* @param array $gids The ID numbers of the groups that can vote
	*/
	public function set_groups($gids) {
		global $I2_SQL;
		
		$this->groups = array();
		$I2_SQL->query('DELETE FROM group_poll_map WHERE pid=%d', $this->pid);

		foreach ($gids as $gid) {
			$gid = trim($gid);
			if ($gid == '') {
				continue;
			}
			$this->groups[] = new Group($gid);
			$I2_SQL->query('INSERT INTO group_poll_map SET gid=%d, pid=%d', $gid, $this->pid);
		}
	}

	/**
	* Sets the time voting begins
	*
	* @param string $dt The date/time, in format YYYY-MM-DD HH:MM:SS
	*/
	public function set_start_datetime($dt) {
		global $I2_SQL;

		$this->mystartdt = $dt;
		$I2_SQL->query('UPDATE polls SET startdt=%s WHERE pid=%d', $dt, $this->pid);
	}

	/**
	* Sets the time voting ends
	*
	* @param string $dt The date/time, in format YYYY-MM-DD HH:MM:SS
	*/
	public function set_end_datetime($dt) {
		global $I2_SQL;
		
		$this->myenddt = $dt;
		$I2_SQL->query('UPDATE polls SET enddt=%s WHERE pid=%d', $dt, $this->pid);
	}

	/**
	* Adds a question to a poll.
	*
	* @param string $question The question text to add
	* @param int $maxvotes The number of answers a user can pick in
	* approval voting; 0 for unlimited approval voting, 1 for normal
	* plurality voting
	* @param array $answers The answers a user may choose from
	*/
	public function add_question($question, $maxvotes, $answers) {
		$this->myquestions[] = PollQuestion::new_question($this->pid, $question, $maxvotes, $answers);
	}

	/**
	* Deletes a question from this poll
	*
	* @param int $qid The ID of the question to delete
	*/
	public function delete_question($qid) {
		for ($k = 0; $k < count($this->questions); $k++) {
			if ($this->questions[$k]->qid == $qid) {
				array_splice($this->questions, $k, 1);
				PollQuestion::delete_question($this->pid, $qid);
				break;
			}
		}
	}


	/**
	* Determines whether a user can access this poll
	*
	* Finds a user's access based on start and end date/times, poll groups, and visibility.
	*
	* @param User $user The user; defaults to the current user
	*/
	public function user_can_access($user=NULL) {
		global $I2_USER;

		if ($user == NULL) {
			$user = $I2_USER;
		}

		// poll visibility
		if ($this->visible == FALSE) {
			return FALSE;
		}

		// within time range
		$timestamp = time();
		$pollstart = strtotime($this->mystartdt);
		if ($timestamp < $pollstart) {
			return FALSE;
		}
		$pollend = strtotime($this->myenddt);
		if ($timestamp > $pollend) {
			return FALSE;
		}

		// groups
		if (count($this->mygroups) == 0) {
			// no groups, so everyone has access
			return TRUE;
		}
		else {
			//groups; find if user is in any of them
			foreach ($this->groupnames as $group) {
				if ($user->is_group_member($group)) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	* Creates a new poll.
	*
	* @param string $name The name of the poll to add
	* @param string $intro An introduction to the poll
	*
	* @return Poll The new poll
	*/
	public static function add_poll($name, $intro) {
		global $I2_SQL;

		$pid = $I2_SQL->query('INSERT INTO polls SET name=%s, introduction=%s', $name, $intro)->get_insert_id();

		return new Poll($pid);
	}

	/**
	* Deletes a poll.
	*
	* @param integer $pid The id of the poll to remove
	*/
	public static function delete_poll($pid) {
		global $I2_SQL;

		$I2_SQL->query('DELETE FROM polls WHERE pid=%d', $pid);
		$I2_SQL->query('DELETE FROM poll_questions WHERE pid=%d', $pid);
		$I2_SQL->query('DELETE FROM poll_answers WHERE pid=%d', $pid);
		$I2_SQL->query('DELETE FROM poll_votes WHERE pid=%d', $pid);
	}

	/**
	* Gets all existant polls
	*
	* @return array An array of Poll objects
	*/
	public static function all_polls() {
		global $I2_SQL;

		$pids = $I2_SQL->query('SELECT pid FROM polls')->fetch_col('pid');
		$polls = array();
		foreach ($pids as $pid) {
			$polls[] = new Poll($pid);
		}

		return $polls;
	}

	/**
	* Gets all polls a user has access to
	*
	* @param User $user The {@link User} to get polls for.
	* @return array The Polls the user has access to.
	*/
	public static function get_user_polls($user) {
		$all = Poll::all_polls();
		$userpolls = array();

		foreach ($all as $poll) {
			if ($poll->user_can_access()) {
				$userpolls[] = $poll;
			}
		}

		return $userpolls;
	}
}
?>