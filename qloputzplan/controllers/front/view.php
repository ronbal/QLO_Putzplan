<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'qloputzplan/classes/PutzplanBuilder.php';

/**
 * Ansicht ohne Login, mobil lesbar:
 * - persoenlicher Link einer Putzkraft (key=...): Abhaken, Gaeste nur mit Vornamen, Voreinstellung = ihre Standorte
 * - alter Teilen-Link (token=...): nur lesen, solange in der Konfiguration aktiv
 * URL: index.php?fc=module&module=qloputzplan&controller=view&key=...
 */
class QloPutzplanViewModuleFrontController extends ModuleFrontController
{
    private $staff = null;
    /** Zugangsparameter fuer alle Links der Seite (key=... oder token=...). Nicht $auth: das ist im FrontController die Kundenanmeldung. */
    private $linkParams = array();

    public function initContent()
    {
        $this->staff = PutzplanTasks::getStaffByToken(Tools::getValue('key'));
        if ($this->staff) {
            $this->linkParams = array('key' => $this->staff['token']);
        } else {
            $token = (string) Configuration::get('QLOPUTZPLAN_TOKEN');
            if (!Configuration::get('QLOPUTZPLAN_SHARE_ENABLED') || $token === '' || !hash_equals($token, (string) Tools::getValue('token'))) {
                header('HTTP/1.1 403 Forbidden');
                die('Kein Zugriff.');
            }
            $this->linkParams = array('token' => $token);
        }

        parent::initContent();

        $monday = PutzplanBuilder::mondayOf(Tools::getValue('week'));
        $hotels = PutzplanBuilder::getHotels();
        $mine = $this->staff ? array_values(array_intersect($this->staff['hotel_ids'], array_keys($hotels))) : array();

        // Auswahl: "mine" (eigene Standorte), "all" oder eine Hotel-ID; ohne Angabe die eigenen Standorte.
        $sel = (string) Tools::getValue('hotel');
        if ($sel === '') {
            $sel = $mine ? 'mine' : 'all';
        }
        if ($sel === 'mine' && !$mine) {
            $sel = 'all';
        }
        if ($sel !== 'mine' && $sel !== 'all' && !isset($hotels[(int) $sel])) {
            $sel = 'all';
        }
        $filter = $sel === 'mine' ? $mine : ($sel === 'all' ? 0 : (int) $sel);

        $link = $this->context->link;
        $url = function (array $params) use ($link) {
            return $link->getModuleLink('qloputzplan', 'view', $params);
        };
        $base = $this->linkParams + array('hotel' => $sel);

        if ($this->staff && Tools::isSubmit('qpp_action')) {
            PutzplanTasks::handlePost($this->staff['name'], (int) $this->staff['id_staff']);
            Tools::redirect($url($base + array('week' => $monday)).'#r'.(int) Tools::getValue('qpp_room'));
        }

        $options = array();
        if ($mine) {
            $options['mine'] = 'Meine Standorte';
        }
        $options['all'] = 'Alle Hotels';
        foreach ($hotels as $hid => $hname) {
            $options[(string) $hid] = $hname;
        }
        $hotelUrls = array();
        foreach ($options as $value => $name) {
            $hotelUrls[] = array(
                'url' => $url($this->linkParams + array('hotel' => $value, 'week' => $monday)),
                'name' => $name,
                'selected' => (string) $value === $sel,
            );
        }

        PutzplanBuilder::$firstNameOnly = (bool) $this->staff;
        $this->context->smarty->assign(array(
            'days' => PutzplanBuilder::buildWeek($monday, $filter),
            'hotel_urls' => $hotelUrls,
            'hotel_title' => $sel === 'all' ? '' : $options[$sel],
            'week' => $monday,
            'week_title' => PutzplanBuilder::formatShort($monday).' - '.PutzplanBuilder::formatShort(date('Y-m-d', strtotime($monday.' +6 days'))),
            'prev_url' => $url($base + array('week' => date('Y-m-d', strtotime($monday.' -7 days')))),
            'next_url' => $url($base + array('week' => date('Y-m-d', strtotime($monday.' +7 days')))),
            'today_url' => $url($base),
            'staff_name' => $this->staff ? $this->staff['name'] : '',
            'can_tick' => (bool) $this->staff,
            'tick_url' => $url($base + array('week' => $monday)),
            'css_url' => _MODULE_DIR_.'qloputzplan/views/css/plan.css',
            'plan_tpl' => _PS_MODULE_DIR_.'qloputzplan/views/templates/_plan.tpl',
        ));
    }

    /** Nackte Seite ohne Shop-Header/Footer, damit Druck/Foto sauber aussieht. */
    public function display()
    {
        try {
            $this->context->smarty->display(_PS_MODULE_DIR_.'qloputzplan/views/templates/front/view.tpl');
        } catch (Exception $e) {
            die('Putzplan-Fehler: '.Tools::safeOutput($e->getMessage()).' ('.basename($e->getFile()).':'.$e->getLine().')');
        } catch (Throwable $e) {
            die('Putzplan-Fehler: '.Tools::safeOutput($e->getMessage()).' ('.basename($e->getFile()).':'.$e->getLine().')');
        }
    }
}
