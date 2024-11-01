<?php
/* 
	This plugin contains code copied from WordPress. WordPress code is
	copyright by the WordPress contributors and released under the GNU
	General Public License version 2 or later, licensed under the GNU
	General Public License version 3 or later.
*/
class WPSquishPro extends WPSquish {

	public function __construct() {
		parent::__construct();
		
		/* Hooks */
		add_action('template_redirect', array($this, 'template_redirect') );
		add_filter('pp_wpsq_make_image', array($this, 'should_make_image'));
		
		add_action('pp_wpsq_admin_page_options', array($this, 'admin_page_options'));
		add_action('pp_wpsq_image_resized', array($this, 'image_resized'), 10, 6);
		add_action('pp_wpsq_admin_page_extra_sections', array($this, 'admin_page_extra_sections'));
		add_filter('cron_schedules', array('WPSquishPro', 'add_cron_schedule'));
		add_action('pp_wpsq_api_run', array($this, 'api_run'));
		add_action('pp_wpsq_image_sizes_table_header_row', array($this, 'image_sizes_table_header_row'));
		add_action('pp_wpsq_image_sizes_table_body_row', array($this, 'image_sizes_table_body_row'), 10, 2); // undo checked
	}
	
	function settings() {
		global $pp_wpsq_settings;
		if (!isset($pp_wpsq_settings)) {
			$pp_wpsq_settings = array_merge(
			array(
				'default_png_quality' => 75,
				'fullsize_png_quality' => 75,
				'sizes_png_quality' => array(),
				'resize_otf_on' => false,
				'api_compressor_on' => false,
				'api_compressor_jpeg_keep_iptc_on' => false,
				'api_compressor_jpeg_keep_comments_on' => false,
				'api_compressor_jpeg_quality_factor' => 1,
			),
			parent::settings() );
		}
		return $pp_wpsq_settings;
	}
	
	
	function should_make_image() {
		$settings = $this->settings();
		return !$settings['resize_otf_on'];
	}
	
	function image_resized($imageFile, $newFile, $width, $height, $shouldCrop, $quality) {
		$settings = $this->settings();
		if ($settings['api_compressor_on']) {
			include_once(WPSquish::$pluginDirectory.'includes/pro/ApiCompressorTask.php');
			new WPSquishApiCompressorTask($imageFile, $newFile, $width, $height, $shouldCrop, $quality * (is_numeric($settings['api_compressor_jpeg_quality_factor']) ? $settings['api_compressor_jpeg_quality_factor'] : 1));
		}
	}
	
	function get_sanitized_settings() {
		$settings = $this->settings();
		$imageSizes = get_intermediate_image_sizes();
		
		// Checkbox fields
		foreach (array('resize_otf_on','api_compressor_on','api_compressor_jpeg_keep_iptc_on','api_compressor_jpeg_keep_comments_on') as $cbField) {
			$_POST[$cbField] = (empty($_POST[$cbField]) ? 0 : 1);
		}
		
		$settings['default_png_quality'] = $this->sanitize_pos_int($settings['default_png_quality'], 75, 1, 100);
		$settings['fullsize_png_quality'] = $this->sanitize_pos_int($settings['fullsize_png_quality'], 75, 1, 100);
		
		// Filter out invalid sizes
		$settings['sizes_png_quality'] = array_intersect_key($settings['sizes_png_quality'], array_flip($imageSizes));
		
		// Sanitize sizes quality values
		foreach ($settings['sizes_png_quality'] as $size => $quality) {
			$settings['sizes_png_quality'][$size] = $this->sanitize_pos_int($quality, 75, 1, 100);
		}
		
		$settings['api_compressor_jpeg_quality_factor'] = $this->sanitize_number($settings['api_compressor_jpeg_quality_factor'], 1);
		
		wp_clear_scheduled_hook( 'pp_wpsq_api_run' );
		if ($_POST['api_compressor_on']) {
			wp_schedule_event(1, 'pp_wpsq_api', 'pp_wpsq_api_run' );
		}
		
		
		$settings = parent::get_sanitized_settings();
		
		return $settings;
	}
	
	function api_run() {
		if ( get_option('pp_wpsq_api_run', false) ) {
			return;
		}
		
		update_option('pp_wpsq_api_run', 1);
		
		$settings = $this->settings();
		include_once(WPSquish::$pluginDirectory.'includes/pro/ApiCompressorTask.php');
		while( WPSquishApiCompressorTask::popAndRun($settings) ) {}
		
		delete_option('pp_wpsq_api_run');
	}
	
	function template_redirect() {
		if (is_404() && empty($_GET['pp_wpsq_reload'])) {
			if (preg_match('/.+\/([0-9]{4}\/[0-9]{2}\/)(.*)(\-[0-9]+x[0-9]+)(\.[a-z]+)(\Z|\?)/iU', $_SERVER['REQUEST_URI'], $match)) {
			
				$posts = get_posts(array(
					'post_type' => 'attachment',
					'posts_per_page' => 1,
					'meta_key' => '_wp_attached_file',
					'meta_value' => $match[1].$match[2].$match[4],
					'fields' => 'ids'
				));
				
				if (!empty($posts)) {
					$metadata = wp_get_attachment_metadata($posts[0]);
					if (!empty($metadata['sizes'])) {
						foreach ($metadata['sizes'] as $size => $sizeData) {
							if ($sizeData['file'] == $match[2].$match[3].$match[4]) {
								$imageSize = $size;
								$imageSizeData = $sizeData;
								break;
							}
						}
						if (isset($imageSize)) {
							$imageSizeData['crop'] = true;
							global $pp_wpsq_image_size;
							$pp_wpsq_image_size = array($imageSize => $imageSizeData);
							
							$uploadDir = wp_upload_dir();
							
							include_once(ABSPATH.'/wp-admin/includes/image.php');
							add_filter('intermediate_image_sizes_advanced', array($this, 'image_generation_size') );
							add_filter('pp_wpsq_make_image', '__true');
							wp_generate_attachment_metadata($posts[0], $uploadDir['basedir'].'/'.$match[1].$match[2].$match[4]);
							remove_filter('intermediate_image_sizes_advanced', array($this, 'image_generation_size') );
							remove_filter('pp_wpsq_make_image', '__true');
							wp_redirect($_SERVER['REQUEST_URI'].(strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&').'pp_wpsq_reload=1');
							die();
						}
					}
				}
			
			}
		}
	}

	function image_generation_size($sizes) {
		global $pp_wpsq_image_size;
		return $pp_wpsq_image_size;
	}
	
	function admin_page_options($settings) {
?>
<div class="pp-wpsq-form-row">
	<label>
		<input type="checkbox" name="resize_otf_on"<?php echo(empty($settings['resize_otf_on']) ? '' : ' checked="checked"'); ?> />
		Generate resized versions of images on the fly
	</label>
	<p class="description pp-wpsq-cb-description">If this option is enabled, downsized versions of uploaded images will be created when first requested, not on upload. This does not affect existing downsized images.</p>
</div>
<?php
	}
	
	function admin_page_extra_sections($settings) {
		global $wpdb;
		$queueCount = $wpdb->get_var('SELECT COUNT(queue_id) FROM '.$wpdb->prefix.'pp_wpsq_api_queue');
		$apiStatsJpeg = get_option('pp_wpsq_api_stats_jpeg', array());
		$apiStatsPng = get_option('pp_wpsq_api_stats_png', array());
?>
<div class="pp-wpsq-form-section">
	<h3>Compression API</h3>
	<div class="pp-wpsq-stat">
		<span><?php echo($queueCount); ?></span>
		<label>image(s) in queue</label>
	</div>
	<?php if ( !empty($apiStatsJpeg['count']) ) { ?>
	<div class="pp-wpsq-stat">
		<span><?php echo( round($apiStatsJpeg['improvement']*100) ); ?>%</span>
		<label>reduction in JPEG size</label>
	</div>
	<?php } ?>
	<?php if ( !empty($apiStatsPng['count']) ) { ?>
	
	<div class="pp-wpsq-stat">
		<span><?php echo( round($apiStatsPng['improvement']*100) ); ?>%</span>
		<label>reduction in PNG size</label>
	</div>
	<?php } ?>
	<div class="pp-wpsq-form-row">
		<label>
			<input type="checkbox" name="api_compressor_on"<?php echo(empty($settings['api_compressor_on']) ? '' : ' checked="checked"'); ?> />
			Use the WP Squish compression API
		</label>
		<p class="description pp-wpsq-cb-description">If this option is enabled, images will be sent to the WP Squish compression API for resize and compression.</p>
	</div>
	<div class="pp-wpsq-form-row">
		<label>
			<input type="checkbox" name="api_compressor_jpeg_keep_iptc_on"<?php echo(empty($settings['api_compressor_jpeg_keep_iptc_on']) ? '' : ' checked="checked"'); ?> />
			Keep IPTC data in JPEG images
		</label>
		<p class="description pp-wpsq-cb-description">Enable this option if you need to retain IPTC data in your JPEG images, for example because it contains copyright information that must be retained in the JPEG file. Changes to this setting will also affect any images currently in the queue.</p>
	</div>
	<div class="pp-wpsq-form-row">
		<label>
			<input type="checkbox" name="api_compressor_jpeg_keep_comments_on"<?php echo(empty($settings['api_compressor_jpeg_keep_comments_on']) ? '' : ' checked="checked"'); ?> />
			Keep comment fields in JPEG images
		</label>
		<p class="description pp-wpsq-cb-description">Enable this option if you need to retain comment fields in your JPEG images, for example because they contain copyright information that must be retained in the JPEG file. Changes to this setting will also affect any images currently in the queue.</p>
	</div>
	<div class="pp-wpsq-form-row">
		<label>
			JPEG quality adjustment factor:
			<input type="number" name="api_compressor_jpeg_quality_factor" value="<?php echo $settings['api_compressor_jpeg_quality_factor']; ?>" />
		</label>
		<p class="description pp-wpsq-cb-description">JPEG quality settings in the compression API may differ in their result from the quality settings specified above for your local image compression functionality. The applicable quality value will be multiplied by the value specified here when compressing using the API.</p>
	</div>

</div>
<?php
	}
	
	function image_sizes_table_header_row($settings) {
?>
<th class="pp-wpsq-alignleft">PNG Quality (1-100)</th>
<?php
	}
	
	function image_sizes_table_body_row($settings, $size) {
?>
<td class="pp-wpsq-jpeg-quality-cell pp-wpsq-aligncenter">
<?php
		switch ( $size ) {
			case 'all':
?>
				<input type="number" min="1" max="100" class="pp-wpsq-jpeg-quality-field pp-wpsq-jpeg-quality-field-all" />
<?php
				break;
			case 'default':
?>
				<input type="number" min="1" max="100" name="default_png_quality" value="<?php echo $settings['default_png_quality']; ?>" class="pp-wpsq-jpeg-quality-field" />
<?php
				break;
			case 'fullsize':
?>
				<input type="number" min="1" max="100" name="fullsize_png_quality" value="<?php echo $settings['fullsize_png_quality']; ?>" class="pp-wpsq-jpeg-quality-field" />
<?php
				break;
			default:
?>
				<input type="number" min="1" max="100" name="sizes_png_quality['.<?php echo esc_attr($size); ?>.']" value="<?php echo(isset($settings['sizes_png_quality'][$size]) ? $settings['sizes_png_quality'][$size] : $settings['default_png_quality']); ?>" class="pp-wpsq-jpeg-quality-field" />
<?php
		}
?>
</td>
<?php
	}
	
	static function add_cron_schedule($s) {
		$s['pp_wpsq_api'] = array(
			'display' => 'WP Squish API',
			'interval' => 60,
		);
		return $s;
	}

}
?>