<?php

namespace Database\Seeders;

use App\Domain\Content\ContentPage;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $i => $page) {
            ContentPage::query()->updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'audience' => $page['audience'],
                    'sort_order' => $i + 1,
                    'published' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, title: string, audience: string, body: string}>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'privacy',
                'title' => 'Datenschutz',
                'audience' => 'all',
                'body' => <<<'TXT'
## Verantwortliche Stelle
FieldOps verarbeitet personenbezogene Daten, um Einsätze, Kundenkonten und Rechnungen für SHK-Betriebe in der DACH-Region bereitzustellen.

## Welche Daten wir nutzen
Name, E-Mail, Telefon, Anschrift, Einsatzbeschreibungen, Fotos vom Einsatzort, Zeit- und Materialdaten sowie Abrechnungsdaten.

## Zwecke
Durchführung von Serviceeinsätzen, Zuweisung an Monteure, Rechnungsstellung, Betrugs- und Missbrauchsprävention sowie gesetzliche Aufbewahrung (u. a. GoBD).

## Rechtsgrundlagen
Art. 6 Abs. 1 lit. b DSGVO (Vertrag), lit. c (gesetzliche Pflichten) und lit. f (berechtigtes Interesse an einem sicheren Betrieb).

## Speicherdauer
Einsatzfotos und Auftragsdaten werden gemäß den Einstellungen des Betriebs und gesetzlicher Fristen gespeichert. Sie können Auskunft, Berichtigung und Löschung verlangen, soweit keine Aufbewahrungspflicht entgegensteht.

## Empfänger
Daten bleiben im Mandanten des Betriebs. Zahlungsabwicklung erfolgt über Stripe. Hosting erfolgt in der EU.

## Ihre Rechte
Sie haben Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch. Beschwerden richten Sie an die zuständige Aufsichtsbehörde.

## Kontakt
datenschutz@fieldops.test
TXT,
            ],
            [
                'slug' => 'faq',
                'title' => 'Häufige Fragen',
                'audience' => 'all',
                'body' => <<<'TXT'
## Wie lege ich als Kunde einen Auftrag an?
Nach der Registrierung bestätigt das Büro Ihr Konto. Danach können Sie in der Kunden-App unter Auftrag einen neuen Einsatz senden.

## Wann kommt der Monteur?
Sobald das Büro den Einsatz plant und zuweist, sehen Sie den Status Geplant und später Unterwegs.

## Warum kann das Büro noch keine Einsätze anlegen?
Neue Büro-Konten müssen vom Super-Admin freigegeben werden. Danach steht die Plantafel zum Anlegen und Zuweisen bereit.

## Wie erfahre ich von einem neuen Einsatz?
Monteure erhalten in der Field-App eine Mitteilung, sobald das Büro sie zuweist. Büro und Super-Admin sehen dieselbe Zuweisung in Mitteilungen.

## Sind Fotos und Unterschriften Pflicht?
Für die Abrechnung empfohlen, aber der Status kann auch ohne Fotos auf Erledigt gesetzt werden, wenn der Betrieb das so handhabt.

## Wie erreiche ich den Support?
Über die Seite Hilfe in der App oder per E-Mail an support@fieldops.test.
TXT,
            ],
            [
                'slug' => 'terms',
                'title' => 'AGB',
                'audience' => 'all',
                'body' => <<<'TXT'
## Geltung
Diese Bedingungen gelten für die Nutzung der FieldOps-Plattform durch Betriebe, Monteure und Endkunden.

## Leistungen
FieldOps stellt Software für Anfrage, Disposition, Einsatzdokumentation und Rechnung bereit. Der Werkvertrag über die SHK-Leistung kommt zwischen Betrieb und Kunden zustande.

## Konten
Kundenkonten werden vom Büro bestätigt. Büro-Konten werden vom Super-Admin freigegeben. Zugangsdaten sind geheim zu halten.

## Pflichten
Nutzer stellen richtige Angaben bereit und missbrauchen die Plattform nicht. Monteure dokumentieren Einsätze wahrheitsgemäß.

## Haftung
FieldOps haftet unbeschränkt bei Vorsatz und grober Fahrlässigkeit sowie bei Verletzung von Leben, Körper und Gesundheit. Im Übrigen ist die Haftung auf den vertragstypischen, vorhersehbaren Schaden begrenzt.

## Laufzeit
SaaS-Verträge laufen monatlich gemäß dem gewählten Plan und können zum Periodenende gekündigt werden.
TXT,
            ],
            [
                'slug' => 'imprint',
                'title' => 'Impressum',
                'audience' => 'all',
                'body' => <<<'TXT'
## Angaben gemäß § 5 DDG
FieldOps Platform
c/o Mustermann SHK GmbH
Werkstattstraße 12
60311 Frankfurt am Main

## Kontakt
Telefon: +49 69 0000 000
E-Mail: hello@fieldops.test

## Vertreten durch
Max Mustermann

## USt-IdNr.
DE123456789

## Verantwortlich für den Inhalt
Max Mustermann, Anschrift wie oben.
TXT,
            ],
            [
                'slug' => 'about',
                'title' => 'Über FieldOps',
                'audience' => 'all',
                'body' => <<<'TXT'
## Die Plantafel fürs Handwerk
FieldOps begleitet den Weg Anfrage → Plantafel → Einsatz → Rechnung. Büro plant am Schreibtisch, Monteure arbeiten unterwegs, Kunden sehen den Status auf dem Handy.

## Für wen
SHK-Betriebe in Deutschland, Österreich und der Schweiz: Notdienst, Wartung und Abrechnung an einem Ort.

## So arbeiten die Apps
Die Kunden-App sendet Aufträge nach Freigabe durchs Büro. Die Field-App führt den Einsatz. Super-Admin steuert Mandanten, Freigaben und diese Inhalte.
TXT,
            ],
            [
                'slug' => 'support',
                'title' => 'Hilfe',
                'audience' => 'all',
                'body' => <<<'TXT'
## Schnelle Hilfe
Prüfen Sie zuerst den Status Ihres Kontos: Kunden warten auf Büro-Freigabe, Büro-Mitarbeitende auf Super-Admin-Freigabe.

## Technischer Support
E-Mail: support@fieldops.test
Mo–Fr 08:00–17:00 (Europe/Berlin)

## Störung vor Ort
Rufen Sie bei einem Notdienst den Betrieb direkt an. Die App ersetzt den Notruf nicht.

## Feedback
Ideen zur Plantafel oder den Apps senden Sie an feedback@fieldops.test.
TXT,
            ],
        ];
    }
}
