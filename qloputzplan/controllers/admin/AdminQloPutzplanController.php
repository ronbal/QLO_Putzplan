<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'qloputzplan/classes/PutzplanBuilder.php';

class AdminQloPutzplanController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        PutzplanBuilder::$debug = (bool) Tools::getValue('debug');
        try {
            $monday = PutzplanBuilder::mondayOf(Tools::getValue('week'));
            $idHotel = (int) Tools::getValue('hotel');
            $hotels = PutzplanBuilder::getHotels();
            if (!isset($hotels[$idHotel])) {
                $idHotel = 0;
            }
            $root = self::$currentIndex.'&token='.$this->token;
            $base = $root.($idHotel ? '&hotel='.$idHotel : '');
            if (Tools::isSubmit('qpp_code_save')) {
                $msg = self::saveCode((int) Tools::getValue('qpp_room'), (string) Tools::getValue('qpp_code'));
                Tools::redirectAdmin($base.'&week='.$monday.'&qpp_msg='.urlencode($msg).'#r'.(int) Tools::getValue('qpp_room'));
            }
            if (Tools::isSubmit('qpp_action')) {
                $emp = $this->context->employee;
                PutzplanTasks::handlePost(trim($emp->firstname.' '.$emp->lastname));
                Tools::redirectAdmin($base.'&week='.$monday.'#r'.(int) Tools::getValue('qpp_room'));
            }
            $hotelUrls = array(array('url' => $root.'&week='.$monday, 'name' => 'Alle Hotels', 'selected' => $idHotel === 0));
            foreach ($hotels as $hid => $hname) {
                $hotelUrls[] = array('url' => $root.'&hotel='.(int) $hid.'&week='.$monday, 'name' => $hname, 'selected' => $idHotel === (int) $hid);
            }
            $shareUrl = $this->context->link->getModuleLink(
                'qloputzplan',
                'view',
                array_merge(array('token' => Configuration::get('QLOPUTZPLAN_TOKEN'), 'week' => $monday), $idHotel ? array('hotel' => $idHotel) : array())
            );

            $this->context->smarty->assign(array(
                'days' => self::withAppData(PutzplanBuilder::buildWeek($monday, $idHotel)),
                'hotel_urls' => $hotelUrls,
                'hotel_title' => $idHotel ? $hotels[$idHotel] : '',
                'week' => $monday,
                'week_title' => PutzplanBuilder::formatShort($monday).' - '.PutzplanBuilder::formatShort(date('Y-m-d', strtotime($monday.' +6 days'))),
                'prev_url' => $base.'&week='.date('Y-m-d', strtotime($monday.' -7 days')),
                'next_url' => $base.'&week='.date('Y-m-d', strtotime($monday.' +7 days')),
                'today_url' => $base,
                'share_url' => Configuration::get('QLOPUTZPLAN_SHARE_ENABLED') ? $shareUrl : '',
                'staff_name' => '',
                'can_tick' => true,
                'can_set_code' => Module::isEnabled('qloapp'),
                'code_msg' => (string) Tools::getValue('qpp_msg'),
                'tick_url' => $base.'&week='.$monday,
                'css_url' => _MODULE_DIR_.'qloputzplan/views/css/plan.css',
                'plan_tpl' => _PS_MODULE_DIR_.'qloputzplan/views/templates/_plan.tpl',
            ));

            $tpl = $this->context->smarty->fetch(_PS_MODULE_DIR_.'qloputzplan/views/templates/admin/plan.tpl');
        } catch (Exception $e) {
            $tpl = self::errorBox($e);
        } catch (Throwable $e) {
            $tpl = self::errorBox($e);
        }
        $this->context->smarty->assign('content', $this->content.$tpl);
    }

    /**
     * Mit dem Modul QLO App: Code der Schluesselbox und offene Meldungen je Zimmer ergaenzen,
     * mit Link auf die Meldungen dieses Zimmers.
     */
    private static function withAppData(array $days)
    {
        $dir = _PS_MODULE_DIR_.'qloapp/classes/';
        if (!Module::isEnabled('qloapp') || !is_file($dir.'AppKeyCodes.php') || !is_file($dir.'AppReports.php')) {
            return $days;
        }
        require_once $dir.'AppKeyCodes.php';
        require_once $dir.'AppReports.php';
        $rooms = array();
        foreach ($days as $day) {
            foreach ($day['items'] as $item) {
                $rooms[] = $item['task_room'];
            }
        }
        $codes = AppKeyCodes::getForRooms($rooms);
        $reports = AppReports::getOpenForRooms($rooms);
        $reportsUrl = 'index.php?controller=AdminQloAppReports&token='.Tools::getAdminTokenLite('AdminQloAppReports').'&filter=active&obj=r';
        foreach ($days as &$day) {
            foreach ($day['items'] as &$item) {
                $r = (int) $item['task_room'];
                $item['app_code'] = isset($codes[$r]) ? $codes[$r]['code'] : '';
                $item['app_reports'] = isset($reports[$r]) ? $reports[$r] : array();
                $item['app_reports_url'] = $reportsUrl.$r;
            }
            unset($item);
        }
        unset($day);
        return $days;
    }

    /** Code der Schluesselbox aus dem Back Office setzen (Modul QLO App); Rueckgabe = Meldung. */
    private static function saveCode($idRoom, $code)
    {
        $file = _PS_MODULE_DIR_.'qloapp/classes/AppKeyCodes.php';
        if (!Module::isEnabled('qloapp') || !is_file($file)) {
            return 'Codes brauchen das Modul QLO App.';
        }
        require_once $file;
        $code = trim($code);
        if (!AppKeyCodes::roomExists($idRoom)) {
            return 'Zimmer unbekannt.';
        }
        if (!AppKeyCodes::isValidCode($code)) {
            return 'Code ungueltig: 1 bis 32 Zeichen, nur Ziffern, Buchstaben, Leer- und Bindestrich.';
        }
        $emp = Context::getContext()->employee;
        return AppKeyCodes::set($idRoom, $code, trim($emp->firstname.' '.$emp->lastname)) ? 'Code gespeichert.' : 'Speichern fehlgeschlagen.';
    }

    private static function errorBox($e)
    {
        return '<div class="alert alert-danger"><b>Putzplan-Fehler:</b> '.Tools::safeOutput($e->getMessage())
            .'<br><small>'.Tools::safeOutput(basename($e->getFile()).':'.$e->getLine()).'</small></div>';
    }
}
