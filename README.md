# QLO_Putzplan

QloApps module (`qloputzplan`): a weekly arrival and preparation list for housekeeping.
Shows per weekday which unit has to be cleaned after departure and what to prepare for the next guests.

QloApps-Modul (`qloputzplan`) für eine wöchentliche Anreise- und Vorbereitungsliste (Putzplan) für die Reinigung.

## Was es zeigt

Pro Wochentag (Mo–So) alle Abreisen mit:

- **Links:** die Unterkunft, die gereinigt werden muss, und für die nächsten Gäste: Name, Gästezahl (Erwachsene/Kinder), Hund sowie eine Checkliste (Betten beziehen, Bettdecken nur für die Gästezahl auslegen, Wäschepakete, Hundeleckerli, Parkplatz).
- **Rechts:** wann die nächsten Gäste kommen. Bei Lücken ab einer einstellbaren Zahl von Tagen (Standard: 2) erscheint „ggf. erst später reinigen“ mit spätestem Reinigungstag.
- Abreisetag = Reinigungstag ist der Normalfall.

## Export

- **Drucken / PDF:** Button in der Admin-Ansicht (Browser-Druckdialog, A4-optimiert).
- **Teilen-Link:** schreibgeschützte Seite ohne Login, geschützt durch einen geheimen Token. Mobil lesbar, als Link, Foto oder Ausdruck weitergebbar. Der Token wird bei der Installation zufällig erzeugt und lässt sich in der Modul-Konfiguration erneuern.

Hinweis: Die Teilen-Seite enthält Gästenamen. Den Link nur an Berechtigte weitergeben.

## Installation

1. `qloputzplan/` zu `qloputzplan.zip` packen (`zip -r qloputzplan.zip qloputzplan`) und im Back Office unter Module → Modul hochladen installieren, oder den Ordner nach `<QloApps>/modules/` kopieren.
2. Menüpunkt „Putzplan“ erscheint unter Bestellungen. Teilen-Link und Stichwörter stehen in der Modul-Konfiguration.

## Datenquelle

- Buchungen: `htl_booking_detail` (`id`, `id_room`, `id_order`, `id_customer`, `date_from`, `date_to`, `adults`, `children`, `hotel_name`, `room_type_name`, `room_num`, `is_refunded`, `is_cancelled`).
- Hund, Parkplatz, Wäschepaket: gebuchte Leistungen aus Zusatzleistungen (`htl_booking_demands.name`) und Dienstleistungen (`htl_room_type_service_product_order_detail.name`), erkannt über Stichwörter (Standard `hund`, `parkplatz`, `wäsche,waesche`; in der Konfiguration änderbar).
- Gästenamen: `customer`.

Entwickelt und eingesetzt mit QloApps 1.6.1. Bei anderen Versionen die Tabellen- und Spaltennamen vorher prüfen.

## Diagnose

Im Back Office zeigt `&debug=1` an der Putzplan-Adresse pro Buchung die erkannten Tabellen und Bestellpositionen, nützlich wenn eine Leistung nicht erkannt wird.

## Anpassen

- Wäschepakete = Gästezahl, sofern eine Wäsche-Leistung gebucht ist.
- Anzeige der Unterkunft: `PutzplanBuilder::unitLabel()`.

## Lizenz

[MIT](LICENSE)
