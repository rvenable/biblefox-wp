<?php
/**
 * The Template for displaying the readings of a single bfox_plan post
 *
 */

/* For posts of type bfox_plan, the post content contains the list of readings,
 * but it is usually better to use bfox_plan_reading_list() to output a better formatted list
 */
// the_content();

/* It's not a bad idea to use a bfox_plan excerpt here, to give a description of the Reading Plan */
// the_excerpt();

/* Call bfox_plan_reading_list() to output the readings
 * Use the 'column_class' to set how many columns it should display with. These correspond to CSS classes styled in style-bfox_plan.css
 * See style-bfox_plan.css for the different options available (or create your own in your theme's style.css)
 */ ?>
<?php if (bfox_plan_reading_list(array('column_class' => 'reading-list-2c-h'))): ?>
	<p><?php _e('In total, this reading plan covers all of the following passages:', 'bfox') ?> <?php echo bfox_ref_links(bfox_plan_total_ref()) ?></p>
<?php else: ?>
	<p><?php _e('This reading plan doesn\'t currently have any readings.', 'bfox') ?></p>
<?php endif ?>