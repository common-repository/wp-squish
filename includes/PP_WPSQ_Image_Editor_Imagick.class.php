<?php
/* 
	This file contains code copied from WordPress. WordPress code is copyright by the WordPress contributors and released under the GNU
	General Public License version 2 or later, licensed under the GNU General Public License version 3 or later.
	
	This file modified by Jonathan Hall 2019-10-09 and earlier.
*/
if (!defined('ABSPATH'))
	die();

class PP_WPSQ_Image_Editor_Imagick extends WP_Image_Editor_Imagick {
	
	/** This class contains code copied from WordPress wp-includes/class-wp-image-editor-imagick.php and wp-includes/class-wp-image-editor-gd.php **/
	public function multi_resize( $sizes ) {
		$metadata = array();
		$orig_size = $this->size;
		$orig_image = $this->image->getImage();
		
		// Added - get settings
		global $WPSquish;
		$settings = $WPSquish::settings();

		foreach ( $sizes as $size => $size_data ) {
			if ( ! $this->image )
				$this->image = $orig_image->getImage();

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

			$resize_result = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
			$duplicate = ( ( $orig_size['width'] == $size_data['width'] ) && ( $orig_size['height'] == $size_data['height'] ) );

			if ( ! is_wp_error( $resize_result ) && ! $duplicate ) {
			
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
				
				$resized = $this->_save( $this->image );
				
				// Added - call action
				do_action('pp_wpsq_image_resized', $this->file, $resized['path'], $size_data['width'], $size_data['height'], $size_data['crop'], $jpeg_quality);

				$this->image->clear();
				$this->image->destroy();
				$this->image = null;

				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[$size] = $resized;
				}
			}

			$this->size = $orig_size;
		}

		$this->image = $orig_image;

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