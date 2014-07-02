<?php
/**
 * @package		Piwik.Counter.Controller
 * @copyright	Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license		GNU General Public License version 2 or later
 * @url			http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url			http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */
namespace Piwik\Plugins\Counter;

use Piwik\Access;
use Piwik\Common;
use Piwik\DataTable\Renderer\Json;
use Piwik\Menu\MenuAdmin;
use Piwik\Menu\MenuTop;
use Piwik\Menu\MenuUser;
use Piwik\Notification\Manager as NotificationManager;
use Piwik\Piwik;
use Piwik\View;
use Piwik\Plugin;

class Controller extends \Piwik\Plugin\Controller {
	public function __construct() {
		// Get API from Plugins\Counter\API
		$this->api = API::getInstance();
	}

	public function index() {
		$this->api->checkAccess(true);

		$view = new View('@Counter/default.twig');
		$this->setBasicVariablesView($view);
		$view->topMenu = MenuTop::getInstance()->getMenu();
		$view->userMenu = MenuUser::getInstance()->getMenu();
		$view->adminMenu = MenuAdmin::getInstance()->getMenu();

		$view->counters = $this->api->getCountersData();
		$view->notifications = '';
		$view->siteName = Piwik::translate('Counter_Settings');
		$view->plugin_info = $this->api->getPluginInfo();
		$view->idSite = Common::getRequestVar('isSite', 1, 'int');
		$view->period = Common::getRequestVar('period', 'day', 'string');
		$view->date = Common::getRequestVar('date', 'yesterday', 'string');
		$view->server_vars = array(
			'protocol' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://',
			'server_name' => Common::sanitizeInputValue($_SERVER['SERVER_NAME']),
			'php_self' => Common::sanitizeInputValue($_SERVER['PHP_SELF'])
		);

		return $view->render();
	}

	public function unpublish() {
		return $this->publish(true);
	}

	public function publish($state=false) {
		$this->api->checkAccess();

		$result = $this->api->publish(Common::getRequestVar('id', 0, 'int'), $state);
		$this->redirectToIndex('Counter', 'index');
	}

	public function add() {
		$this->api->checkAccess(true);

		$view = new View('@Counter/default_add.twig');
		$this->setBasicVariablesView($view);
		$view->topMenu = MenuTop::getInstance()->getMenu();
		$view->userMenu = MenuUser::getInstance()->getMenu();
		$view->adminMenu = MenuAdmin::getInstance()->getMenu();

		$view->notifications = '';
		$view->token = Access::getInstance()->getTokenAuth();
		$view->plugin_info = $this->api->getPluginInfo();
		$view->idSite = Common::getRequestVar('isSite', 1, 'int');
		$view->period = Common::getRequestVar('period', 'day', 'string');
		$view->date = Common::getRequestVar('date', 'yesterday', 'string');
		$view->list_sites = $this->api->getSitesList();

		return $view->render();
	}

	public function edit() {
		$this->api->checkAccess();

		$view = new View('@Counter/default_edit.twig');
		$this->setBasicVariablesView($view);
		$view->topMenu = MenuTop::getInstance()->getMenu();
		$view->userMenu = MenuUser::getInstance()->getMenu();
		$view->adminMenu = MenuAdmin::getInstance()->getMenu();

		$view->notifications = '';
		$view->plugin_info = $this->api->getPluginInfo();
		$view->idSite = Common::getRequestVar('isSite', 1, 'int');
		$view->period = Common::getRequestVar('period', 'day', 'string');
		$view->date = Common::getRequestVar('date', 'yesterday', 'string');
		$view->list_sites = $this->api->getSitesList();
		$view->data = $this->api->getCountersData(Common::getRequestVar('id', 0, 'int'));

		return $view->render();
	}

	public function save() {
		$this->apply('save');
	}

	public function apply($task='apply') {
		$this->api->checkAccess();

		$result = $this->api->save();

		if ($task == 'apply') {
			$this->redirectToIndex('Counter', 'edit', null, null, null, array('id'=>$result));
		} else {
			$this->redirectToIndex('Counter', 'index');
		}
	}

	public function remove() {
		$this->api->checkAccess();

		$result = $this->api->remove(Common::getRequestVar('id', 0, 'int'));

		Json::sendHeaderJSON();
		echo $result;
	}

	public function siteidPrecheck() {
		$this->api->checkAccess();

		Json::sendHeaderJSON();
		echo $this->api->siteidPrecheck(Common::getRequestVar('idsite', 0, 'int'));
	}

	public function checkpath() {
		$this->api->checkAccess();

		clearstatcache();
		$path = trim(Common::getRequestVar('path', '', 'string'));
		$success = file_exists($path) ? 1 : 0;

		Json::sendHeaderJSON();
		echo json_encode(array('success'=>$success));
	}

	public function preview() {
		$this->api->checkAccess();
		$this->api->previewImage();
	}

	public function clearCache() {
		$this->api->checkAccess();

		Json::sendHeaderJSON();
		echo $this->api->clearCache();
	}

	public function show() {
		$this->api->showImage();
	}

	public function live() {
		$this->api->getLiveVisitorsCount();
	}
}
