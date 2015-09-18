<?php
/**
 * @package  Stats
 * @author   Alan Hardman <alan@phpizza.com>
 * @license  GPL
 * @version  1.0.0
 */

namespace Plugin\Stats;

class Base extends \Plugin {

	/**
	 * Check if plugin is installed
	 * @return bool
	 */
	function _installed() {
		return !!\Base::instance()->get("statsplugin.key");
	}

	/**
	 * Install plugin
	 */
	function _install() {
		$f3 = \Base::instance();
		$key = sha1(mt_rand() . mt_rand());
		$config = new \Model\Config;
		$config->setVal("statsplugin.key", $key);
		$config->setVal("statsplugin.last_sent", 0);
	}

	/**
	 * Initialize plugin
	 */
	function _load() {
		$f3 = \Base::instance();
		$f3->route("GET /stats", "Plugin\Stats\Controller->index");

		// Post stats if last update was more than one week ago
		if($f3->get("statsplugin.last_sent") < strtotime("-1 week")) {
			$this->postStats();
		}
	}

	/**
	 * Generate page for admin panel
	 */
	public function _admin() {
		echo \Helper\View::instance()->render("stats/admin.html");
	}

	/**
	 * Asynchronously post anonymous statistics to meta.phproject.org
	 */
	public function postStats() {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");

		// Add unique ID
		$data = array("key" => $f3->get("statsplugin.key"), "revision" => $f3->get("revision"));

		// Add basic stats
		$result = $db->exec("SELECT COUNT(id) AS `count` FROM user WHERE role != 'group'");
		$data["users"] = $result[0]["count"];
		$result = $db->exec("SELECT COUNT(id) AS `count` FROM user WHERE role = 'group'");
		$data["groups"] = $result[0]["count"];
		$result = $db->exec("SELECT COUNT(id) AS `count` FROM issue");
		$data["issues"] = $result[0]["count"];
		$result = $db->exec("SELECT COUNT(id) AS `count` FROM issue_update");
		$data["updates"] = $result[0]["count"];
		$result = $db->exec("SELECT COUNT(id) AS `count` FROM issue_comment");
		$data["comments"] = $result[0]["count"];
		$result = $db->exec("SELECT value as version FROM config WHERE attribute = 'version'");
		$data["version"] = $result[0]["version"];

		if($this->asyncPost("http://meta.phproject.org/stats/post.php", $data)) {
			$config = new \Model\Config;
			$config->setVal("statsplugin.last_sent", time());
		}
	}

	/**
	 * Asynchronously post data via HTTP and sockets
	 * @see    http://stackoverflow.com/q/14587514/873843
	 * @param  string $url
	 * @param  array  $params
	 */
	protected function asyncPost($url, array $params = array()) {
		// create POST string
		$post_params = array();
		foreach ($params as $key => &$val) {
			$post_params[] = $key . '=' . urlencode($val);
		}
		$post_string = implode('&', $post_params);

		// get URL segments
		$parts = parse_url($url);

		// workout port and open socket
		$port = isset($parts['port']) ? $parts['port'] : 80;
		$fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);

		if($fp) {
			// create output string
			$output  = "POST " . $parts['path'] . " HTTP/1.1\r\n";
			$output .= "Host: " . $parts['host'] . "\r\n";
			$output .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$output .= "Content-Length: " . strlen($post_string) . "\r\n";
			$output .= "Connection: Close\r\n\r\n";
			$output .= !empty($post_string) ? $post_string : '';

			// send output to $url handle
			fwrite($fp, $output);
			fclose($fp);

			return true;
		} else {
			return false;
		}

	}

}
