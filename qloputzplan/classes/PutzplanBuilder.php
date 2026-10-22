<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Baut aus den QloApps-Buchungen (htl_booking_detail) die Wochenliste:
 * links Abreise/Reinigung, rechts die naechsten Gaeste inkl. Vorbereitungs-Checkliste.
 */
class PutzplanBuilder
{
    /** Diagnosemodus (?debug=1 im Back Office): zeigt rohe Zaehler je Buchungszeile. */
    public static $debug = false;
    public static $lastError = '';

    private static $weekdays = array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag');

    /** Montag der Woche, in der $date liegt (Y-m-d). */
    public static function mondayOf($date)
    {
        $ts = strtotime($date ? $date : 'today');
        if ($ts === false) {
            $ts = time();
        }
        $dow = (int) date('N', $ts); // 1 = Montag
        return date('Y-m-d', strtotime('-'.($dow - 1).' days', $ts));
    }

    public static function formatDay($ymd)
    {
        $ts = strtotime($ymd);
        return self::$weekdays[(int) date('w', $ts)].', '.date('d.m.', $ts);
    }

    public static function formatShort($ymd)
    {
        $ts = strtotime($ymd);
        return substr(self::$weekdays[(int) date('w', $ts)], 0, 2).' '.date('d.m.', $ts);
    }

    /**
     * @param string $monday Y-m-d eines Montags
     * @return array Liste von Tagen (Mo-So), je mit 'items'
     */
    public static function buildWeek($monday)
    {
        $gapThreshold = max(1, (int) Configuration::get('QLOPUTZPLAN_GAP_DAYS'));
        $sunday = date('Y-m-d', strtotime($monday.' +6 days'));

        $departures = Db::getInstance()->executeS(
            'SELECT hbd.id_room, hbd.id_order, hbd.hotel_name, hbd.room_type_name, hbd.room_num, hbd.city,
                    DATE(hbd.date_to) AS dep_date,
                    c.firstname, c.lastname
             FROM `'._DB_PREFIX_.'htl_booking_detail` hbd
             LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.id_customer = hbd.id_customer)
             WHERE hbd.is_refunded = 0 AND hbd.is_cancelled = 0
               AND DATE(hbd.date_to) BETWEEN \''.pSQL($monday).'\' AND \''.pSQL($sunday).'\'
             ORDER BY hbd.date_to ASC, hbd.hotel_name ASC, hbd.room_num ASC'
        );
        if (!is_array($departures)) {
            $departures = array();
        }

        $days = array();
        for ($i = 0; $i < 7; $i++) {
            $ymd = date('Y-m-d', strtotime($monday.' +'.$i.' days'));
            $days[$ymd] = array('date' => $ymd, 'label' => self::formatDay($ymd), 'items' => array());
        }

        foreach ($departures as $dep) {
            $next = Db::getInstance()->getRow(
                'SELECT hbd.id, hbd.id_order, hbd.id_room, hbd.adults, hbd.children, DATE(hbd.date_from) AS arr_date,
                        c.firstname, c.lastname
                 FROM `'._DB_PREFIX_.'htl_booking_detail` hbd
                 LEFT JOIN `'._DB_PREFIX_.'customer` c ON (c.id_customer = hbd.id_customer)
                 WHERE hbd.id_room = '.(int) $dep['id_room'].'
                   AND hbd.is_refunded = 0 AND hbd.is_cancelled = 0
                   AND DATE(hbd.date_from) >= \''.pSQL($dep['dep_date']).'\'
                   AND hbd.id_order <> '.(int) $dep['id_order'].'
                 ORDER BY hbd.date_from ASC'
            );

            $item = array(
                'unit' => self::unitLabel($dep),
                'departure_info' => 'Abreise heute: '.self::guestName($dep).' (Bestellung #'.(int) $dep['id_order'].')',
                'has_next' => false,
                'next_info' => 'Keine weitere Anreise gebucht',
                'gap_hint' => '',
                'persons_text' => '',
                'prep_title' => '',
                'services_text' => '',
                'checklist' => array(),
            );

            if ($next) {
                $adults = max(0, (int) $next['adults']);
                $children = max(0, (int) $next['children']);
                $persons = $adults + $children;
                $services = self::getServices((int) $next['id']);
                $dogs = self::hasService($services, 'QLOPUTZPLAN_KW_DOG');
                $parking = self::hasService($services, 'QLOPUTZPLAN_KW_PARKING');
                $linen = self::hasService($services, 'QLOPUTZPLAN_KW_LINEN');
                $gap = (int) round((strtotime($next['arr_date']) - strtotime($dep['dep_date'])) / 86400);

                $item['has_next'] = true;
                $item['next_info'] = 'Naechste Gaeste: '.self::formatShort($next['arr_date']).' - '.self::guestName($next).' (Bestellung #'.(int) $next['id_order'].')';
                if ($gap === 0) {
                    $item['gap_hint'] = 'Anreise noch am selben Tag - heute fertig machen';
                } elseif ($gap >= $gapThreshold) {
                    $latest = date('Y-m-d', strtotime($next['arr_date'].' -1 day'));
                    $item['gap_hint'] = 'Luecke '.$gap.' Tage - ggf. erst spaeter reinigen (spaetestens '.self::formatShort($latest).')';
                } else {
                    $item['gap_hint'] = 'Luecke '.$gap.' Tag'.($gap > 1 ? 'e' : '').' - am besten direkt reinigen';
                }

                $guestText = $persons.' Gaest'.($persons === 1 ? '' : 'e');
                if ($children > 0) {
                    $guestText .= ' ('.$adults.' Erw., '.$children.' Kind'.($children === 1 ? '' : 'er').')';
                }
                $item['prep_title'] = 'Vorbereitung fuer '.self::guestName($next).' (Anreise '.self::formatShort($next['arr_date']).')';
                $item['services_text'] = count($services) ? 'Gebucht: '.implode(', ', $services) : 'Keine Zusatzleistungen gebucht';
                if (self::$debug) {
                    $item['services_text'] .= ' [Diagnose: Buchungszeile id='.(int) $next['id'].', '.self::debugCounts((int) $next['id'], (int) $next['id_order']).', Modul v0.2.3]';
                }
                $item['persons_text'] = $guestText.', '.($dogs ? 'mit Hund' : 'kein Hund');

                $item['checklist'][] = $persons.' Betten beziehen';
                $item['checklist'][] = 'Bettdecken: nur '.$persons.' auslegen, uebrige wegraeumen';
                $item['checklist'][] = $linen ? $persons.' Waeschepaket'.($persons === 1 ? '' : 'e').' bereitstellen' : 'Kein Waeschepaket gebucht';
                $item['checklist'][] = $dogs ? 'Hundeleckerli hinlegen' : 'Kein Hundeleckerli';
                $item['checklist'][] = 'Parkplatz: '.($parking ? 'Ja' : 'Nein');
            }

            $days[$dep['dep_date']]['items'][] = $item;
        }

        return array_values($days);
    }

    private static function guestName($row)
    {
        $name = trim((string) $row['firstname'].' '.(string) $row['lastname']);
        return $name !== '' ? $name : 'Gast unbekannt';
    }

    private static function unitLabel($row)
    {
        $parts = array();
        foreach (array('room_type_name', 'hotel_name') as $k) {
            if (!empty($row[$k]) && !in_array($row[$k], $parts)) {
                $parts[] = $row[$k];
            }
        }
        $label = implode(', ', $parts);
        $extra = array();
        if (!empty($row['room_num'])) {
            $extra[] = 'Zimmer '.$row['room_num'];
        }
        $extra[] = 'Raum-ID '.(int) $row['id_room'];
        return $label.' ('.implode(', ', $extra).')';
    }

    /**
     * Gebuchte Leistungen einer Buchungszeile: Zusatzleistungen (htl_booking_demands)
     * und Dienstleistungen/Service-Produkte (htl_room_type_service_product_order_detail).
     */
    private static function getServices($id_htl_booking)
    {
        $names = array();

        $rows = Db::getInstance()->executeS(
            'SELECT `name` FROM `'._DB_PREFIX_.'htl_booking_demands` WHERE id_htl_booking = '.(int) $id_htl_booking
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $names[] = (string) $r['name'];
            }
        }

        try {
            $rows = Db::getInstance()->executeS(
                'SELECT COALESCE(NULLIF(sp.`name`, \'\'), od.`product_name`) AS `name`, sp.`quantity`
                 FROM `'._DB_PREFIX_.'htl_room_type_service_product_order_detail` sp
                 LEFT JOIN `'._DB_PREFIX_.'order_detail` od ON (od.id_order_detail = sp.id_order_detail)
                 WHERE sp.id_htl_booking_detail = '.(int) $id_htl_booking
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $q = (int) $r['quantity'];
                    $names[] = (string) $r['name'].($q > 1 ? ' (x'.$q.')' : '');
                }
            }
        } catch (Exception $e) {
            self::$lastError = $e->getMessage();
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
        }

        return $names;
    }

    private static function debugCounts($id, $id_order)
    {
        $out = array();
        $out['Zusatz'] = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_demands` WHERE id_htl_booking = '.(int) $id);
        $tables = array();
        foreach (array('%service%', '%demand%') as $like) {
            $rows = Db::getInstance()->executeS('SHOW TABLES LIKE \''.pSQL(str_replace('_', '\\_', _DB_PREFIX_)).$like.'\'');
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $tables[] = reset($r);
                }
            }
        }
        $text = 'Zusatz='.$out['Zusatz'].'; Tabellen: '.($tables ? implode(', ', $tables) : 'keine gefunden');
        if (self::$lastError !== '') {
            $text .= '; Fehler Dienstleistungen: '.self::$lastError;
        }
        $lines = Db::getInstance()->executeS(
            'SELECT product_name, product_quantity FROM `'._DB_PREFIX_.'order_detail` WHERE id_order = '.(int) $id_order
        );
        $names = array();
        if (is_array($lines)) {
            foreach ($lines as $l) {
                $names[] = $l['product_name'].' x'.(int) $l['product_quantity'];
            }
        }
        $text .= '; Bestellpositionen: '.($names ? implode(' | ', $names) : 'keine');
        return $text;
    }

    /** Prueft, ob ein Zusatzleistungs-Name eines der (kommagetrennten) Stichwoerter enthaelt. */
    private static function hasService($names, $configKey)
    {
        $keywords = array_filter(array_map('trim', explode(',', (string) Configuration::get($configKey))));
        foreach ($names as $name) {
            foreach ($keywords as $kw) {
                if ($kw !== '' && Tools::strpos(Tools::strtolower($name), Tools::strtolower($kw)) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
}
