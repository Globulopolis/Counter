<?php
/**
 * @package    Piwik.Counter
 * @copyright  Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 3 or later
 * @url        http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url        http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Exception;
use Piwik\Access;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugins\SitesManager\API as SitesManager;

/**
 * Plugin model class.
 */
class Model
{
    private static $rawPrefix = 'counter_sites';

    private $table;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->table = Common::prefixTable(self::$rawPrefix);
    }

    /**
     * Get the vars from edit form and filter them.
     *
     * @param   boolean  $encode  Encode 'params' array into JSON string.
     *
     * @return  array
     */
    public function getForm($encode = false)
    {
        // Manipulating with the date
        $start_date = Common::getRequestVar('start_date', '', 'string');
        $start_date_period = Common::getRequestVar('start_date_period', '', 'string');

        if (empty($start_date) && empty($start_date_period)) {
            $start_date_period = 'none';
        }

        $data = array(
            'id'        => Common::getRequestVar('id', 0, 'int'),
            'idsite'    => Common::getRequestVar('siteid', 0, 'int'),
            'title'     => Common::getRequestVar('title', 'Default site', 'string'),
            'params'    => array(
                'show_sitename'            => Common::getRequestVar('show_sitename', 1, 'int'),
                'show_visits'              => Common::getRequestVar('show_visits', 1, 'int'),
                'show_views'               => Common::getRequestVar('show_views', 1, 'int'),
                'sitename_pos_x'           => Common::getRequestVar('sitename_pos_x', 3, 'int'),
                'sitename_pos_y'           => Common::getRequestVar('sitename_pos_y', 13, 'int'),
                'visitors_pos_x'           => Common::getRequestVar('visitors_pos_x', 3, 'int'),
                'visitors_pos_y'           => Common::getRequestVar('visitors_pos_y', 26, 'int'),
                'views_pos_x'              => Common::getRequestVar('views_pos_x', 30, 'int'),
                'views_pos_y'              => Common::getRequestVar('views_pos_y', 26, 'int'),
                'img_size_x'               => Common::getRequestVar('img_size_x', 80, 'int'),
                'img_size_y'               => Common::getRequestVar('img_size_y', 31, 'int'),
                'sitename_font_size'       => Common::getRequestVar('sitename_font_size', 7, 'int'),
                'visits_font_size'         => Common::getRequestVar('visits_font_size', 7, 'int'),
                'hits_font_size'           => Common::getRequestVar('hits_font_size', 7, 'int'),
                'img_path'                 => $this->cleanPath(Common::getRequestVar('img_path', '', 'string')),
                'font_path'                => $this->cleanPath(Common::getRequestVar('font_path', '', 'string')),
                'cache'                    => Common::getRequestVar('cache', 1, 'int'),
                'cache_time'               => Common::getRequestVar('cache_time', 900, 'int'),
                'start_date'               => $start_date,
                'start_date_period'        => $start_date_period,
                'token'                    => Common::getRequestVar('token', 'anonymous', 'string'),
                'color_sitename'           => Common::getRequestVar('color_sitename', '#03374A', 'string'),
                'color_visits'             => Common::getRequestVar('color_visits', '#F16045', 'string'),
                'color_views'              => Common::getRequestVar('color_views', '#E07A52', 'string'),
                'last_minutes'             => Common::getRequestVar('last_minutes', 30, 'int'),
                'check_interval'           => Common::getRequestVar('check_interval', 10000, 'int'),
                'static'                   => Common::getRequestVar('static', 1, 'int'),
                'livestat_elem_id'         => Common::getRequestVar('livestat_elem_id', '', 'string'),
                'tpl_by_countries'         => Common::getRequestVar('tpl_by_countries', '', 'string'),
                'tpl_by_countries_elem_id' => Common::getRequestVar('tpl_by_countries_elem_id', '', 'string'),
                'format_numbers'           => Common::getRequestVar('format_numbers', 0, 'int')
            ),
            'visits'    => Common::getRequestVar('visits', 0, 'int'),
            'views'     => Common::getRequestVar('views', 0, 'int'),
            'published' => Common::getRequestVar('published', 0, 'int')
        );

        if ($encode) {
            $data['params'] = json_encode($data['params']);
        }

        return $data;
    }

    /**
     * Get the list of counters
     *
     * @param   boolean  $return_id  Return only primary keys of counters.
     *
     * @return  array
     */
    public function getItems($return_id = false)
    {
        $sites_ids = SitesManager::getInstance()->getSitesIdWithAdminAccess();
        $result = array();

        if (!empty($sites_ids)) {
            $rows = Db::fetchAll("SELECT id, idsite, title, params, published"
                . "\n FROM " . $this->table
                . "\n WHERE idsite IN (" . implode(',', $sites_ids) . ")"
                . "\n ORDER BY idsite ASC");

            if ($return_id) {
                foreach ($rows as $row) {
                    $result[] = $row['id'];
                }
            } else {
                foreach ($rows as $key => $row) {
                    $result[$key]['id']        = $row['id'];
                    $result[$key]['idsite']    = $row['idsite'];
                    $result[$key]['params']    = json_decode($row['params'], true);
                    $result[$key]['title']     = $row['title'];
                    $result[$key]['published'] = $row['published'];
                }
            }
        }

        return $result;
    }

    /**
     * Get data for single record.
     *
     * @param   integer  $id  Counter ID.
     *
     * @return  array
     *
     * @throws  Exception
     */
    public function getItem($id)
    {
        $result = array();

        if (!isset($id[0])) {
            throw new Exception(Piwik::translate('Counter_Error_has_occurred'));
        }

        $row = Db::fetchRow("SELECT id, idsite, title, params, visits, views, published FROM " . $this->table . " WHERE id = " . (int)$id[0]);

        $result['id']                         = $row['id'];
        $result['idsite']                     = $row['idsite'];
        $result['params']                     = json_decode($row['params'], true);
        $result['params']['tpl_by_countries'] = html_entity_decode($result['params']['tpl_by_countries'], ENT_QUOTES, 'UTF-8');
        $result['title']                      = $row['title'];
        $result['visits']                     = $row['visits'];
        $result['views']                      = $row['views'];
        $result['published']                  = $row['published'];

        // Get the proper token if it's not set by default
        if (empty($result['params']['token'])) {
            $result['params']['token'] = Access::getInstance()->getTokenAuth();
        }

        /* Cross-Origin Resource Sharing (CORS) */
        /* We fetch the URLs associated to this counter */
        $rows = Db::fetchAll("SELECT s.main_url, u.url"
            . "\n FROM " . Common::prefixTable('site') . " AS s"
            . "\n LEFT JOIN " . Common::prefixTable('site_url') . " AS u ON s.idsite = u.idsite"
            . "\n WHERE s.idsite = " . (int)$row['idsite']);

        $origins = array();
        $p = array('/http:\/\//', '/https:\/\//');
        $r = array('', '');

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $origins[] = $row['main_url'];
                $origins[] = $row['url'];
            }

            $origins = array_unique(preg_replace($p, $r, $origins));
        }

        $result['origins'] = $origins;
        $result['sites'] = $this->getSitesList();

        return $result;
    }

    /**
     * Get the list of all sites.
     *
     * @return  array
     */
    public function getSitesList()
    {
        $result = Db::fetchAll("SELECT idsite, name"
            . "\n FROM " . Common::prefixTable('site')
            . "\n ORDER BY idsite");

        return $result;
    }

    /**
     * Method to check if counter for that site is allready exists.
     *
     * @param   integer  $idsite  Site ID.
     *
     * @return  boolean  True if counter exists for $idsite, false otherwise.
     */
    public function counterExists($idsite)
    {
        $total = Db::fetchOne("SELECT COUNT(id) FROM " . $this->table . " WHERE idsite = " . (int) $idsite);

        if ($total > 0) {
            return true;
        }

        return false;
    }

    /**
     * Method to change the published state of one or more records.
     *
     * @param   array    $ids    IDs
     * @param   boolean  $state  Action state
     *
     * @return  boolean  True on success.
     *
     * @since   3.0
     */
    public function publish($ids, $state)
    {
        $ids = array_intersect($ids, $this->getItems(true));

        try {
            Db::query("UPDATE " . $this->table
                . "\n SET published = '" . (int)$state . "'"
                . "\n WHERE id IN (" . implode(',', $ids) . ") ");
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @since   3.0
     */
    public function save($data)
    {
        if (empty($data['idsite'])) {
            return false;
        }

        if (empty($data['id'])) {
            $exists = $this->counterExists($data['idsite']);

            if ($exists) {
                return false;
            }

            $bind = array(null, $data['idsite'], $data['title'], $data['params'], $data['visits'], $data['views'], $data['published']);
            $query = sprintf('INSERT INTO %s (id, idsite, title, params, visits, views, published) VALUES (?,?,?,?,?,?,?)', $this->table);
        } else {
            $bind = array($data['idsite'], $data['title'], $data['params'], $data['visits'], $data['views'], $data['published'], $data['id']);
            $query = sprintf('UPDATE %s SET idsite = ?, title = ?, params = ?, visits = ?, views = ?, published = ? WHERE id = ?', $this->table);
        }

        try {
            Db::query($query, $bind);

            if (empty($data['id'])) {
                return Db::get()->lastInsertId();
            } else {
                return $data['id'];
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function remove($ids)
    {
        $ids = array_intersect($ids, $this->getItems(true));

        try {
            Db::query("DELETE FROM " . $this->table
                . "\n WHERE id IN (" . implode(',', $ids) . ")");
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function cleanPath($path, $ds = DIRECTORY_SEPARATOR)
    {
        if (!is_string($path) && !empty($path)) {
            return '';
        }

        $path = trim($path);

        if (empty($path)) {
            $path = PIWIK_DOCUMENT_ROOT;
        } elseif (($ds == '\\') && ($path[0] == '\\') && ($path[1] == '\\')) {
            $path = "\\" . preg_replace('#[/\\\\]+#', $ds, $path);
        } else {
            $path = preg_replace('#[/\\\\]+#', $ds, $path);
        }

        return $path;
    }
}
