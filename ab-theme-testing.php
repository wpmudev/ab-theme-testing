<?php
/*
Plugin Name: A/B Theme Testing Plugin
Version: 1.1
Plugin URI: http://premium.wpmudev.org/project/ab-theme-testing
Description: This plugin rotates themes for A/B testing integrating with Google Analytics. One theme gets shown for A, another for B and so on (and the user who sees a theme keeps on seeing it when they come back via cookie tracking).
Author: Aaron Edwards (for Incsub)
Author URI: http://uglyrobot.com
WDP ID: 174
*/

/* 
Copyright 2009-2013 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Hooks----------------------------------------------------------------//
//------------------------------------------------------------------------//

add_action('admin_menu', 'ab_theme_testing_plug_pages');
add_action('plugins_loaded', 'ab_theme_testing_random_theme');

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function ab_theme_testing_plug_pages() {
	add_submenu_page('themes.php', __('A/B Theme Testing'), __('A/B Theme Testing'), 'switch_themes', 'ab_theme_testing', 'ab_theme_testing_admin_output' );
}

//if theme cookie not set, choose random theme and set cookie
function ab_theme_testing_random_theme() {
  global $current_ab_theme;
	$options = get_option('ab_theme_testing');
	
	//is testing enabled and possible?
	if ($options['testing_enable']==0 || !is_array($options['tracking_themes']) || count($options['tracking_themes']) < 2)
    return;
  
  $cookie = $_COOKIE["ab_theme_testing_".COOKIEHASH];
  
  if ($cookie && in_array($cookie, $options['tracking_themes'])) {
		
		$current_ab_theme = $cookie;

	}	else {
    //rotate testing themes
    $last_theme = $options['last_theme'];
    $new_theme = $last_theme+1;
    if (array_key_exists($new_theme, $options['tracking_themes'])) {
      $current_ab_theme = $options['tracking_themes'][$new_theme];
      $options['last_theme'] = $new_theme;
      update_option('ab_theme_testing', $options);
    } else { //rewind
      $current_ab_theme = $options['tracking_themes'][0];
      $options['last_theme'] = 0;
      update_option('ab_theme_testing', $options);
    }
    
    //set cookie
    $expire = time() + 15768000; //6 month expire
		setcookie("ab_theme_testing_" . COOKIEHASH, $current_ab_theme, $expire, COOKIEPATH);
	}
	
	//theme switch hooks
  add_filter('stylesheet', 'ab_theme_testing_filter_stylesheet');
  add_filter('template', 'ab_theme_testing_filter_template');
	
	//tracking hooks
	add_action('wp_footer', 'ab_theme_testing_tracking_output', 50); //50 = make sure it comes after any existing tracking code
}

function ab_theme_testing_filter_stylesheet($value) {
	global $current_ab_theme;
  
	if (!$current_ab_theme)
		return $value;
	
	$bits = explode('|', $current_ab_theme);
  
  return $bits[0];
}

function ab_theme_testing_filter_template($value) {
	global $current_ab_theme;
  
	if (!$current_ab_theme)
		return $value;
	
	$bits = explode('|', $current_ab_theme);
  
  return $bits[1];
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function ab_theme_testing_tracking_output() {
  global $current_ab_theme;
  
  $options = get_option('ab_theme_testing');
  $bits = explode('|', $current_ab_theme);
	
  $segment = $bits[0];
  
	//what GA variable slot to put it in
	if (!defined('AB_THEME_TESTING_SLOT'))
		define('AB_THEME_TESTING_SLOT', 1);
  ?>
	<script type="text/javascript">
		if (typeof(pageTracker) !== 'undefined') {
			pageTracker._setCustomVar(<?php echo AB_THEME_TESTING_SLOT; ?>, "Theme Test", "<?php echo $segment; ?>", 2);
			pageTracker._trackPageview();
		} else if (typeof(_gaq) !== 'undefined') {
			_gaq.push(['_setCustomVar', <?php echo AB_THEME_TESTING_SLOT; ?>, "Theme Test", "<?php echo $segment; ?>", 2]);
			_gaq(['_trackPageview']);
		}
	</script>
	<?php
}


//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function ab_theme_testing_admin_output() {
  if ( function_exists('current_user_can') && !current_user_can('switch_themes') )
		wp_die(__('Cheatin&#8217; uh?'));
			
  if (isset($_POST['save_settings'])) {
		if (!check_admin_referer('ab_theme_testing')) die("Security Problem");
		
		$options = array();
		
		//process options
		$options['testing_enable'] = ($_POST['ab_theme_testing_enable']==1) ? 1 : 0;
		
		//process themes
    if (is_array($_POST['theme'])) {
      $options['tracking_themes'] = array_keys($_POST['theme']);
    } else {
      $options['tracking_themes'] = array();
    }
		
		update_option('ab_theme_testing', $options);
		?><div id="message" class="updated fade"><p><?php _e('Settings Saved!') ?></p></div><?php
	}
  
  $options = get_option('ab_theme_testing');
  
  echo '<div class="wrap">';
  
  global $super_cache_enabled;
  if ($super_cache_enabled) {
	 ?><div id="message" class="error"><p>WARNING: This plugin is incompatible with WP Super Cache. You must set it to "HALF ON" mode or turn it to "OFF".</p></div><?php
    $disable = ' disabled="disabled"';
  }
	?>    
	<h2><?php _e('A/B Theme Testing Settings') ?></h2>
	<p>
  <?php _e('This plugin rotates themes to assign them evenly between visitors. A visitor\'s theme assignment is saved via a cookie and only changes when you disable the plugin or choose different themes to test. It also creates a custom segment in Google Analytics for each theme visitors are assigned. The segment names will be of the format "Theme Test: THEMENAME" in Analytics. You can then analyze browsing and buying behavior based upon the custom segments named after the themes selected for testing.') ?>
  <a href="http://analytics.blogspot.com/2009/07/segment-your-traffic-with-user-defined.html" target="_blank"><?php _e('More information on custom segmentations in Analytics &raquo;') ?></a>
  </p>
	<p><?php _e('The custom segment data may take up to 24 hours to begin showing in Analytics. Click the "User Defined" link under "Visitors", then select the "Goal Conversion" tab. (Note: You will have to create at least one goal or funnel in Google Analytics to track conversions!)') ?></p>
  <p style="text-align:center;"><img src="../../wp-content/plugins/ab-theme-testing/screenshot.jpg" alt="<?php _e('Screenshot of the Google Analytics User Defined area with theme testing data.') ?>" title="<?php _e('Screenshot of the Google Analytics User Defined area with theme testing data.') ?>" /></p>
  <form method="post" action="">
	<table class="form-table">
		<tr valign="top"> 
			<th scope="row"><?php _e('Enable Theme Testing?') ?></th> 
			<td>
        <label>
				  <input name="ab_theme_testing_enable" id="ab_theme_testing_enable" type="checkbox" value="1" <?php checked('1', $options['testing_enable']); ?><?php echo $disable; ?> /> <?php _e('Enable') ?>
				</label>
			</td>
		</tr>
		<tr valign="top"> 
			<th scope="row"><?php _e('Track Admin Pages?') ?></th> 
			<td>
        <label>
				  <input name="ab_theme_testing_admin_track" id="ab_theme_testing_admin_track" type="checkbox" value="1" <?php checked('1', $options['admin_track']); ?> /> <?php _e('Enable') ?>
				</label><br />
				<small><?php _e('You may want to track admin pages if your Goal or Conversion screen occurs in the wp-admin/ area for logged in users.') ?></small>
			</td>
		</tr>
	</table>
	
	<p><?php _e('Select at least 2 themes you would like to test') ?>:</p>
	<table class="widefat" style="width:40%;">
		<thead>
			<tr>
				<th><?php _e('Theme') ?></th>
				<th><?php _e('Version') ?></th>
			</tr>
		</thead>
		<tbody id="plugins">
		<?php
		$themes = get_themes();
		foreach( (array) $themes as $key => $theme ) {
			$theme_key = $theme->stylesheet.'|'.$theme->template;
			$class = ('alt' == $class) ? '' : 'alt';
			$class1 = $enabled = $disabled = '';
		  
		  if (is_array($options['tracking_themes']))
        $checked = ( in_array($theme_key, $options['tracking_themes']) ) ? 'checked="checked"' : '';
        
			$class1 = ' active';
			?>
			<tr valign="top" class="<?php echo $class.$class1; ?>">
				<th>
				<label>
				  <input name="theme[<?php echo $theme_key ?>]" type="checkbox" value="1" <?php echo $checked; ?>/> 
					<?php echo esc_html($key) ?>
				</label>
				</th> 
				<td><?php echo $theme['Version'] ?></td>
			</tr> 
		<?php 
    } ?>
		</tbody>
	</table>
	
	<?php wp_nonce_field('ab_theme_testing'); ?>
	<p class="submit"><input type="submit" name="save_settings" class="button-primary" value="<?php _e('Save Settings &raquo;'); ?>" /></p>
	</form>
	</div>
<?php
}

include_once( dirname( __FILE__ ) . '/dash-notice/wpmudev-dash-notification.php' );