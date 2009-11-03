<?php
/**
* @author Derek Morris
* @package modules
* @subpackage JS
* @filesource
*/

/**
* @package modules
* @subpackage JS
*/
// This is to allow for theme-specific javascript
// Note that most of this is copied from css.mod.php5
// Although it's not as powerful, or as complex
// But it should be faster, and the javascript for the
// styles isn't likely to be too complex anyway.
class JS implements Module {

	private $warnings = array();

	private $date;

	private $script_path;

	private $script_cache;

	/**
	* Required by the {@link Module} interface.
	*/
	function init_box() {
		return FALSE;
	}

	/**
	* Required by the {@link Module} interface.
	*/
	function display_box($disp) {
		return FALSE;
	}
	
	/**
	* Required by the {@link Module} interface.
	*/
	function display_pane($disp) {
		
		header('Content-type: text/javascript');
		header("Last-Modified: {$this->gmdate}");
		header('Cache-Control: public');
		header('Pragma:'); // Unset Pragma header
		header('Expires:'); // Unset Expires header
		echo "/* Server-Cache: {$this->script_cache} */\n";
		echo "/* Client-Cached: {$this->date} */\n";
		
		$disp->clear_buffer();
		$text = file_get_contents($this->script_cache); // Put something here!!!
		echo $text;
		
		Display::stop_display();
		
		exit;
	}
	
	/**
	* Required by the {@link Module} interface.
	*/
	function get_name() {
		return 'js';
	}

	/**
	* Required by the {@link Module} interface.
	*/
	function init_pane() {
		global $I2_ARGS, $I2_USER, $I2_FS_ROOT;
		
		$current_style = $I2_ARGS[1];

		if (ends_with($current_style, '.js')) {
			$current_style = substr($current_style, 0, strlen($current_style) - 3);
		}
		
		$this->script_path = $I2_FS_ROOT . 'javascriptstyles/' . $current_style . '.js';
		$cache_dir = i2config_get('cache_dir', NULL, 'core') . 'javascriptstyles/';
		if (!is_dir($cache_dir)) {
			mkdir($cache_dir, 0777, TRUE);
		}

		$script_cache = $cache_dir . $I2_USER->uid;

		//Recomi)le the cache if it's stale
		if (!file_exists($script_cache)) {
			// || (filemtime($script_cache) < filemtime($this->script_path))
			$date = date("D M j G:i:s T Y");
			$contents = "/* Server cache '$current_style' created on $date */\n";
			foreach ($this->warnings as $message) {
				$contents .= "/* WARNING: $message */\n";
			}
			$parser = new Display();
			$contents .= $parser->fetch($this->script_path,array(),FALSE);
			$contents .= "\n/* End of file */\n";
			file_put_contents($script_cache,$contents);
		}
		
		$this->gmdate = gmdate('D, d M Y H:i:s', filemtime($script_cache)) . ' GMT';
		
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			$if_modified_since = preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
			if ($if_modified_since == $this->gmdate) {
				Display::stop_display();
				header('HTTP/1.0 304 Not Modified');
				exit;
			}
		}

		$date = date('D M j G:i:s T Y');
		$this->date = $date;

		$this->script_cache = $script_cache;

		return 'js';
	}
}

?>