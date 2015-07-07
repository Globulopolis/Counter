<?php
/**
 * @package        Piwik.Counter
 * @copyright    Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license        GNU General Public License version 3 or later
 * @url            http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url            http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin;

class Counter extends Plugin
{
	public function getListHooksRegistered()
	{
		return array(
			'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
			'AssetManager.getJavaScriptFiles' => 'getJsFiles',
		);
	}

	public function getStylesheetFiles(&$stylesheets)
	{
		$stylesheets[] = 'plugins/Counter/assets/css/style.css';
	}

	public function getJsFiles(&$jsFiles)
	{
		$jsFiles[] = 'plugins/Counter/assets/js/ui.aurora.min.js';
	}

	// This function needed if table for Counter plugin doesn't exists. E.g. we install plugin via copying into plugins folder.
	public function activate()
	{
		$this->install();
		//$this->update();
	}

	public function update()
	{
		/*try {
			$query = "ALTER TABLE `" . Common::prefixTable('counter_sites') . "`"
				. " ADD COLUMN `visits` INT (11) DEFAULT 0 NOT NULL AFTER `params`,"
				. " ADD COLUMN `views` INT (11) DEFAULT 0 NOT NULL AFTER `visits`,"
				. " ADD INDEX `idx_idsite` (`idsite`),"
				. " ADD INDEX `idx_state` (`published`)";

			Db::exec($query);

		} catch (Exception $e) {
			if (!Db::get()->isErrNo($e, '1050')) {
				throw $e;
			}
		}*/
		include_once './Updates/2.0.13.php';
		$u = new Updates_2_0_13();
		$u::update();
	}

	public function install()
	{
		try {
			$query = "CREATE TABLE IF NOT EXISTS " . Common::prefixTable('counter_sites') . "(  "
				. " id INT(11) NOT NULL AUTO_INCREMENT,"
				. " idsite INT(11) NOT NULL,"
				. " title VARCHAR(64) NOT NULL DEFAULT '',"
				. " params TEXT NOT NULL,"
				. " visits INT(11) NOT NULL DEFAULT '0',"
				. " views INT(11) NOT NULL DEFAULT '0',"
				. " published TINYINT(1) NOT NULL DEFAULT '0',"
				. " PRIMARY KEY (id),"
				. " KEY idx_idsite (idsite),"
				. " KEY idx_state (published)"
				. " ) ENGINE=MYISAM DEFAULT CHARSET=utf8";

			Db::exec($query);
		} catch (Exception $e) {
			if (!Db::get()->isErrNo($e, '1050')) {
				throw $e;
			}
		}
	}

	public function uninstall()
	{
		Db::dropTables(Common::prefixTable('counter_sites'));
	}
}
