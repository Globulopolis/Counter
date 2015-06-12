<?php
/**
 * @package		Piwik.Counter
 * @copyright	Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license		GNU General Public License version 3 or later
 * @url			http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url			http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\SitesManager\API as SitesManager;

class Model {
	private static $rawPrefix = 'counter_sites';
	private $table;

	public function __construct() {
		$this->table = Common::prefixTable(self::$rawPrefix);
	}

	public function getItems() {
		$sites = SitesManager::getInstance()->getSitesIdWithAdminAccess();
		$result = array();

		$rows = Db::fetchAll("SELECT id, idsite, title, params, published"
			. "\n FROM ".$this->table
			. "\n WHERE idsite IN (".implode(',', $sites).")"
			. "\n ORDER BY idsite ASC");

		foreach ($rows as $key=>$row) {
			$result[$key]['id'] = $row['id'];
			$result[$key]['idsite'] = $row['idsite'];
			$result[$key]['params'] = json_decode($row['params'], true);
			$result[$key]['title'] = $row['title'];
			$result[$key]['published'] = $row['published'];
		}

		return $result;
	}

	public function getSitesList() {
		$result = Db::fetchAll("SELECT idsite, name"
			. "\n FROM ".Common::prefixTable('site')
			. "\n ORDER BY idsite");

		return $result;
	}

	public function publish($id, $state) {
		Db::query("UPDATE ".$this->table
			. "\n SET published = '".(int)$state."'"
			. "\n WHERE id IN (".implode(',', $id).") ");
	}

	public function remove($id) {
		Db::query("DELETE FROM ".$this->table
			. "\n WHERE id = ? ", array($id));
	}

	private function getDb() {
		return Db::get();
	}
}
