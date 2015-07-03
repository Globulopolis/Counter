<?php
/**
 * @package		Piwik.Counter.API
 * @copyright	Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license		GNU General Public License version 3 or later
 * @url			http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url			http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */
namespace Piwik\Plugins\Counter;

use Exception;
use Piwik\Access;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Db;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin\Manager as Manager;
use Piwik\Plugins\SitesManager\API as SitesManager;
use Zend_Cache;

/**
 * Display Hits/Visits on image. Display Hits/Visits/from Countries stats as text via ajax requests.
 */
class API extends \Piwik\Plugin\API {
	/**
	 * Method to get a list of counters.
	 *
	 * @return  array
	 */
	public function getItems() {
		$result = $this->getModel()->getItems();

		return $result;
	}

	/**
	 * Method to get a counter data.
	 *
	 * @return  mixed  Array on success, false otherwise
	 */
	public function getItem() {
		$id = Common::getRequestVar('id', array(), 'array');

		if (empty($id)) {
			$id[] = Common::getRequestVar('id', 0, 'int');

			if (empty($id)) {
				return false;
			}
		}

		$this->checkAccess();

		$result = $this->getModel()->getItem($id);

		return $result;
	}

	/**
	 * Method to get a list of sites.
	 *
	 * @return  array
	 */
	public function getSitesList() {
		$result = $this->getModel()->getSitesList();

		return $result;
	}

	/**
	 * Method to change the published state of one or more records.
	 *
	 * @param   array    $ids    A list of the primary keys to change.
	 * @param   integer  $state  The value of the published state.
	 *
	 * @return  boolean  True on success.
	 */
	public function publish($ids, $state=1) {
		if (empty($ids)) {
			return false;
		}

		$this->checkAccess();

		$result = $this->getModel()->publish($ids, $state);

		return $result;
	}

	/**
	 * Remove counter(s) from DB and clear the image cache.
	 *
	 * @param    array    $ids   A list of the primary keys to remove.
	 *
	 * @return   boolean  True on success.
	 */
	public function remove($ids) {
		if (empty($ids)) {
			return false;
		}

		$this->checkAccess();

		$this->clearCache($ids);
		$result = $this->getModel()->remove($ids);

		return $result;
	}

	/**
	 * Method to check if counter exists for that site
	 *
	 * @param    integer   $idsite   Site ID
	 *
	 * @return   string
	 */
	public function counterExists($idsite) {
		if (empty($idsite)) {
			return json_encode(array('success' => 0));
		}

		$result = $this->getModel()->counterExists($idsite);

		return json_encode($result);
	}

	/**
	 * Method to save the data into DB
	 *
	 * @return   integer   Return lastInsertID or item ID on update
	 */
	public function save() {
		$id = Common::getRequestVar('id', array(), 'array');
		$site_id = Common::getRequestVar('siteid', 0, 'int');

		if (empty($id) || empty($site_id)) {
			return false;
		}

		$this->checkAccess();

		$result = $this->getModel()->save($id, $this->getModel()->getForm(true));

		return $result;
	}

	/**
	 * Enqueue message in the session and display it.
	 *
	 * @param    string    $message     The text to display
	 * @param    string    $style       Style of the message. See CONTEXT_* in core/Notification.php fot valid message styles.
	 * @param    string    $type        Message type. See TYPE_* in core/Notification.php fot valid message types.
	 * @param    integer   $priority    Priority. See PRIORITY_* in core/Notification.php fot valid message priorities.
	 *
	 * @return	void
	 */
	public function enqueueMessage($message, $style='info', $type='transient', $priority=0) {
		$notification = new Notification($message);

		switch (strtolower($style)) {
			case 'success':
				$notification->context = Notification::CONTEXT_SUCCESS;
				break;

			case 'error':
				$notification->context = Notification::CONTEXT_ERROR;
				break;

			case 'warning':
				$notification->context = Notification::CONTEXT_WARNING;
				break;

			case 'info':
			default:
				$notification->context = Notification::CONTEXT_INFO;
				break;
		}

		if (!empty($type) || !is_null($type)) {
			if ($type == 'persistent') {
				$notification->type = Notification::TYPE_PERSISTENT;
			} elseif ($type == 'toast') {
				$notification->type = Notification::TYPE_TOAST;
			} else {
				$notification->type = Notification::TYPE_TRANSIENT;
			}
		}

		if (!empty($priority)) {
			if ($priority === 1) {
				$notification->priority = Notification::PRIORITY_MIN;
			} elseif ($priority === 25) {
				$notification->priority = Notification::PRIORITY_LOW;
			} elseif ($priority === 50) {
				$notification->priority = Notification::PRIORITY_HIGH;
			} elseif ($priority === 100) {
				$notification->priority = Notification::PRIORITY_MAX;
			}
		}

		Notification\Manager::notify('myUniqueNotificationId', $notification);
	}

	/**
	 * Check the user access rights
	 *
	 * @param   boolean  $is_index   True if counter data requested from frontpage
	 *
	 * @return	boolean
	 * @throws  Exception
	 */
	public function checkAccess($is_index=false) {
		$sites_manager = SitesManager::getInstance();
		$access = Access::getInstance();
		$sites_ids = $sites_manager->getSitesIdWithAdminAccess();

		// Checking the user access rights. If the user not an admin for at least one site when throw an error
		if (count($sites_ids) <= 0) {
			throw new Exception(Piwik::Translate('General_ExceptionPrivilegeAtLeastOneWebsite', array('admin')));
		} else {
			// Don't perfom checking for index
			if ($is_index === false) {
				if ($access->hasSuperUserAccess()) {
					return true;
				}

				$ids = Common::getRequestVar('id', array(), 'array');
				$action = Common::getRequestVar('action', '', 'string');

				if (empty($ids) && $action == 'counter_exists') {
					return true;
				}

				if (!empty($ids) && ($action != 'save' || $action != 'apply' || $action != 'remove' || $action != 'publish' || $action != 'unpublish')) {
					$login = $access->getLogin();

					$result = Db::fetchAll("SELECT idsite"
						. "\n FROM ".Common::prefixTable('access')
						. "\n WHERE idsite IN (SELECT idsite FROM ".Common::prefixTable('counter_sites')." WHERE id IN (".implode(',', $ids).")) AND access = 'admin' AND login = '".$login."'");

					if (count($result) > 0) {
						return true;
					} else {
						throw new Exception(Piwik::Translate('General_ExceptionPrivilegeAtLeastOneWebsite', array('admin')));
					}
				} else {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Clear cache for custom counter
	 *
	 * @param    array  $params   An array with the counter data.
	 *
	 * @return   mixed
	 */
	private function createImage($params=array()) {
		$id = Common::getRequestVar('id', 0, 'int');
		$params = count($params) > 0 ? $params : $this->getItem($id);
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

		$request = new Request('method=VisitsSummary.get&idSite='.(int)$params['idsite'].'&period=range&date='.$date.','.date('Y-m-d').'&format=php&serialize=0&token_auth='.$params['params']['token']);
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
				$visits = $c_visits + $value['nb_visits'];
				$views = $c_views + $value['nb_actions'];
			}
		}

		// Add or subtract initial values
		if ($params['visits'] != 0) {
			if ($params['visits'] < 0 && $visits > abs($params['visits'])) {
				$visits = $visits - abs($params['visits']);
			} elseif ($params['visits'] > 0) {
				// Number of visits cannot be more than number of views, so we not change the number of visits
				if (($visits + abs($params['visits'])) < $views) {
					$visits = $visits + abs($params['visits']);
				}
			}
		}

		if ($params['views'] != 0) {
			if ($params['views'] < 0 && $views > abs($params['views'])) {
				$views = $views - abs($params['views']);
			} elseif ($params['views'] > 0) {
				$views = $views + abs($params['views']);
			}
		}

		// Format numbers to human readable
		if (array_key_exists('format_numbers', $params['params']) && $params['params']['format_numbers'] == 1) {
			$data = array($this->formatNumber($visits), $this->formatNumber($views));
		} else {
			$data = array($visits, $views);
		}

		if (!empty($params['params']['img_path']) && file_exists($params['params']['img_path'])) {
			$mime = $this->getMime($params['params']['img_path']);
			$dst_im = imagecreatetruecolor($params['params']['img_size_x'], $params['params']['img_size_y']);

			if ($mime == 'image/png') {
				$src_im = imagecreatefrompng($params['params']['img_path']);
				imagealphablending($src_im, true);
				imagesavealpha($src_im, true);
			} elseif ($mime == 'image/gif') {
				$src_im = imagecreatefromgif($params['params']['img_path']);
			} elseif ($mime == 'image/jpeg') {
				$src_im = imagecreatefromjpeg($params['params']['img_path']);
			} else { // Unsupported image type
				// Output an 1x1 transparent gif
				$im = imagecreatetruecolor(1, 1);
				imagecolortransparent($im, imagecolorallocate($im, 0, 0, 0));

				header('Content-type: image/gif');

				imagegif($im);
				imagedestroy($im);

				exit();
			}

			list($w, $h) = getimagesize($params['params']['img_path']);
			if (!empty($params['params']['font_path']) && file_exists($params['params']['font_path'])) {
				// Draw sitename
				if (!empty($params['title']) && $params['params']['show_sitename'] == 1) {
					$rgb_arr_sitename = $this->rgb2array($params['params']['color_sitename']);
					$color_sitename = imagecolorallocate($src_im, $rgb_arr_sitename['r'], $rgb_arr_sitename['g'], $rgb_arr_sitename['b']);
					imagettftext($src_im, $params['params']['sitename_font_size'], 0, $params['params']['sitename_pos_x'], $params['params']['sitename_pos_y'], $color_sitename, $params['params']['font_path'], $params['title']);
				}

				// Draw visits
				if ($params['params']['show_visits'] == 1) {
					$rgb_arr_visits = $this->rgb2array($params['params']['color_visits']);
					$color_visits = imagecolorallocate($src_im, $rgb_arr_visits['r'], $rgb_arr_visits['g'], $rgb_arr_visits['b']);
					imagettftext($src_im, $params['params']['visits_font_size'], 0, $params['params']['visitors_pos_x'], $params['params']['visitors_pos_y'], $color_visits, $params['params']['font_path'], $data[0]);
				}

				// Draw views
				if ($params['params']['show_views'] == 1) {
					$rgb_arr_views = $this->rgb2array($params['params']['color_views']);
					$color_views = imagecolorallocate($src_im, $rgb_arr_views['r'], $rgb_arr_views['g'], $rgb_arr_views['b']);
					imagettftext($src_im, $params['params']['hits_font_size'], 0, $params['params']['views_pos_x'], $params['params']['views_pos_y'], $color_views, $params['params']['font_path'], $data[1]);
				}
			} else {
				// Draw sitename
				if (!empty($params['title']) && $params['params']['show_sitename'] == 1) {
					$rgb_arr_sitename = $this->rgb2array($params['params']['color_sitename']);
					// Text must be only in Latin2!
					imagestring($src_im, $params['params']['sitename_font_size']-5, $params['params']['sitename_pos_x'], $params['params']['sitename_pos_y']-10, $params['title'], imagecolorallocate($src_im, $rgb_arr_sitename['r'], $rgb_arr_sitename['g'], $rgb_arr_sitename['b']));
				}

				// Draw visits
				if ($params['params']['show_visits'] == 1) {
					$rgb_arr_visits = $this->rgb2array($params['params']['color_visits']);
					imagestring($src_im, $params['params']['visits_font_size']-5, $params['params']['visitors_pos_x'], $params['params']['visitors_pos_y']-10, $data[0], imagecolorallocate($src_im, $rgb_arr_visits['r'], $rgb_arr_visits['g'], $rgb_arr_visits['b']));
				}

				// Draw views
				if ($params['params']['show_views'] == 1) {
					$rgb_arr_views = $this->rgb2array($params['params']['color_views']);
					imagestring($src_im, $params['params']['hits_font_size']-5, $params['params']['views_pos_x']+5, $params['params']['views_pos_y']-10, $data[1], imagecolorallocate($src_im, $rgb_arr_views['r'], $rgb_arr_views['g'], $rgb_arr_views['b']));
				}
			}

			imagecopyresampled($dst_im, $src_im, 0, 0, 0, 0, $params['params']['img_size_x'], $params['params']['img_size_y'], $w, $h);

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

		return false;
	}

	/**
	 * Show image from cache or create if it not exists
	 *
	 * @return   mixed
	 */
	public function showImage() {
		$id = Common::getRequestVar('id', 0, 'int');
		$params = $this->getItem($id);
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
					'cache_dir' => $this->getModel()->cleanPath(PIWIK_DOCUMENT_ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)
				)
			);

			// Test if cache is available
			if ($cache->test($cache_id)) {
				$mime = $this->getMime($params['params']['img_path']);
				header('Content-type: '.$mime);

				// Load and output image from cache
				echo $cache->load($cache_id, true);
			} else {
				// Build new image and save into cache
				if (!$cache->start($cache_id)) {
					$this->createImage();
					$cache->end();
				}
			}
		} else {
			$this->createImage();
		}
	}

	/**
	 * Create image for preview while editing counter data. For backend only. This method do not use cache.
	 *
	 * @return   mixed
	 */
	public function previewImage() {
		$this->checkAccess();
		@set_time_limit(0);
		$this->createImage($this->getModel()->getForm());
	}

	/**
	 * Method to get live visitors count
	 *
	 * @return	string
	 */
	public function getLiveVisitorsCount() {
		$type = Common::getRequestVar('type', '', 'string');
		$id = Common::getRequestVar('id', 0, 'int');
		$params = $this->getItem();

		if ($params['published'] != 1) exit();

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
			$nbv_m = '';
			$nbc_m = '';
			$nba_m = '';
			$format = '';

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
					$date_range = $params['params']['start_date'].','.date('Y-m-d'); // default date range
				}

				// Process offsets
				// Visits
				if (preg_match('#\[nb_visits(.*?)\]#', $params['params']['tpl_by_countries'], $nbv_matches)) {
					preg_match('#\[nb_visits(\soffset="(?P<offset>.+?)")\]#i', $params['params']['tpl_by_countries'], $nbv_m);
				}

				// Countries
				if (preg_match('#\[nb_countries(.*?)\]#', $params['params']['tpl_by_countries'], $nbc_matches)) {
					preg_match('#\[nb_countries(\soffset="(?P<offset>.+?)")\]#i', $params['params']['tpl_by_countries'], $nbc_m);
				}

				if (preg_match('#\[nb_actions(.*?)\]#', $params['params']['tpl_by_countries'], $nbc_matches)) {
					preg_match('#\[nb_actions(\soffset="(?P<offset>.+?)")\]#i', $params['params']['tpl_by_countries'], $nba_m);
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

				if (in_array(preg_replace($protocol, $r, $origin), $params['origins'])) {
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
					'nb_visits'    => '#\[nb_visits(.*?)\]#',
					'nb_countries' => '#\[nb_countries(.*?)\]#',
					'nb_actions'   => '#\[nb_actions(.*?)\]#',
					'date'         => '#\[date(.*?)\]#'
				);
				$data = array(
					'nb_visits'    => $nb_visits,
					'nb_actions'   => $nb_actions,
					'nb_countries' => $nb_countries,
					'date'         => $format
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
	 * @param    array  $ids   A list of the primary keys.
	 *
	 * @return   boolean  True on success.
	 */
	public function clearCache($ids) {
		if (empty($ids)) {
			return false;
		}

		if (Common::getRequestVar('action', '', 'string') !== 'remove') {
			$this->checkAccess();
		}

		$errors = array();
		$cache = Zend_Cache::factory('Output', 'File',
			array(),
			array(
				'cache_dir' => $this->getModel()->cleanPath(PIWIK_DOCUMENT_ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)
			)
		);

		foreach ($ids as $id) {
			if (!$cache->remove('counter_image_'.md5($id).'_'.$id)) {
				$errors[] = $id;
			}
		}

		if (!empty($errors)) {
			return false;
		}

		return true;
	}

	/**
	 * Get plugin information
	 *
	 * @return	array
	 */
	public function getPluginInfo() {
		$plugins = Manager::getInstance()->loadAllPluginsAndGetTheirInfo();

		return $plugins['Counter'];
	}

	/**
	 * Method to get proper MIME type
	 *
	 * @param   string  $path    Absolute path to file
	 *
	 * @return	string
	 */
	private function getMime($path) {
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

	private function formatNumber($number) {
		if ($number > 1000000000000) {
			$number = round(($number / 1000000000000), 1).'T';
		} else if ($number > 1000000000) {
			$number = round(($number / 1000000000), 1).'B';
		} else if ($number > 1000000) {
			$number = round(($number / 1000000), 1).'M';
		} else if ($number > 1000) {
			$number = round(($number / 1000), 1).'K';
		} else {
			$number = number_format($number);
		}

		return $number;
	}

	private function rgb2array($rgb) {
		$rgb = str_replace('#', '', $rgb);

		return array(
			'r' => base_convert(substr($rgb, 0, 2), 16, 10),
			'g' => base_convert(substr($rgb, 2, 2), 16, 10),
			'b' => base_convert(substr($rgb, 4, 2), 16, 10)
		);
	}

	private function getModel() {
		return new Model();
	}
}
