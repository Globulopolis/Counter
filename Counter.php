<?php
/**
 * @package		Piwik.Counter
 * @copyright	Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license		GNU General Public License version 2 or later
 * @url			http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url			http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Menu\MenuAdmin;
use Piwik\Menu\MenuTop;
use Piwik\Piwik;

class Counter extends \Piwik\Plugin {
	public function getListHooksRegistered() {
		return array(
			'Menu.Admin.addItems' => 'addMenu'
		);
	}
	
	public function addMenu() {
		MenuAdmin::getInstance()->addEntry('Counter_Settings', array('module' => 'Counter', 'action' => 'index'), !Piwik::isUserIsAnonymous(), $order = 10);
	}

	// This function needed if table for Counter plugin doesn't exists. E.g. we install plugin via copying into plugins folder.
	public function activate() {
		$this->install();
	}

	public function install() {
		try {
			$query = "CREATE TABLE IF NOT EXISTS `".Common::prefixTable('counter_sites')."`(  "
				. " `id` INT(11) NOT NULL AUTO_INCREMENT,"
				. " `idsite` INT(11) NOT NULL,"
				. " `title` VARCHAR(64) NOT NULL DEFAULT '',"
				. " `params` TEXT NOT NULL,"
				. " `published` TINYINT(1) NOT NULL DEFAULT 0,"
				. " PRIMARY KEY (`id`)"
				. " ) ENGINE=MYISAM DEFAULT CHARSET=utf8";

			Db::exec($query);
		} catch (Exception $e) {
			if (!Db::get()->isErrNo($e, '1050')) {
				throw $e;
			}
		}
	}

	public function uninstall() {
		Db::dropTables(Common::prefixTable('counter_sites'));
	}
}
