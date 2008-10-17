<?php

	class BfoxMessage
	{
		private $table_name;

		function BfoxMessage()
		{
			$this->table_name = BFOX_BASE_TABLE_PREFIX . "message";

			// Types of messages
			$this->types = array('message', 'join_request');
			$this->types = array_merge($this->types, array_flip($this->types));

			// Join Request Status
			$this->join_request_status = array('waiting', 'accepted', 'declined');
			$this->join_request_status = array_merge($this->join_request_status, array_flip($this->join_request_status));

			if (!$this->are_tables_installed())
				$this->create_tables();

			global $blog_id;
//			$this->send_join_request($blog_id);
		}

		function are_tables_installed()
		{
			global $wpdb;
			return (!isset($this->table_name) || ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name));
		}
		
		function create_tables()
		{
			// Note this function creates the table with dbDelta() which apparently has some pickiness
			// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

			if (isset($this->table_name))
			{
				$sql = "CREATE TABLE $this->table_name (
				id " . BFOX_COL_TYPE_ID . " NOT NULL AUTO_INCREMENT,
				thread_id " . BFOX_COL_TYPE_ID . " NULL,
				from_id " . BFOX_COL_TYPE_ID . " NULL,
				to_id " . BFOX_COL_TYPE_ID . " NULL,
				type INTEGER UNSIGNED NOT NULL,
				time DATETIME NULL,
				value BIGINT NULL,
				PRIMARY KEY  (id)
				);";
				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
		}

		function send_message($thread_id, $from, $to, $type = 'message', $value = NULL)
		{
			global $wpdb;

			if (!is_int($type)) $type = $this->types[$type];

			$insert = $wpdb->prepare("INSERT INTO $this->table_name
									 SET from_id = %d, to_id = %d, type = %d, time = NOW()",
									 $from, $to, $type);

			if (!is_null($value)) $insert .= $wpdb->prepare(', value = %d', $value);
			if (!is_null($thread_id)) $insert .= $wpdb->prepare(', thread_id = %d', $thread_id);

			$wpdb->query($insert);
		}

		function send_join_request($blog_id)
		{
			global $user_ID;
			$this->send_message(NULL, $user_ID, $blog_id, 'join_request');
		}

		function send_join_request_reply($reply_to_user_id, $reply_value)
		{
			global $user_ID;
			if (!is_int($reply_value)) $reply_value = $this->join_request_status[$reply_value];
			$this->send_message(NULL, $user_ID, $reply_to_user_id, 'join_request_reply', $reply_value);
		}
		
		function get_join_requests($user_id = NULL, $blog_id = NULL)
		{
			global $wpdb;

			// Get all the join requests
			$select = $wpdb->prepare("SELECT * FROM $this->table_name WHERE type = %d", $this->types['join_request']);
			if (!is_null($user_id)) $select .= $wpdb->prepare(' AND from_id = %d', $user_id);
			if (!is_null($blog_id)) $select .= $wpdb->prepare(' AND to_id = %d', $blog_id);
			$results = $wpdb->get_results($select . ' ORDER BY time DESC');

			// Create an array of all join requests
			$requests = array();
			foreach ($results as $result)
			{
				$result->user_id = $result->from_id;
				$result->blog_id = $result->to_id;
				$result->status = $this->join_request_status['waiting'];
				$requests[$result->thread_id] = $result;
				$threads[] = $wpdb->prepare('thread_id = %d', $result->id);
			}
			
			if (0 < count($threads))
			{
				$select = $wpdb->prepare("SELECT * FROM $this->table_name
										 WHERE type = %d AND (" . implode(' OR ', $threads) . ") ORDER BY time DESC LIMIT 1",
										 $this->types['join_request_reply']);
				$results = $wpdb->get_results($select);
				foreach ($results as $result)
				{
					$requests[$result->thread_id]->status = $result->value;
				}
			}

			return $requests;
		}
	}

	global $bfox_message;
	$bfox_message = new BfoxMessage();
	
	function bfox_join_request_menu()
	{
		global $blog_id, $bfox_message;
		$requests = $bfox_message->get_join_requests(NULL, $blog_id);
		
		if (0 < count($requests))
		{
			echo '<div class="wrap">';
			echo '<h2>' . __('Requests') . '</h2>';
			echo '<ul>';
			foreach ($requests as $request)
			{
				$user = get_userdata($request->user_id);
				echo '<li> User ' . $user->user_login . ' (' . $user->user_email . ') requests to join this bible study.</li>';
			}
			echo '</ul></div>';
		}
	}

?>
