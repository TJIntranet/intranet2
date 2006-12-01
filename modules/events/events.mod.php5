<?php
/**
* Just contains the definition for the class {@link Events}.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2005 The Intranet 2 Development Team
* @package modules
* @subpackage Events
* @filesource
*/

/**
* The module that lets you register for events (read: AMC)
* @package modules
* @subpackage News
*/
class Events implements Module {

	/**
	* The display object to use
	*/
	private $display;

	/**
	* Template for the specified action
	*/
	private $template;

	/**
	* Template arguments for the specified action
	*/
	private $template_args = array();

	/**
	* Required by the {@link Module} interface.
	*/
	function init_pane() {
		global $I2_ARGS, $I2_USER;

		$args = array();
		if(count($I2_ARGS) <= 1) {
			$this->home();
			return array('Events: Home', 'Events');
		}
		else {
			$method = $I2_ARGS[1];
			if(method_exists($this, $method)) {
				return $this->$method();
			}
			$this->template = 'events_error.tpl';
			$this->template_args = array('method' => $method, 'args' => $I2_ARGS);
		}
		return array('Error', 'Error');
	}

	/**
	 * The events home page
	 */
	public function home() {
		global $I2_USER;

		$this->template = 'events_home.tpl';
		$this->template_args['signed_up'] = Event::user_events();
		//$this->template_args['may_sign_up'] = Event::non_user_events();
		$this->template_args['verifier_events'] = Event::verifier_events();
	}

	public function view() {
		global $I2_USER, $I2_ARGS;

		$event = new Event($I2_ARGS[2]);

		if (! ($event->user_can_sign_up() || $event->user_signed_up())) {
			$this->template = 'events_error.tpl';
			return 'Permission denied';
		}

		$this->template_args['event'] = $event;
		$this->template = 'events_view.tpl';
		return 'Events: View Event';
	}

	/**
	 * Sign up for an event
	 */
	public function signup() {
		global $I2_USER, $I2_ARGS;

		$event = new Event($I2_ARGS[2]);

		if (! $event->user_can_sign_up()) {
			warn('You are not allowed to sign up for this activity!');
			$this->template = 'events_error.tpl';
			return 'Permission denied';
		}

		if (isset($_REQUEST['event_sign_up'])) {
			$block = new EventBlock($_REQUEST['event_block']);
			if (! $event->user_can_sign_up($I2_USER, $block)) {
				warn('You are not allowed to sign up for this activity in the selected time period!');
				$this->template = 'events_error.tpl';
				return 'Permission denied';
			}
			$verifier = new User($_REQUEST['event_verifier']);
			$event->sign_up($block, $verifier);
			redirect('events');
		}

		$this->template_args['event'] = $event;
		$this->template = 'events_signup.tpl';
		return 'Events: Sign Up';
	}

	/**
	 * Verify users' payment
	 */
	public function verify() {
		global $I2_USER, $I2_ARGS;

		$event = new Event($I2_ARGS[2]);

		if (! $event->user_is_verifier()) {
			$this->template = 'events_error.tpl';
			return 'Permission denied';
		}

		if (isset($I2_ARGS[3])) {
			$user = new User($I2_ARGS[3]);
			if (isset($_REQUEST['events_verify_user'])) {
				$event->verify_payment($user);
				redirect('events/verify/'.$event->eid);
			}
			$this->template = 'events_verify_user.tpl';
			$this->template_args['event'] = $event;
			$this->template_args['user'] = $user;
		}
		else {
			$this->template = 'events_verify.tpl';
			$this->template_args['event'] = $event;
			$this->template_args['users'] = $event->verifier_users();
		}

		return 'Events: Verify Payment';
	}

	/**
	* Required by the {@link Module} interface.
	*/
	function display_pane($display) {
		$display->disp($this->template, $this->template_args);
	}
	
	/**
	* Required by the {@link Module} interface.
	*/
	function init_box() {
		return false;
		//return 'News: '.$num.' post'.($num==1?'':'s').' to read';
	}

	/**
	* Required by the {@link Module} interface.
	*/
	function display_box($display) {
		//$display->disp('news_box.tpl',array('summaries'=>$this->summaries,'newsadmin'=>$this->newsadmin));
	}

	/**
	* Required by the {@link Module} interface.
	*/
	function get_name() {
		return 'Events';
	}
}

?>