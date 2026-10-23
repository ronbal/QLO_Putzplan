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
                'days' => PutzplanBuilder::buildWeek($monday, $idHotel),
                'hotel_urls' => $hotelUrls,
                'hotel_title' => $idHotel ? $hotels[$idHotel] : '',
                'week' => $monday,
                'week_title' => PutzplanBuilder::formatShort($monday).' - '.PutzplanBuilder::formatShort(date('Y-m-d', strtotime($monday.' +6 days'))),
                'prev_url' => $base.'&week='.date('Y-m-d', strtotime($monday.' -7 days')),
                'next_url' => $base.'&week='.date('Y-m-d', strtotime($monday.' +7 days')),
                'today_url' => $base,
                'share_url' => $shareUrl,
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

    private static function errorBox($e)
    {
        return '<div class="alert alert-danger"><b>Putzplan-Fehler:</b> '.Tools::safeOutput($e->getMessage())
            .'<br><small>'.Tools::safeOutput(basename($e->getFile()).':'.$e->getLine()).'</small></div>';
    }
}
