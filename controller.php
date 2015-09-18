<?php

namespace Plugin\Stats;

class Controller extends \Controller {

	public function index($f3) {
		$this->_requireLogin(0);
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


		// TODO: add issue stats


		// Render view
		echo \Helper\View::instance()->render("stats/index.html");
	}

}
