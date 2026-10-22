<?php
/**
 * QLO Putzplan - Anreise- und Vorbereitungsliste fuer QloApps.
 *
 * Zeigt pro Wochentag, welche Unterkunft nach der Abreise gereinigt werden muss
 * und was fuer die naechsten Gaeste vorzubereiten ist. Druck- und Teilen-Ansicht inklusive.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/classes/PutzplanBuilder.php';

class QloPutzplan extends Module
{
    public function __construct()
    {
        $this->name = 'qloputzplan';
        $this->tab = 'administration';
        $this->version = '0.2.3';
        $this->author = 'Ronbal';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Putzplan / Anreiseliste');
        $this->description = $this->l('Woechentliche Abreise- und Vorbereitungsliste fuer die Reinigung, mit Druck- und Teilen-Ansicht.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        Configuration::updateValue('QLOPUTZPLAN_TOKEN', Tools::passwdGen(32));
        Configuration::updateValue('QLOPUTZPLAN_GAP_DAYS', 2);
        Configuration::updateValue('QLOPUTZPLAN_KW_DOG', 'hund');
        Configuration::updateValue('QLOPUTZPLAN_KW_PARKING', 'parkplatz');
        Configuration::updateValue('QLOPUTZPLAN_KW_LINEN', 'wäsche,waesche');

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminQloPutzplan';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Putzplan';
        }

        return $tab->add();
    }

    public function uninstall()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminQloPutzplan');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
        Configuration::deleteByName('QLOPUTZPLAN_TOKEN');
        Configuration::deleteByName('QLOPUTZPLAN_GAP_DAYS');
        Configuration::deleteByName('QLOPUTZPLAN_KW_DOG');
        Configuration::deleteByName('QLOPUTZPLAN_KW_PARKING');
        Configuration::deleteByName('QLOPUTZPLAN_KW_LINEN');

        return parent::uninstall();
    }

    /** Konfigurationsseite: Lueckenschwelle + Teilen-Link. */
    public function getContent()
    {
        $out = '';
        if (Tools::isSubmit('submitQloPutzplan')) {
            Configuration::updateValue('QLOPUTZPLAN_GAP_DAYS', max(1, (int) Tools::getValue('QLOPUTZPLAN_GAP_DAYS')));
            foreach (array('DOG', 'PARKING', 'LINEN') as $k) {
                Configuration::updateValue('QLOPUTZPLAN_KW_'.$k, trim((string) Tools::getValue('QLOPUTZPLAN_KW_'.$k)));
            }
            if (Tools::getValue('regenerate_token')) {
                Configuration::updateValue('QLOPUTZPLAN_TOKEN', Tools::passwdGen(32));
            }
            $out .= $this->displayConfirmation($this->l('Gespeichert.'));
        }

        $share = $this->context->link->getModuleLink($this->name, 'view', array('token' => Configuration::get('QLOPUTZPLAN_TOKEN')));
        $out .= '<form method="post" class="defaultForm form-horizontal"><div class="panel">'
            .'<h3>Putzplan</h3>'
            .'<p><label>Luecke in Tagen, ab der "ggf. spaeter reinigen" angezeigt wird</label><br>'
            .'<input type="number" min="1" name="QLOPUTZPLAN_GAP_DAYS" value="'.(int) Configuration::get('QLOPUTZPLAN_GAP_DAYS').'"></p>'
            .'<p class="help-block">Stichwoerter (kommagetrennt), an denen Zusatzleistungen erkannt werden:</p>'
            .'<p><label>Hund</label><br><input type="text" name="QLOPUTZPLAN_KW_DOG" value="'.Tools::safeOutput(Configuration::get('QLOPUTZPLAN_KW_DOG')).'"></p>'
            .'<p><label>Parkplatz</label><br><input type="text" name="QLOPUTZPLAN_KW_PARKING" value="'.Tools::safeOutput(Configuration::get('QLOPUTZPLAN_KW_PARKING')).'"></p>'
            .'<p><label>Waeschepaket</label><br><input type="text" name="QLOPUTZPLAN_KW_LINEN" value="'.Tools::safeOutput(Configuration::get('QLOPUTZPLAN_KW_LINEN')).'"></p>'
            .'<p><label>Teilen-Link (nur lesen, ohne Login)</label><br><input type="text" readonly style="width:100%" value="'.Tools::safeOutput($share).'"></p>'
            .'<p><label><input type="checkbox" name="regenerate_token" value="1"> Neuen Link erzeugen (alter Link wird ungueltig)</label></p>'
            .'<button type="submit" name="submitQloPutzplan" class="btn btn-default">Speichern</button>'
            .'</div></form>';

        return $out;
    }
}
