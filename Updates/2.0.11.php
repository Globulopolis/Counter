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
use Piwik\Updater;
use Piwik\Updates;

class Updates_2_0_11 extends Updates
{
	static function getSql()
	{
		return array(
			"ALTER TABLE `" . Common::prefixTable('counter_sites') . "` ADD COLUMN `visits` INT (11) DEFAULT 0 NOT NULL AFTER `params`" => 1060,
			"ALTER TABLE `" . Common::prefixTable('counter_sites') . "` ADD COLUMN `views` INT (11) DEFAULT 0 NOT NULL AFTER `visits`"  => 1060,
			"ALTER TABLE `" . Common::prefixTable('counter_sites') . "` ADD INDEX `idx_idsite` (`idsite`)"                              => 1060,
			"ALTER TABLE `" . Common::prefixTable('counter_sites') . "` ADD INDEX `idx_state` (`published`)"                            => 1060
		);
	}

	static function update()
	{
		Updater::updateDatabase(__FILE__, self::getSql());
	}
}
