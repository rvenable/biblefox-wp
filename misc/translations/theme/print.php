<?php

// Limit the ref to 10 chapters
$input_ref = new BfoxRef($_REQUEST['bfoxp']);
list($ref) = $input_ref->get_sections(10, 1);

$trans_ids = BfoxTrans::get_ids_by_short_name();
$trans = new BfoxTrans($trans_ids[$_REQUEST['trans']]);

// Get the show options param (default is to show options)
$show_options = !(isset($_REQUEST['opts']) && !$_REQUEST['opts']);

$allow_tooltips = !!$_REQUEST['allow_tooltips'];
// TODO: We shouldn't really use tooltips on the print screen, but use inline scriptures
if (!$allow_tooltips) wp_deregister_script('bfox-blog');

// All we need are the scripture styles and script
// TODO: Buddypress is adding a few other syles which we don't need to load here
wp_enqueue_script('bfox-theme-print', BFOX_TRANS_URL . '/theme/print.js', array('jquery'));

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

	<head profile="http://gmpg.org/xfn/11">
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
		<title><?php echo $ref->get_string() ?></title>
		<meta name="generator" content="WordPress <?php bloginfo('version'); ?>" /> <!-- leave this for stats -->
		<link rel="stylesheet" href="<?php echo BFOX_TRANS_URL . '/theme/bible-print.css'; ?>" type="text/css" media="screen" />
		<?php wp_head(); ?>
	</head>

	<body>
	<?php if ($ref->is_valid()): ?>
		<?php if ('ESV' == $trans->short_name): ?>
			<script type="text/javascript" src="http://www.gnpcb.org/esv/share/js/?action=doPassageQuery&passage=<?php echo urlencode($ref->get_string()) ?>&include-audio-link=0&include-copyright=true"></script>
		<?php else: // Begin other translations ?>
		<?php
		// Get the verse data from the bible translation
		$formatter = new BfoxVerseFormatter(TRUE);
		$formatter->use_footnotes(array());

		$books = $trans->get_verses_in_books($ref->sql_where());
		$title = $ref->get_string();
		$bcvs = BfoxRef::get_bcvs($ref->get_seqs());

		?>
		<?php foreach ($books as $book => $chapters): $title = ' ' ?>
			<?php foreach ($chapters as $chapter => $verses): ?>
				<div class="post">
					<?php if (!empty($title)): ?>
						<h2><?php echo BfoxRef::create_book_string($book, $bcvs[$book]); ?></h2>
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
		<?php endif // End other translations ?>
	<?php endif ?>
	</body>
</html>
