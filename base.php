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
	 * Initialize plugin
	 */
	function _load() {
		$f3 = \Base::instance();
		$f3->route("GET /stats", function() use ($f3) {
			$db = $f3->get("db.instance");

			// User stats
			$result = $db->exec("SELECT author_name name, COUNT(*) num
					FROM issue_detail
					GROUP BY author_id
					ORDER BY num DESC
					LIMIT 25");
			$f3->set("user_created", $result);

			$result = $db->exec("SELECT owner_name name, COUNT(*) num
					FROM issue_detail
					WHERE status_closed = '1'
					GROUP BY owner_id
					ORDER BY num DESC
					LIMIT 25");
			$f3->set("user_closed", $result);

			$result = $db->exec("SELECT u.name, COUNT(*) num
					FROM issue_update iu
					JOIN user u ON u.id = iu.user_id
					GROUP BY u.id
					ORDER BY num DESC
					LIMIT 25");
			$f3->set("user_updates", $result);

			$result = $db->exec("SELECT u.name, SUM(iuf.new_value - iuf.old_value) num
					FROM issue_update iu
					JOIN issue_update_field iuf ON iu.id = iuf.issue_update_id
					JOIN `user` u ON u.id = iu.user_id
					WHERE iuf.field = 'hours_spent'
					GROUP BY u.id
					ORDER BY num DESC
					LIMIT 25");
			$f3->set("user_hours", $result);


			// Issue stats


			// Render view
			echo \Helper\View::instance()->render("stats/index.html");
		});
	}

	/**
	 * Generate page for admin panel
	 */
	public function _admin() {
		echo \Helper\View::instance()->render("stats/admin.html");
	}

}
