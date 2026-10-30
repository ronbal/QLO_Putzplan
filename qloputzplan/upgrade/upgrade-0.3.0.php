<?php
/**
 * 0.3.0: Haken "Gereinigt", Kontrolle nach Leerstand, Putzkraefte mit persoenlichem Link.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'qloputzplan/classes/PutzplanTasks.php';

function upgrade_module_0_3_0($module)
{
    Configuration::updateValue('QLOPUTZPLAN_VACANCY_DAYS', 14);
    // Abreisen vor dem Update gelten als erledigt und wandern nicht als "offen" mit.
    Configuration::updateValue('QLOPUTZPLAN_TASKS_SINCE', date('Y-m-d'));
    Configuration::updateValue('QLOPUTZPLAN_SHARE_ENABLED', 1);

    return PutzplanTasks::installDb();
}
