<?php
global $tooltip_refs;
$ref_str = $tooltip_refs->get_string();

$query = BfoxBlog::query_for_refs($tooltip_refs);
$count = 0;

?>

<div class="bfox-tooltip-posts">
	<div><?php echo $ref_str . __(' blog posts', 'biblefox-blog') ?></div>
	<ul>
		<?php while($count < 10 && $query->have_posts()): $count++; $query->the_post() ?>
		<li><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></li>
		<?php endwhile ?>
		<?php if (0 == $count): ?>
		<li><?php _e('No blog posts', 'biblefox-blog') ?></li>
		<?php endif ?>
	</ul>
	<div><?php echo $ref_str . __(' links', 'biblefox-blog') ?></div>
	<ul>
		<li><?php echo Biblefox::ref_bible_link(array('ref_str' => $ref_str, 'text' => __('Bible Reader', 'biblefox-blog'))) ?></li>
		<li><?php echo BfoxBlog::ref_blog_link(array('ref_str' => $ref_str, 'text' => __('Post Archive', 'biblefox-blog'))) ?></li>

		<?php if (current_user_can('edit_posts')): ?>
		<li><?php echo BfoxBlog::ref_write_link($ref_str, __('Write a post', 'biblefox-blog')) ?></li>
		<?php endif ?>
	</ul>
</div>

<div class="bfox-tooltip-bible">
	<?php $iframe = new BfoxIframe($tooltip_refs) ?>
	<select class="bfox-iframe-select">
		<?php echo $iframe->select_options() ?>
	</select>
	<iframe class="bfox-iframe bfox-tooltip-iframe" src="<?php echo $iframe->url() ?>"></iframe>
</div>
