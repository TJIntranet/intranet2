<?php

/**
* Just contains the definition for the module {@link CodeInterface}.
* @author The Intranet 2 Development Team <intranet2@tjhsst.edu>
* @copyright 2005 The Intranet 2 Development Team
* @since 1.0
* @package modules
* @subpackage Admin
* @filesource
*/

/**
* A module to show phpinfo (for admins only) to help debug php config 
* @package modules
* @subpackage Admin
*/
class PHPInfo extends Module {

	/**
	* Send back the command name.
	*/
	function init_cli() {
		return "phpinfo";
	}

	/**
	* Unused; Not supported for this module.
	*
	* @param Display $disp The Display object to use for output.
	*/
	function display_cli($disp) {
		global $I2_USER;
		if (!$I2_USER->is_group_member('admin_all'))
			return "<div>Access Denied</div>";
		ob_start();
		phpinfo();
		$pinfo=ob_get_contents();
		ob_end_clean();
		$pinfo=preg_replace( '%^.*<body>(.*)</body>.*$%ms','$1',$pinfo);
		return $pinfo;
	}

	/**
	* Displays all of a module's main content.
	*
	* @param Display $disp The Display object to use for output.
	*/
	function display_pane($disp) {
		return;
	}
	
	/**
	* Gets the module's name.
	*
	* @returns string The name of the module.
	*/
	function get_name() {
		return 'phpinfo';
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
		global $I2_USER;
		if (!$I2_USER->is_group_member('admin_all'))
			return FALSE;

		phpinfo();
		exit();
	}
}
?>
