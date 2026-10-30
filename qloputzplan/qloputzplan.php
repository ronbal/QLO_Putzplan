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
        $this->version = '0.3.8';
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
        if (!parent::install() || !PutzplanTasks::installDb()) {
            return false;
        }

        Configuration::updateValue('QLOPUTZPLAN_TOKEN', Tools::passwdGen(32));
        Configuration::updateValue('QLOPUTZPLAN_GAP_DAYS', 2);
        Configuration::updateValue('QLOPUTZPLAN_KW_DOG', 'hund');
        Configuration::updateValue('QLOPUTZPLAN_KW_PARKING', 'parkplatz');
        Configuration::updateValue('QLOPUTZPLAN_KW_LINEN', 'wäsche,waesche');
        Configuration::updateValue('QLOPUTZPLAN_VACANCY_DAYS', 14);
        Configuration::updateValue('QLOPUTZPLAN_TASKS_SINCE', date('Y-m-d'));
        Configuration::updateValue('QLOPUTZPLAN_SHARE_ENABLED', 1);

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
        Configuration::deleteByName('QLOPUTZPLAN_VACANCY_DAYS');
        Configuration::deleteByName('QLOPUTZPLAN_TASKS_SINCE');
        Configuration::deleteByName('QLOPUTZPLAN_SHARE_ENABLED');
        // Tabellen mit Haken und Putzkraeften bleiben erhalten (Protokoll).

        return parent::uninstall();
    }

    /** Link fuer eine Putzkraft: die App (Modul qloapp), wenn installiert, sonst die Putzplan-Seite. */
    public static function staffLink($token)
    {
        if (Module::isEnabled('qloapp')) {
            return Tools::getShopDomainSsl(true).__PS_BASE_URI__.'modules/qloapp/app/index.html?k='.urlencode($token);
        }
        return Context::getContext()->link->getModuleLink('qloputzplan', 'view', array('key' => $token));
    }

    /** Konfigurationsseite: Einstellungen, Putzkraefte mit persoenlichem Link, alter Teilen-Link. */
    public function getContent()
    {
        $out = '';
        if (Tools::isSubmit('submitQloPutzplan')) {
            Configuration::updateValue('QLOPUTZPLAN_GAP_DAYS', max(1, (int) Tools::getValue('QLOPUTZPLAN_GAP_DAYS')));
            Configuration::updateValue('QLOPUTZPLAN_VACANCY_DAYS', max(1, (int) Tools::getValue('QLOPUTZPLAN_VACANCY_DAYS')));
            foreach (array('DOG', 'PARKING', 'LINEN') as $k) {
                Configuration::updateValue('QLOPUTZPLAN_KW_'.$k, trim((string) Tools::getValue('QLOPUTZPLAN_KW_'.$k)));
            }
            Configuration::updateValue('QLOPUTZPLAN_SHARE_ENABLED', Tools::getValue('QLOPUTZPLAN_SHARE_ENABLED') ? 1 : 0);
            if (Tools::getValue('regenerate_token')) {
                Configuration::updateValue('QLOPUTZPLAN_TOKEN', Tools::passwdGen(32));
            }

            $staffPost = Tools::getValue('staff');
            if (is_array($staffPost)) {
                foreach ($staffPost as $idStaff => $s) {
                    if (!empty($s['delete'])) {
                        PutzplanTasks::deleteStaff((int) $idStaff);
                    } elseif (trim((string) $s['name']) !== '') {
                        PutzplanTasks::updateStaff(
                            (int) $idStaff,
                            (string) $s['name'],
                            isset($s['hotels']) ? (array) $s['hotels'] : array(),
                            !empty($s['active']),
                            !empty($s['new_token'])
                        );
                    }
                }
            }
            $newName = trim((string) Tools::getValue('new_staff_name'));
            if ($newName !== '') {
                PutzplanTasks::addStaff($newName, (array) Tools::getValue('new_staff_hotels', array()));
            }
            $out .= $this->displayConfirmation($this->l('Gespeichert.'));
        }

        $hotels = PutzplanBuilder::getHotels();
        $hotelBoxes = function ($field, array $checked) use ($hotels) {
            $html = '';
            foreach ($hotels as $hid => $hname) {
                $html .= '<label style="font-weight:normal;margin-right:12px"><input type="checkbox" name="'.$field.'[]" value="'.(int) $hid.'"'
                    .(in_array((int) $hid, $checked, true) ? ' checked' : '').'> '.Tools::safeOutput($hname).'</label>';
            }
            return $html;
        };

        $rows = '';
        foreach (PutzplanTasks::getStaffList() as $st) {
            $id = (int) $st['id_staff'];
            $link = self::staffLink($st['token']);
            $rows .= '<tr>'
                .'<td><input type="text" name="staff['.$id.'][name]" value="'.Tools::safeOutput($st['name']).'"></td>'
                .'<td>'.$hotelBoxes('staff['.$id.'][hotels]', $st['hotel_ids']).'</td>'
                .'<td><input type="checkbox" name="staff['.$id.'][active]" value="1"'.($st['active'] ? ' checked' : '').'></td>'
                .'<td><input type="text" readonly style="width:100%" onclick="this.select();" value="'.Tools::safeOutput($link).'">'
                .'<label style="font-weight:normal"><input type="checkbox" name="staff['.$id.'][new_token]" value="1"> neuer Link</label></td>'
                .'<td><label style="font-weight:normal"><input type="checkbox" name="staff['.$id.'][delete]" value="1"> loeschen</label></td>'
                .'</tr>';
        }
        $rows .= '<tr>'
            .'<td><input type="text" name="new_staff_name" placeholder="Neue Putzkraft (Name)"></td>'
            .'<td>'.$hotelBoxes('new_staff_hotels', array()).'</td>'
            .'<td colspan="3" class="help-block">Link entsteht beim Speichern.</td>'
            .'</tr>';

        $share = $this->context->link->getModuleLink($this->name, 'view', array('token' => Configuration::get('QLOPUTZPLAN_TOKEN')));
        $out .= '<form method="post" class="defaultForm form-horizontal"><div class="panel">'
            .'<h3>Putzplan</h3>'
            .'<p><label>Luecke in Tagen, ab der "ggf. spaeter reinigen" angezeigt wird</label><br>'
            .'<input type="number" min="1" name="QLOPUTZPLAN_GAP_DAYS" value="'.(int) Configuration::get('QLOPUTZPLAN_GAP_DAYS').'"></p>'
            .'<p><label>Leerstand in Tagen, ab dem vor der Anreise eine Kontrolle faellig ist</label><br>'
            .'<input type="number" min="1" name="QLOPUTZPLAN_VACANCY_DAYS" value="'.(int) Configuration::get('QLOPUTZPLAN_VACANCY_DAYS').'"></p>'
            .'<p class="help-block">Stichwoerter (kommagetrennt), an denen Zusatzleistungen erkannt werden:</p>'
            .'<p><label>Hund</label><br><input type="text" name="QLOPUTZPLAN_KW_DOG" value="'.Tools::safeOutput(Configuration::get('QLOPUTZPLAN_KW_DOG')).'"></p>'
            .'<p><label>Parkplatz</label><br><input type="text" name="QLOPUTZPLAN_KW_PARKING" value="'.Tools::safeOutput(Configuration::get('QLOPUTZPLAN_KW_PARKING')).'"></p>'
            .'<p><label>Waeschepaket</label><br><input type="text" name="QLOPUTZPLAN_KW_LINEN" value="'.Tools::safeOutput(Configuration::get('QLOPUTZPLAN_KW_LINEN')).'"></p>'
            .'</div><div class="panel">'
            .'<h3>Putzkraefte</h3>'
            .'<p class="help-block">Jede Putzkraft bekommt einen persoenlichen Link ohne Anmeldung. Damit hakt sie Reinigungen ab, ihr Name wird gespeichert. '
            .(Module::isEnabled('qloapp') ? '<strong>Der Link fuehrt in die App (QLO App) mit Codes der Schluesselboxen und Meldungen.</strong> ' : '')
            .'Die Standorte sind nur die Voreinstellung, fuer Vertretungen kann sie jederzeit auf andere Hotels umschalten. Gaeste erscheinen dort nur mit Vornamen.</p>'
            .'<table class="table"><thead><tr><th>Name</th><th>Standorte</th><th>Aktiv</th><th>Persoenlicher Link</th><th></th></tr></thead><tbody>'
            .$rows.'</tbody></table>'
            .'</div><div class="panel">'
            .'<h3>Alter Teilen-Link</h3>'
            .'<p><label><input type="checkbox" name="QLOPUTZPLAN_SHARE_ENABLED" value="1"'.(Configuration::get('QLOPUTZPLAN_SHARE_ENABLED') ? ' checked' : '').'> aktiv (nur lesen, mit vollen Gaestenamen)</label></p>'
            .'<p><input type="text" readonly style="width:100%" value="'.Tools::safeOutput($share).'"></p>'
            .'<p><label><input type="checkbox" name="regenerate_token" value="1"> Neuen Link erzeugen (alter Link wird ungueltig)</label></p>'
            .'<button type="submit" name="submitQloPutzplan" class="btn btn-default">Speichern</button>'
            .'</div></form>';

        return $out;
    }
}
