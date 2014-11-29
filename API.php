<?php
/**
 * @package		Piwik.Counter.API
 * @copyright	Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license		GNU General Public License version 2 or later
 * @url			http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url			http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */
namespace Piwik\Plugins\Counter;

use Exception;
use Piwik\Access;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Db;
use Piwik\Log;
use Piwik\Piwik;
use Piwik\Plugins\SitesManager\API as SitesManager;
use Zend_Cache;

define('DS', DIRECTORY_SEPARATOR);

// I don't know if Zend_Cache is available so I include it. Need some tests.
require_once PIWIK_INCLUDE_PATH.DS.'libs'.DS.'Zend'.DS.'Cache.php';

class API extends \Piwik\Plugin\API {
	static private $instance = null;

	static public function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function getPluginInfo() {
		$pluginManager = \Piwik\Plugin\Manager::getInstance();
		$plugins = $pluginManager->loadAllPluginsAndGetTheirInfo();

		return $plugins['Counter'];
	}

	private function rgb2array($rgb) {
		$rgb = str_replace('#', '', $rgb);

		return array(
			'r'=>base_convert(substr($rgb, 0, 2), 16, 10),
			'g'=>base_convert(substr($rgb, 2, 2), 16, 10),
			'b'=>base_convert(substr($rgb, 4, 2), 16, 10),
		);
	}

	public function publish($id, $state) {
		if ($id == 0) return false;

		// true for unpublish
		$_state = ($state === true) ? 0 : 1;
		$result = Db::exec("UPDATE `".Common::prefixTable('counter_sites')."` SET `published` = '".(int)$_state."' WHERE `id` = ".(int)$id);

		return $result;
	}

	public function getSitesList() {
		$result = Db::fetchAll("SELECT `idsite`, `name` FROM `".Common::prefixTable('site')."` ORDER BY `idsite` ASC");

		return $result;
	}

	public function siteidPrecheck($idsite) {
		$query_result = Db::fetchOne("SELECT COUNT(`id`) FROM `".Common::prefixTable('counter_sites')."` WHERE `idsite` = ".(int)$idsite);

		if ($query_result != 0) {
			// Counter for this site is allready exists
			$result = array('success'=>0);
		} else {
			$ts_created = Db::fetchOne("SELECT DATE_FORMAT(`ts_created`, '%Y-%m-%d') AS `ts_created` FROM `".Common::prefixTable('site')."` WHERE `idsite` = ".(int)$idsite);
			$result = array('success'=>1, 'ts_created'=>$ts_created);
		}

		return json_encode($result);
	}

	public function save() {
		$id = Common::getRequestVar('id', 0, 'int');
		$siteid = Common::getRequestVar('siteid', 0, 'int');
		$published = Common::getRequestVar('published', 0, 'int');
		$title = Common::getRequestVar('title', 'Default site', 'string');

		// Manipulating with date
		$start_date = Common::getRequestVar('start_date', '', 'string');
		$start_date_period = Common::getRequestVar('start_date_period', '', 'string');
		if (empty($start_date) && empty($start_date_period)) {
			$start_date_period = 'none';
		}
		
		$params = json_encode(array(
			'show_sitename' => 		Common::getRequestVar('show_sitename', 1, 'int'),
			'show_visits' => 		Common::getRequestVar('show_visits', 1, 'int'),
			'show_views' => 		Common::getRequestVar('show_views', 1, 'int'),
			'sitename_pos_x' => 	Common::getRequestVar('sitename_pos_x', 3, 'int'),
			'sitename_pos_y' => 	Common::getRequestVar('sitename_pos_y', 13, 'int'),
			'visitors_pos_x' => 	Common::getRequestVar('visitors_pos_x', 3, 'int'),
			'visitors_pos_y' => 	Common::getRequestVar('visitors_pos_y', 26, 'int'),
			'views_pos_x' => 		Common::getRequestVar('views_pos_x', 30, 'int'),
			'views_pos_y' => 		Common::getRequestVar('views_pos_y', 26, 'int'),
			'img_size_x' => 		Common::getRequestVar('img_size_x', 80, 'int'),
			'img_size_y' => 		Common::getRequestVar('img_size_y', 31, 'int'),
			'sitename_font_size' => Common::getRequestVar('sitename_font_size', 7, 'int'),
			'visits_font_size' => 	Common::getRequestVar('visits_font_size', 7, 'int'),
			'hits_font_size' => 	Common::getRequestVar('hits_font_size', 7, 'int'),
			'img_path' => 			Common::getRequestVar('img_path', '', 'string'),
			'font_path' => 			Common::getRequestVar('font_path', '', 'string'),
			'cache' => 				Common::getRequestVar('cache', 1, 'int'),
			'cache_time' => 		Common::getRequestVar('cache_time', 900, 'int'),
			'start_date' => 		$start_date,
			'start_date_period' => 	$start_date_period,
			'token' => 				Common::getRequestVar('token', 'anonymous', 'string'),
			'color_sitename' => 	Common::getRequestVar('color_sitename', '#03374A', 'string'),
			'color_visits' => 		Common::getRequestVar('color_visits', '#F16045', 'string'),
			'color_views' => 		Common::getRequestVar('color_views', '#E07A52', 'string'),
			'last_minutes' => 		Common::getRequestVar('last_minutes', 30, 'int'),
			'check_interval' => 	Common::getRequestVar('check_interval', 10000, 'int'),
			'static' => 			Common::getRequestVar('static', 1, 'int'),
			'livestat_elem_id' => 	Common::getRequestVar('livestat_elem_id', '', 'string'),
			'tpl_by_countries' => 	Common::getRequestVar('tpl_by_countries', '', 'string'),
			'tpl_by_countries_elem_id' => Common::getRequestVar('tpl_by_countries_elem_id', '', 'string'),
		));

		if ($siteid == 0) {
			return false;
			exit();
		}

		if ($id == 0) {
			$db = Db::get();

			 $db->insert(
				Common::prefixTable('counter_sites'),
				array(
					'idsite' => (int)$siteid,
					'title' => $title,
					'params' => $params,
					'published' => (int)$published
				)
			);

			return $db->lastInsertId();
		} else {
			Db::exec("UPDATE `".Common::prefixTable('counter_sites')."`"
				. "\n SET `idsite` = '".(int)$siteid."', `title` = '".$title."', `params` = '".$params."', `published` = '".(int)$published."'"
				. "\n WHERE `id` = ".(int)$id);

			return $id;
		}
	}

	/**
	 * Remove counter from DB
	 *
	 * @param   bool  $id		Counter ID
	 *
	 * @return	json	string
	 */
	public function remove($id) {
		$success = 0;

		if (!empty($id)) {
			Db::exec("DELETE FROM `".Common::prefixTable('counter_sites')."` WHERE `id` = ".(int)$id);
			$success = 1;
		}

		return json_encode(array('success'=>$success));
	}

	/**
	 * Get data for one counter or list of counters
	 *
	 * @param   bool  $id		False - get data for all counters
	 *
	 * @return	array
	 */
	public function getCountersData($id=false) {
		$result = array();

		// if false - retrieve data for all sites
		if ($id === false) {
			$sites = SitesManager::getInstance()->getSitesIdWithAdminAccess();
			$query = Db::fetchAll("SELECT `id`, `idsite`, `title`, `params`, `published`"
				. "\n FROM `".Common::prefixTable('counter_sites')."`"
				. "\n WHERE `idsite` IN (".implode(',', $sites).")"
				. "\n ORDER BY `idsite` ASC");

			if(!empty($query)) {
				foreach ($query as $key=>$row) {
					$result[$key]['id'] = $row['id'];
					$result[$key]['idsite'] = $row['idsite'];
					$result[$key]['params'] = json_decode($row['params'], true);
					$result[$key]['title'] = $row['title'];
					$result[$key]['published'] = $row['published'];
				}
			}
		} else {
			$row = Db::fetchRow("SELECT `id`, `idsite`, `title`, `params`, `published` FROM `".Common::prefixTable('counter_sites')."` WHERE `id` = ".$id);

			$result['id'] = $row['id'];
			$result['idsite'] = $row['idsite'];
			$result['params'] = json_decode($row['params'], true);
			$result['params']['tpl_by_countries'] = html_entity_decode($result['params']['tpl_by_countries'], ENT_QUOTES, 'UTF-8');
			$result['title'] = $row['title'];
			$result['published'] = $row['published'];

			if (empty($result['params']['token'])) {
				$result['params']['token'] = Access::getInstance()->getTokenAuth();
			}

			/* Cross-Origin Resource Sharing (CORS) */
			/* we fetch the urls associated to this counter */
			$query = "SELECT `s`.`main_url`, `u`.`url`"
				. "\n FROM `".Common::prefixTable('site')."` AS `s`"
				. "\n LEFT JOIN `".Common::prefixTable('site_url')."` AS `u` ON `s`.`idsite` = `u`.`idsite`"
				. "\n WHERE `s`.`idsite` = ".(int)$row['idsite'];
			$rows = Db::fetchAll($query);

			$origins = array();
			$p = array('/http:\/\//', '/https:\/\//');
			$r = array('', '');

			if (!empty($rows)) {
				foreach($rows as $row) {
					$origins[] = $row['main_url'];
					$origins[] = $row['url'];
				}

				$origins = array_unique(preg_replace($p, $r, $origins));
			}

			$result['origins'] = $origins;
		}

		return $result;
	}

	/**
	 * Checking the user access rights
	 *
	 * @param   bool  $is_index		True if counter data request from frontpage
	 *
	 * @return	true or Exception
	 */
	public function checkAccess($is_index=false) {
		$sites_manager = SitesManager::getInstance();
		$access = Access::getInstance();
		$id_arr = $sites_manager->getSitesIdWithAdminAccess();

		// Checking the user access rights. If the user not an admin for at least one site when throw an error
		if (count($id_arr) <= 0) {
			throw new Exception(Piwik::Translate('General_ExceptionPrivilegeAtLeastOneWebsite', array('admin')));
		} else {
			// Don't perfom checking for index
			if ($is_index === false) {
				if ($access->hasSuperUserAccess()) {
					return true;
				}

				$id = Common::getRequestVar('id', 0, 'int');
				$action = Common::getRequestVar('action', '', 'string');

				if (empty($id) && $action == 'siteidPrecheck') {
					return true;
				}

				if (!empty($id) && ($action != 'save' || $action != 'apply')) {
					$login = $access->getLogin();
					$result = Db::fetchRow("SELECT `idsite`"
						. "\n FROM `".Common::prefixTable('access')."`"
						. "\n WHERE `idsite` = ("
							. "\n\t SELECT `idsite`"
							. "\n\t FROM `".Common::prefixTable('counter_sites')."`"
							. "\n\t	WHERE `id` = ".(int)$id
						. "\n ) AND `access` = 'admin' AND `login` = '".$login."'");

					if ($result === false) {
						throw new Exception(Piwik::Translate('General_ExceptionPrivilegeAccessWebsite', array('admin', $id)));
					}
				} else {
					return true;
				}
			}
		}
	}

	private function createImage($extra=array()) {
		$id = Common::getRequestVar('id', 0, 'int');
		$params = count($extra) > 0 ? $extra : $this->getCountersData($id);
		$date_request = Common::getRequestVar('date', '', 'string');
		$date_start = $params['params']['start_date'];
		$date_start_period = $params['params']['start_date_period'];

		if (!empty($date_request)) {
			$_date = $date_request;
		} else {
			if (!empty($date_start)) {
				$_date = $date_start;
			} else {
				if ($date_start_period != 'none') {
					$_date = $date_start_period;
				} else {
					$_date = 'day';
				}
			}
		}

		if ($_date == 'day') {
			$date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y')));
		} elseif ($_date == 'yesterday') {
			$date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-1, date('Y')));
		} elseif ($_date == 'week') {
			$date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')-7, date('Y')));
		} elseif ($_date == 'month') {
			$date = date('Y-m-d', mktime(0, 0, 0, date('m')-1, date('d'), date('Y')));
		} elseif ($_date == 'year') {
			$date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), date('Y')-1));
		} else {
			$date = $_date;
		}

		$counter_params = &$params['params'];

		$request = new Request('method=VisitsSummary.get&idSite='.(int)$params['idsite'].'&period=range&date='.$date.','.date('Y-m-d').'&format=php&serialize=0&token_auth='.$counter_params['token']);
		$result = $request->process();

		$visits = 0;
		$views = 0;

		if (isset($result['result']) && $result['result'] == 'error') {
			echo $result['message'];
			return false;
		}

		if (isset($result['nb_visits'])) {
			$visits = $result['nb_visits'];
			$views = $result['nb_actions'];
		} else {
			$c_visits = 0;
			$c_views = 0;

			foreach ($result as $value) {
				$visits = $c_visits+$value['nb_visits'];
				$views = $c_views+$value['nb_actions'];
			}
		}

		$data = array($visits, $views);

		if (!empty($counter_params['img_path']) && file_exists($counter_params['img_path'])) {
			$mime = $this->getMime($params['params']['img_path']);
			$dst_im = imagecreatetruecolor($counter_params['img_size_x'], $counter_params['img_size_y']);

			if ($mime == 'image/png') {
				$src_im = imagecreatefrompng($counter_params['img_path']);
				imagealphablending($src_im, true);
				imagesavealpha($src_im, true);
			} elseif ($mime == 'image/gif') {
				$src_im = imagecreatefromgif($counter_params['img_path']);
			} elseif ($mime == 'image/jpeg') {
				$src_im = imagecreatefromjpeg($counter_params['img_path']);
			} else { // Unsupported image type
				// Output an 1x1 transparent gif
				$im = imagecreatetruecolor(1, 1);
				imagecolortransparent($im, imagecolorallocate($im, 0, 0, 0));

				header('Content-type: image/gif');

				imagegif($im);
				imagedestroy($im);

				exit();
			}

			list($w, $h) = getimagesize($counter_params['img_path']);
			if (!empty($counter_params['font_path']) && file_exists($counter_params['font_path'])) {
				// Draw sitename
				if (!empty($params['title']) && $counter_params['show_sitename'] == 1) {
					$rgb_arr_sitename = $this->rgb2array($counter_params['color_sitename']);
					$color_sitename = imagecolorallocate($src_im, $rgb_arr_sitename['r'], $rgb_arr_sitename['g'], $rgb_arr_sitename['b']);
					imagettftext($src_im, $counter_params['sitename_font_size'], 0, $counter_params['sitename_pos_x'], $counter_params['sitename_pos_y'], $color_sitename, $counter_params['font_path'], $params['title']);
				}

				// Draw visits
				if ($counter_params['show_visits'] == 1) {
					$rgb_arr_visits = $this->rgb2array($counter_params['color_visits']);
					$color_visits = imagecolorallocate($src_im, $rgb_arr_visits['r'], $rgb_arr_visits['g'], $rgb_arr_visits['b']);
					imagettftext($src_im, $counter_params['visits_font_size'], 0, $counter_params['visitors_pos_x'], $counter_params['visitors_pos_y'], $color_visits, $counter_params['font_path'], $data[0]);
				}

				// Draw views
				if ($counter_params['show_views'] == 1) {
					$rgb_arr_views = $this->rgb2array($counter_params['color_views']);
					$color_views = imagecolorallocate($src_im, $rgb_arr_views['r'], $rgb_arr_views['g'], $rgb_arr_views['b']);
					imagettftext($src_im, $counter_params['hits_font_size'], 0, $counter_params['views_pos_x'], $counter_params['views_pos_y'], $color_views, $counter_params['font_path'], $data[1]);
				}
			} else {
				// Draw sitename
				if (!empty($params['title']) && $counter_params['show_sitename'] == 1) {
					$rgb_arr_sitename = $this->rgb2array($counter_params['color_sitename']);
					// Text must be only in Latin2!
					imagestring($src_im, $counter_params['sitename_font_size']-5, $counter_params['sitename_pos_x'], $counter_params['sitename_pos_y']-10, $params['title'], imagecolorallocate($src_im, $rgb_arr_sitename['r'], $rgb_arr_sitename['g'], $rgb_arr_sitename['b']));
				}

				// Draw visits
				if ($counter_params['show_visits'] == 1) {
					$rgb_arr_visits = $this->rgb2array($counter_params['color_visits']);
					imagestring($src_im, $counter_params['visits_font_size']-5, $counter_params['visitors_pos_x'], $counter_params['visitors_pos_y']-10, $data[0], imagecolorallocate($src_im, $rgb_arr_visits['r'], $rgb_arr_visits['g'], $rgb_arr_visits['b']));
				}

				// Draw views
				if ($counter_params['show_views'] == 1) {
					$rgb_arr_views = $this->rgb2array($counter_params['color_views']);
					imagestring($src_im, $counter_params['hits_font_size']-5, $counter_params['views_pos_x']+5, $counter_params['views_pos_y']-10, $data[1], imagecolorallocate($src_im, $rgb_arr_views['r'], $rgb_arr_views['g'], $rgb_arr_views['b']));
				}
			}

			imagecopyresampled($dst_im, $src_im, 0, 0, 0, 0, $counter_params['img_size_x'], $counter_params['img_size_y'], $w, $h);

			header('Content-type: '.$mime);

			if ($mime == 'image/png') {
				imagepng($src_im);
			} elseif ($mime == 'image/gif') {
				imagegif($src_im);
			} else {
				imagejpeg($src_im, null, 75);
			}

			imagedestroy($src_im);
		}
	}

	/**
	 * Show image from cache or create a new one
	 *
	 * @return image binary
	 */
	public function showImage() {
		$id = Common::getRequestVar('id', 0, 'int');
		$params = $this->getCountersData($id);
		$cache_id = 'counter_image_'.md5($id).'_'.$id;

		// If the counter has not been published - output an 1x1 transparent gif
		if ($params['published'] != 1) {
			$im = imagecreatetruecolor(1, 1);
			imagecolortransparent($im, imagecolorallocate($im, 0, 0, 0));

			header('Content-type: image/gif');

			imagegif($im);
			imagedestroy($im);

			exit();
		}

		if ($params['params']['cache'] == 1) {
			$cache = Zend_Cache::factory('Output', 'File',
				array(
					'lifetime' => (int)$params['params']['cache_time'],
					'automatic_serialization' => false
				),
				array(
					'cache_dir'=>PIWIK_DOCUMENT_ROOT.DS.'tmp'.DS.'cache'.DS
				)
			);

			// Test if cache is available
			if ($cache->test($cache_id)) {
				$mime = $this->getMime($params['params']['img_path']);
				header('Content-type: '.$mime);

				// Load and output image from cache
				echo $cache->load($cache_id, true);
			} else {
				// Build new image and save to cache
				if (!$cache->start($cache_id)) {
					echo $this->createImage();
					$cache->end();
				}
			}
		} else {
			echo $this->createImage();
		}
	}

	/**
	 * Create image for preview while editing counter data. For backend only
	 *
	 * @return image binary
	 */
	public function previewImage() {
		$params = array(
			'id' => 		Common::getRequestVar('id', 0, 'int'),
			'idsite' => 	Common::getRequestVar('siteid', 0, 'int'),
			'published' => 	Common::getRequestVar('published', 0, 'int'),
			'title' => 		Common::getRequestVar('title', 'Default site', 'string'),
			'params' => array(
				'show_sitename' => 		Common::getRequestVar('show_sitename', 1, 'int'),
				'show_visits' => 		Common::getRequestVar('show_visits', 1, 'int'),
				'show_views' => 		Common::getRequestVar('show_views', 1, 'int'),
				'sitename_pos_x' => 	Common::getRequestVar('sitename_pos_x', 3, 'int'),
				'sitename_pos_y' => 	Common::getRequestVar('sitename_pos_y', 13, 'int'),
				'visitors_pos_x' => 	Common::getRequestVar('visitors_pos_x', 3, 'int'),
				'visitors_pos_y' => 	Common::getRequestVar('visitors_pos_y', 26, 'int'),
				'views_pos_x' => 		Common::getRequestVar('views_pos_x', 30, 'int'),
				'views_pos_y' => 		Common::getRequestVar('views_pos_y', 26, 'int'),
				'img_size_x' => 		Common::getRequestVar('img_size_x', 80, 'int'),
				'img_size_y' => 		Common::getRequestVar('img_size_y', 31, 'int'),
				'sitename_font_size' => Common::getRequestVar('sitename_font_size', 7, 'int'),
				'visits_font_size' => 	Common::getRequestVar('visits_font_size', 7, 'int'),
				'hits_font_size' => 	Common::getRequestVar('hits_font_size', 7, 'int'),
				'img_path' => 			Common::getRequestVar('img_path', '', 'string'),
				'font_path' => 			Common::getRequestVar('font_path', '', 'string'),
				'date' => 				Common::getRequestVar('date', '', 'string'),
				'start_date' => 		Common::getRequestVar('start_date', '', 'string'),
				'start_date_period' => 	Common::getRequestVar('start_date_period', '', 'string'),
				'token' => 				Common::getRequestVar('token', 'anonymous', 'string'),
				'color_sitename' => 	Common::getRequestVar('color_sitename', '#03374A', 'string'),
				'color_visits' => 		Common::getRequestVar('color_visits', '#F16045', 'string'),
				'color_views' => 		Common::getRequestVar('color_views', '#E07A52', 'string')
			)
		);

		@set_time_limit(0);
		echo $this->createImage($params);
	}

	public function getLiveVisitorsCount() {
		$type = Common::getRequestVar('type', '', 'string');
		$id = Common::getRequestVar('id', 0, 'int');
		$params = $this->getCountersData($id);

		if ($params['published'] != 1) die();

		if ($type == 'js') {
			if (!empty($params['params']['tpl_by_countries'])) {
				$dom_elem_id = $params['params']['tpl_by_countries_elem_id'];
			} else {
				$dom_elem_id = $params['params']['livestat_elem_id'];
			}

			$data_ajax_url = (array_key_exists('HTTPS', $_SERVER) ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'?module=Counter&action=live&id='.$id;

			header('Content-type: text/javascript');

			echo 'var elem = "'.$dom_elem_id.'", url = "'.$data_ajax_url.'"; function createXHR(){var a;if(window.ActiveXObject){try{a=new ActiveXObject("Microsoft.XMLHTTP")}catch(b){document.getElementById(elem).innerHTML=b.message;a=null}}else{a=new XMLHttpRequest}return a}function sendRequest(){var a=createXHR();a.onreadystatechange=function(){if(a.readyState===4){document.getElementById(elem).innerHTML=a.responseText}};a.open("GET",url,true);a.send()}sendRequest();';
			echo ($params['params']['static'] == 0) ? 'setInterval(sendRequest, '.(int)$params['params']['check_interval'].');' : '';
		} else {
			if (!empty($params['params']['tpl_by_countries'])) {
				// Search for start_date value for period
				if (preg_match('#\[date(.*?)\]#', $params['params']['tpl_by_countries'], $matches)) {
					preg_match('#\[date(\sstart="(?P<start_date>.+?)")?(\sformat="(?P<date_format>.+?)")?(\send="(?P<end_date>.+?)")?\]#i', $params['params']['tpl_by_countries'], $m);

					if (isset($m['start_date']) && !empty($m['start_date'])) {
						$start_date = $m['start_date'];
					} else {
						$start_date = $params['params']['start_date'];
					}

					$end_date = (isset($m['end_date']) && !empty($m['end_date'])) ? $m['end_date'] : date('Y-m-d');

					if (isset($m['date_format']) && !empty($m['date_format'])) {
						$format = date($m['date_format'], strtotime($start_date));
					} else {
						$format = $start_date;
					}

					$date_range = $start_date.','.$end_date;
				} else {
					$format = '';
					$date_range = $params['params']['start_date'].','.date('Y-m-d'); // default date range
				}

				// Process offsets
				// Visits
				if (preg_match('#\[nb_visits(.*?)\]#', $params['params']['tpl_by_countries'], $nbv_matches)) {
					preg_match('#\[nb_visits(\soffset="(?P<offset>.+?)")\]#i', $params['params']['tpl_by_countries'], $nbv_m);
				} else {
					$nbv_m = '';
				}

				// Countries
				if (preg_match('#\[nb_countries(.*?)\]#', $params['params']['tpl_by_countries'], $nbc_matches)) {
					preg_match('#\[nb_countries(\soffset="(?P<offset>.+?)")\]#i', $params['params']['tpl_by_countries'], $nbc_m);
				} else {
					$nbc_m = '';
				}

				if (preg_match('#\[nb_actions(.*?)\]#', $params['params']['tpl_by_countries'], $nbc_matches)) {
					preg_match('#\[nb_actions(\soffset="(?P<offset>.+?)")\]#i', $params['params']['tpl_by_countries'], $nba_m);
				} else {
					$nba_m = '';
				}

				$request = new Request('method=UserCountry.getCountry&idSite='.$params['idsite'].'&period=range&date='.$date_range.'&format=JSON&token_auth='.$params['params']['token']);
				$result = json_decode($request->process());
			} else {
				$request = new Request('method=Live.getCounters&idSite='.$params['idsite'].'&lastMinutes='.(int)$params['params']['last_minutes'].'&format=JSON&token_auth='.$params['params']['token']);
				$result = json_decode($request->process());
			}

			/* Cross-Origin Resource Sharing (CORS) */
			/* if piwik is hosted on a different site than the
			 * page calling the js, then the browser will break
			 * the script. This is unless a proper CORS is returned.
			 * this is done by checking which the head 'Origin' with
			 * the list of $params['origins'] and if there's a match
			 * to return the proper 'Access-Control-Allow-Origin' header
			 *
			 * Limitations: Unsure how to distringuish/restrict http/https
			 * at the moment, there's no checks on that. We only check
			 * the hostname provided. Let's assume the counter in itself
			 * isn't too much of a sensitive data anyway.
			*/
			if (!function_exists('getallheaders')) {
				// See http://www.php.net/manual/en/function.getallheaders.php#84262
				$headers = '';
				foreach ($_SERVER as $name=>$value) {
					if (substr($name, 0, 5) == 'HTTP_') {
						$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
					}
				}
			} else {
				$headers = getallheaders();
			}

			$protocol = array('/http:\/\//', '/https:\/\//');
			$r = array('', '');

			if (array_key_exists('Origin', $headers)) {
				$origin = $headers['Origin'];

				if ( in_array(preg_replace($protocol, $r, $origin), $params['origins']) ) {
					header('Access-Control-Allow-Origin: '. $origin);
				}
			}

			if (!empty($params['params']['tpl_by_countries'])) {
				$nb_countries = count($result);
				$nb_visits = 0;
				$nb_actions = 0;

				foreach ($result as $data) {
					$nb_visits = $nb_visits + (int)$data->nb_visits;
					$nb_actions = $nb_actions + (int)$data->nb_actions;
				}

				// Offsets for visitors
				if (is_array($nbv_m)) {
					if (array_key_exists('offset', $nbv_m) && substr($nbv_m['offset'], 0, 1) == '-') { // Negative offset
						if ($nb_visits > 0) {
							$nb_visits = $nb_visits - (int)substr($nbv_m['offset'], 1);
						}
					} elseif (array_key_exists('offset', $nbv_m) && substr($nbv_m['offset'], 0, 1) == '+') { // Positive offset
						$nb_visits = $nb_visits + (int)substr($nbv_m['offset'], 1);
					}
				}

				// Offsets for countries
				if (is_array($nbc_m)) {
					if (array_key_exists('offset', $nbc_m) && substr($nbc_m['offset'], 0, 1) == '-') { // Negative offset
						if ($nb_countries > 0) {
							$nb_countries = $nb_countries - (int)substr($nbc_m['offset'], 1);
						}
					} elseif (array_key_exists('offset', $nbc_m) && substr($nbc_m['offset'], 0, 1) == '+') { // Positive offset
						$nb_countries = $nb_countries + (int)substr($nbc_m['offset'], 1);
					}
				}

				// Offsets for actions
				if (is_array($nba_m)) {
					if (array_key_exists('offset', $nba_m) && substr($nba_m['offset'], 0, 1) == '-') { // Negative offset
						if ($nb_actions > 0) {
							$nb_actions = $nb_actions - (int)substr($nba_m['offset'], 1);
						}
					} elseif (array_key_exists('offset', $nba_m) && substr($nba_m['offset'], 0, 1) == '+') { // Positive offset
						$nb_actions = $nb_actions + (int)substr($nba_m['offset'], 1);
					}
				}

				$patterns = array(
					'nb_visits'=>'#\[nb_visits(.*?)\]#',
					'nb_countries'=>'#\[nb_countries(.*?)\]#',
					'nb_actions'=>'#\[nb_actions(.*?)\]#',
					'date'=>'#\[date(.*?)\]#'
				);
				$data = array(
					'nb_visits'=>$nb_visits,
					'nb_actions'=>$nb_actions,
					'nb_countries'=>$nb_countries,
					'date'=>$format,
				);
				ksort($data);
				ksort($patterns);
				$str = preg_replace($patterns, $data, $params['params']['tpl_by_countries']);

				header('Content-type: text/html');
				echo $str;
			} else {
				header('Content-type: text/html');
				echo $result[0]->visits;
			}
		}
	}

	/**
	 * Clear cache for custom counter
	 *
	 * @return json string
	 */
	public function clearCache() {
		$id = Common::getRequestVar('id', 0, 'int');
		$cache_id = 'counter_image_'.md5($id).'_'.$id;
		$lifetime = Common::getRequestVar('lifetime', 0, 'int');
		$cache = Zend_Cache::factory('Output', 'File',
			array(),
			array(
				'cache_dir'=>PIWIK_DOCUMENT_ROOT.DS.'tmp'.DS.'cache'.DS
			)
		);
		$success = 0;

		if ($cache->remove($cache_id)) {
			$success = 1;
		}

		return json_encode(array('success'=>$success));
	}

	/**
	 * Method to get proper MIME type
	 *
	 * @param   string  $path		Absolute path to file
	 *
	 * @return	string
	 */
	protected function getMime($path) {
		if (!empty($path) && file_exists($path)) {
			if (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime = finfo_file($finfo, $path);
				finfo_close($finfo);
			} elseif (function_exists('mime_content_type')) {
				$mime = mime_content_type($path);
			} else {
				$ext = pathinfo($path, PATHINFO_EXTENSION);
				if ($ext == 'png') {
					$mime = 'image/png';
				} elseif ($ext == 'gif') {
					$mime = 'image/gif';
				} elseif ($ext == 'jpg' || $ext == 'jpeg') {
					$mime = 'image/jpeg';
				} elseif ($ext == 'bmp') {
					$mime = 'image/bmp';
				} else {
					$mime = 'text/html';
				}
			}
		} else {
			$mime = 'image/jpeg';
		}

		return $mime;
	}
}
