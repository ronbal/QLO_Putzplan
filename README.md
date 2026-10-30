# QLO_Putzplan

QloApps module (`qloputzplan`): a weekly arrival and preparation list for housekeeping.
Shows per weekday which unit has to be cleaned after departure and what to prepare for the next guests.

QloApps-Modul (`qloputzplan`) für eine wöchentliche Anreise- und Vorbereitungsliste (Putzplan) für die Reinigung.

## Was es zeigt

Pro Wochentag (Mo–So) alle Abreisen mit:

- **Links:** die Unterkunft, die gereinigt werden muss, und für die nächsten Gäste: Name, Gästezahl (Erwachsene/Kinder), Hund sowie eine Checkliste (Betten beziehen, Bettdecken nur für die Gästezahl auslegen, Wäschepakete, Hundeleckerli, Parkplatz).
- **Rechts:** wann die nächsten Gäste kommen. Bei Lücken ab einer einstellbaren Zahl von Tagen (Standard: 2) erscheint „ggf. erst später reinigen“ mit spätestem Reinigungstag.
- Abreisetag = Reinigungstag ist der Normalfall.
- **Hotel-Filter:** Auswahl „Alle Hotels“ oder ein einzelnes Hotel/ein einzelner Standort (zeigt alle Zimmer dieses Hotels). Der Filter gilt auch für Ausdruck und Teilen-Link, der Hotelname steht dann in der Überschrift.

## Abhaken und persönliche Links (ab 0.3.0)

- **Putzkräfte** werden in der Modul-Konfiguration angelegt, mit Name und Standard-Standorten. Jede bekommt einen **persönlichen Link** ohne Anmeldung (`…&key=…`), der sich einzeln erneuern oder sperren lässt.
- Über den Link sieht die Putzkraft zuerst ihre Standorte („Meine Standorte“) und kann für Vertretungen auf „Alle Hotels“ oder ein anderes Hotel umschalten. Gäste erscheinen dort nur mit Vornamen.
- **Gereinigt:** ein Tipp speichert Datum, Uhrzeit und Namen, „Rückgängig“ nimmt den Haken zurück. Erledigte Einträge werden grau und rutschen ans Ende des Tages. Im Back Office kann auch der Admin abhaken, dann mit seinem Namen.
- **Offene Reinigungen wandern mit:** Nicht abgehakte Reinigungen erscheinen zusätzlich unter „Heute“ („Noch offen seit …“) bzw. am spätesten Reinigungstag. Es zählen nur Abreisen ab dem Update auf 0.3.0.
- **Kontrolle nach Leerstand:** Stand ein Zimmer mindestens 14 Tage leer (einstellbar), erscheint am Tag vor der Anreise „Kontrolle vor Anreise“ mit kurzer Checkliste, ebenfalls abhakbar.
- Gezählt wird je Zimmer (`id_room`), in Kölpinsee also je Wohnung.
- Der **alte Teilen-Link** (nur lesen, volle Gästenamen) lässt sich in der Konfiguration abschalten.

## Export

- **Drucken / PDF:** Button in der Admin-Ansicht (Browser-Druckdialog, A4-optimiert).
- **Teilen-Link:** schreibgeschützte Seite ohne Login, geschützt durch einen geheimen Token. Mobil lesbar, als Link, Foto oder Ausdruck weitergebbar. Der Token wird bei der Installation zufällig erzeugt und lässt sich in der Modul-Konfiguration erneuern.

Hinweis: Der alte Teilen-Link enthält volle Gästenamen. Den Link nur an Berechtigte weitergeben oder abschalten, sobald die persönlichen Links genutzt werden.

## Installation

1. `qloputzplan/` zu `qloputzplan.zip` packen (`zip -r qloputzplan.zip qloputzplan`) und im Back Office unter Module → Modul hochladen installieren, oder den Ordner nach `<QloApps>/modules/` kopieren.
2. Menüpunkt „Putzplan“ erscheint unter Bestellungen. Teilen-Link und Stichwörter stehen in der Modul-Konfiguration.

## Datenquelle

- Buchungen: `htl_booking_detail` (`id`, `id_room`, `id_order`, `id_customer`, `date_from`, `date_to`, `adults`, `children`, `hotel_name`, `room_type_name`, `room_num`, `is_refunded`, `is_cancelled`).
- Hund, Parkplatz, Wäschepaket: gebuchte Leistungen aus Zusatzleistungen (`htl_booking_demands.name`) und Dienstleistungen (QloApps 1.7: `service_product_order_detail.name`, 1.6: `htl_room_type_service_product_order_detail.name`), erkannt über Stichwörter (Standard `hund`, `parkplatz`, `wäsche,waesche`; in der Konfiguration änderbar).
- Gästenamen: `customer`.
- Haken: `qloputzplan_task` (Zimmer, Datum, Art `clean`/`check`, Zeitpunkt, Name). Putzkräfte: `qloputzplan_staff`. Beide Tabellen bleiben bei der Deinstallation erhalten.

Entwickelt mit QloApps 1.6.1, eingesetzt mit QloApps 1.7. Bei anderen Versionen die Tabellen- und Spaltennamen vorher prüfen.

## Diagnose

Im Back Office zeigt `&debug=1` an der Putzplan-Adresse pro Buchung die erkannten Tabellen und Bestellpositionen, nützlich wenn eine Leistung nicht erkannt wird.

## Anpassen

- Wäschepakete = Gästezahl, sofern eine Wäsche-Leistung gebucht ist.
- Anzeige der Unterkunft: `PutzplanBuilder::unitLabel()`.

## Lizenz

[MIT](LICENSE)
