<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'qloputzplan/classes/PutzplanBuilder.php';

/**
 * Nur-Lesen-Ansicht per geheimem Token (zum Teilen als Link, Foto oder Ausdruck).
 * URL: index.php?fc=module&module=qloputzplan&controller=view&token=...
 */
class QloPutzplanViewModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $token = (string) Configuration::get('QLOPUTZPLAN_TOKEN');
        if ($token === '' || !hash_equals($token, (string) Tools::getValue('token'))) {
            header('HTTP/1.1 403 Forbidden');
            die('Kein Zugriff.');
        }

        parent::initContent();

        $monday = PutzplanBuilder::mondayOf(Tools::getValue('week'));
        $hotels = PutzplanBuilder::getHotels();
        $idHotel = (int) Tools::getValue('hotel');
        if (!isset($hotels[$idHotel])) {
            $idHotel = 0;
        }
        $base = array('token' => $token) + ($idHotel ? array('hotel' => $idHotel) : array());
        $hotelUrls = array(array(
            'url' => $this->context->link->getModuleLink('qloputzplan', 'view', array('token' => $token, 'week' => $monday)),
            'name' => 'Alle Hotels',
            'selected' => $idHotel === 0,
        ));
        foreach ($hotels as $hid => $hname) {
            $hotelUrls[] = array(
                'url' => $this->context->link->getModuleLink('qloputzplan', 'view', array('token' => $token, 'hotel' => (int) $hid, 'week' => $monday)),
                'name' => $hname,
                'selected' => $idHotel === (int) $hid,
            );
        }
        $link = $this->context->link;

        $this->context->smarty->assign(array(
            'days' => PutzplanBuilder::buildWeek($monday, $idHotel),
            'hotel_urls' => $hotelUrls,
            'hotel_title' => $idHotel ? $hotels[$idHotel] : '',
            'week' => $monday,
            'week_title' => PutzplanBuilder::formatShort($monday).' - '.PutzplanBuilder::formatShort(date('Y-m-d', strtotime($monday.' +6 days'))),
            'prev_url' => $link->getModuleLink('qloputzplan', 'view', $base + array('week' => date('Y-m-d', strtotime($monday.' -7 days')))),
            'next_url' => $link->getModuleLink('qloputzplan', 'view', $base + array('week' => date('Y-m-d', strtotime($monday.' +7 days')))),
            'today_url' => $link->getModuleLink('qloputzplan', 'view', $base),
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
