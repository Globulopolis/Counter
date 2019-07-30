<?php
/**
 * @package    Piwik.Counter
 * @copyright  Copyright (C) 2010 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 3 or later
 * @url        http://xn--80aeqbhthr9b.com/en/others/piwik/10-piwik-graphical-counter.html
 * @url        http://киноархив.com/ru/разное/piwik/9-piwik-графический-счетчик.html
 */

namespace Piwik\Plugins\Counter;

use Piwik\Common;
use Piwik\DataTable\Renderer\Json;
use Piwik\Nonce;
use Piwik\Plugin;
use Piwik\Plugins\SitesManager\API as APISitesManager;
use Piwik\Translation\Translator;
use Piwik\View;
use Exception;

class Controller extends Plugin\Controller
{
    /**
     * Template name.
     *
     * @var    string
     */
    private $template = 'default';

    /**
     * Template name.
     *
     * @var    string
     */
    protected $api;

    /**
     * Translator class.
     *
     * @var   Translator
     */
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->api = API::getInstance();
        $this->translator = $translator;

        parent::__construct();
    }

    public function index()
    {
        $view = new View('@Counter/' . $this->template . '.twig');
        $this->setBasicVariablesView($view);
        $this->setGeneralVariablesView($view);
        $view->counters = $this->api->getItems();
        $view->data = (object) array(
            'plugin_info' => $this->api->getPluginInfo(),
            'formNonce'   => Nonce::getNonce('Counter.index'),
            'cacheNonce'  => Nonce::getNonce('Counter.cacheClear')
        );

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

    public function unpublish()
    {
        $this->publish(0);
    }

    /**
     * Method to change the published state of one or more records.
     *
     * @param   integer  $state  The value of the published state.
     *
     * @return  void
     * @throws  \Exception
     */
    public function publish($state = 1)
    {
        $nonce = Common::getRequestVar('nonce', null, 'string');

        if (!Nonce::verifyNonce('Counter.index', $nonce)) {
            $this->api->enqueueMessage($this->translator->translate('General_ExceptionNonceMismatch'), 'error');
            $this->redirectToIndex('Counter', 'index');

            return;
        }

        $this->api->checkAccess();

        $ids = Common::getRequestVar('id', array(), 'array');

        if (empty($ids)) {
            $this->api->enqueueMessage($this->translator->translate('Counter_List_make_selection'), 'error');
            $this->redirectToIndex('Counter', 'index');

            return;
        }

        $model = new Model();
        $result = $model->publish($ids, $state);

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

    /**
     * Remove counter(s) from DB and clear the image cache.
     *
     * @return   mixed|void
     * @throws   Exception
     */
    public function remove()
    {
        $nonce = Common::getRequestVar('nonce', null, 'string');

        if (!Nonce::verifyNonce('Counter.index', $nonce)) {
            $this->api->enqueueMessage($this->translator->translate('General_ExceptionNonceMismatch'), 'error');
            $this->redirectToIndex('Counter', 'index');

            return;
        }

        $this->api->checkAccess();

        $ids = Common::getRequestVar('id', array(), 'array');

        if (empty($ids)) {
            $this->api->enqueueMessage($this->translator->translate('Counter_List_make_selection'), 'error');
            $this->redirectToIndex('Counter', 'index');

            return;
        }

        $model = new Model();
        $this->api->clearCache($ids);
        $result = $model->remove($ids);

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

    public function clearCache()
    {
        $nonce = Common::getRequestVar('c_nonce', null, 'string');

        if (!Nonce::verifyNonce('Counter.cacheClear', $nonce)) {
            throw new \Exception($this->translator->translate('General_ExceptionNonceMismatch'));
        }

        $result = $this->api->clearCache(Common::getRequestVar('id', array(), 'array'));

        if (strtolower(Common::getRequestVar('format', '', 'string')) === 'json') {
            Json::sendHeaderJSON();
            echo json_encode(array('success' => $result));

            return;
        }

        if (!$result) {
            $this->api->enqueueMessage($this->translator->translate('Counter_Cache_clear_error'), 'error');
        } else {
            $this->api->enqueueMessage($this->translator->translate('Counter_Cache_cleared'), 'success', 'toast');
        }

        $this->redirectToIndex('Counter', 'index');
    }

    public function create()
    {
        return $this->edit('add');
    }

    public function edit($tpl = 'edit')
    {
        $this->api->checkAccess();

        $view = new View('@Counter/' . $this->template . '_' . $tpl . '.twig');
        $this->setBasicVariablesView($view);
        $this->setGeneralVariablesView($view);
        $view->data = (object) array(
            'plugin_info' => $this->api->getPluginInfo(),
            'item'        => $this->api->getItem(),
            'formNonce'   => Nonce::getNonce('Counter.formEdit'),
            'cacheNonce'  => Nonce::getNonce('Counter.cacheClear')
        );

        // Some request vars
        $viewableIdSites = APISitesManager::getInstance()->getSitesIdWithAtLeastViewAccess();
        $defaultIdSite = reset($viewableIdSites);
        $view->idSite = Common::getRequestVar('idSite', $defaultIdSite, 'int');
        $view->period = Common::getRequestVar('period', 'day', 'string');
        $view->date = Common::getRequestVar('date', 'yesterday', 'string');

        return $view->render();
    }

    public function save()
    {
        $this->apply('save');
    }

    public function apply($task = 'apply')
    {
        $nonce = Common::getRequestVar('nonce', null, 'string');

        if (!Nonce::verifyNonce('Counter.formEdit', $nonce)) {
            $this->api->enqueueMessage($this->translator->translate('General_ExceptionNonceMismatch'), 'error');
            $this->redirectToIndex('Counter', 'index');

            return;
        }

        $this->api->checkAccess();

        $model = new Model();
        $data = $model->getForm(true);
        $result = $model->save($data);

        if (!$result) {
            $this->api->enqueueMessage($this->translator->translate('Counter_Save_error'), 'error');
        } else {
            $this->api->enqueueMessage($this->translator->translate('Counter_Saved'), 'success', 'toast');
        }

        if ($task == 'apply') {
            $this->redirectToIndex('Counter', 'edit', null, null, null, array('id' => $result));
        } else {
            $this->redirectToIndex('Counter', 'index');
        }
    }

    public function preview()
    {
        $this->api->previewImage();
    }

    public function show()
    {
        $this->api->showImage();
    }

    public function live()
    {
        $this->api->getLiveVisitorsCount();
    }
}
