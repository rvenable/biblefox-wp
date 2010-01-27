<?php

// Limit the refs to 10 chapters
$input_refs = new BfoxRefs($_REQUEST['bible_print_ref']);
list($refs) = $input_refs->get_sections(10, 1);

$trans_ids = BfoxTrans::get_ids_by_short_name();
$trans = new BfoxTrans($trans_ids[$_REQUEST['trans']]);

// Get the show options param (default is to show options)
$show_options = !(isset($_REQUEST['show_options']) && !$_REQUEST['show_options']);

$allow_tooltips = !!$_REQUEST['allow_tooltips'];
// TODO: We shouldn't really use tooltips on the print screen, but use inline scriptures
if (!$allow_tooltips) wp_deregister_script('bfox-blog');

// All we need are the scripture styles and script
// TODO: Buddypress is adding a few other syles which we don't need to load here
wp_enqueue_script('bfox-theme-print', get_stylesheet_directory_uri() . '/_inc/js/print.js', array('jquery'));

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

	<head profile="http://gmpg.org/xfn/11">
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php echo $refs->get_string() ?></title>
		<meta name="generator" content="WordPress <?php bloginfo('version'); ?>" /> <!-- leave this for stats -->
		<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri() . '/bible-print.css'; ?>" type="text/css" media="screen" />
		<?php wp_head(); ?>
	</head>

	<body>
	<?php if ($refs->is_valid()): ?>
		<?php
		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter(TRUE);
		$formatter->use_footnotes(array());

		$books = $trans->get_verses_in_books($refs->sql_where());
		$title = $refs->get_string();
		$bcvs = BfoxRefs::get_bcvs($refs->get_seqs());

		?>
		<?php foreach ($books as $book => $chapters): $title = ' ' ?>
			<?php foreach ($chapters as $chapter => $verses): ?>
				<div class="post">
					<?php if (!empty($title)): ?>
						<h2><?php echo BfoxRefs::create_book_string($book, $bcvs[$book]); ?></h2>
					<?php $title = ''; endif ?>
					<span class='chapter_head'><?php echo $chapter ?></span>
					<?php echo $formatter->format($verses) ?>
				</div>
			<?php endforeach ?>
		<?php endforeach ?>

		<?php $footnotes = $formatter->get_footnotes() ?>
		<?php if (!empty($footnotes)): ?>
		<div class="post">
			<h3><?php _e('Footnotes', 'bp-bible') ?></h3>
			<ul class="footnotes">
			<?php foreach ($footnotes as $footnote): ?>
				<li><?php echo $footnote ?></li>
			<?php endforeach ?>
			</ul>
		</div>
		<?php endif ?>

		<?php if ($show_options): ?>
		<div class="bfox-view-options">
			<?php $options = array(
				'jesus' => __('<span class="bible_jesus">Show Jesus\' words in red</span>'),
				'paragraphs' => __('Display verses as paragraphs'),
				'verse_nums' => __('Hide verse numbers'),
				'footnotes' => __('Hide footnote links')) ?>
			<p><?php _e('View Options', 'bp-bible') ?></p>
			<ul class="bfox-view-options-content">
			<?php foreach ($options as $name => $label): ?>
				<li>
					<input type="checkbox" name="<?php echo $name ?>" id="option_<?php echo $name ?>" class="view_option"/>
					<label for="option_<?php echo $name ?>"><?php echo $label ?></label>
				</li>
			<?php endforeach ?>
			</ul>
		</div>
		<?php endif ?>
	<?php endif ?>
	</body>
</html>
