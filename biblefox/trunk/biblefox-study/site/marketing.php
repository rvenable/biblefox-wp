<?php

function bfox_add_analytics()
{
	?>
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
var pageTracker = _gat._getTracker("UA-6583355-1");
pageTracker._trackPageview();
} catch(err) {}</script>
	<?php
}

if (!defined('BFOX_TESTBED'))
{
	add_action('wp_footer', 'bfox_add_analytics');
	add_action('admin_footer', 'bfox_add_analytics');
}

?>