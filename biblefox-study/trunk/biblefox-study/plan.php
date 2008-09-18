<?php

	class Plan
	{
		private $table_name = BFOX_TABLE_READ_PLAN;

		private function create_table()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			$sql = "CREATE TABLE " . $this->table_name . " (
			plan_id int,
			period_id int,
			ref_id int,
			verse_start int,
			verse_end int
			);";
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		function insert($plan_refs_array)
		{
			global $wpdb;
			$plan_id = 1;

			// If the table doesn't exist, create it
			if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name)
				$this->create_table();
			else
				$plan_id = 1 + $wpdb->get_var("SELECT MAX(plan_id) FROM $this->table_name");

			$period_id = 0;
			foreach ($plan_refs_array as $plan_refs)
			{
				$ref_id = 0;
				foreach ($plan_refs->get_sets() as $unique_ids)
				{
					$insert = $wpdb->prepare("INSERT INTO $this->table_name (plan_id, period_id, ref_id, verse_start, verse_end) VALUES (%d, %d, %d, %d, %d)", $plan_id, $period_id, $ref_id, $unique_ids[0], $unique_ids[1]);
					$wpdb->query($insert);
					$ref_id++;
				}

				$period_id++;
			}
		}

		function get($plan_id)
		{
			global $wpdb;
			$results = $wpdb->get_results($wpdb->prepare("SELECT * from $this->table_name WHERE plan_id = %d", $plan_id));

			$sets = array();
			foreach ($results as $result)
			{
				$sets[$result->period_id][$result->ref_id] = array($result->verse_start, $result->verse_end);
			}

			// Sort the $sets array by its keys (period_id) so that it is in the proper order
			ksort($sets);

			$plan_refs_array = array();
			foreach ($sets as $unique_ids)
			{
				// Sort the $unique_ids array by its keys (ref_id) so that it is in the proper order
				ksort($unique_ids);

				$refs = new BibleRefs($unique_ids);
				if ($refs->is_valid())
					$plan_refs_array[] = $refs;
			}

			return $plan_refs_array;
		}

		function get_plan_ids()
		{
			global $wpdb;
			$plan_ids = $wpdb->get_col("SELECT plan_id from $this->table_name GROUP BY plan_id");
			if (is_array($plan_ids)) return $plan_ids;
			return array();
		}

		function delete($plan_id)
		{
			global $wpdb;
			$wpdb->query($wpdb->prepare("DELETE FROM $this->table_name WHERE plan_id = %d", $plan_id));
		}
	}

	global $bfox_plan;
	$bfox_plan = new Plan();

?>
