<?php
/**
 * @package		Piwik.Counter.Controller
 * @copyright	Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license		GNU General Public License version 3 or later
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
use Piwik\Plugin;
use Piwik\Plugins\SitesManager\API as APISitesManager;
use Piwik\Translation\Translator;
use Piwik\View;

class Controller extends Plugin\Controller {
	/**
	 * Template name.
	 *
	 * @var    string
	 */
	private $template = 'default';

	/**
	 * Translator class.
	 *
	 * @var   Translator
	 */
	private $translator;

	public function __construct(Translator $translator) {
		$this->api = API::getInstance();
		$this->translator = $translator;

		parent::__construct();
	}

	public function index() {
		$view = new View('@Counter/'.$this->template.'.twig');
		$this->setBasicVariablesView($view);
		$this->setGeneralVariablesView($view);
		$view->topMenu = MenuTop::getInstance()->getMenu();
		$view->userMenu = MenuUser::getInstance()->getMenu();
		$view->adminMenu = MenuAdmin::getInstance()->getMenu();
		$view->counters = $this->api->getItems();
		$view->plugin_info = $this->api->getPluginInfo();

		$viewableIdSites = APISitesManager::getInstance()->getSitesIdWithAtLeastViewAccess();
		$defaultIdSite = reset($viewableIdSites);
		$view->idSite = Common::getRequestVar('idSite', $defaultIdSite, 'int');

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
		$this->publish(0);
	}

	public function publish($state=1) {
		$ids = Common::getRequestVar('id', array(), 'array');
		$result = $this->api->publish($ids, $state);

		if (!$result) {
			$this->api->enqueueMessage($this->translator->translate('Counter_Error_has_occurred'), 'error');
		} else {
			if ($state == 1) {
				if (count($ids) > 1) {
					$message = $this->translator->translate('Counter_Published_success_1');
				} else {
					$message = $this->translator->translate('Counter_Published_success_0');
				}
			} else {
				if (count($ids) > 1) {
					$message = $this->translator->translate('Counter_Unpublished_success_1');
				} else {
					$message = $this->translator->translate('Counter_Unpublished_success_0');
				}
			}

			$this->api->enqueueMessage($message, 'success', 'toast');
		}

		$this->redirectToIndex('Counter', 'index');
	}

	public function remove() {
		$ids = Common::getRequestVar('id', array(), 'array');
		$result = $this->api->remove($ids);

		if (strtolower(Common::getRequestVar('format', '', 'string')) === 'json') {
			Json::sendHeaderJSON();
			echo json_encode(array('success' => $result));
		}

		if (!$result) {
			$this->api->enqueueMessage($this->translator->translate('Counter_Remove_error'), 'error');
		} else {
			if (count($ids) > 1) {
				$message = $this->translator->translate('Counter_Removed_1');
			} else {
				$message = $this->translator->translate('Counter_Removed_0');
			}

			$this->api->enqueueMessage($message, 'success', 'toast');
		}

		$this->redirectToIndex('Counter', 'index');
	}

	public function clearCache() {
		$result = $this->api->clearCache(Common::getRequestVar('id', array(), 'array'));

		if (strtolower(Common::getRequestVar('format', '', 'string')) === 'json') {
			Json::sendHeaderJSON();
			echo json_encode(array('success' => $result));
		}

		if (!$result) {
			$this->api->enqueueMessage($this->translator->translate('Counter_Cache_clear_error'), 'error');
		} else {
			$this->api->enqueueMessage($this->translator->translate('Counter_Cache_cleared'), 'success', 'toast');
		}

		$this->redirectToIndex('Counter', 'index');
	}

	public function counterExists() {
		Json::sendHeaderJSON();
		echo $this->api->counterExists(Common::getRequestVar('idsite', 0, 'int'));
	}

	public function add() {
		$this->api->checkAccess();

		$view = new View('@Counter/'.$this->template.'_add.twig');
		$this->setBasicVariablesView($view);
		$this->setGeneralVariablesView($view);
		$view->topMenu = MenuTop::getInstance()->getMenu();
		$view->userMenu = MenuUser::getInstance()->getMenu();
		$view->adminMenu = MenuAdmin::getInstance()->getMenu();
		$view->token = Access::getInstance()->getTokenAuth();
		$view->plugin_info = $this->api->getPluginInfo();

		$viewableIdSites = APISitesManager::getInstance()->getSitesIdWithAtLeastViewAccess();
		$defaultIdSite = reset($viewableIdSites);
		$view->idSite = Common::getRequestVar('idSite', $defaultIdSite, 'int');

		$view->period = Common::getRequestVar('period', 'day', 'string');
		$view->date = Common::getRequestVar('date', 'yesterday', 'string');
		$view->list_sites = $this->api->getSitesList();

		return $view->render();
	}

	public function edit() {
		$this->api->checkAccess();

		$view = new View('@Counter/'.$this->template.'_edit.twig');
		$this->setBasicVariablesView($view);
		$this->setGeneralVariablesView($view);
		$view->topMenu = MenuTop::getInstance()->getMenu();
		$view->userMenu = MenuUser::getInstance()->getMenu();
		$view->adminMenu = MenuAdmin::getInstance()->getMenu();
		$view->plugin_info = $this->api->getPluginInfo();

		$viewableIdSites = APISitesManager::getInstance()->getSitesIdWithAtLeastViewAccess();
		$defaultIdSite = reset($viewableIdSites);
		$view->idSite = Common::getRequestVar('idSite', $defaultIdSite, 'int');

		$view->period = Common::getRequestVar('period', 'day', 'string');
		$view->date = Common::getRequestVar('date', 'yesterday', 'string');
		$view->list_sites = $this->api->getSitesList();
		$view->data = $this->api->getItem();

		return $view->render();
	}

	public function save() {
		$this->apply('save');
	}

	public function apply($task='apply') {
		$result = $this->api->save();

		if (!$result) {
			$this->api->enqueueMessage($this->translator->translate('Counter_Save_error'), 'error');
		} else {
			$this->api->enqueueMessage($this->translator->translate('Counter_Saved'), 'success', 'toast');
		}

		if ($task == 'apply') {
			$this->redirectToIndex('Counter', 'edit', null, null, null, array('id[]' => $result));
		} else {
			$this->redirectToIndex('Counter', 'index');
		}
	}

	/**
	 * Method to check if file exists
	 *
	 * @return  string
	 */
	public function checkpath() {
		$this->api->checkAccess();

		clearstatcache();
		$path = trim(Common::getRequestVar('path', '', 'string'));
		$success = file_exists($path) ? 1 : 0;

		Json::sendHeaderJSON();
		echo json_encode(array('success' => $success));
	}

	public function preview() {
		$this->api->previewImage();
	}

	public function show() {
		$this->api->showImage();
	}

	public function live() {
		$this->api->getLiveVisitorsCount();
	}
}
