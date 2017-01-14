<?php
/**
 * @package    Piwik.Counter
 * @copyright  Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 3 or later
 * @url        http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url        http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

/**
 * Menu provider.
 */
class Menu extends \Piwik\Plugin\Menu
{
    /**
     * Configures the admin menu which is supposed to contain only administration related items such as
     * "Websites", "Users" or "Settings".
     */
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::isUserHasSomeAdminAccess()) {
            $menu->addPlatformItem('Counter_Settings', $this->urlForAction('index'), 10);
        }
    }
}
