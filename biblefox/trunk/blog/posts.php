<?php

define(BFOX_TABLE_POST_REFS, BFOX_BASE_TABLE_PREFIX . 'post_refs');

class BfoxPosts
{
	const table = BFOX_TABLE_POST_REFS;
	const ref_type_tag = 0;
	const ref_type_content = 1;

	public static function create_table()
	{
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

	public static function get_post_ids(BibleRefs $refs)
	{
		$post_ids = array();

		if ($refs->is_valid())
		{
			global $wpdb, $blog_id;

			$post_ids = $wpdb->get_col($wpdb->prepare("
				SELECT DISTINCT post_id
				FROM " . self::table ."
				WHERE blog_id = %d AND " . $refs->sql_where2('verse_begin', 'verse_end'),
				$blog_id));
		}

		return $post_ids;
	}

	public static function get_post_ids_for_blogs(BibleRefs $refs, $blog_ids)
	{
		$post_ids = array();

		if (!empty($blog_ids))
		{
			global $wpdb;

			foreach ($blog_ids as &$blog_id) $blog_id = $wpdb->prepare('%d', $blog_id);

			$post_ids = //$wpdb->get_col("
				"SELECT DISTINCT blog_id, post_id
				FROM " . self::table . "
				WHERE blog_id IN (" . implode(',', $blog_ids) . ") AND " . $refs->sql_where2('verse_begin', 'verse_end');
		}

		return $post_ids;
	}

	public static function set_post_refs($post_id, BibleRefs $refs, $ref_type)
	{
		global $wpdb, $blog_id;

		$wpdb->query($wpdb->prepare('DELETE FROM ' . self::table . ' WHERE (blog_id = %d) AND (post_id = %d) AND (ref_type = %d)', $blog_id, $post_id, $ref_type));

		$values = array();
		foreach ($refs->get_seqs() as $seq) $values []= $wpdb->prepare('(%d, %d, %d, %d, %d)', $blog_id, $post_id, $ref_type, $seq->start, $seq->end);

		if (!empty($values))
		{
			$wpdb->query($wpdb->prepare("
				INSERT INTO " . self::table . "
				(blog_id, post_id, ref_type, verse_begin, verse_end)
				VALUES " . implode(', ', $values)));
		}
	}

	public static function get_refs($post_ids = array())
	{
		global $wpdb, $blog_id;

		$refs = array();

		$ids = array();
		foreach ($post_ids as $post_id) $ids []= $wpdb->prepare('%d', $post_id);

		if (!empty($ids))
		{
			$results = $wpdb->get_results($wpdb->prepare('
				SELECT post_id, verse_begin, verse_end
				FROM ' . self::table . '
				WHERE blog_id = %d AND post_id IN (' . implode(',', $ids) . ')',
				$blog_id
				));

			foreach ($results as $result)
			{
				if (isset($refs[$result->post_id])) $refs[$result->post_id]->add_seq($result->verse_begin, $result->verse_end);
				else $refs[$result->post_id] = RefManager::get_from_sets(array(array($result->verse_begin, $result->verse_end)));
			}
		}

		return $refs;
	}

	public static function get_post_refs($post_id)
	{
		$refs = self::get_refs(array($post_id));
		if (isset($refs[$post_id])) return $refs[$post_id];
		else return new BibleRefs();
	}

	public static function get_refs_for_blogs($posts)
	{
		global $wpdb;

		$refs = array();

		$ids = array();
		foreach ($posts as $post) $ids []= $wpdb->prepare('(blog_id = %d AND post_id = %d)', $post->blog_id, $post->post_id);

		if (!empty($ids))
		{
			$results = $wpdb->get_results('SELECT blog_id, post_id, ref_type, verse_begin, verse_end FROM ' . self::table . ' WHERE ' . implode(' OR ', $ids));

			foreach ($results as $result)
			{
				$ref &= $refs[$result->blog_id][$result->post_id][$result->ref_type];
				if (isset($ref)) $ref->add_seq($result->verse_begin, $result->verse_end);
				else $ref = RefManager::get_from_sets(array(array($result->verse_begin, $result->verse_end)));
			}
		}

		return $refs;
	}
}

?>