<?php
/**
 * Plugin Name: WP Squish
 * Description: Reduce the amount of storage space consumed by your WordPress installation through the application of user-definable JPEG compression levels and image resolution limits to uploaded images.
 * Plugin URI: https://wpzone.co/product/wp-squish/
 * Version: 1.0.1
 * Author: WP Zone
 * Author URI: http://wpzone.co/?utm_source=wp-squish&utm_medium=link&utm_campaign=wp-plugin-author-uri
 * License: GNU General Public License version 3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * GitLab Plugin URI: https://gitlab.com/aspengrovestudios/wp-squish/
 * AGS Info: ids.aspengrove 412073 adminPage admin.php?page=pp_wpsq docs 
 */
 
 /* 
	This plugin contains code copied from WordPress. WordPress code is
	copyright by the WordPress contributors and released under the GNU
	General Public License version 2 or later, licensed under the GNU
	General Public License version 3 or later.
 */

if (!defined('ABSPATH'))
	die();

class WPSquish {

	const VERSION = '1.0.1';
	const API_URL = 'https://api.wpsquish.com/wp-admin/admin-ajax.php';
	//const API_URL = 'http://localhost/dev/layoutsserver/wp-admin/admin-ajax.php';
	public static 	$pluginBaseUrl,
					$pluginDirectory,
					$pluginFile;
	
	public function __construct() {
		self::$pluginBaseUrl = plugin_dir_url(__FILE__);
		self::$pluginDirectory = __DIR__.'/';
		self::$pluginFile = __FILE__;
		
		//include_once(self::$pluginDirectory.'updater/EDD_SL_Plugin_Updater.php');
		
		/* Hooks */
		add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'action_links') );
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts') );
		add_action('admin_menu', array($this, 'admin_menu') );
		add_filter('wp_image_editors', array($this, 'image_editors') );
		add_filter('wp_generate_attachment_metadata', array($this, 'generate_attachment_metadata') , 10, 2);
		
	}
	
	function action_links($links) {
		array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'admin.php?page=pp_wpsq')).'">Settings</a>');
		return $links;
	}
	
	function admin_scripts($hook) {
		if ($hook != 'settings_page_pp_wpsq')
			return;
		
		wp_enqueue_script('pp-wpsq-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), false, true);
		wp_enqueue_style('pp-wpsq-admin', plugins_url('css/admin.css', __FILE__));
		
		wp_enqueue_script('rangeslider', plugins_url('js/rangeslider.min.js', __FILE__), array('jquery'), false, true);
		wp_enqueue_style('rangeslider', plugins_url('css/rangeslider.css', __FILE__));
	}
	
	function admin_menu() {
		add_options_page('WP Squish', 'WP Squish', 'manage_options', 'pp_wpsq', array($this, 'page') );
	}
	
	function get_sanitized_settings() {
		$settings = $this->settings();
		$imageSizes = get_intermediate_image_sizes();
	
		// Checkbox fields
		foreach (array('fullsize_res_limit_on', 'fullsize_compress_on') as $cbField) {
			$_POST[$cbField] = (empty($_POST[$cbField]) ? 0 : 1);
		}
		$settings = array_merge($settings, array_intersect_key($_POST, $settings));
		
		// Sanitize numeric values
		$settings['fullsize_res_limit_w'] = $this->sanitize_pos_int($settings['fullsize_res_limit_w'], 1920);
		$settings['fullsize_res_limit_h'] = $this->sanitize_pos_int($settings['fullsize_res_limit_h'], 1080);
		$settings['default_jpeg_quality'] = $this->sanitize_pos_int($settings['default_jpeg_quality'], 75, 1, 100);
		$settings['fullsize_jpeg_quality'] = $this->sanitize_pos_int($settings['fullsize_jpeg_quality'], 75, 1, 100);
		
		// Filter out invalid sizes
		$settings['sizes_jpeg_quality'] = array_intersect_key($settings['sizes_jpeg_quality'], array_flip($imageSizes));
		
		// Sanitize sizes quality values
		foreach ($settings['sizes_jpeg_quality'] as $size => $quality) {
			$settings['sizes_jpeg_quality'][$size] = $this->sanitize_pos_int($quality, 75, 1, 100);
		}
		
		return $settings;
	}
	
	function page() {
		$settings = $this->settings();
		$imageSizes = get_intermediate_image_sizes();
		$additionalImageSizes = wp_get_additional_image_sizes();
		
		if (!empty($_POST)) {
			check_admin_referer('pp_wpsq_settings_save');
			$settings = $this->get_sanitized_settings();
			update_option('pp_wpsq_settings', $settings, false);
			
			global $pp_wpsq_settings;
			$pp_wpsq_settings = $settings;
		}
?>
<div id="pp-wpsq-admin" class="wrap">
		
	<h2>WP Squish<br /><small>Compress your WordPress!&trade;</small></h2>
	<div id="pp-wpsq-intro">
		<p>The WP Squish plugin helps to reduce the amount of storage space consumed by your WordPress installation through the application of user-definable JPEG compression levels and image resolution limits to uploaded images. Below, you can configure the resolution limit of the &quot;full size&quot; images as well as the compression level (on a scale of 1 to 100) of each image size curently defined in your WordPress installation.</p>
	</div>

	<form action="" method="post">

		<div class="pp-wpsq-form-section">
			<h3>Options</h3>
			
			<div class="pp-wpsq-form-row">
				<label>
					<input type="checkbox" name="fullsize_res_limit_on"<?php echo(empty($settings['fullsize_res_limit_on']) ? '' : ' checked="checked"'); ?> />
					Limit full size image resolution to
					<input type="number" min="1" name="fullsize_res_limit_w" value="<?php echo $settings['fullsize_res_limit_w']; ?>" /> x
					<input type="number" min="1" name="fullsize_res_limit_h" value="<?php echo $settings['fullsize_res_limit_h']; ?>" />
				</label>
				<p class="description pp-wpsq-cb-description">Images exceeding the limit will be resized when uploaded.</p>
			</div>
			
			<div class="pp-wpsq-form-row">
				<label>
					<input type="checkbox" name="fullsize_compress_on"<?php echo(empty($settings['fullsize_compress_on']) ? '' : ' checked="checked"'); ?> />
					Always recompress full size JPEG images
				</label>
				<p class="description pp-wpsq-cb-description">If this option is not enabled, the full size compression will only be applied if an image is resized due to the image resolution limit.</p>
			</div>
			
			<?php do_action('pp_wpsq_admin_page_options', $settings); ?>
		
		</div>
		
		<div class="pp-wpsq-form-section">
			<h3>Image Compression Settings</h3>
			
			<table id="pp-wpsq-image-sizes-table" cellpadding="0" cellspacing="0">
				<thead>
					<tr>
						<th class="pp-wpsq-alignleft">Image Size</th>
						<th class="pp-wpsq-alignleft">JPEG Quality (1-100)</th>
						<?php do_action('pp_wpsq_image_sizes_table_header_row', $settings); ?>
					</tr>
				</thead>
				<tbody>
					<tr class="pp-wpsq-jpeg-quality-row-all">
						<td><strong>All Sizes</strong></td>
						<td class="pp-wpsq-jpeg-quality-cell pp-wpsq-aligncenter">
							<input type="number" min="1" max="100" class="pp-wpsq-jpeg-quality-field pp-wpsq-jpeg-quality-field-all" />
						</td>
						<?php do_action('pp_wpsq_image_sizes_table_body_row', $settings, 'all'); ?>
					</tr>
					<tr>
						<td>Default / Other</td>
						<td class="pp-wpsq-jpeg-quality-cell pp-wpsq-aligncenter">
							<input type="number" min="1" max="100" name="default_jpeg_quality" value="<?php echo $settings['default_jpeg_quality']; ?>" class="pp-wpsq-jpeg-quality-field" />
						</td>
						<?php do_action('pp_wpsq_image_sizes_table_body_row', $settings, 'default'); ?>
					</tr>
					<tr>
						<td>Full Size</td>
						<td class="pp-wpsq-jpeg-quality-cell pp-wpsq-aligncenter">
							<input type="number" min="1" max="100" name="fullsize_jpeg_quality" value="<?php echo $settings['fullsize_jpeg_quality']; ?>" class="pp-wpsq-jpeg-quality-field" />
						</td>
						<?php do_action('pp_wpsq_image_sizes_table_body_row', $settings, 'fullsize'); ?>
					</tr>
					<?php foreach ($imageSizes as $size) { ?>
					<tr>
						<td><?php echo esc_html($size.' - '.(isset($additionalImageSizes[$size]['width']) ? $additionalImageSizes[$size]['width'] : get_option($size.'_size_w')).'x'.(isset($additionalImageSizes[$size]['height']) ? $additionalImageSizes[$size]['height'] : get_option($size.'_size_h')))?></td>
						<td class="pp-wpsq-jpeg-quality-cell pp-wpsq-aligncenter">
							<input type="number" min="1" max="100" name="sizes_jpeg_quality['.<?php echo esc_attr($size); ?>.']" value="<?php echo(isset($settings['sizes_jpeg_quality'][$size]) ? $settings['sizes_jpeg_quality'][$size] : $settings['default_jpeg_quality'])?>" class="pp-wpsq-jpeg-quality-field" />
						</td>
						<?php do_action('pp_wpsq_image_sizes_table_body_row', $settings, $size); ?>
					</tr>
					<?php } ?>
	
				</tbody>
			</table>
		</div>
		
		<?php do_action('pp_wpsq_admin_page_extra_sections', $settings); ?>
		
		<?php wp_nonce_field('pp_wpsq_settings_save'); ?>
		<button type="submit" class="button-primary">Save Changes</button>
	</form>
<?php
	$potent_slug = 'wp-squish';
	include(__DIR__.'/plugin-credit.php');
?>
</div>
		
<?php 
	}
	
	function settings() {
		global $pp_wpsq_settings;
		if (!isset($pp_wpsq_settings)) {
			$pp_wpsq_settings = array_merge(
			array(
				'fullsize_res_limit_on' => false,
				'fullsize_res_limit_w' => 1920,
				'fullsize_res_limit_h' => 1080,
				'fullsize_compress_on' => false,
				'default_jpeg_quality' => 75,
				'fullsize_jpeg_quality' => 75,
				'sizes_jpeg_quality' => array()
			),
			get_option('pp_wpsq_settings', array()));
		}
		return $pp_wpsq_settings;
	}
	
	function image_editors($editors) {
		foreach ($editors as $i => $editor) {
			switch ($editor) {
				case 'WP_Image_Editor_GD':
					include_once(dirname(__FILE__).'/includes/PP_WPSQ_Image_Editor_GD.class.php');
					$editors[$i] = 'PP_WPSQ_Image_Editor_GD';
					break;
				case 'WP_Image_Editor_Imagick':
					include_once(dirname(__FILE__).'/includes/PP_WPSQ_Image_Editor_Imagick.class.php');
					$editors[$i] = 'PP_WPSQ_Image_Editor_Imagick';
					break;
			}
		}
		return $editors;
	}
	
	function generate_attachment_metadata($metadata, $attachmentId) {
		$settings = $this->settings();
		
		if ( (empty($settings['fullsize_res_limit_on']) && empty($settings['fullsize_compress_on'])) || get_post_meta($attachmentId, '_pp-wpsq-processed', true) == 1) {
			return $metadata;
		}
		
		$file = get_attached_file($attachmentId);
		if (file_is_displayable_image($file)) {
		
			$editor = wp_get_image_editor($file);
			if (!is_wp_error($editor)) {
				$imageSize = $editor->get_size();
				if ($imageSize['width'] > $settings['fullsize_res_limit_w'] || $imageSize['height'] > $settings['fullsize_res_limit_h']) {
					$editor->resize($settings['fullsize_res_limit_w'], $settings['fullsize_res_limit_h']);
					// Update width and height
					$metadata = array_merge($metadata, $editor->get_size());
					$resized = true;
				}
				if (!empty($resized) ||
					(!empty($settings['fullsize_compress_on']) && !strcasecmp(get_post_mime_type($attachmentId), 'image/jpeg'))) {
					$editor->set_quality($settings['fullsize_jpeg_quality']);
					
					add_filter('pp_wpsq_make_image', '__true');
					$editor->save($file);
					remove_filter('pp_wpsq_make_image', '__true');
				}
			}
		}
		
		update_post_meta($attachmentId, '_pp-wpsq-processed', 1);
		
		return $metadata;
	}
	
	function sanitize_pos_int($val, $default, $min=false, $max=false) {
		$absIntVal = absint($val);
		if ($absIntVal != $val
			|| ($min !== false && $absIntVal < $min)
			|| ($max !== false && $absIntVal > $max)) {
			return $default;
		}
		return $absIntVal;
	}
	
	function sanitize_number($val, $default, $min=false, $max=false) {
		if (!is_numeric($val)
			|| ($min !== false && $absIntVal < $min)
			|| ($max !== false && $absIntVal > $max)) {
			return $default;
		}
		return $val;
	}
}

@include_once(WPSquish::$pluginDirectory.'includes/pro/pro.php');
$WPSquish = class_exists('WPSquishPro') ? new WPSquishPro() : new WPSquish();
?>
