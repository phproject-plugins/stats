<?php

namespace Plugin\Stats;

class Controller extends \Controller {

	public function __construct() {
		$this->_requireLogin(0);
	}

	public function index(\Base $f3) {
		$f3->set("title", "Stats");
		echo \Helper\View::instance()->render("stats/index.html");
	}

	// Trend data
	// @todo return valid monthly data
	public function trends(\Base $f3) {
		$db = $f3->get("db.instance");
		$offset = \Helper\View::instance()->timeoffset();

		// Load daily stats
		$dailyQueryStart = date('Y-m-d H:i:s', strtotime('-30 days'));
		$dailyStart = strtotime("-30 days") + $offset;
		$result = $db->exec("SELECT DATE(closed_date) cd, COUNT(*) cn
				FROM issue
				WHERE closed_date >= ?
				GROUP BY cd ORDER BY cd", $dailyQueryStart);
		$dailyClosed = [];
		foreach ($result as $r) {
			$dailyClosed[$r["cd"]] = (int)$r["cn"];
		}

		$result = $db->exec("SELECT DATE(created_date) cd, COUNT(*) cn
				FROM issue
				WHERE created_date >= ?
				GROUP BY cd ORDER BY cd", $dailyQueryStart);
		$dailyCreated = [];
		foreach ($result as $r) {
			$dailyCreated[$r["cd"]] = (int)$r["cn"];
		}

		$dates = $this->_createDateRangeArray(date("Y-m-d", $dailyStart), date("Y-m-d", time() + $offset));
		$dailyLabels = [];
		foreach($dates as $date) {
			$dailyLabels[$date] = date("M j", strtotime($date));
			if(!isset($dailyClosed[$date])) {
				$dailyClosed[$date] = 0;
			}
			if(!isset($dailyCreated[$date])) {
				$dailyCreated[$date] = 0;
			}
		}
		ksort($dailyClosed);
		ksort($dailyCreated);

		// Load monthly stats
		$monthlyQueryStart = date('Y-m-01 H:i:s', strtotime('-36 months'));
		$monthlyStart = strtotime("-36 months") + $offset;
		$result = $db->exec("SELECT YEAR(closed_date) cy, MONTH(closed_date) cm, COUNT(*) cn
				FROM issue
				WHERE closed_date >= ?
				GROUP BY cy, cm
				ORDER BY cy DESC, cm DESC", $monthlyQueryStart);
		$monthlyClosed = [];
		foreach ($result as $r) {
			$monthlyClosed["{$r['cy']}-{$r['cm']}"] = $r["cn"];
		}

		$result = $db->exec("SELECT YEAR(created_date) cy, MONTH(created_date) cm, COUNT(*) cn
				FROM issue
				WHERE created_date >= ?
				GROUP BY cy, cm
				ORDER BY cy DESC, cm DESC", $monthlyQueryStart);
		$monthlyCreated = [];
		foreach ($result as $r) {
			$monthlyCreated["{$r['cy']}-{$r['cm']}"] = $r["cn"];
		}

		$months = $this->_createMonthRangeArray(date("Y-m", $monthlyStart), date("Y-m", time() + $offset));
		$monthlyLabels = [];
		foreach($months as $month) {
			$monthlyLabels[$month] = date("M Y", strtotime($month));
			if(!isset($monthlyClosed[$month])) {
				$monthlyClosed[$month] = 0;
			}
			if(!isset($monthlyCreated[$month])) {
				$monthlyCreated[$month] = 0;
			}
		}
		ksort($monthlyClosed);
		ksort($monthlyCreated);

		header("Cache-Control: private, max-age=3600");
		$this->_printJson([
			"daily" => [
				"labels" => array_values($dailyLabels),
				"datasets" => [
					[
						"data" => array_values($dailyClosed),
						"label" => $f3->get("dict.closed"),
						"borderColor" => "#3498db",
						"pointBackgroundColor" => "#3498db",
						"pointBorderColor" => "#3498db",
					], [
						"data" => array_values($dailyCreated),
						"label" => $f3->get("dict.cols.created"),
						"borderColor" => "#2ecc71",
						"pointBackgroundColor" => "#2ecc71",
						"pointBorderColor" => "#2ecc71",
					]
				]
			],
			"monthly" => [
				"labels" => array_values($monthlyLabels),
				"datasets" => [
					[
						"data" => array_values($monthlyClosed),
						"label" => $f3->get("dict.closed"),
						"borderColor" => "#3498db",
						"pointBackgroundColor" => "#3498db",
						"pointBorderColor" => "#3498db",
					], [
						"data" => array_values($monthlyCreated),
						"label" => $f3->get("dict.cols.created"),
						"borderColor" => "#2ecc71",
						"pointBackgroundColor" => "#2ecc71",
						"pointBorderColor" => "#2ecc71",
					]
				]
			],
		]);
	}

	// User stats
	public function users(\Base $f3) {
		$db = $f3->get("db.instance");

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

		header("Cache-Control: private, max-age=3600");
		$this->_render("stats/ajax/users.html");
	}

	// Issue stats
	public function issues(\Base $f3) {
		$db = $f3->get("db.instance");

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

		header("Cache-Control: private, max-age=3600");
		$this->_render("stats/ajax/issues.html");
	}

	/**
	 * Takes two dates and creates an inclusive array of the dates between
	 * the from and to dates in YYYY-MM-DD format.
	 * @param  string $dateFrom
	 * @param  string $dateTo
	 * @return array
	 */
	protected function _createDateRangeArray($dateFrom, $dateTo) {
		$range = [];
		$from = strtotime($dateFrom);
		$to = strtotime($dateTo);

		if ($to >= $from) {
			$range[] = date('Y-m-d', $from); // first entry
			while ($from < $to) {
				$from += 86400; // add 24 hours
				$range[] = date('Y-m-d', $from);
			}
		}

		return $range;
	}

	/**
	 * Takes two months and creates an inclusive array of the months between
	 * the from and to months in YYYY-MM format.
	 * @param  string $monthFrom
	 * @param  string $monthTo
	 * @return array
	 */
	protected function _createMonthRangeArray($monthFrom, $monthTo) {
		$range = [];
		$from = strtotime($monthFrom);
		$to = strtotime($monthTo);

		if ($to >= $from) {
			$last = date('Y-n', $from);
			$range[] = $last;
			while ($from < $to) {
				$last = date('Y-n', strtotime($last . " +1 month"));
				$from = strtotime($last);
				$range[] = $last;
			}
		}

		return $range;
	}

}
