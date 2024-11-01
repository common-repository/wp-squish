<?php
/* 
	This plugin contains code copied from WordPress. WordPress code is
	copyright by the WordPress contributors and released under the GNU
	General Public License version 2 or later, licensed under the GNU
	General Public License version 3 or later.
*/
class WPSquishApiCompressorTask {
	public function __construct($imageFile, $newFile, $width, $height, $shouldCrop, $quality) {
		if (!@$this->add($imageFile, $newFile, $width, $height, $shouldCrop, $quality)) {
			self::createTable();
			$this->add($imageFile, $newFile, $width, $height, $shouldCrop, $quality);
		}
	}
	
	function add($imageFile, $newFile, $width, $height, $shouldCrop, $quality) {
		global $wpdb;
		return $wpdb->insert(
			$wpdb->prefix.'pp_wpsq_api_queue',
			array(
				'image_file' => $imageFile,
				'new_file' => $newFile,
				'width' => $width,
				'height' => $height,
				'should_crop' => $shouldCrop ? 1 : 0,
				'quality' => $quality,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);
	}
	
	static function popAndRun($settings) {
		global $wpdb;
		$task = $wpdb->get_row('SELECT * FROM '.$wpdb->prefix.'pp_wpsq_api_queue ORDER BY queue_id ASC LIMIT 1');
		
		if ($task) {
			
			$sourceFileDotPos = strpos($task->image_file, '.');
			if ($sourceFileDotPos) {
				$sourceFileExt = substr($task->image_file, $sourceFileDotPos + 1);
			} else {
				$sourceFileExt = '';
			}
			
			switch ( @strtoupper($sourceFileExt) ) {
				case 'JPG':
				case 'JPEG':
					$sourceFileType = 'jpeg';
					break;
				case 'PNG':
					$sourceFileType = 'png'; // all good
					break;
			}
		
			$apiData = array(
				'action' => 'wp_squish_compress_image',
				'image' => new CURLFile($task->image_file),
				'width' => $task->width,
				'height' => $task->height,
				'should_crop' => $task->should_crop,
				'quality' => $task->quality,
				'site' => get_option('siteurl'),
				// '_ags_layouts_token' => AGSLayoutsAccount::getToken(),
			);
			
			if ($sourceFileType == 'jpeg') {
				if ($settings['api_compressor_jpeg_keep_iptc_on']) {
					$apiData['jpeg_keep_iptc'] = 1;
				}
				
				if ($settings['api_compressor_jpeg_keep_comments_on']) {
					$apiData['jpeg_keep_comments'] = 1;
				}
			}
			
			$curl = curl_init(WPSquish::API_URL);
			curl_setopt_array($curl, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $apiData
			));
			
			$response = curl_exec($curl);
			
			if ($response && substr($response, -17) == 'WP_SQUISH_SUCCESS') {
				$response = substr($response, 0, -17);
				$sizeBefore = filesize($task->new_file);
				$sizeAfter = file_put_contents($task->new_file, $response);
				$improvement = ($sizeBefore - $sizeAfter) / $sizeBefore;
				
				$apiStats = get_option('pp_wpsq_api_stats_'.$sourceFileType, array());
				
				if ( empty($apiStats['count']) || empty($apiStats['improvement']) ) {
					$apiStats['count'] = 1;
					$apiStats['improvement'] = $improvement;
				} else {
					$apiStats['improvement'] = ( ($apiStats['improvement'] * $apiStats['count']) + $improvement ) / ++$apiStats['count'];
				}
				
				update_option('pp_wpsq_api_stats_'.$sourceFileType, $apiStats, false);
				
				return self::delete($task->image_file, $task->new_file, $task->width, $task->height, $task->should_crop);
				
				
				
				@file_put_contents(__DIR__.'/api-sizes.txt', "$sizeBefore\t$sizeAfter\t$improvement\n", FILE_APPEND);
			}

		}
		
		return false;
		
	}
	
	static function delete($imageFile, $newFile, $width, $height, $shouldCrop) {
		global $wpdb;
		return $wpdb->delete(
			$wpdb->prefix.'pp_wpsq_api_queue',
			array(
				'image_file' => $imageFile,
				'new_file' => $newFile,
				'width' => $width,
				'height' => $height,
				'should_crop' => $shouldCrop ? 1 : 0,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%d',
				'%d',
			)
		);
	}
	
	static function createTable() {
		global $wpdb;
		$wpdb->query('
			CREATE TABLE '.$wpdb->prefix.'pp_wpsq_api_queue (
				queue_id INT(11) AUTO_INCREMENT,
				image_file VARCHAR(1000),
				new_file VARCHAR(1000),
				width INT(11),
				height INT(11),
				should_crop TINYINT(1),
				quality INT(11),
				PRIMARY KEY (queue_id)
			)
		');
	}
	

}
?>