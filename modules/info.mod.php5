<?php
/**
* Just contains the definition for the {@link Module} Info.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2005 The Intranet 2 Development Team
* @since 1.0
* @package modules
* @subpackage Info
* @filesource
*/

/**
* A Module to display static information, so that the information does not require an entire class devoted to it.
* @package modules
* @subpackage Info
*/
class Info implements Module {

	/**
	* Displays all of a module's ibox content.
	*
	* @param Display $disp The Display object to use for output.
	* @abstract
	*/
	function display_box($disp) {
		return FALSE;
	}
	
	/**
	* Displays all of a module's main content.
	*
	* @param Display $disp The Display object to use for output.
	* @abstract
	*/
	function display_pane($disp) {
		global $I2_ARGS;
		
		$section = implode("/", array_slice($I2_ARGS, 1));
		try {
			$disp->disp($section . '.tpl');
		}
		catch( I2Exception $e ) {
			$disp->disp('error.tpl', array('sec'=>$section));
		}
	}
	
	/**
	* Gets the module's name.
	*
	* @returns string The name of the module.
	* @abstract
	*/
	function get_name() {
		return 'info';
	}
	/**
	* Performs all initialization necessary for this module to be 
	* displayed in an ibox.
	*
	* @returns string The title of the box if it is to be displayed,
	*                 otherwise FALSE if this module doesn't have an
	*                 intrabox.
	* @abstract
	*/
	function init_box() {
		return FALSE;
	}

	/**
	* Performs all initialization necessary for this module to be
	* displayed as the main page.
	*
	* @returns mixed Either a string, which will be the title for both the
	*                main pane and for part of the page title, or an array
	*                of two strings: the first is part of the page title,
	*                and the second is the title of the content pane. To
	*                specify no titles, return an empty array. To specify
	*                that this module has no main content pane (and will
	*                show an error if someone tries to access it as such),
	*                return FALSE.
	* @abstract
	*/
	function init_pane() {
		global $I2_ARGS;
		
		if(!isset($I2_ARGS[1])) {
			redirect();
		}
		elseif(Display::is_template('info/'.$I2_ARGS[1].'.tpl')) {
			return ucfirst($I2_ARGS[1]);
		}
		else {
			return 'Error';
		}
	}

	/**
	* Returns whether this module functions as an intrabox.
	*
	* @returns boolean True if the module has an intrabox, false if it does not.
	*
	* @abstract
	*/
	function is_intrabox() {
		return FALSE;
	}
	
}
?>
