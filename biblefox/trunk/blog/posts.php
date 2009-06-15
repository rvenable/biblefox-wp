<?php

define(BFOX_TABLE_POST_REFS, BFOX_BASE_TABLE_PREFIX . 'post_refs');

class BfoxPosts {
	const table = BFOX_TABLE_POST_REFS;
	const ref_type_tag = 0;
	const ref_type_content = 1;

	public static function create_table() {
		// Note: for blog_id and post_id see WP's implementation in wp-admin/includes/schema.php
		BfoxUtility::create_table(self::table, "
			blog_id BIGINT(20) NOT NULL,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			ref_type BOOLEAN NOT NULL,
			verse_begin MEDIUMINT UNSIGNED NOT NULL,
			verse_end MEDIUMINT UNSIGNED NOT NULL,
			INDEX (blog_id, post_id),
			INDEX (verse_begin, verse_end)");
	}

	public static function get_post_ids(BibleRefs $refs) {
		$post_ids = array();

		if ($refs->is_valid()) {
			global $wpdb, $blog_id;

			$post_ids = $wpdb->get_col($wpdb->prepare("
				SELECT DISTINCT post_id
				FROM " . self::table ."
				WHERE blog_id = %d AND " . $refs->sql_where2(),
				$blog_id));
		}

		return $post_ids;
	}

	public static function get_post_ids_for_blogs(BibleRefs $refs, $blog_ids) {
		$post_ids = array();

		if (!empty($blog_ids)) {
			global $wpdb;

			foreach ($blog_ids as &$blog_id) $blog_id = $wpdb->prepare('%d', $blog_id);

			$results = $wpdb->get_results(
				"SELECT DISTINCT blog_id, post_id
				FROM " . self::table . "
				WHERE blog_id IN (" . implode(',', $blog_ids) . ") AND " . $refs->sql_where2());

			foreach ((array) $results as $result) $post_ids[$result->blog_id] []= $result->post_id;
		}

		return $post_ids;
	}

	private static function set_post_refs($post_id, BibleRefs $refs, $ref_type) {
		global $wpdb, $blog_id;

		$wpdb->query($wpdb->prepare('DELETE FROM ' . self::table . ' WHERE (blog_id = %d) AND (post_id = %d) AND (ref_type = %d)', $blog_id, $post_id, $ref_type));

		$values = array();
		foreach ($refs->get_seqs() as $seq) $values []= $wpdb->prepare('(%d, %d, %d, %d, %d)', $blog_id, $post_id, $ref_type, $seq->start, $seq->end);

		if (!empty($values)) {
			$wpdb->query($wpdb->prepare("
				INSERT INTO " . self::table . "
				(blog_id, post_id, ref_type, verse_begin, verse_end)
				VALUES " . implode(', ', $values)));
		}
	}

	public static function get_refs($post_ids = array()) {
		global $wpdb, $blog_id;

		$refs = array();

		$ids = array();
		foreach ($post_ids as $post_id) if (!empty($post_id)) $ids []= $wpdb->prepare('%d', $post_id);

		if (!empty($ids)) {
			$results = $wpdb->get_results($wpdb->prepare('
				SELECT post_id, verse_begin, verse_end
				FROM ' . self::table . '
				WHERE blog_id = %d AND post_id IN (' . implode(',', $ids) . ')',
				$blog_id
				));

			foreach ($results as $result) {
				if (!isset($refs[$result->post_id])) $refs[$result->post_id] = new BibleRefs;
				$refs[$result->post_id]->add_seq($result->verse_begin, $result->verse_end);
			}
		}

		return $refs;
	}

	public static function get_post_refs($post_id) {
		$refs = self::get_refs(array($post_id));
		if (isset($refs[$post_id])) return $refs[$post_id];
		else return new BibleRefs;
	}

	/*public static function get_refs_for_blogs($posts) {
		global $wpdb;

		$refs = array();

		$ids = array();
		foreach ($posts as $post) $ids []= $wpdb->prepare('(blog_id = %d AND post_id = %d)', $post->blog_id, $post->post_id);

		if (!empty($ids)) {
			$results = $wpdb->get_results('SELECT blog_id, post_id, ref_type, verse_begin, verse_end FROM ' . self::table . ' WHERE ' . implode(' OR ', $ids));

			foreach ($results as $result) {
				if (!isset($refs[$result->blog_id][$result->post_id][$result->ref_type])) $refs[$result->blog_id][$result->post_id][$result->ref_type] = new BibleRefs;
				$refs[$result->blog_id][$result->post_id][$result->ref_type]->add_seq($result->verse_begin, $result->verse_end);
			}
		}

		return $refs;
	}*/

	public static function update_post($post, $get_input_tag = FALSE) {
		$post_id = $post->ID;
		if (!empty($post_id)) {
			/*
			 * Post Content Refs
			 */
			$content_refs = new BibleRefs;

			// Get the bible references from the post content
			BfoxRefParser::simple_html($post->post_content, $content_refs);

			// Save these bible references
			if ($content_refs->is_valid()) self::set_post_refs($post_id, $content_refs, self::ref_type_content);

			/*
			 * Post Tag Refs
			 */
			$tags_refs = new BibleRefs;

			// Try to get a hidden tag from form input
			if ($get_input_tag) {
				$new_tag_refs = new BibleRefs($_POST[BfoxBlog::var_bible_ref]);
				if ($new_tag_refs->is_valid()) {
					$tags_refs->add_seqs($new_tag_refs->get_seqs());
					$new_tag = $new_tag_refs->get_string(BibleMeta::name_short);
				}
			}

			// Get the bible references from the post tags
			$tags = wp_get_post_tags($post_id, array('fields' => 'names'));
			foreach ($tags as &$tag) {
				$refs = new BibleRefs($tag);
				if ($refs->is_valid()) {
					$tag = $refs->get_string(BibleMeta::name_short);
					$tags_refs->add_seqs($refs->get_seqs());
				}

				if (trim($tag) == $new_tag) $new_tag = '';
			}

			if (!empty($new_tag)) $tags []= $new_tag;

			// Save these bible references
			self::set_post_refs($post_id, $tags_refs, self::ref_type_tag);
			// If we actually found some references, then re-save the tags again to use our modified tags
			if ($tags_refs->is_valid()) wp_set_post_tags($post_id, $tags);
		}
	}

	public static function refresh_posts() {
		global $wpdb;
		$ids = array();
		$posts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type = 'post'");
		foreach ($posts as $post) {
			self::update_post($post);
			$ids []= $post->ID;
		}
		return "Updated posts: " . implode(', ', $ids);
	}
}

?>