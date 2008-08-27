<?php
class StudentOfTheDay implements Module {
	private $user;
	public function get_name() {
		return "Student of the Day!";
	}

	public function init_pane() {
		$this->user = new User();
		return "Student of the Day!";
	}

	public function display_pane($disp) {
		global $I2_ROOT;
		$student = $this->user->name;
		$uidnumber = $this->user->iodineUidNumber;
		$disp->raw_display("<span style=\"font-weight: bold;\">The student of the day is.... $student!!!!!</span><img src=\"$I2_ROOT/pictures/$uidnumber\" />");
	}

	public function init_box() {
		return false;
	}

	public function display_box($disp) {
	}
}
?>