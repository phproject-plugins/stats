<?php

namespace Plugin\Stats;

class Controller extends \Controller {

	public function index($f3) {
		$this->_requireLogin(0);
		$db = $f3->get("db.instance");

		// User stats
		$result = $db->exec("SELECT AVG(a.num) num
				FROM (
					SELECT COUNT(*) AS num
					FROM issue i
					GROUP BY i.author_id
					HAVING num > 0
				) a");
		$f3->set("user_avg_created", $result[0]['num']);

		$result = $db->exec("SELECT AVG(a.num) num
				FROM (
					SELECT COUNT(*) num
					FROM issue_comment c
					GROUP BY c.user_id
				) a");
		$f3->set("user_avg_comments", $result[0]['num']);

		$result = $db->exec("SELECT AVG(a.num) num
				FROM (
					SELECT COUNT(*) num
					FROM issue_update u
					GROUP BY u.user_id
				) a");
		$f3->set("user_avg_updates", $result[0]['num']);

		$result = $db->exec("SELECT AVG(a.num) num
				FROM (
					SELECT SUM(new_value - old_value) num
					FROM issue_update_field f
					JOIN issue_update u ON u.id = f.issue_update_id
					WHERE f.field = 'hours_spent'
					GROUP BY u.user_id
					HAVING num > 0
				) a");
		$f3->set("user_avg_hours_spent", $result[0]['num']);

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
		$result = $db->exec("SELECT SUM(hours_spent) num FROM issue");
		$f3->set("issues_total_hours_spent", $result[0]['num']);

		$result = $db->exec("SELECT AVG(hours_spent) num
				FROM issue
				WHERE hours_spent > 0
					AND hours_spent IS NOT NULL");
		$f3->set("issues_avg_hours_spent", $result[0]['num']);

		$result = $db->exec("SELECT COUNT(*) num FROM issue WHERE closed_date IS NOT NULL");
		$f3->set("issues_total_closed", $result[0]['num']);

		$result = $db->exec("SELECT i.id, i.name, COUNT(*) num
				FROM issue i
				JOIN issue_comment c ON c.issue_id = i.id
				GROUP BY i.id
				ORDER BY num DESC
				LIMIT 25");
		$f3->set("top_issue_comments", $result);

		$result = $db->exec("SELECT i.id, i.name, COUNT(*) num
				FROM issue i
				JOIN issue_update u ON u.issue_id = i.id
				GROUP BY i.id
				ORDER BY num DESC
				LIMIT 25");
		$f3->set("top_issue_updates", $result);

		$result = $db->exec("SELECT i.id, i.name, i.hours_spent num
				FROM issue i
				WHERE hours_spent > 0
					AND hours_spent IS NOT NULL
				ORDER BY hours_spent DESC
				LIMIT 25");
		$f3->set("top_issue_hours", $result);


		// Render view
		echo \Helper\View::instance()->render("stats/index.html");
	}

}
