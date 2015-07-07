<?php
/**
 * @package        Piwik.Counter
 * @copyright    Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license        GNU General Public License version 3 or later
 * @url            http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url            http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Piwik\Common;
use Piwik\Db;
use Piwik\Updates;

class Updates_2_0_13 extends Updates
{
	static function update()
	{
		$rows = Db::fetchAll("SELECT id, params FROM " . Common::prefixTable('counter_sites'));

		foreach ($rows as $row) {
			$params = json_decode($row['params'], true);
			$params['format_numbers'] = 0;

            $bind = array(json_encode($params), $row['id']);
            $query = sprintf('UPDATE %s SET params = ? WHERE id = ?', Common::prefixTable('counter_sites'));

            Db::query($query, $bind);
		}
	}
}
