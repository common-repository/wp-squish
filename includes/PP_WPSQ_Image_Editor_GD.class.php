<?php
/* 
	This file contains code copied from WordPress. WordPress code is copyright by the WordPress contributors and released under the GNU
	General Public License version 2 or later, licensed under the GNU General Public License version 3 or later.
	
	This file modified by Jonathan Hall 2019-10-09 and earlier.
*/
if (!defined('ABSPATH'))
	die();
	
class PP_WPSQ_Image_Editor_GD extends WP_Image_Editor_GD {
	
	/** This class contains code copied from WordPress wp-includes/class-wp-image-editor-gd.php etc. **/
	public function multi_resize($sizes) {
		$metadata = array();
		$orig_size = $this->size;
		
		// Added - get settings
		global $WPSquish;
		$settings = $WPSquish::settings();

		foreach ( $sizes as $size => $size_data ) {
			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
				continue;
			}

			if ( ! isset( $size_data['width'] ) ) {
				$size_data['width'] = null;
			}
			if ( ! isset( $size_data['height'] ) ) {
				$size_data['height'] = null;
			}

			if ( ! isset( $size_data['crop'] ) ) {
				$size_data['crop'] = false;
			}

			$image = $this->_resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
			$duplicate = ( ( $orig_size['width'] == $size_data['width'] ) && ( $orig_size['height'] == $size_data['height'] ) );

			if ( ! is_wp_error( $image ) && ! $duplicate ) {
			
				// Added - set quality
				if ( 'image/jpeg' === $this->mime_type ) {
					$jpeg_quality = isset($settings['sizes_jpeg_quality'][$size]) ? $settings['sizes_jpeg_quality'][$size] : $settings['default_jpeg_quality'];
					$this->set_quality($jpeg_quality);
				} else if ( 'image/png' === $this->mime_type ) {
					$jpeg_quality = isset($settings['sizes_png_quality'][$size]) ? $settings['sizes_png_quality'][$size] : $settings['default_png_quality'];
					$this->set_quality($jpeg_quality);
				} else {
					$jpeg_quality = 0;
				}
				
				$resized = $this->_save( $image );
				
				// Added - call action
				do_action('pp_wpsq_image_resized', $this->file, $resized['path'], $size_data['width'], $size_data['height'], $size_data['crop'], $jpeg_quality);

				imagedestroy( $image );

				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[$size] = $resized;
				}
			}

			$this->size = $orig_size;
		}
		
		return $metadata;
	}
	
	
	public function make_image($filename, $function, $arguments) {
		if (apply_filters('pp_wpsq_make_image', true)) {
			return parent::make_image($filename, $function, $arguments);
		} else {
			return true;
		}
	}
	/** End copied code **/
}
?>