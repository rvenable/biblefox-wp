<?php

define('BFOX_USER_LEVEL_MANAGE_USERS', 'edit_users');
define('BFOX_COL_TYPE_ID', 'BIGINT(20) UNSIGNED');

	class BfoxMessage
	{
		private $table_name;

		function BfoxMessage()
		{
			$this->table_name = BFOX_BASE_TABLE_PREFIX . "message";

			// Types of messages
			$this->types = array('message', 'join_request', 'join_request_reply');
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
			global $wpdb, $user_ID;
			if (!is_int($reply_value)) $reply_value = $this->join_request_status[$reply_value];
			$thread_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $this->table_name WHERE type = %d AND from_id = %d", $this->types['join_request'], $reply_to_user_id));
			$this->send_message($thread_id, $user_ID, $reply_to_user_id, 'join_request_reply', $reply_value);
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
				$requests[$result->id] = $result;
				$threads[] = $wpdb->prepare('thread_id = %d', $result->id);
			}

			if (0 < count($threads))
			{
				$select = $wpdb->prepare("SELECT * FROM $this->table_name
										 WHERE type = %d AND (" . implode(' OR ', $threads) . ") ORDER BY time",
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
		if (isset($_POST['accept_users'])) $reply_value = $bfox_message->join_request_status['accepted'];
		if (isset($_POST['decline_users'])) $reply_value = $bfox_message->join_request_status['declined'];

		$requests = $bfox_message->get_join_requests(NULL, $blog_id);

		$waiting = array();
		foreach ($requests as $id => $request)
		{
			if ($bfox_message->join_request_status['waiting'] == $request->status)
				$waiting[$request->user_id] = $request;
		}

		if (0 < count($requests))
		{
			echo '<div class="wrap">';
			echo '<h2>' . __('User Requests') . '</h2>';

			if (current_user_can(BFOX_USER_LEVEL_MANAGE_USERS) && isset($reply_value))
			{
				check_admin_referer('bulk-user-requests');
				$messages = array();
				foreach ((array) $_POST['select_user'] as $user_id)
				{
					if (isset($waiting[$user_id]))
					{
						$user = get_userdata($user_id);
						if ('accepted' == $bfox_message->join_request_status[$reply_value])
						{
							$role = $_POST['new_role-' . $user_id];
							add_user_to_blog($blog_id, $user_id, $role);
							$messages[] = 'Added new user: ' . $user->user_login . ' (' . $role . ')';
							wp_mail($user->user_email, sprintf(__('[%s] Request to Join Accepted'), get_option('blogname')), "Hi,\n\nYou have been added to a bible study: '" . get_option( 'blogname' ) . "' at\n" . site_url() . "\n");
						}
						else
						{
							$messages[] = 'Declined user: ' . $user->user_login;
							wp_mail($user->user_email, sprintf(__('[%s] Request to Join Declined'), get_option('blogname')), "Hi,\n\nYour request to join this bible study has been declined: '" . get_option( 'blogname' ) . "' at\n" . site_url() . "\n");
						}

						$bfox_message->send_join_request_reply($user_id, $reply_value);
						unset($waiting[$user_id]);
					}
				}
				if (0 < count($messages))
					echo '<br class="clear"/><div id="message" class="updated fade"><p>' . implode('<br/>', $messages) . '</p></div>';
			}

			if (0 < count($waiting))
			{

				echo '<p><strong>The following users request to join this bible study:</strong></p>';

?>
<form id="posts-filter" action="" method="post">
<input type="hidden" name="page" value="<?php echo BFOX_PROGRESS_SUBPAGE; ?>">

<div class="tablenav">

<div class="alignleft">
<input type="submit" value="<?php _e('Accept Selected Users'); ?>" name="accept_users" class="button-secondary" />
<input type="submit" value="<?php _e('Decline Selected Users'); ?>" name="decline_users" class="button-secondary" />
<?php wp_nonce_field('bulk-user-requests'); ?>
</div>

<br class="clear" />
</div>

<br class="clear" />

<table class="widefat">
	<thead>
	<tr>
		<th scope="col" class="check-column"></th>
        <th scope="col"><?php _e('User') ?></th>
		<th scope="col"><?php _e('Email') ?></th>
		<th scope="col"><?php _e('New Role') ?></th>
	</tr>
	</thead>
	<tbody id="the-list" class="list:cat">
<?php
				foreach ($waiting as $request)
				{
					$user = get_userdata($request->user_id);

					$class = ('alternate' == $class) ? '' : 'alternate';
					echo '<tr class="' . $class . '">';
					echo '<th scope="row" class="check-column"> <input type="checkbox" name="select_user[]" value="' . $user->ID . '" /></th>';
					echo '<td>' . $user->user_login . '</td>';
					echo '<td>' . $user->user_email . '</td>';
					echo '<td><select name="new_role-' . $user->ID . '" id="new_role">';
					wp_dropdown_roles('author');
					echo '</select></td>';
					echo '</tr>';
				}
				echo '</table></form><br class="clear"/>';
			}
			else
			{
				echo '<p><strong>There are no remaining user requests at this time.</strong></p>';
			}
			echo '</div>';
		}
	}

?>
