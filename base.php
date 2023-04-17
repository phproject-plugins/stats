<?php
/**
 * @package  Stats
 * @author   Alan Hardman <alan@phpizza.com>
 * @license  GPL
 * @version  1.0.0
 */

namespace Plugin\Stats;

class Base extends \Plugin
{
	/**
	 * Initialize plugin
	 */
	function _load() {
		$f3 = \Base::instance();
		$f3->route("GET /stats", "Plugin\Stats\Controller->index");
		$f3->route("GET /stats/trends", "Plugin\Stats\Controller->trends");
		$f3->route("GET /stats/users", "Plugin\Stats\Controller->users");
		$f3->route("GET /stats/issues", "Plugin\Stats\Controller->issues");
	}

	/**
	 * Generate page for admin panel
	 */
	public function _admin() {
		echo \Helper\View::instance()->render("stats/admin.html");
	}
}
