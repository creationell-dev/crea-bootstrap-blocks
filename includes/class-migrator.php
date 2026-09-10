<?php
/**
 * The rewriter: `areoi/*` block delimiters become `creabb/*` block delimiters.
 *
 * WAS DIESE KLASSE TUT UND WAS SIE BEWUSST NICHT TUT
 *
 * Sie ersetzt je Blockbegrenzer zwei Dinge: den Namens-Token `areoi/` durch
 * `creabb/` und die flache Attribut-JSON durch den Container
 * {"blockstudio":{"name":"creabb/<block>","attributes":{…}}}. Der Container ist
 * kein Schmuck: Blockstudio liest Feldwerte AUSSCHLIESSLICH von dort; flach
 * gespeicherte Top-Level-Werte werden beim Rendern verworfen und durch die
 * Defaults ersetzt (S-4).
 *
 * Sie parst den Content NICHT. Kein `parse_blocks()`, kein
 * `serialize_blocks()` — eine vollstaendige Reserialisierung veraenderte
 * Leerraum und Formatierung im gesamten Dokument und machte den DOM-Diff aus
 * `06-verifikation.md` wertlos. Ein Regex trifft nur die Begrenzer; alles
 * dazwischen bleibt Byte fuer Byte stehen.
 *
 * Sie ist ohne WordPress benutzbar. Das ist die Voraussetzung dafuer, dass
 * `tests/test-migrator-rewrite.php` sie Zeichen fuer Zeichen festhaelt, statt
 * ihr Verhalten erst auf Produktivdaten zu zeigen.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Creationell\BootstrapBlocks\Legacy;

/**
 * Rewrites block delimiters without touching anything between them.
 */
class Migrator {

    /**
     * The namespace being migrated away from, including the slash.
     */
    public const OLD_NAMESPACE = 'areoi/';

    /**
     * The namespace being migrated to, including the slash.
     */
    public const NEW_NAMESPACE = 'creabb/';

    /**
     * The nesting depth every `json_decode()` and `json_encode()` here works with.
     *
     * ZWEI EBENEN UEBER DER PHP-STANDARDTIEFE 512, UND ZWAR AUS EINEM GRUND:
     * Die verpackte Form ist genau zwei Ebenen tiefer als die flache
     * ({"blockstudio":{"attributes":{…}}}). Bliebe das Auspacken bei 512 und
     * das Einpacken bei der Standardtiefe, koennte die Attributkarte einen
     * Traeger, den der Umschreiber gerade geschrieben hat, NICHT MEHR LESEN —
     * und der Abgleich von Bein 1 meldete Phantomverluste an einem Bestand, dem
     * nichts fehlt.
     *
     * Was jenseits dieser Tiefe liegt, scheitert LAUT: `json_decode()` liefert
     * null (unlesbare JSON, Beanstandung), `json_encode()` liefert false
     * (Beanstandung, Begrenzer bleibt stehen). Gemessen an PHP 8.4.
     */
    private const JSON_DEPTH = 514;

    /**
     * Matches one complete JSON string literal, escapes included.
     *
     * DERSELBE ZWEIG WIE IN `JSON_PATTERN`, hier einzeln, weil zwei Pruefungen
     * ihn brauchen: Die Schluesselzaehlung muss Zeichenketten ueberlesen, und
     * die Zahlenpruefung muss sie vorher aus der JSON herausnehmen. Sonst
     * zaehlte ein Doppelpunkt in einem Textattribut als Schluessel und eine
     * Ziffer in einer Zeichenkette als Zahl.
     *
     * ACHTUNG BEIM ABTIPPEN: Im PHP-Literal steht jeder Backslash DOPPELT —
     * dieselbe Falle wie bei `JSON_PATTERN`.
     */
    private const JSON_STRING = '"(?:[^"\\\\]++|\\\\.)*+"';

    /**
     * PCRE findings of methods that have no findings list of their own.
     *
     * WARUM ES DIESEN BEHAELTER GIBT: Ein PCRE-Fehler ist von „null Treffer"
     * nicht zu unterscheiden — `preg_match_all()` liefert false, und false ist
     * im if-Kontext dasselbe wie 0. Der Docblock von `JSON_PATTERN` nennt genau
     * das die teuerste Gefahr dieses Plans: „Die Migration schriebe dann gar
     * nichts mehr um und meldete das nicht."
     *
     * `rewrite()` und `findings()` haengen ihren Befund an die eigene
     * Beanstandungsliste. `count_blocks()`, `count_closers()`,
     * `unmatched_delimiters()` und `attribute_map()` liefern nur Zahlen und
     * Karten; sie legen ihn hier ab. Eingesammelt wird er von `rewrite()`, von
     * `findings()` und von `tally()` (Aufgabe 11) — jeweils mit
     * `take_pcre_findings()` und jeweils, nachdem alle Suchlaeufe des Traegers
     * gelaufen sind.
     *
     * GELEERT WIRD NUR BEIM ABHOLEN, nie beim Betreten einer Methode. Ein
     * Befund, den niemand abgeholt hat, taucht damit beim naechsten Abholen auf
     * — die Zuordnung zum Traeger kann dann danebenliegen, der Befund selbst
     * geht aber nie verloren. Er nennt die Methode, aus der er stammt.
     *
     * @var array<int, string>
     */
    private static array $pcre_findings = [];

    /**
     * The nesting depth the WordPress block parser reads with.
     *
     * `parse_blocks()` dekodiert die Attribut-JSON eines Begrenzers mit der
     * PHP-Standardtiefe 512. Was der Umschreiber tiefer schreibt, kann der
     * Parser nicht mehr lesen — der Block waere danach fuer WordPress kein
     * Block mehr, und zwar still: Der Zaehlabgleich sieht den Begrenzer, die
     * Attributkarte liest ihn mit der eigenen, groesseren Tiefe, und beide
     * melden Ruhe.
     *
     * GESCHRIEBEN WIRD DESHALB MIT 512, GELESEN MIT `JSON_DEPTH`. Die
     * Leseseite braucht die zwei Ebenen mehr, weil die verpackte Form tiefer
     * ist als die flache; die Schreibseite darf sie nicht ausnutzen.
     */
    private const PARSER_DEPTH = 512;

    /**
     * `json_encode()` with a pinned float precision.
     *
     * `serialize_precision` ist eine gewoehnliche ini-Einstellung, und `17` ist
     * ein legaler Wert. Unter `17` schreibt `json_encode()` aus `0.15` die Zahl
     * `0.14999999999999999`. Das traefe diesen Umschreiber doppelt: Der
     * Rundlauftest beanstandete jeden Block mit Hintergrundfarbe (Regel 1.6 des
     * Kompatibilitaetsvertrags legt dort `"a": 0.15` ab), und das
     * Zurueckschreiben veraenderte den gespeicherten Wert.
     *
     * Beides waere ein Schaden, den die Zielinstanz bestimmt und nicht der
     * Code. `-1` ist die kuerzeste Darstellung, die verlustfrei zurueckliest —
     * und der PHP-Standard.
     *
     * Laesst die Umgebung `ini_set()` nicht zu, bleibt es beim eingestellten
     * Wert; die Beanstandungen der Zahlenpruefung sind dann strenger als
     * noetig, aber nichts wird still falsch geschrieben.
     *
     * @param mixed $value Value to encode.
     * @param int   $flags Encoding flags.
     * @param int   $depth Maximum nesting depth.
     * @return string|false
     */
    private static function encode_json( mixed $value, int $flags = 0, int $depth = self::PARSER_DEPTH ): string|false {
        // phpcs:ignore WordPress.PHP.IniSet.Risky -- Begruendung im Docblock darueber: Der geschriebene Zahlenwert darf nicht davon abhaengen, wie die Zielinstanz ihre php.ini gesetzt hat. Der vorherige Wert wird unmittelbar danach wiederhergestellt.
        $previous = ini_set( 'serialize_precision', '-1' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Diese Klasse laeuft ohne WordPress; wp_json_encode() gaebe es hier nicht.
        $json = json_encode( $value, $flags, max( 1, $depth ) );

        if ( is_string( $previous ) ) {
            // phpcs:ignore WordPress.PHP.IniSet.Risky -- Die Wiederherstellung des Ausgangswerts.
            ini_set( 'serialize_precision', $previous );
        }

        return $json;
    }

    /**
     * Records a PCRE error, if the last match had one.
     *
     * @param string $where Name of the calling method, for the message.
     * @return bool Whether an error was recorded.
     */
    private static function note_pcre_error( string $where ): bool {
        if ( PREG_NO_ERROR === preg_last_error() ) {
            return false;
        }

        self::$pcre_findings[] = sprintf(
            'Regex-Fehler in %s: %s. Der Inhalt wurde NICHT vollstaendig geprueft.',
            $where,
            preg_last_error_msg()
        );

        return true;
    }

    /**
     * Hands out the collected PCRE findings and empties the container.
     *
     * @return array<int, string>
     */
    public static function take_pcre_findings(): array {
        $findings = self::$pcre_findings;

        self::$pcre_findings = [];

        return $findings;
    }

    /**
     * Matches a balanced JSON object, string literals included.
     *
     * DREI ZWEIGE, UND DIE REIHENFOLGE IST DER PUNKT:
     *
     *   1. `[^{}"]++`  — alles, was weder Klammer noch Anfuehrungszeichen ist.
     *   2. `"(?:[^"\\]++|\\.)*+"` — ein vollstaendiger JSON-String samt
     *      seiner Escapes. Er wird als Ganzes ueberlesen; was darin steht,
     *      zaehlt fuer die Klammernbilanz NICHT mit.
     *   3. `(?P>json)` — die Rekursion fuer ein verschachteltes Objekt.
     *
     * OHNE ZWEIG 2 waere das Muster kaputt, und zwar lautlos: Eine reine
     * Klammernbilanz zaehlt jede `{` und `}` mit — auch die in einem
     * Attributwert. `custom_class` mit einer Klammer, ein Inline-CSS-Schnipsel,
     * ein Textattribut mit `}` reichen. Die Bilanz endet dann zu frueh, das
     * optionale json-Element scheitert, und weil es Teil des Gesamtmusters ist,
     * trifft der GESAMTE Begrenzer nicht mehr. Der Block bleibt unmigriert.
     *
     * Die possessiven Quantoren sind kein Schmuck: Die drei Zweige beginnen mit
     * verschiedenen Zeichen und sind damit ueberschneidungsfrei; ohne `++` und
     * `*+` liesse sich das Muster mit einer langen, klammerlosen JSON in
     * exponentielles Backtracking treiben.
     *
     * ACHTUNG BEIM ABTIPPEN: Im PHP-Literal steht jeder Backslash des Musters
     * DOPPELT. `'[^"\\\\]'` ergibt die Zeichenklasse `[^"\\]`, und die
     * schliesst `"` und den Backslash aus. Mit nur zwei Backslashes im Literal
     * entstuende `[^"\]` — eine unabgeschlossene Zeichenklasse, und
     * `preg_match_all()` lieferte fuer JEDEN Traeger false. Die Migration
     * schriebe dann gar nichts mehr um und meldete das nicht.
     */
    private const JSON_PATTERN = '\{(?:[^{}"]++|"(?:[^"\\\\]++|\\\\.)*+"|(?P>json))*\}';

    /**
     * Matches an opening, self-closing or closing block delimiter of one namespace.
     *
     * SO ENG WIE DER BLOCKPARSER DES KERNS UND KEIN STUECK WEITER.
     * `class-wp-block-parser.php` benutzt ein Muster OHNE `i`-Modifikator und
     * mit einem ZWINGENDEN `\s+` vor dem Ende. `<!-- wp:AREOI/column {…} -->`
     * und `<!-- wp:areoi/column-->` sind fuer WordPress deshalb keine Bloecke,
     * sondern inerte HTML-Kommentare.
     *
     * Ein Umschreiber, der sie doch traefe, machte aus ihnen echte, gerenderte
     * Bloecke — eine Inhaltsaenderung auf Produktivdaten, die kein Abgleich als
     * Fehler sieht, weil sie auf beiden Seiten gleich gezaehlt wird.
     *
     * @param string $block_namespace Namespace including the trailing slash.
     */
    private static function pattern( string $block_namespace ): string {
        return '#<!--\s+(?P<close>/)?wp:' . preg_quote( $block_namespace, '#' )
            . '(?P<name>[a-z0-9][a-z0-9_-]*)'
            . '(?:\s+(?P<json>' . self::JSON_PATTERN . '))?'
            . '\s+(?P<self>/)?-->#';
    }

    /**
     * Matches the START of a delimiter — no JSON, no recursion, no bracket count.
     *
     * DER LOOKAHEAD AUF `\s` IST KEIN SCHMUCK: Der Blockparser des Kerns
     * verlangt hinter dem Blocknamen ein `\s+`. Ohne den Lookahead faende
     * dieses Muster auch `<!-- wp:areoi/column-->` — fuer WordPress kein
     * Block — und meldete ihn als „nicht getroffen", worauf ein Lauf abbraeche.
     *
     * DAS SIMPLE MUSTER. Es kann nichts als den Anfang eines Begrenzers finden
     * und irrt deshalb nicht dort, wo `pattern()` irren kann. Zwei Stellen
     * benutzen es: der Gegenzaehler in `rewrite()` und `count_blocks()`
     * (Aufgabe 10). Beide sehen damit dieselben Fundstellen — genau darum geht
     * es, wenn `rewrite()` sein eigenes Ergebnis gegenzaehlt.
     *
     * @param string $block_namespace Namespace including the trailing slash.
     */
    private static function candidate_pattern( string $block_namespace ): string {
        return '#<!--\s+(?P<close>/)?wp:' . preg_quote( $block_namespace, '#' )
            . '(?P<name>[a-z0-9][a-z0-9_-]*)(?=\s)#';
    }

    /**
     * Lists every delimiter start that the strict pattern did NOT match.
     *
     * Verglichen werden Byte-Offsets, nicht Zahlen: Beide Muster beginnen am
     * `<!--`, also steht an jedem Treffer des simplen Musters entweder auch ein
     * Treffer des strengen — oder eine Fundstelle, die die Migration
     * stillschweigend uebergangen haette.
     *
     * @param string $content   Post content, meta value or option value.
     * @param string $block_namespace Namespace including the trailing slash.
     * @return array<int, string> One snippet per missed delimiter.
     */
    private static function unmatched_delimiters( string $content, string $block_namespace ): array {
        $auswertung = self::candidate_analysis( $content, $block_namespace );

        return null === $auswertung ? [] : $auswertung['missed'];
    }

    /**
     * The number of opening delimiters the simple pattern finds.
     *
     * @param string $content         Post content, meta value or option value.
     * @param string $block_namespace Namespace including the trailing slash.
     * @return int|null Null when one of the two searches failed.
     */
    private static function candidate_openers( string $content, string $block_namespace ): ?int {
        $auswertung = self::candidate_analysis( $content, $block_namespace );

        return null === $auswertung ? null : $auswertung['openers'];
    }

    /**
     * Runs both patterns once and reports what the strict one missed.
     *
     * Verglichen werden Byte-Offsets, nicht Zahlen: Beide Muster beginnen am
     * `<!--`, also steht an jedem Treffer des simplen Musters entweder auch ein
     * Treffer des strengen — oder eine Fundstelle, die die Migration
     * stillschweigend uebergangen haette.
     *
     * AUSGENOMMEN SIND FUNDSTELLEN INNERHALB EINES TREFFERS. Steht der Text
     * `<!-- wp:areoi/…` in einem Attributwert, findet ihn das simple Muster
     * dort; das strenge ueberliest ihn als Teil einer JSON-Zeichenkette. Ohne
     * diese Wache meldete der Lauf eine „nicht getroffene" Fundstelle und
     * braeche ab, obwohl nichts fehlt.
     *
     * @param string $content         Post content, meta value or option value.
     * @param string $block_namespace Namespace including the trailing slash.
     * @return array{missed: array<int, string>, openers: int}|null
     */
    private static function candidate_analysis( string $content, string $block_namespace ): ?array {
        $found = [];
        $hits  = [];

        // BEIDE SUCHLAEUFE WERDEN AUF PCRE-FEHLER GEPRUEFT. Scheitert der
        // erste, kennt diese Methode keine Fundstelle und meldete pflichtgemaess
        // „nichts uebersehen" — die glatteste aller Luegen. Scheitert der
        // zweite, hielte sie JEDE Fundstelle fuer uebersehen und ertraenkte den
        // Bericht in Rauschen. In beiden Faellen wird der Fehler gemeldet und
        // nichts zurueckgegeben; der Befund kommt aus dem Behaelter.
        if ( false === preg_match_all( self::candidate_pattern( $block_namespace ), $content, $found, PREG_OFFSET_CAPTURE ) ) {
            self::note_pcre_error( 'candidate_analysis()' );

            return null;
        }

        if ( false === preg_match_all( self::pattern( $block_namespace ), $content, $hits, PREG_OFFSET_CAPTURE ) ) {
            self::note_pcre_error( 'candidate_analysis()' );

            return null;
        }

        $matched = [];
        $spans   = [];

        foreach ( $hits[0] as $hit ) {
            $start             = (int) $hit[1];
            $matched[ $start ] = true;
            $spans[]           = [ $start, $start + strlen( (string) $hit[0] ) ];
        }

        $missed  = [];
        $openers = 0;

        foreach ( $found[0] as $index => $hit ) {
            $offset    = (int) $hit[1];
            $innerhalb = false;

            foreach ( $spans as $span ) {
                if ( $offset > $span[0] && $offset < $span[1] ) {
                    $innerhalb = true;

                    break;
                }
            }

            if ( $innerhalb ) {
                continue;
            }

            if ( '/' !== (string) ( $found['close'][ $index ][0] ?? '' ) ) {
                ++$openers;
            }

            if ( isset( $matched[ $offset ] ) ) {
                continue;
            }

            $end    = strpos( $content, '-->', $offset );
            $length = false === $end ? 160 : min( 160, $end - $offset + 3 );

            $missed[] = substr( $content, $offset, $length );
        }

        return [
            'missed'  => $missed,
            'openers' => $openers,
        ];
    }

    /**
     * The finding for a delimiter whose attribute JSON cannot be read.
     *
     * DIE MELDUNGEN STEHEN AN EINER STELLE, weil zwei Wege sie erzeugen:
     * `rewrite()` beim Umschreiben und `findings()` (Aufgabe 10) beim blossen
     * Hinsehen. Das Inventar ruft nur noch `findings()` — es braucht die
     * Beanstandungen, nicht den umgeschriebenen Text. Waeren die Meldungen
     * zweimal ausgeschrieben, driftete der Wortlaut auseinander, und ein
     * Befund saehe im Scan anders aus als im Lauf.
     *
     * @param string $name Bare block name.
     * @param string $json The unreadable JSON, verbatim.
     */
    private static function unreadable_message( string $name, string $json ): string {
        return sprintf(
            'Attribut-JSON nicht lesbar an wp:%s%s: %s',
            self::OLD_NAMESPACE,
            $name,
            $json
        );
    }

    /**
     * The finding for a closing delimiter that carries attribute JSON.
     *
     * @param string $name Bare block name.
     * @param string $json The attribute JSON, verbatim.
     */
    private static function closing_json_message( string $name, string $json ): string {
        return sprintf(
            'Schliessender Begrenzer mit Attribut-JSON an wp:%s%s — nicht umgeschrieben, damit nichts verlorengeht: %s',
            self::OLD_NAMESPACE,
            $name,
            $json
        );
    }

    /**
     * The finding for a foreign attribute named `blockstudio`.
     *
     * @param string $name Bare block name.
     * @param string $json The delimiter JSON, verbatim.
     */
    private static function foreign_container_message( string $name, string $json ): string {
        return sprintf(
            'Fremdes Attribut `blockstudio` ohne Attribut-Container an wp:%s%s: %s',
            self::OLD_NAMESPACE,
            $name,
            $json
        );
    }

    /**
     * The finding for attribute JSON that carries the same key twice.
     *
     * @param string $name    Bare block name.
     * @param int    $raw     Keys counted in the raw JSON.
     * @param int    $decoded Keys left after decoding.
     * @param string $json    The delimiter JSON, verbatim.
     */
    private static function duplicate_key_message( string $name, int $raw, int $decoded, string $json ): string {
        return sprintf(
            'Doppelter Attributschluessel an wp:%s%s: %d Schluessel in der JSON, %d nach dem Lesen: %s',
            self::OLD_NAMESPACE,
            $name,
            $raw,
            $decoded,
            $json
        );
    }

    /**
     * The finding for a number literal that loses digits when it is read.
     *
     * @param string $name      Bare block name.
     * @param string $literal   The number as it stands in the content.
     * @param string $reencoded The number as PHP would write it back.
     */
    private static function precision_message( string $name, string $literal, string $reencoded ): string {
        return sprintf(
            'Zahl verliert Stellen an wp:%s%s: %s wird beim Lesen zu %s',
            self::OLD_NAMESPACE,
            $name,
            $literal,
            $reencoded
        );
    }

    /**
     * The finding for a number literal that changes its JSON type when it is read.
     *
     * Der Wortlaut sagt ausdruecklich TYP und nicht Stellen: Es geht keine
     * Ziffer verloren, und wer die Zeile liest, soll nicht nach einem
     * Rundungsfehler suchen, den es nicht gibt.
     *
     * @param string $name      Bare block name.
     * @param string $literal   The number as it stands in the content.
     * @param string $reencoded The number as PHP would write it back.
     */
    private static function number_shape_message( string $name, string $literal, string $reencoded ): string {
        return sprintf(
            'Zahl wechselt den JSON-Typ an wp:%s%s: %s wird beim Lesen zu %s — gleiche Ziffern, aus Gleitkomma wird Ganzzahl',
            self::OLD_NAMESPACE,
            $name,
            $literal,
            $reencoded
        );
    }

    /**
     * The finding for a container `json_encode()` refuses to write.
     *
     * @param string $name Bare block name.
     * @param string $why  `json_last_error_msg()` right after the failed encode.
     * @param string $json The delimiter JSON, verbatim.
     */
    private static function unencodable_message( string $name, string $why, string $json ): string {
        return sprintf(
            'Attribut-Container nicht schreibbar an wp:%s%s (%s): %s',
            self::OLD_NAMESPACE,
            $name,
            $why,
            $json
        );
    }

    /**
     * The finding for a container the WordPress block parser cannot read back.
     *
     * @param string $name Bare block name.
     * @param string $json The delimiter JSON, verbatim.
     */
    private static function unreadable_container_message( string $name, string $json ): string {
        return sprintf(
            'Attribut-Container zu tief fuer den Blockparser an wp:%s%s: geschrieben, aber mit der Standardtiefe %d nicht mehr lesbar: %s',
            self::OLD_NAMESPACE,
            $name,
            self::PARSER_DEPTH,
            $json
        );
    }

    /**
     * The finding for a delimiter the strict pattern did not match.
     *
     * @param string $snippet The delimiter start, as found in the content.
     */
    private static function unmatched_message( string $snippet ): string {
        return sprintf(
            'Blockbegrenzer nicht getroffen und deshalb nicht migriert: %s',
            $snippet
        );
    }

    /**
     * Tells whether a `blockstudio` value really is the attribute container.
     *
     * DIE EINE DEFINITION, AN DIE SICH BEIDE SEITEN HALTEN. `rewrite()`
     * ueberspringt einen Begrenzer als „schon verpackt" genau dann, wenn diese
     * Pruefung zustimmt, und `attribute_map()` packt genau dann aus. Gaebe es
     * zwei Fassungen — hier `property_exists()`, dort ein Blick auf
     * `attributes` —, dann wuerde ein Begrenzer mit einem FREMDEN Attribut
     * namens `blockstudio` still uebersprungen und trotzdem als flacher Block
     * gelesen: unmigriert, nach S-4 mit Defaults gerendert, und im Abgleich
     * unsichtbar.
     *
     * Akzeptiert wird beides — Objekt und Array —, weil `rewrite()` OHNE assoc
     * dekodiert (Objekte bleiben Objekte) und `attribute_map()` MIT assoc.
     *
     * @param mixed $value Value of the `blockstudio` key, or null when absent.
     */
    private static function is_attribute_container( mixed $value ): bool {
        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }

        if ( ! is_array( $value ) || ! array_key_exists( 'attributes', $value ) ) {
            return false;
        }

        return is_object( $value['attributes'] ) || is_array( $value['attributes'] );
    }

    /**
     * The attribute keys that never go into the container.
     *
     * DIE EINE FASSUNG DIESER LISTE STEHT AUF `Legacy` (Abschlussbefund W3);
     * hier wird sie gelesen, nie nachgebaut. `lock` und `metadata` sind
     * Core-Buchfuehrung: Der Editor und `WP_Block` lesen sie OBEN im
     * `attrs`-Objekt, und `Legacy::lift_attributes()` hebt sie deshalb
     * ausdruecklich nicht.
     *
     * WAS PASSIERT, WENN MAN SIE DOCH HEBT: Der Editor verliert den
     * Blocknamen, die Musterbindung und die Kategorien des Blocks. Gemessen an
     * den drei Produktivdatenbanken: 2064 von 30 674 Bloecken tragen
     * `metadata`, also jeder vierzehnte. Im Frontend faellt es nicht auf — der
     * DOM-Diff der Abnahme saehe davon nichts.
     *
     * @return array<int, string>
     */
    private static function reserved_keys(): array {
        return Legacy::RESERVED;
    }

    /**
     * The attribute set a delimiter really carries, in a comparable order.
     *
     * BEIDE SPEICHERFORMEN WERDEN AUF DIESELBE FORM GEBRACHT, sonst vergliche
     * Bein 1 Aepfel mit Birnen: Der Bestandsblock traegt alles flach, der
     * migrierte die Vertragsattribute im Container und die reservierten
     * Schluessel daneben.
     *
     * Die Reihenfolge ist festgelegt: erst die Attribute des Containers
     * beziehungsweise die nicht reservierten Schluessel, danach die
     * reservierten — jeweils in ihrer urspruenglichen Reihenfolge. Nur so
     * stimmen vorher und nachher auch in der Schluesselreihenfolge ueberein,
     * die `reconcile_attributes()` vergleicht.
     *
     * @param array<string, mixed> $data Decoded attribute JSON of one delimiter.
     * @return array<string, mixed>
     */
    private static function effective_attributes( array $data ): array {
        $effective = [];

        // Die flache Ebene zuerst, in ihrer Reihenfolge. Nach dem Lauf steht
        // dort dasselbe wie davor — der Container kommt nur dazu.
        foreach ( $data as $key => $value ) {
            if ( 'blockstudio' === (string) $key ) {
                continue;
            }

            $effective[ $key ] = $value;
        }

        // Und was NUR im Container steht: ein Block, den der Editor nach der
        // Migration gespeichert hat, traegt seine Feldwerte dort und sonst
        // nirgends. Ohne diesen Zweig waere seine Karte leer.
        if ( self::is_attribute_container( $data['blockstudio'] ?? null ) ) {
            foreach ( (array) $data['blockstudio']['attributes'] as $key => $value ) {
                if ( ! array_key_exists( $key, $effective ) ) {
                    $effective[ $key ] = $value;
                }
            }
        }

        return $effective;
    }

    /**
     * Maps every block of a content string onto its attributes.
     *
     * DER SCHLUESSEL IST DIE POSITION, NICHT DIE `block_id`. `block_id` ist
     * nicht eindeutig: Beim Duplizieren im Editor setzt das Alt-Plugin sie nur
     * fuer den obersten Block und die erste innerBlocks-Ebene zurueck
     * (assets/js/areoi.js, Zeilen 38-46), tiefer liegende Bloecke behalten ihre
     * alte ID. Eine Karte `block_id => Attribute` verlore den zweiten der
     * beiden Bloecke stillschweigend — und der Wertevergleich von Bein 1
     * prueft danach nur noch einen von ihnen.
     *
     * Der Schluessel lautet `<laufende Nummer>:<block_id>`. Gezaehlt wird jeder
     * oeffnende und selbstschliessende Begrenzer in DOKUMENTREIHENFOLGE ueber
     * BEIDE Namensraeume, ab 1 — nur so steht derselbe Block vor und nach der
     * Migration unter demselben Schluessel, auch in einem Traeger, in dem
     * waehrend eines Teillaufs beide Speicherformen nebeneinander liegen.
     *
     * Die laufende Nummer zaehlt auch dann weiter, wenn ein Begrenzer keine
     * lesbare Attribut-JSON hat und deshalb nicht in die Karte kommt. Wuerde
     * sie das nicht, verschoebe ein einziger kaputter Begrenzer die Nummern
     * aller folgenden Bloecke und der Abgleich meldete lauter Phantomverluste.
     *
     * BEIDE SPEICHERFORMEN werden gelesen: die flache des Bestands und die
     * verpackte nach der Migration. Genau das macht die Karte zum Werkzeug von
     * Bein 1 — sie wird vor und nach dem Lauf erhoben und muss identisch sein,
     * obwohl die Speicherform sich planmaessig geaendert hat.
     *
     * @param string $content Post content, meta value or option value.
     * @return array<string, array<string, mixed>>
     */
    private static function attribute_map( string $content ): array {
        $hits = [];

        foreach ( [ self::OLD_NAMESPACE, self::NEW_NAMESPACE ] as $namespace ) {
            $found = [];

            $count = preg_match_all( self::pattern( $namespace ), $content, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

            // false ist NICHT null Treffer. Ohne diese Unterscheidung waere
            // eine leere Attributkarte aus einem Regex-Fehler von einer leeren
            // Attributkarte aus einem blockfreien Traeger ununterscheidbar —
            // und Bein 1 vergliche zwei leere Karten miteinander.
            if ( false === $count ) {
                self::note_pcre_error( 'attribute_map()' );

                continue;
            }

            if ( 0 === $count ) {
                continue;
            }

            foreach ( $found as $match ) {
                $hits[ (int) $match[0][1] ] = $match;
            }
        }

        // Dokumentreihenfolge ueber beide Namensraeume hinweg.
        ksort( $hits );

        $map   = [];
        $index = 0;

        foreach ( $hits as $match ) {
            if ( '/' === (string) ( $match['close'][0] ?? '' ) ) {
                continue;
            }

            ++$index;

            $json = (string) ( $match['json'][0] ?? '' );

            // `JSON_BIGINT_AS_STRING`: Ohne das Flag wird eine Ganzzahl
            // jenseits von PHP_INT_MAX zu einem Float, und die Karte laese vor
            // wie nach dem Lauf denselben Float — der Ziffernverlust waere
            // unsichtbar. Dasselbe Flag steht in `rewrite()`; beide muessen
            // dieselbe Zahl lesen, sonst vergleicht Bein 1 zwei Formen.
            //
            // `JSON_DEPTH` statt 512: Die verpackte Form, die hier nach dem
            // Lauf ankommt, ist zwei Ebenen tiefer als die flache davor.
            $data = '' === $json ? [] : json_decode( $json, true, self::JSON_DEPTH, JSON_BIGINT_AS_STRING );

            if ( ! is_array( $data ) ) {
                // Unlesbar. `rewrite()` hat das bereits beanstandet; hier zaehlt
                // nur, dass die laufende Nummer nicht stehen bleibt.
                continue;
            }

            // DIESELBE PRUEFUNG WIE IN `rewrite()`. Ein fremdes Attribut namens
            // `blockstudio` ist kein Container und wird deshalb auch hier nicht
            // ausgepackt — sonst laese die Karte etwas anderes, als der
            // Umschreiber schreibt.
            //
            // `effective_attributes()` bringt beide Speicherformen auf dieselbe
            // Form und dieselbe Schluesselreihenfolge; die reservierten
            // Schluessel stehen in beiden Faellen hinten.
            $data = self::effective_attributes( $data );

            $block_id = isset( $data['block_id'] ) && is_string( $data['block_id'] ) && '' !== $data['block_id']
                ? $data['block_id']
                : '-';

            $map[ sprintf( '%d:%s', $index, $block_id ) ] = $data;
        }

        return $map;
    }

    /**
     * Counts the keys of the RAW JSON, on every level.
     *
     * Gezaehlt wird jede vollstaendige JSON-Zeichenkette, auf die — nach
     * beliebigem Leerraum — ein Doppelpunkt folgt. In gueltiger JSON steht ein
     * Doppelpunkt hinter genau einer Sache: einem Schluessel. Auf einen Wert
     * folgt `,`, `}` oder `]`, nie ein Doppelpunkt.
     *
     * Der Zweig `JSON_STRING` ist der Grund, warum ein Doppelpunkt IN einem
     * Textattribut nicht mitzaehlt: Die Zeichenkette wird als Ganzes gelesen,
     * samt ihrer Escapes.
     *
     * @param string $json Attribute JSON, verbatim from the delimiter.
     * @return int|null Key count, or null when the search itself failed.
     */
    private static function raw_key_count( string $json ): ?int {
        $matches = [];
        $count   = preg_match_all( '#' . self::JSON_STRING . '\s*+:#', $json, $matches );

        if ( false === $count ) {
            self::note_pcre_error( 'raw_key_count()' );

            return null;
        }

        return $count;
    }

    /**
     * Counts the keys that survived `json_decode()`, on every level.
     *
     * Listen zaehlen NICHT mit: Dekodiert wird ohne assoc, ein JSON-Array ist
     * danach ein PHP-Array ohne Schluessel im Sinne dieser Zaehlung, ein
     * JSON-Objekt ein `stdClass`.
     *
     * @param mixed $value Decoded attribute value.
     */
    private static function decoded_key_count( mixed $value ): int {
        $count = 0;

        if ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $item ) {
                $count += 1 + self::decoded_key_count( $item );
            }

            return $count;
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                $count += self::decoded_key_count( $item );
            }
        }

        return $count;
    }

    /**
     * The significant digits of a number literal, without any formatting.
     *
     * VORZEICHEN, PUNKT, EXPONENT UND RANDNULLEN FALLEN WEG. Nur so ist `1e5`
     * dasselbe wie `100000` und `1.50` dasselbe wie `1.5` — verschiedene
     * Schreibweisen derselben Zahl, bei denen keine Ziffer verloren geht. Wer
     * hier die Texte direkt vergleicht, beanstandet Bestand, dem nichts fehlt.
     *
     * SIE BEANTWORTET NUR DIE ZIFFERNFRAGE. Ob ein Literal beim Rundlauf seinen
     * JSON-TYP wechselt, sieht sie ausdruecklich nicht — dafuer steht
     * `drops_fraction()` daneben. Ohne die zweite Frage lief `1e5` hier ohne
     * jede Beanstandung durch, und der Attributvergleich meldete den Typwechsel
     * erst NACH dem Schreiben.
     *
     * @param string $number A JSON number literal.
     */
    private static function significant_digits( string $number ): string {
        $mantissa = explode( 'e', strtolower( $number ) )[0];

        return trim( str_replace( [ '-', '+', '.' ], '', $mantissa ), '0' );
    }

    /**
     * Whether a number literal loses its float shape on the way back.
     *
     * DER FALL, DEN DIE ZIFFERNPRUEFUNG DURCHWINKT UND DER ATTRIBUTVERGLEICH
     * TROTZDEM MELDET. Gemessen auf PHP 8.4: `1e5` wird beim Rundlauf zu
     * `100000`, `100.0` zu `100`, `1.0` zu `1`. Die Ziffernfolge stimmt in allen
     * drei Faellen — `significant_digits()` sieht keinen Unterschied —, aber aus
     * einer JSON-Gleitkommazahl ist eine JSON-Ganzzahl geworden.
     *
     * WARUM DAS EINE BEANSTANDUNG IST. Regel 1.5 des Kompatibilitaetsvertrags
     * haelt Wert UND Typ fest, und `reconcile_attributes()` (Aufgabe 10)
     * vergleicht typgleich. Bliebe der Fall hier unbeanstandet, schriebe der
     * Lauf den Begrenzer um, Bein 1 meldete danach eine Abweichung, und der
     * Befehl endete mit dem Hinweis auf das Backup — NACH dem Schreiben, auf
     * Bestand, dem nichts fehlt. Als Beanstandung faellt er wie jede andere VOR
     * dem Schreiben auf, und der Begrenzer bleibt unveraendert stehen.
     *
     * ERKANNT WIRD ER AN DER SCHREIBWEISE, nicht am PHP-Typ: Das Literal der
     * Eingabe traegt einen Dezimalpunkt oder ein Exponentzeichen, die
     * zurueckgeschriebene Form keines von beidem. `0.15` aus dem Farbobjekt
     * (Regel 1.6) kommt als `0.15` zurueck und faellt nicht darunter; `1.50`
     * kommt als `1.5` zurueck und ebenfalls nicht. Eine Ganzzahl wie `0` oder
     * `42` traegt schon in der Eingabe keinen Punkt und wird gar nicht erst
     * geprueft.
     *
     * @param string $literal   The number as it stands in the content.
     * @param string $reencoded The number as PHP would write it back.
     */
    private static function drops_fraction( string $literal, string $reencoded ): bool {
        $was_float = str_contains( $literal, '.' ) || str_contains( strtolower( $literal ), 'e' );

        if ( ! $was_float ) {
            return false;
        }

        return ! str_contains( $reencoded, '.' ) && ! str_contains( strtolower( $reencoded ), 'e' );
    }

    /**
     * Lists every number literal that does not survive being read.
     *
     * DIE PRUEFUNG GILT DEM RUNDLAUF, NICHT DEM TYP. Gleitkommazahlen sind im
     * Bestand legitim: Das Farbobjekt aus Regel 1.6 des
     * Kompatibilitaetsvertrags traegt "a": 0.15 im rgb-Schluessel und dieselben
     * Werte in hsv und hsl. Eine pauschale Beanstandung jedes Floats traefe
     * JEDEN Block mit Hintergrundfarbe — auf einer Bestandsinstallation ueber tausend.
     *
     * Der Schaden ist enger und lautlos: Ein Literal mit mehr Stellen, als ein
     * Double tragen kann, verliert die ueberzaehligen Stellen schon beim
     * DEKODIEREN. Aus 0.1234567890123456789 wird 0.12345678901234568, und der
     * Abgleich sieht es nicht, weil beide Seiten denselben Float lesen.
     *
     * Zeichenketten werden vorher herausgenommen; sonst zaehlte eine Ziffer in
     * einem Textattribut als Zahl. Was danach an Ziffern uebrig bleibt, ist ein
     * Zahlenliteral — `true`, `false` und `null` tragen keine.
     *
     * Die grosse GANZZAHL steht hier nicht: `JSON_BIGINT_AS_STRING` haelt sie
     * als Zeichenkette fest, jede Ziffer bleibt stehen, und der Vergleich
     * gegen das Literal geht auf.
     *
     * ZWEI ARTEN VON BEFUND, und beide gehoeren hierher. `precision` ist der
     * Stellenverlust oben. `shape` ist der TYPWECHSEL: ein Literal in Bruch-
     * oder Exponentschreibweise, dessen Wert ganzzahlig ist — `1e5`, `100.0`,
     * `1.0`. Es behaelt jede Ziffer und wechselt trotzdem den JSON-Typ, und
     * genau deshalb winkte die Ziffernpruefung es durch, waehrend der
     * typgleiche Attributvergleich in Bein 1 danach eine Abweichung meldete.
     * Herleitung in `drops_fraction()`.
     *
     * JE LITERAL HOECHSTENS EIN BEFUND. Die Ziffernpruefung steigt mit
     * `continue` aus; sonst bekaeme ein Literal, das beides tut, zwei
     * Beanstandungen fuer denselben Schaden.
     *
     * @param string $json Attribute JSON, verbatim from the delimiter.
     * @return array<int, array{0: string, 1: string, 2: string}> Literal, what PHP makes of it, and the kind of finding: `precision` or `shape`.
     */
    private static function lossy_numbers( string $json ): array {
        $stripped = preg_replace( '#' . self::JSON_STRING . '#', '""', $json );

        if ( ! is_string( $stripped ) ) {
            self::note_pcre_error( 'lossy_numbers()' );

            return [];
        }

        $numbers = [];
        $count   = preg_match_all( '#-?(?:0|[1-9][0-9]*)(?:\.[0-9]++)?(?:[eE][+-]?[0-9]++)?#', $stripped, $numbers );

        if ( false === $count ) {
            self::note_pcre_error( 'lossy_numbers()' );

            return [];
        }

        $lossy = [];

        foreach ( $numbers[0] as $literal ) {
            $literal = (string) $literal;
            $value   = json_decode( $literal, false, 2, JSON_BIGINT_AS_STRING );

            // Der Bigint-Zweig: `JSON_BIGINT_AS_STRING` liefert die Ziffernfolge
            // unveraendert zurueck. Weicht sie trotzdem ab, ist etwas
            // passiert, das niemand entworfen hat.
            if ( is_string( $value ) ) {
                if ( $value !== $literal ) {
                    $lossy[] = [ $literal, $value, 'precision' ];
                }

                continue;
            }

            $reencoded = self::encode_json( $value );

            // INF und NAN. `1e400` dekodiert zu INF, und INF laesst sich nicht
            // wieder als JSON schreiben — der Wert waere nach dem Lauf weg.
            if ( ! is_string( $reencoded ) ) {
                $lossy[] = [ $literal, '(nicht kodierbar)', 'precision' ];

                continue;
            }

            if ( self::significant_digits( $literal ) !== self::significant_digits( $reencoded ) ) {
                $lossy[] = [ $literal, $reencoded, 'precision' ];

                continue;
            }

            // DER TYPWECHSEL, und er steht NACH der Ziffernpruefung: Ist die
            // Ziffernfolge schon verloren, ist das die schwerere Auskunft, und
            // zwei Beanstandungen fuer dasselbe Literal helfen niemandem.
            if ( self::drops_fraction( $literal, $reencoded ) ) {
                $lossy[] = [ $literal, $reencoded, 'shape' ];
            }
        }

        return $lossy;
    }

    /**
     * Lists what is wrong with the attribute JSON of ONE delimiter.
     *
     * DIE BEIDEN PRUEFUNGEN, DIE BEIDE WEGE FAHREN. `rewrite()` benutzt sie, um
     * einen Begrenzer stehen zu lassen, statt ihn beschaedigt zu schreiben;
     * `findings()` (Aufgabe 10) benutzt dieselbe Methode, damit das Inventar
     * denselben Befund VOR dem ersten Schreibzugriff meldet. Waeren die
     * Meldungen zweimal ausgeschrieben, driftete der Wortlaut auseinander.
     *
     * Was hier NICHT steht, ist die Schreibbarkeit des Containers: Sie haengt
     * am `json_encode()` des Umschreibers und ist eine Aussage ueber den
     * Schreibvorgang, nicht ueber den Inhalt — dieselbe Trennung wie beim
     * Zaehlabgleich, den `findings()` ebenfalls nicht kennt.
     *
     * @param string $name    Bare block name.
     * @param string $json    Attribute JSON, verbatim from the delimiter.
     * @param object $decoded The same JSON, decoded without assoc.
     * @return array<int, string> Human readable findings; empty means "nothing to report".
     */
    private static function attribute_findings( string $name, string $json, object $decoded ): array {
        $findings = [];
        $raw_keys = self::raw_key_count( $json );

        if ( null !== $raw_keys ) {
            $kept = self::decoded_key_count( $decoded );

            // EIN DOPPELTER SCHLUESSEL IST SYNTAKTISCH GUELTIGE JSON.
            // `json_decode()` behaelt den letzten Wert, der erste ist weg — und
            // ohne diese Zaehlung bliebe `errors` leer und der Abgleich gruen,
            // weil beide Karten dieselbe JSON dekodieren und denselben Verlust
            // uebernehmen.
            if ( $raw_keys !== $kept ) {
                $findings[] = self::duplicate_key_message( $name, $raw_keys, $kept, $json );
            }
        }

        // ZWEI WORTLAUTE, EINE QUELLE. `lossy_numbers()` sagt je Literal, was
        // ihm fehlt: Stellen oder der Typ. Beides in denselben Satz zu giessen,
        // liesse den Leser raten, welcher der beiden Schaeden vorliegt.
        foreach ( self::lossy_numbers( $json ) as $pair ) {
            $findings[] = 'shape' === $pair[2]
                ? self::number_shape_message( $name, $pair[0], $pair[1] )
                : self::precision_message( $name, $pair[0], $pair[1] );
        }

        return $findings;
    }

    /**
     * Encodes block attributes the way the block editor does.
     *
     * NACHBAU VON `serialize_block_attributes()`, absichtlich ohne Delegation:
     * Der Core-Helfer existiert nur mit geladenem WordPress, und der Umschreiber
     * muss ohne WordPress messbar bleiben. Die SECHS Ersetzungen stammen
     * unveraendert von dort (wp-includes/blocks.php) und sind in dieser
     * Reihenfolge zu lesen:
     *
     *   1. der verdoppelte Backslash der JSON-Escape. Bliebe er stehen,
     *      verschmilzt er mit dem folgenden Anfuehrungszeichen: Regel 6 griffe
     *      dann auf das falsche Zeichenpaar und lieferte unlesbare JSON. Diese
     *      Regel MUSS zuerst laufen.
     *   2. `--` — es wuerde den HTML-Blockkommentar vorzeitig beenden und den
     *      Content der Seite zerstoeren.
     *   3./4./5. `<`, `>` und `&` — sie machten aus einem Attributwert Markup.
     *   6. das maskierte Anfuehrungszeichen, damit der Wert nicht aus der JSON
     *      ausbricht.
     *
     * KEINE DIESER REGELN DARF ENTFALLEN, auch nicht, um einen Test gruen zu
     * bekommen. Der Core ersetzt mit einem einzigen `strtr()` gleichzeitig;
     * hintereinander laufende `preg_replace()`-Aufrufe kommen hier zum selben
     * Ergebnis, weil keine Ersatzzeichenkette ein Zeichen enthaelt, das eine
     * spaetere Regel noch einmal traefe. Nachgemessen wird das in Aufgabe 15
     * gegen den echten Core-Helfer.
     *
     * SIE GIBT `false` HERAUS, STATT ZU CASTEN. `json_encode()` scheitert
     * messbar — bei zu tiefer Verschachtelung, bei INF/NAN, bei ungueltigem
     * UTF-8. Ein `(string)` darauf macht daraus den LEERSTRING, und der
     * Begrenzer stuende danach ohne jedes Attribut da: `wrapped` hochgezaehlt,
     * `errors` leer, vollstaendiger und lautloser Attributverlust. Gemessen an
     * PHP 8.4 trat das mit der Standardtiefe 512 bereits ab 510 Ebenen im
     * Attributwert ein, weil der Container zwei Ebenen drauflegt. Der Aufrufer
     * MUSS auf `false` pruefen und den Begrenzer dann unveraendert lassen —
     * dieselbe Behandlung wie bei unlesbarer Eingabe-JSON.
     *
     * @param mixed $attributes Attributes as decoded from the delimiter.
     * @return string|false The serialized attributes, or false when they cannot be encoded.
     */
    public static function serialize_attributes( mixed $attributes ): string|false {
        $json = self::encode_json( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, self::PARSER_DEPTH );

        if ( ! is_string( $json ) ) {
            return false;
        }

        $json = (string) preg_replace( '/\\\\\\\\/', '\\\\u005c', $json );
        $json = (string) preg_replace( '/--/', '\\\\u002d\\\\u002d', $json );
        $json = (string) preg_replace( '/</', '\\\\u003c', $json );
        $json = (string) preg_replace( '/>/', '\\\\u003e', $json );
        $json = (string) preg_replace( '/&/', '\\\\u0026', $json );
        $json = (string) preg_replace( '/\\\\"/', '\\\\u0022', $json );

        return $json;
    }

    /**
     * Rewrites every `areoi/*` delimiter of a content string.
     *
     * @param string $content Post content, meta value or option value.
     * @return array{
     *     content: string,
     *     renamed: int,
     *     wrapped: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     attributes: array<string, array<string, mixed>>
     * }
     */
    public static function rewrite( string $content ): array {
        $renamed = 0;
        $wrapped = 0;
        $skipped = 0;
        $openers = 0;
        $errors  = [];

        $rewritten = preg_replace_callback(
            self::pattern( self::OLD_NAMESPACE ),
            static function ( array $hit ) use ( &$renamed, &$wrapped, &$skipped, &$openers, &$errors ): string {
                $name = strtolower( $hit['name'] );
                $new  = self::NEW_NAMESPACE . $name;

                if ( isset( $hit['close'] ) && '/' === $hit['close'] ) {
                    /*
                     * Das Muster des Kerns laesst auch an einem SCHLIESSENDEN
                     * Begrenzer eine Attribut-JSON zu. Sie kommentarlos
                     * wegzuwerfen waere stiller Datenverlust — der Begrenzer
                     * bleibt deshalb stehen und ein Mensch sieht ihn an.
                     */
                    if ( isset( $hit['json'] ) && '' !== $hit['json'] ) {
                        $errors[] = self::closing_json_message( $name, (string) $hit['json'] );

                        return $hit[0];
                    }

                    ++$renamed;

                    return '<!-- /wp:' . $new . ' -->';
                }

                // Ab hier ist es ein oeffnender oder selbstschliessender
                // Begrenzer. Der Zaehler laeuft VOR jeder Fallunterscheidung,
                // damit auch der kaputte Fall im Gegenzaehler auftaucht.
                ++$openers;

                $suffix = ( isset( $hit['self'] ) && '/' === $hit['self'] ) ? ' /-->' : ' -->';
                $json   = isset( $hit['json'] ) ? $hit['json'] : '';

                if ( '' === $json ) {
                    ++$renamed;

                    return '<!-- wp:' . $new . $suffix;
                }

                // Objekte bleiben Objekte: `json_decode()` OHNE assoc, sonst
                // wuerde ein leeres `{}` beim Zurueckschreiben zur leeren Liste.
                //
                // `JSON_BIGINT_AS_STRING` ist kein Feinschliff: Ohne das Flag
                // wird eine Ganzzahl jenseits von PHP_INT_MAX zu einem Float,
                // und `serialize_attributes()` schriebe den Float zurueck — aus
                // 12345678901234567890 wuerde 1.2345678901234567e+19. Der
                // Abgleich saehe es nicht, weil beide Seiten denselben Float
                // lesen. Mit dem Flag bleibt jede Ziffer erhalten; der Wert
                // steht danach als Zeichenkette im Begrenzer. Die Typaenderung
                // ist der niedrigste Preis, den PHP anbietet — verlorene
                // Ziffern sind nicht reparabel.
                //
                // `JSON_DEPTH` statt 512, damit Auspacken und Einpacken
                // denselben Spielraum haben; siehe die Konstante.
                $decoded = json_decode( $json, false, self::JSON_DEPTH, JSON_BIGINT_AS_STRING );

                if ( null === $decoded || ! is_object( $decoded ) ) {
                    $errors[] = self::unreadable_message( $name, $json );

                    return $hit[0];
                }

                if ( property_exists( $decoded, 'blockstudio' ) ) {
                    // DIESELBE BEDINGUNG WIE IN `attribute_map()`, und das ist
                    // der ganze Punkt: Dort wird nur ausgepackt, wenn
                    // `blockstudio` ein Container mit dem Unterschluessel
                    // `attributes` ist. Fragte diese Stelle bloss, OB der
                    // Schluessel da ist, gingen die beiden auseinander — ein
                    // Begrenzer mit einem fremden Attribut dieses Namens (null,
                    // String, Zahl, halber Container) wuerde still
                    // uebersprungen, bliebe unmigriert, renderte danach mit
                    // Defaults (S-4), und die Attributkarte laese ihn weiter
                    // als flachen Block. Der Abgleich saehe nichts.
                    if ( ! self::is_attribute_container( $decoded->blockstudio ) ) {
                        $errors[] = self::foreign_container_message( $name, $json );

                        return $hit[0];
                    }

                    // Bereits verpackt (Regel M3). Der Begrenzer wird nur
                    // umbenannt und die JSON Zeichen fuer Zeichen stehen
                    // gelassen — MIT EINER AUSNAHME: Steht im Container noch
                    // der alte Blockname, muss er mit. Blockstudio liest genau
                    // diesen Namen zuerst (block.php:1942,
                    // `$attributes['blockstudio']['name'] ??`); bliebe er auf
                    // `areoi/*`, renderte der Block nichts mehr, sobald der
                    // Legacy-Alias faellt — und kein Bein der Abnahme saehe es,
                    // weil weder Zaehlung noch Attributvergleich den Namen
                    // anfassen.
                    ++$skipped;

                    $container_name = $decoded->blockstudio->name ?? null;

                    if ( ! is_string( $container_name ) || ! str_starts_with( $container_name, self::OLD_NAMESPACE ) ) {
                        return '<!-- wp:' . $new . ' ' . $json . $suffix;
                    }

                    $renamed_container                    = $decoded;
                    $renamed_container->blockstudio->name = $new;

                    $rewritten_json = self::serialize_attributes( $renamed_container );

                    if ( ! is_string( $rewritten_json ) ) {
                        $errors[] = self::unencodable_message( $name, json_last_error_msg(), $json );

                        return $hit[0];
                    }

                    return '<!-- wp:' . $new . ' ' . $rewritten_json . $suffix;
                }

                // ZWEI VERLUSTE, DIE DAS DEKODIEREN SCHON HINTER SICH HAT:
                // ein doppelter Schluessel (der erste Wert ist weg) und ein
                // Zahlenliteral mit zu vielen Stellen (die ueberzaehligen sind
                // weg). Beides ueberlebte den Abgleich unsichtbar, weil beide
                // Seiten dieselbe JSON dekodieren. Der Begrenzer bleibt
                // deshalb stehen — genau wie bei unlesbarer JSON.
                $reported = self::attribute_findings( $name, $json, $decoded );

                if ( [] !== $reported ) {
                    foreach ( $reported as $message ) {
                        $errors[] = $message;
                    }

                    return $hit[0];
                }

                // KOPIEREN, NICHT VERSCHIEBEN — und das ist der Wortlaut der
                // harten Regel 2: „gleiche Schluessel, gleiche Werte, gleiche
                // Reihenfolge, EIN CONTAINER MEHR".
                //
                // Blockstudio baut seine Template-Variable `$block` aus den
                // Attributen, die OBEN stehen, und zwar BEVOR es den Container
                // hineinmischt (block.php:2017-2019). Danach raeumt es
                // `$attributes` auf und wirft jeden Schluessel weg, der kein
                // registriertes Feld ist (block.php:1890-1901). `className`,
                // `anchor` und `align` sind keine Felder — sie kommen aus
                // `supports` und vom Kern. Wer sie in den Container
                // VERSCHIEBT, verliert sie an beiden Stellen, und im Markup
                // fehlen danach Zusatzklassen und HTML-Anker. Gemessen an zwei
                // Produktivdatenbanken: `className` an 4735 von 11961
                // oeffnenden Begrenzern.
                //
                // `Legacy::lift_attributes()` macht es aus demselben Grund so:
                // Es kopiert in den Container und laesst die flachen Schluessel
                // stehen. Der Umschreiber nimmt ihm die Arbeit nur ab.
                //
                // In den Container gehen alle Schluessel AUSSER den
                // reservierten — dieselbe Menge, die `Legacy::flat_attributes()`
                // bildet.
                $reserved = self::reserved_keys();
                $outer    = new \stdClass();
                $inner    = new \stdClass();

                foreach ( get_object_vars( $decoded ) as $key => $value ) {
                    $outer->{$key} = $value;

                    if ( ! in_array( (string) $key, $reserved, true ) ) {
                        $inner->{$key} = $value;
                    }
                }

                $outer->blockstudio             = new \stdClass();
                $outer->blockstudio->name       = $new;
                $outer->blockstudio->attributes = $inner;

                $serialized = self::serialize_attributes( $outer );

                // KEIN CAST AUF false. Der Leerstring ergaebe einen Begrenzer
                // ohne jedes Attribut, hochgezaehlt als `wrapped` und ohne eine
                // Zeile in `errors` — der lautloseste Datenverlust, den diese
                // Klasse haben koennte.
                if ( ! is_string( $serialized ) ) {
                    $errors[] = self::unencodable_message( $name, json_last_error_msg(), $json );

                    return $hit[0];
                }

                /*
                 * WAS GESCHRIEBEN WIRD, MUSS DER BLOCKPARSER AUCH LESEN
                 * KOENNEN. `parse_blocks()` dekodiert mit der PHP-Standardtiefe;
                 * `json_encode()` zaehlt seine Tiefe um eine Ebene anders als
                 * `json_decode()`, und genau diese eine Ebene rutschte sonst
                 * durch: Der Container waere geschrieben, der Block fuer
                 * WordPress aber kein Block mehr — still, weil der
                 * Zaehlabgleich den Begrenzer sieht und die Attributkarte ihn
                 * mit der eigenen, groesseren Tiefe liest.
                 *
                 * Statt die Grenze auszurechnen, wird sie GEMESSEN: einmal
                 * zurueckdekodieren, mit derselben Tiefe wie der Parser.
                 */
                if ( null === json_decode( $serialized, true, self::PARSER_DEPTH ) ) {
                    $errors[] = self::unreadable_container_message( $name, $json );

                    return $hit[0];
                }

                ++$wrapped;

                return '<!-- wp:' . $new . ' ' . $serialized . $suffix;
            },
            $content
        );

        if ( ! is_string( $rewritten ) ) {
            self::note_pcre_error( 'rewrite()' );

            return [
                'content'    => $content,
                'renamed'    => 0,
                'wrapped'    => 0,
                'skipped'    => 0,
                'errors'     => array_merge(
                    [ 'Der Umschreiber ist am Inhalt gescheitert (preg_replace_callback lieferte null).' ],
                    self::take_pcre_findings()
                ),
                'attributes' => [],
            ];
        }

        // GUERTEL UND HOSENTRAEGER. Ein Nichttreffer des Begrenzermusters ist
        // lautlos: nicht getroffen heisst nicht umgeschrieben heisst nicht
        // gezaehlt. Gegengezaehlt wird deshalb mit dem simplen Muster, auf dem
        // auch `count_blocks()` beruht — es kann nichts als den Anfang eines
        // Begrenzers finden und irrt dort nicht, wo das strenge Muster irrt.
        $unmatched = self::unmatched_delimiters( $content, self::OLD_NAMESPACE );

        foreach ( $unmatched as $snippet ) {
            $errors[] = self::unmatched_message( $snippet );
        }

        // Und dieselbe Zaehlung noch einmal als nackte Zahl, fuer den Fall,
        // dass die Fundstellensuche selbst nichts zu zeigen hat.
        //
        // SIE BENUTZT DIESELBE AUSWERTUNG wie die Fundstellensuche oben — samt
        // ihrer Wache gegen Begrenzer INNERHALB eines Attributwerts. Zaehlte
        // sie roh, meldete ein `custom_class` mit dem Text `<!-- wp:areoi/…`
        // einen Zaehlabgleichsfehler und braechte den Lauf ab, obwohl nichts
        // fehlt.
        $expected = self::candidate_openers( $content, self::OLD_NAMESPACE );

        if ( null !== $expected && $openers !== $expected && [] === $unmatched ) {
            $errors[] = sprintf(
                'Zaehlabgleich im Umschreiber: %d oeffnende areoi-Begrenzer gefunden, %d bearbeitet.',
                $expected,
                $openers
            );
        }

        // Aus dem QUELLTEXT, nicht aus dem Ergebnis: Bein 1 stellt diese Karte
        // gegen die desselben Traegers nach dem Lauf. Sie wird VOR dem Abholen
        // der PCRE-Befunde erhoben, damit ein Fehler in ihrem Suchlauf noch in
        // dieser Beanstandungsliste landet.
        $attributes = self::attribute_map( $content );

        return [
            'content'    => $rewritten,
            'renamed'    => $renamed,
            'wrapped'    => $wrapped,
            'skipped'    => $skipped,
            'errors'     => array_merge( $errors, self::take_pcre_findings() ),
            'attributes' => $attributes,
        ];
    }
    /**
     * Counts the blocks of one namespace, by bare block name.
     *
     * NUR OEFFNENDE UND SELBSTSCHLIESSENDE Begrenzer werden gezaehlt. Ein
     * selbstschliessender Block hat keinen schliessenden Begrenzer; wuerde man
     * ihn mitzaehlen, haette dasselbe Dokument je nach Verschachtelung eine
     * andere Zahl. Gezaehlt wird, was ein Block IST.
     *
     * GEZAEHLT WIRD MIT `candidate_pattern()`, dem simplen Muster ohne
     * Klammernbilanz und ohne Rekursion — demselben, mit dem `rewrite()` sein
     * eigenes Ergebnis gegenzaehlt. Nur weil beide dieselbe Fundstellensuche
     * fahren, ist die Gegenzaehlung ueberhaupt eine Aussage.
     *
     * @param string $content   Post content, meta value or option value.
     * @param string $block_namespace Namespace including the trailing slash.
     * @return array<string, int> Bare block name to count, sorted by name.
     */
    public static function count_blocks( string $content, string $block_namespace ): array {
        $matches = [];
        $found   = preg_match_all( self::candidate_pattern( $block_namespace ), $content, $matches, PREG_SET_ORDER );

        // DIESE METHODE LIEFERT NUR ZAHLEN und kann einen Regex-Fehler
        // deshalb nicht selbst melden. Sie legt ihn in den Behaelter; der
        // Aufrufer holt ihn mit `take_pcre_findings()` ab (`tally()`,
        // Aufgabe 11). Ohne das waere ein gescheiterter Suchlauf von einem
        // blockfreien Traeger nicht zu unterscheiden — und der Zaehlabgleich
        // von Bein 1 verglich zwei Nullen miteinander und meldete Ruhe.
        if ( false === $found ) {
            self::note_pcre_error( 'count_blocks()' );

            return [];
        }

        if ( 0 === $found ) {
            return [];
        }

        $counts = [];

        foreach ( $matches as $match ) {
            if ( '/' === ( $match['close'] ?? '' ) ) {
                continue;
            }

            $name = strtolower( (string) $match['name'] );

            $counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
        }

        ksort( $counts );

        return $counts;
    }

    /**
     * Counts the CLOSING delimiters of one namespace, by bare block name.
     *
     * DER GEGENZWEIG ZU `count_blocks()`, und er existiert fuer die Abnahme:
     * `05-migration.md` verlangt von `verify`, dass oeffnende UND schliessende
     * Begrenzer gezaehlt werden — „ein verwaister `<!-- /wp:areoi/… -->` ist
     * ein Treffer". `count_blocks()` ueberspringt jeden Schliesser, und das
     * bleibt so: Der Zaehlabgleich von Bein 1 zaehlt Bloecke, nicht Kommentare.
     * Ohne diesen zweiten Zaehler haette ein Traeger, in dem nur noch ein
     * Schliesser steht, ueberall die Zahl null — und `verify` meldete gruen auf
     * einen areoi-Kommentar, der in der Datenbank steht.
     *
     * Gezaehlt wird mit demselben simplen Muster wie in `count_blocks()`, nur
     * mit umgekehrter Bedingung auf `close`.
     *
     * @param string $content   Post content, meta value or option value.
     * @param string $block_namespace Namespace including the trailing slash.
     * @return array<string, int> Bare block name to count, sorted by name.
     */
    public static function count_closers( string $content, string $block_namespace ): array {
        $matches = [];
        $found   = preg_match_all( self::candidate_pattern( $block_namespace ), $content, $matches, PREG_SET_ORDER );

        // Wie in `count_blocks()`: nur Zahlen, also geht der Regex-Fehler in
        // den Behaelter. Fiele er hier unter den Tisch, meldete `verify` gruen
        // auf einen verwaisten `<!-- /wp:areoi/… -->`, der in der Datenbank
        // steht — und auf diese Meldung hin wird der Legacy-Alias abgeschaltet.
        if ( false === $found ) {
            self::note_pcre_error( 'count_closers()' );

            return [];
        }

        if ( 0 === $found ) {
            return [];
        }

        $counts = [];

        foreach ( $matches as $match ) {
            if ( '/' !== ( $match['close'] ?? '' ) ) {
                continue;
            }

            $name = strtolower( (string) $match['name'] );

            $counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
        }

        ksort( $counts );

        return $counts;
    }

    /**
     * Maps every block of a content string onto its attributes.
     *
     * EINE FASSADE, KEINE ZWEITE FASSUNG. Die Karte entsteht in
     * `attribute_map()` (Aufgabe 9), weil `rewrite()` sie selbst zurueckgibt.
     * Es darf nur EINE Nummerierung geben: Zwei Fassungen desselben
     * Schluessels — eine ueber `areoi/*` allein, eine ueber beide
     * Namensraeume — verglichen in einem teilmigrierten Traeger Bloecke
     * miteinander, die nichts miteinander zu tun haben.
     *
     * @param string $content Post content, meta value or option value.
     * @return array<string, array<string, mixed>>
     */
    public static function block_attributes( string $content ): array {
        return self::attribute_map( $content );
    }

    /**
     * Lists what is wrong with a content string — without rewriting it.
     *
     * DASSELBE ERGEBNIS WIE `rewrite()['errors']`, NUR OHNE DIE ARBEIT. Das
     * Inventar braucht die Beanstandungen eines Traegers, nicht seinen
     * umgeschriebenen Text; vorher fuhr es dafuer je Traeger einen
     * vollstaendigen Umschreiblauf und warf das Ergebnis weg — je Block ein
     * `json_decode()`, ein `json_encode()`, sechs Maskierungslaeufe und ein neu
     * gebauter String, und das zweimal je Lauf (Vorher- und Nachher-Inventar),
     * im Trockenlauf zusaetzlich zum echten Umschreiben.
     *
     * Die drei Meldungen kommen aus denselben Helfern, die `rewrite()` benutzt.
     * Nur deshalb kann `tests/test-migrator-inventory.php`, Abschnitt 7,
     * verlangen, dass beide Wege WOERTLICH dasselbe liefern — die Zusage, die
     * einen zweiten Pruefweg ueberhaupt vertretbar macht.
     *
     * ZWEI MELDUNGEN FEHLEN HIER, UND BEIDE AUS DEMSELBEN GRUND: Sie sind
     * Aussagen ueber den LAUF, nicht ueber den Inhalt, und es gibt hier keinen
     * Lauf. Das ist zum einen der Zaehlabgleich des Umschreibers gegen sich
     * selbst, zum anderen die Schreibbarkeit des Attribut-Containers — sie
     * haengt am `json_encode()` des Umschreibers. Ein Begrenzer, dessen
     * Container zu tief zum Schreiben ist, faellt deshalb HIER nicht auf und
     * bleibt unveraendert stehen.
     *
     * ER FAELLT TROTZDEM VOR DEM ERSTEN SCHREIBZUGRIFF AUF, nur nicht an
     * dieser Stelle: `Migrate_Command::run()` (Aufgabe 12) faehrt im
     * schreibenden Zweig zusaetzlich `Migration_Support::simulate()`, und die
     * geht je Traegerart durch dieselbe Umschreibfunktion wie die
     * Schreibmethode. Auf deren Beanstandungen bricht der Lauf genauso ab wie
     * auf denen des Vorher-Inventars.
     *
     * Alles, was der INHALT hergibt, steht dagegen hier — einschliesslich
     * doppelter Attributschluessel, verlorener Zahlenstellen und gewechselter
     * Zahlentypen, alle drei aus `attribute_findings()`. Dafuer existiert das
     * Vorher-Inventar: Es ist die ERSTE der beiden Sperren vor dem ersten
     * Schreibzugriff — die billige, die ueber den Inhalt urteilt. Die zweite
     * ist der Probelauf in `run()`, der ueber den Schreibvorgang urteilt.
     * Keine von beiden ist fuer sich allein vollstaendig.
     *
     * @param string $content Post content, meta value or option value.
     * @return array<int, string> Human readable findings; empty means "nothing to report".
     */
    public static function findings( string $content ): array {
        $findings = [];
        $matches  = [];
        $found    = preg_match_all( self::pattern( self::OLD_NAMESPACE ), $content, $matches, PREG_SET_ORDER );

        if ( false === $found ) {
            self::note_pcre_error( 'findings()' );
        }

        if ( false !== $found && $found > 0 ) {
            foreach ( $matches as $match ) {
                if ( '/' === ( $match['close'] ?? '' ) ) {
                    continue;
                }

                $json = isset( $match['json'] ) ? (string) $match['json'] : '';

                if ( '' === $json ) {
                    continue;
                }

                $name = strtolower( (string) $match['name'] );

                // Dieselben Dekodierbedingungen wie in `rewrite()`, samt
                // `JSON_BIGINT_AS_STRING` und samt `JSON_DEPTH`: Beide muessen
                // dieselbe Zahl lesen und dieselbe Tiefe schaffen.
                $decoded = json_decode( $json, false, self::JSON_DEPTH, JSON_BIGINT_AS_STRING );

                if ( null === $decoded || ! is_object( $decoded ) ) {
                    $findings[] = self::unreadable_message( $name, $json );

                    continue;
                }

                // EIN VERPACKTER BEGRENZER WIRD NICHT WEITER GEPRUEFT. Seine
                // JSON geht im Lauf Zeichen fuer Zeichen durch; was darin
                // steht, wird weder gelesen noch neu geschrieben, und der
                // Umschreiber sieht sie sich aus demselben Grund nicht an.
                if ( property_exists( $decoded, 'blockstudio' ) ) {
                    if ( ! self::is_attribute_container( $decoded->blockstudio ) ) {
                        $findings[] = self::foreign_container_message( $name, $json );
                    }

                    continue;
                }

                foreach ( self::attribute_findings( $name, $json, $decoded ) as $message ) {
                    $findings[] = $message;
                }
            }
        }

        foreach ( self::unmatched_delimiters( $content, self::OLD_NAMESPACE ) as $snippet ) {
            $findings[] = self::unmatched_message( $snippet );
        }

        // Zuletzt, in derselben Reihenfolge wie in `rewrite()`: Was die
        // Suchlaeufe selbst zu melden hatten.
        foreach ( self::take_pcre_findings() as $message ) {
            $findings[] = $message;
        }

        return $findings;
    }

    /**
     * Compares two inventories, block type by block type.
     *
     * @param array<string, int> $before Counts before the migration.
     * @param array<string, int> $after  Counts after the migration.
     * @return array<int, string> Human readable findings; empty means "identical".
     */
    public static function reconcile_counts( array $before, array $after ): array {
        $names = array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) );
        sort( $names );

        $findings = [];

        foreach ( $names as $name ) {
            $left  = (int) ( $before[ $name ] ?? 0 );
            $right = (int) ( $after[ $name ] ?? 0 );

            if ( $left !== $right ) {
                $findings[] = sprintf(
                    '%s: vorher %d, nachher %d',
                    $name,
                    $left,
                    $right
                );
            }
        }

        return $findings;
    }

    /**
     * Compares two attribute maps, block by block.
     *
     * DREIFACH STRIKT, weil jede der drei Eigenschaften Teil des
     * Kompatibilitaetsvertrags ist: die Schluesselmenge (Regel 1.1), die
     * Schluesselreihenfolge (Regel 1.2, sie steht so im Blockkommentar) und die
     * Werte samt Typ (Regel 1.5 — aus dem String "64" darf nie die Zahl 64
     * werden).
     *
     * VERGLICHEN WIRD JE EINTRAG, NICHT JE `block_id`. Der Schluessel der Karte
     * ist `<laufende Nummer>:<block_id>`, in Aufgabe 11 zusaetzlich mit
     * Fundstelle und Traegerkennung versehen. Zwei Bloecke mit derselben
     * `block_id` — im selben Traeger oder in zweien — sind damit zwei
     * Eintraege. Waeren sie einer, ueberschriebe der eine den anderen, und der
     * Vergleich prueft nur noch einen von beiden.
     *
     * @param array<string, array<string, mixed>> $before Map before the migration.
     * @param array<string, array<string, mixed>> $after  Map after the migration.
     * @return array<int, string> Human readable findings; empty means "identical".
     */
    public static function reconcile_attributes( array $before, array $after ): array {
        $findings = [];

        foreach ( $before as $entry => $attributes ) {
            if ( ! array_key_exists( $entry, $after ) ) {
                $findings[] = sprintf( 'Eintrag %s fehlt nach der Migration', (string) $entry );

                continue;
            }

            $left  = $attributes;
            $right = $after[ $entry ];

            if ( array_keys( $left ) !== array_keys( $right ) ) {
                $findings[] = sprintf(
                    'Eintrag %s: Schluesselmenge oder Reihenfolge weicht ab (vorher: %s, nachher: %s)',
                    (string) $entry,
                    implode( ', ', array_map( 'strval', array_keys( $left ) ) ),
                    implode( ', ', array_map( 'strval', array_keys( $right ) ) )
                );

                continue;
            }

            foreach ( $left as $key => $value ) {
                if ( $value !== $right[ $key ] ) {
                    /*
                     * `WordPress.PHP.DevelopmentFunctions` haelt `var_export()` fuer
                     * Debugcode. Hier ist es der WORTLAUT einer Beanstandung: Der
                     * Vergleich ist typgleich, und nur `var_export()` zeigt den
                     * Unterschied zwischen "0" und 0, zwischen false und null.
                     * Eine Ausgabe, die den Typ verschweigt, liesse den Leser
                     * genau die Frage raten, wegen der die Zeile ueberhaupt da ist.
                     */
                    // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Begruendung im Block darueber.
                    $findings[] = sprintf(
                        'Eintrag %s, Attribut %s: vorher %s, nachher %s',
                        (string) $entry,
                        (string) $key,
                        var_export( $value, true ),
                        var_export( $right[ $key ], true )
                    );
                    // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_var_export
                }
            }
        }

        foreach ( array_keys( $after ) as $entry ) {
            if ( ! array_key_exists( $entry, $before ) ) {
                $findings[] = sprintf( 'Eintrag %s ist nach der Migration neu hinzugekommen', (string) $entry );
            }
        }

        return $findings;
    }
}
