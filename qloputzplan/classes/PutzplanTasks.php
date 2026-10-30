<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Erledigte Aufgaben (Reinigung, Kontrolle nach Leerstand) und Putzkraefte mit persoenlichem Link.
 * Eine Aufgabe ist eindeutig ueber Zimmer + Datum + Art; abgehakt = Zeile vorhanden.
 */
class PutzplanTasks
{
    const TYPE_CLEAN = 'clean';
    const TYPE_CHECK = 'check';

    public static function installDb()
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;
        $sql = array();
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}qloputzplan_task` (
            `id_task` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_room` INT UNSIGNED NOT NULL,
            `task_date` DATE NOT NULL,
            `task_type` VARCHAR(16) NOT NULL DEFAULT 'clean',
            `done_at` DATETIME NOT NULL,
            `done_by` VARCHAR(64) NOT NULL DEFAULT '',
            `id_staff` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_task`),
            UNIQUE KEY `room_date_type` (`id_room`, `task_date`, `task_type`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8";
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}qloputzplan_staff` (
            `id_staff` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(64) NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `hotels` VARCHAR(255) NOT NULL DEFAULT '',
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_staff`),
            UNIQUE KEY `token` (`token`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8";

        foreach ($sql as $q) {
            if (!Db::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }

    public static function key($idRoom, $date, $type)
    {
        return (int) $idRoom.'|'.$date.'|'.$type;
    }

    /** Erledigte Aufgaben im Zeitraum: key() => Zeile. */
    public static function getDone($from, $to)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `'._DB_PREFIX_.'qloputzplan_task`
             WHERE task_date BETWEEN \''.pSQL($from).'\' AND \''.pSQL($to).'\''
        );
        $done = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $done[self::key($r['id_room'], $r['task_date'], $r['task_type'])] = $r;
            }
        }
        return $done;
    }

    /**
     * Prueft, ob es zu Zimmer + Datum + Art wirklich eine Buchung gibt
     * (Reinigung = Abreise an dem Tag, Kontrolle = Anreise an dem Tag).
     */
    public static function isValidTask($idRoom, $date, $type)
    {
        if (!Validate::isDate($date) || !in_array($type, array(self::TYPE_CLEAN, self::TYPE_CHECK), true)) {
            return false;
        }
        $col = $type === self::TYPE_CLEAN ? 'date_to' : 'date_from';
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `'._DB_PREFIX_.'htl_booking_detail`
             WHERE id_room = '.(int) $idRoom.' AND DATE('.$col.') = \''.pSQL($date).'\'
               AND is_refunded = 0 AND is_cancelled = 0'
        );
    }

    public static function markDone($idRoom, $date, $type, $by, $idStaff = 0)
    {
        return Db::getInstance()->execute(
            'INSERT IGNORE INTO `'._DB_PREFIX_.'qloputzplan_task` (id_room, task_date, task_type, done_at, done_by, id_staff)
             VALUES ('.(int) $idRoom.', \''.pSQL($date).'\', \''.pSQL($type).'\', NOW(), \''.pSQL(Tools::substr($by, 0, 64)).'\', '.(int) $idStaff.')'
        );
    }

    public static function undo($idRoom, $date, $type)
    {
        return Db::getInstance()->delete(
            'qloputzplan_task',
            'id_room = '.(int) $idRoom.' AND task_date = \''.pSQL($date).'\' AND task_type = \''.pSQL($type).'\''
        );
    }

    /** Abhaken oder Zuruecknehmen aus einem Formular-POST; true, wenn etwas geaendert wurde. */
    public static function handlePost($by, $idStaff = 0)
    {
        $action = (string) Tools::getValue('qpp_action');
        $idRoom = (int) Tools::getValue('qpp_room');
        $date = (string) Tools::getValue('qpp_date');
        $type = (string) Tools::getValue('qpp_type');
        if (!in_array($action, array('done', 'undo'), true) || !self::isValidTask($idRoom, $date, $type)) {
            return false;
        }
        if ($action === 'done') {
            return self::markDone($idRoom, $date, $type, $by, $idStaff);
        }
        return self::undo($idRoom, $date, $type);
    }

    /* ---------------------------- Putzkraefte ---------------------------- */

    public static function getStaffList()
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'qloputzplan_staff` ORDER BY name ASC');
        return is_array($rows) ? array_map(array('PutzplanTasks', 'withHotels'), $rows) : array();
    }

    /** Aktive Putzkraft zu einem persoenlichen Link-Schluessel, sonst null. */
    public static function getStaffByToken($token)
    {
        $token = (string) $token;
        if (Tools::strlen($token) < 20) {
            return null;
        }
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'qloputzplan_staff` WHERE token = \''.pSQL($token).'\' AND active = 1'
        );
        // Vergleich zeitkonstant wiederholen, die SQL-Abfrage dient nur dem Finden.
        if (!$row || !hash_equals((string) $row['token'], $token)) {
            return null;
        }
        return self::withHotels($row);
    }

    public static function addStaff($name, array $hotelIds)
    {
        return Db::getInstance()->insert('qloputzplan_staff', array(
            'name' => pSQL(Tools::substr(trim($name), 0, 64)),
            'token' => pSQL(Tools::passwdGen(32)),
            'hotels' => pSQL(implode(',', array_map('intval', $hotelIds))),
            'active' => 1,
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    public static function updateStaff($idStaff, $name, array $hotelIds, $active, $newToken)
    {
        $data = array(
            'name' => pSQL(Tools::substr(trim($name), 0, 64)),
            'hotels' => pSQL(implode(',', array_map('intval', $hotelIds))),
            'active' => $active ? 1 : 0,
        );
        if ($newToken) {
            $data['token'] = pSQL(Tools::passwdGen(32));
        }
        return Db::getInstance()->update('qloputzplan_staff', $data, 'id_staff = '.(int) $idStaff);
    }

    public static function deleteStaff($idStaff)
    {
        return Db::getInstance()->delete('qloputzplan_staff', 'id_staff = '.(int) $idStaff);
    }

    private static function withHotels($row)
    {
        $row['hotel_ids'] = array_values(array_filter(array_map('intval', explode(',', (string) $row['hotels']))));
        return $row;
    }
}
