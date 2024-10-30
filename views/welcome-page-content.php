<?php
defined( 'ABSPATH' ) || exit;

$instance = vgse_events();
?>
<p><?php _e('Thank you for installing our plugin.', $instance->textname); ?></p>

<?php
$steps = array();

$steps['supported_event_plugin'] = '<p>' . __('This plugin lets you edit events in the spreadsheet and it supports these event plugins: The Events Calendar by ModernTribe (we will add support for more plugins in the next update). This plugin requires one of those plugins.', $instance->textname) . '</p>';
$steps['open_editor'] = '<p>' . __('In the left menu, go to "WP Sheet Editor > Edit events"', $instance->textname) . '</p>';

include VGSE_DIR . '/views/free-extensions-for-welcome.php';
$steps['free_extensions'] = $free_extensions_html;

$steps = apply_filters('vg_sheet_editor/users/welcome_steps', $steps);

if (!empty($steps)) {
	echo '<ol class="steps">';
	foreach ($steps as $key => $step_content) {
		if (empty($step_content)) {
			continue;
		}
		?>
		<li><?php echo wp_kses_post($step_content); ?></li>		
		<?php
	}

	echo '</ol>';
}	