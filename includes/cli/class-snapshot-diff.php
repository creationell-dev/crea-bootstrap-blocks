<?php
/**
 * Compares two snapshots: generated CSS and rendered markup.
 *
 * WAS DIESE KLASSE LEISTEN MUSS
 *
 * `06-verifikation.md` nennt sieben erwartete, zulaessige Abweichungen und
 * sagt dazu: „Diese Liste ist abschliessend — jede Abweichung ausserhalb ist
 * ein Fehler. Jede Abweichung innerhalb ist im Diff einzeln nachzuweisen,
 * nicht wegzuerklaeren." Genau das tut diese Klasse: Sie erklaert nichts weg,
 * sie BENENNT jede Abweichung einzeln und stellt sie als `expected` oder
 * `error` hin.
 *
 * DIE ZWEI GRENZEN DER NORMALISIERUNG
 *
 * Ohne Normalisierung ist jeder Diff rot — Nonces, Cache-Buster und Leerraum
 * unterscheiden zwei Aufrufe derselben Seite. Mit zu viel Normalisierung ist
 * jeder Diff gruen. Deshalb wird die Klassenreihenfolge ausdruecklich NICHT
 * angefasst: Sie ist Teil des Kompatibilitaetsvertrags.
 *
 * DIE DRITTE GRENZE: DIESE KLASSE KENNT DIE RAUSCHGRENZE DER SEITE NICHT
 *
 * Am 2026-09-10 auf einer Bestandsinstanz gemessen: 904 Markup-Befunde
 * zwischen zwei Aufnahmen desselben, UNVERAENDERTEN Zustands. Ursache waren
 * Zufalls-IDs eines E-Mail-Verschleierers und eine Kaskade ab dem
 * `<form>`-Tag einer Formularseite. Beides ist Eigenschaft der Installation,
 * nicht des Nachbaus — und diese Klasse kann die beiden nicht unterscheiden,
 * weil ihr dazu jede Bezugsgroesse fehlt.
 *
 * Die Konsequenz ist bewusst KEINE weitere Normalisierung: Ein Muster, das
 * `eeb-<zahl>-<zahl>` wegraeumt, raeumt auch eine echte Abweichung an dieser
 * Stelle weg. Die Bezugsgroesse wird stattdessen GEMESSEN — zwei Aufnahmen
 * desselben Zustands gegeneinander, vor der Migration. Eine nackte
 * Fehlerzahl aus `summary_line()` ist ohne sie kein Urteil.
 *
 * ZWEI VORAUSSETZUNGEN DES VERGLEICHS, die derselbe Lauf gefunden hat:
 * Beide Aufnahmen brauchen denselben Cache-Zustand — eine gecachte gegen eine
 * frisch gerenderte Seite erzeugt Befunde, die niemand verursacht hat. Und der
 * CSS-Teil traegt nur, solange das Alt-Plugin aktiv und der Inhalt noch
 * `areoi/*` ist; danach meldet `css_reference_findings()` selbst
 * `css-reference-missing`, und die Regelbefunde daneben sind dessen Folge.
 *
 * WARUM JEDER REGEX-AUFRUF BEWACHT IST
 *
 * `preg_replace()` gibt bei einem Abbruch der Engine — Backtrack- oder
 * Rekursionsgrenze — `null` zurueck, `preg_match_all()` und `preg_split()`
 * geben `false`. Ein Cast nach `(string)` macht daraus den Leerstring, und ab
 * da laeuft der ganze Vergleich auf leeren Marken: null Funde, Bilanz gruen,
 * Seite unbesehen. Das generierte Inline-CSS waechst nicht mit der Seite,
 * sondern mit der Installation — das Original erzeugt es aus ALLEN
 * `wp_block`-Posts ueber sechs Breakpoints —, die Grenze ist also erreichbar.
 * Deshalb bricht hier jeder Aufruf laut ab, statt still leer zu liefern.
 *
 * Rein und ohne WordPress benutzbar.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks\CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The DOM diff of leg 2.
 */
class Snapshot_Diff {

    /**
     * Matches the generated inline stylesheet of the old plugin and of the rebuild.
     */
    /**
     * Handle under which the ORIGINAL prints its generated stylesheet.
     *
     * Waehrend der Uebergangsphase sind beide Plugins gleichzeitig aktiv
     * (05-migration.md, Schritt 3 bis 7). Beide erzeugen dann ein Inline-CSS
     * mit DENSELBEN Selektoren `.block-<id>` — das Alt-Plugin, weil sein
     * Sammler nicht auf den Blocknamen keyt, sondern `block_id`, `padding_*`,
     * `margin_*` und `height_dimension_*` flach aus den Attributen liest, und
     * weil die Migration die flache Ebene stehen laesst statt sie zu
     * verschieben.
     *
     * Wer beide zu EINEM String verkettet, laesst in `css_rules()` das
     * spaeter gedruckte gewinnen — und das ist in der Standardkonfiguration
     * das Original, auf BEIDEN Seiten des Vergleichs. Der Diff haelt dann das
     * Original gegen sich selbst und antwortet „identisch": nicht der leere
     * Vergleich, der auffiele, sondern der bestandene, der nicht auffaellt.
     *
     * Deshalb tragen die beiden Handles Rollen: Die VORHER-Aufnahme wird auf
     * das Stylesheet des Originals geschnitten, die NACHHER-Aufnahme auf das
     * des Nachbaus.
     */
    public const HANDLE_ORIGINAL = 'areoi-style-index-inline-css';

    /**
     * Handle under which THIS plugin prints its generated stylesheet.
     */
    public const HANDLE_REBUILD = 'crea-bootstrap-blocks-inline-css';

    private const INLINE_CSS_PATTERN =
        '#<style[^>]*id=[\'"](?:areoi-style-index-inline-css|crea-bootstrap-blocks-inline-css)[\'"][^>]*>(.*?)</style>#is';

    /**
     * Elements whose content is not prose and must not be normalised as text.
     *
     * `preg_split()` on tags cannot tell the body of a `<script>` from a
     * paragraph. Would the text rules run there, a changed date inside inline
     * JavaScript — or inside preformatted text, which is page content — would
     * be unified away and the diff would stay silent about a real change.
     */
    private const OPAQUE_ELEMENTS = [ 'script', 'style', 'pre', 'textarea' ];

    /**
     * How deep the anchor based comparison recurses before it gives up.
     *
     * Der Deckel ist eine Reissleine, keine Einstellung: Jede Ebene halbiert
     * den Bereich mindestens einmal, 24 Ebenen decken jede reale Seite ab.
     */
    private const MAX_DIFF_DEPTH = 24;

    /**
     * Builds one finding.
     *
     * @param string $status   `expected` or `error`.
     * @param string $kind     Short machine readable kind, e.g. `class-added`.
     * @param string $detail   Human readable detail.
     * @param int    $position Token position, or -1 when there is none.
     * @return array{status: string, kind: string, detail: string, position: int}
     */
    public static function finding( string $status, string $kind, string $detail, int $position = -1 ): array {
        return [
            'status'   => $status,
            'kind'     => $kind,
            'detail'   => $detail,
            'position' => $position,
        ];
    }

    /**
     * Stops the comparison because the regex engine aborted.
     *
     * WARUM EINE AUSNAHME UND KEIN RUECKGABEWERT: Jede stille Ausweichform
     * dieser Stelle faelscht das Ergebnis in dieselbe Richtung. Liefert sie den
     * Leerstring, hat die Seite null Marken und der Vergleich null Funde — der
     * Lauf meldet gruen, ohne die Seite gesehen zu haben. Bein 2 ist aber das
     * Abnahmekriterium; ein falsches Gruen ist genau der Schaden, den es
     * verhindern soll. Der Aufrufer faengt die Ausnahme je URL ab und macht
     * daraus einen Befund, statt den ganzen Lauf zu verlieren.
     *
     * @param string $where Name of the calling method, for the message.
     * @throws \RuntimeException Always.
     */
    private static function abort( string $where ): never {
        // Die Meldung geht an einen CLI-Datenstrom, nie in eine HTML-Antwort;
        // `esc_html()` gibt es hier ausserdem nicht, weil diese Klasse ohne
        // WordPress benutzbar bleibt. Beide Bestandteile sind eigene Literale
        // beziehungsweise die Meldung der PCRE-Erweiterung, keine Nutzereingabe.
        $message = sprintf(
            'Regex-Abbruch in %s: %s. Der Vergleich waere danach still leer — deshalb bricht er hier ab.',
            $where,
            preg_last_error_msg()
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Begruendung im Block darueber.
        throw new \RuntimeException( $message );
    }

    /**
     * Replaces, and insists that the engine finished.
     *
     * @param string $pattern     The pattern.
     * @param string $replacement The replacement.
     * @param string $subject     The subject.
     * @param string $where       Name of the calling method, for the message.
     * @throws \RuntimeException When the regex engine aborted.
     */
    private static function replace( string $pattern, string $replacement, string $subject, string $where ): string {
        $out = preg_replace( $pattern, $replacement, $subject );

        if ( ! is_string( $out ) ) {
            self::abort( $where );
        }

        return $out;
    }

    /**
     * Matches all, and insists that the engine finished.
     *
     * @param string $pattern The pattern.
     * @param string $subject The subject.
     * @param string $where   Name of the calling method, for the message.
     * @return array<int, string> The first capturing group, as strings.
     * @throws \RuntimeException When the regex engine aborted.
     */
    private static function match_all( string $pattern, string $subject, string $where ): array {
        $matches = [];

        if ( false === preg_match_all( $pattern, $subject, $matches ) ) {
            self::abort( $where );
        }

        if ( ! isset( $matches[1] ) || ! is_array( $matches[1] ) ) {
            return [];
        }

        $group = [];

        foreach ( $matches[1] as $one ) {
            $group[] = (string) $one;
        }

        return $group;
    }

    /**
     * Splits, and insists that the engine finished.
     *
     * @param string $pattern The pattern.
     * @param string $subject The subject.
     * @param int    $flags   Split flags.
     * @param string $where   Name of the calling method, for the message.
     * @return array<int, string>
     * @throws \RuntimeException When the regex engine aborted.
     */
    private static function split( string $pattern, string $subject, int $flags, string $where ): array {
        $parts = preg_split( $pattern, $subject, -1, $flags );

        if ( ! is_array( $parts ) ) {
            self::abort( $where );
        }

        $out = [];

        foreach ( $parts as $part ) {
            // `PREG_SPLIT_OFFSET_CAPTURE` wird hier nie gesetzt; der Zweig
            // steht, weil der Ruecktyp von `preg_split()` ihn offenlaesst.
            $out[] = is_array( $part ) ? (string) $part[0] : (string) $part;
        }

        return $out;
    }

    /**
     * Builds the cut pattern for one handle — or for both.
     *
     * Ein unbekanntes Handle WIRFT, statt still den Leerstring zu liefern. Ein
     * Tippfehler im Handlenamen waere sonst genau der Fehler, den diese ganze
     * Trennung verhindern soll: Der Schnitt findet nichts, der Vergleich laeuft
     * auf leeren Regelmengen, und die Bilanz meldet „identisch".
     *
     * @param string $handle One of the two known handles; empty takes both.
     * @throws \InvalidArgumentException When the handle is neither known nor empty.
     */
    private static function inline_css_pattern( string $handle ): string {
        if ( '' === $handle ) {
            return self::INLINE_CSS_PATTERN;
        }

        if ( self::HANDLE_ORIGINAL !== $handle && self::HANDLE_REBUILD !== $handle ) {
            // Dieselbe Lage wie in `abort()`: Die Meldung geht an einen
            // CLI-Datenstrom, nie in eine HTML-Antwort, und `esc_html()` gibt es
            // hier nicht — diese Klasse bleibt ohne WordPress benutzbar. Der
            // eingesetzte Wert ist ein Handlename aus dem eigenen Code.
            $message = sprintf( 'Unbekanntes Stylesheet-Handle: %s', $handle );

            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Begruendung im Block darueber.
            throw new \InvalidArgumentException( $message );
        }

        return '#<style[^>]*id=[\'"]' . preg_quote( $handle, '#' ) . '[\'"][^>]*>(.*?)</style>#is';
    }

    /**
     * Reports whether the two captures can carry the comparison at all.
     *
     * Die Rollentrennung allein genuegt nicht. Sie schneidet aus der
     * VORHER-Aufnahme das Stylesheet des Originals und aus der NACHHER-Aufnahme
     * das des Nachbaus — aber wenn eines der beiden gar nicht da ist, liefert
     * der Schnitt den Leerstring, und `compare_css()` vergleicht eine leere
     * Regelmenge gegen eine leere. Das Urteil hiesse dann wieder „identisch",
     * diesmal aus dem entgegengesetzten Grund.
     *
     * Genau dagegen steht dieser Waechter. Er ist eine REINE Funktion und
     * deshalb von einer Suite ausfuehrbar: Eine Entscheidung, die nur im
     * Kommando steht, fuehrt keine Suite aus (E-135).
     *
     * Was er NICHT beanstandet:
     *
     *   - Dass ueberhaupt kein Stylesheet erzeugt wurde. Auf einer Seite ohne
     *     gespeicherte Abstandswerte ist das der richtige Zustand — auf einer der
     *     gemessenen Installationen sind es 0 Byte, ueber den ganzen Bestand.
     *   - Dass die NACHHER-Aufnahme kein Stylesheet des Nachbaus traegt, obwohl
     *     das Original Regeln erzeugt hat. Das ist nicht still: `compare_css()`
     *     laeuft ueber die Regeln der Referenz und meldet JEDE einzelne als
     *     `css-missing-rule`. Und es ist nicht einmal immer ein Fehler — faellt
     *     die einzige Regel wegen eines abgewiesenen Werts weg (erwartete
     *     Abweichung 7), erzeugt der Nachbau planmaessig gar kein Stylesheet.
     *     Ein Waechter an dieser Stelle klagte genau den geplanten Zustand an.
     *
     * Still ist allein die umgekehrte Richtung, und deshalb steht sie hier: Ist
     * die Referenz leer, laeuft `compare_css()` ueber eine leere Regelmenge.
     * Eine Regel, die NUR der Nachbau erzeugt, faellt dabei durch — sie hat
     * keinen Gegenpart, ueber den der Vergleich stolpern koennte.
     *
     * Der Name nennt die VORHER-Seite allein, und das ist kein Versehen: Auf der
     * Nachher-Seite gibt es keinen stillen Ausfall. Fehlt dort das Stylesheet
     * des Nachbaus, meldet `compare_css()` jede Regel der Referenz einzeln.
     *
     * @param string $before_html Raw source of the reference page.
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function css_reference_findings( string $before_html ): array {
        $reference = self::extract_inline_css( $before_html, self::HANDLE_ORIGINAL );

        // Die Vorher-Aufnahme traegt das Stylesheet des Nachbaus, aber keines
        // des Originals: Sie ist nach der Umschreibung entstanden oder mit
        // deaktiviertem Alt-Plugin. Sie ist dann keine Referenz — und der
        // Vergleich, der auf ihr fusst, kein Nachweis.
        if ( '' === $reference && '' !== self::extract_inline_css( $before_html, self::HANDLE_REBUILD ) ) {
            return [
                self::finding(
                    'error',
                    'css-reference-missing',
                    'die Vorher-Aufnahme traegt kein Stylesheet des Alt-Plugins, nur eines des Nachbaus'
                    . ' — sie taugt nicht als Referenz'
                ),
            ];
        }

        return [];
    }

    /**
     * Cuts the generated inline CSS out of a captured page.
     *
     * Ohne `$handle` kommen BEIDE Handles mit — das ist die Form, in der
     * `snapshot create` das erzeugte CSS als lesbares Beiwerk neben die
     * Aufnahme schreibt. Fuer den VERGLEICH ist sie die falsche: Siehe den
     * Docblock von `HANDLE_ORIGINAL`. Dort wird je Seite genau ein Handle
     * geschnitten, und `compare()` weist die Rollen zu.
     *
     * Der `sourceURL`-Anhang faellt weg: `WP_Styles` haengt an jedes
     * Inline-Stylesheet einen Kommentar mit der Quelle an. Er gehoert nicht zum
     * erzeugten CSS und stuende sonst in jedem Vergleich als Unterschied, sobald
     * sich das Handle aendert — was bei der Migration genau der Fall ist.
     *
     * @param string $html   Captured page source.
     * @param string $handle Restrict to one handle; empty takes both.
     * @throws \InvalidArgumentException When the handle is neither known nor empty.
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function extract_inline_css( string $html, string $handle = '' ): string {
        $parts = [];

        foreach ( self::match_all( self::inline_css_pattern( $handle ), $html, 'extract_inline_css()' ) as $css ) {
            $css = self::replace( '~/\*#\s*sourceURL=.*?\*/~s', '', $css, 'extract_inline_css()' );
            $css = trim( $css );

            if ( '' !== $css ) {
                $parts[] = $css;
            }
        }

        return implode( "\n", $parts );
    }

    /**
     * Blanks the generated inline CSS without removing the element.
     *
     * Gebraucht fuer `block_ids()`: Gesucht sind die block_id-Werte, die auf der
     * SEITE vorkommen — nicht die, fuer die es zufaellig eine CSS-Regel gibt.
     * Genau an dieser Unterscheidung haengt die erwartete Abweichung 2 aus
     * `06-verifikation.md`.
     *
     * @param string $html Captured page source.
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function mask_inline_css( string $html ): string {
        return self::replace( self::INLINE_CSS_PATTERN, '<style></style>', $html, 'mask_inline_css()' );
    }

    /**
     * Normalises a captured page for comparison.
     *
     * @param string $html Captured page source.
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function normalize_html( string $html ): string {
        // Das generierte CSS wird getrennt verglichen.
        $out = self::replace( self::INLINE_CSS_PATTERN, '', $html, 'normalize_html()' );

        // HTML-Kommentare. Blockbegrenzer sind hier NICHT gemeint — die
        // erreichen eine gerenderte Seite ohnehin nie: `do_blocks()` setzt die
        // Ausgabe aus `render_block()` je geparstem Block zusammen und gibt die
        // Begrenzer nicht wieder aus. Was hier verschwindet, sind Kommentare aus
        // Theme, Cache und fremden Plugins — Rauschen, das sich zwischen zwei
        // Aufrufen derselben Seite aendert, ohne dass sich etwas geaendert hat.
        $out = self::replace( '/<!--.*?-->/s', '', $out, 'normalize_html()' );

        // Nonces und CSRF-Token. Auf die Gattung geschnitten, nicht auf den
        // heute bekannten Namen: `06-verifikation.md` verlangt „Nonces,
        // wp_nonce-Felder, CSRF-Token" in der Mehrzahl, und der Kern erzeugt
        // ausser `_wpnonce` noch Felder wie `_wp_unfiltered_html_comment_*`
        // sowie den REST-Nonce als blosses Funktionsargument. Nonces sind je
        // `wp_nonce_tick()` zwoelf Stunden stabil; zwischen Vorher- und
        // Nachher-Aufnahme liegen Migration, Zaehlabgleich und `verify`, eine
        // Tickgrenze ist also leicht ueberschritten.
        $out = self::replace(
            '/(<input[^>]*\bname=[\'"](?:_wp[^\'"]*|[^\'"]*nonce)[\'"][^>]*\bvalue=)[\'"][^\'"]*[\'"]/i',
            '$1"NONCE"',
            $out,
            'normalize_html()'
        );
        $out = self::replace(
            '/(<input[^>]*\bvalue=)[\'"][^\'"]*[\'"]([^>]*\bname=[\'"](?:_wp[^\'"]*|[^\'"]*nonce)[\'"])/i',
            '$1"NONCE"$2',
            $out,
            'normalize_html()'
        );
        $out = self::replace( '/\b(_wpnonce|_ajax_nonce|_wpnonce_[A-Za-z0-9_-]+)=[A-Za-z0-9]+/i', '$1=NONCE', $out, 'normalize_html()' );
        $out = self::replace( '/([\'"][A-Za-z0-9_]*nonce[A-Za-z0-9_]*[\'"]\s*:\s*)[\'"][^\'"]*[\'"]/i', '$1"NONCE"', $out, 'normalize_html()' );
        $out = self::replace( '/(createNonceMiddleware\(\s*)[\'"][^\'"]*[\'"]/i', '$1"NONCE"', $out, 'normalize_html()' );

        // Cache-Buster an Asset-URLs.
        $out = self::replace( '/([?&])ver=[^&"\'\s>]*/i', '$1ver=VER', $out, 'normalize_html()' );

        // Zeitstempel im Attribut.
        $out = self::replace( '/(datetime=)[\'"][^\'"]*[\'"]/i', '$1"TS"', $out, 'normalize_html()' );

        // Zeitstempel, Kommentarzaehler und "vor x Stunden" im TEXT
        // (06-verifikation.md, Abschnitt Normalisierung).
        $out = self::normalize_text_nodes( $out );

        // Leerraum zwischen Tags. Das Original erzeugt sein Markup per
        // String-Konkatenation mit grosszuegiger Einrueckung, Blockstudio
        // rueckt anders ein — das ist keine Abweichung.
        $out = self::replace( '/>\s+</', '><', $out, 'normalize_html()' );

        return trim( $out );
    }

    /**
     * Unifies times, dates and comment counters in text nodes.
     *
     * WARUM DAS SEIN MUSS: Zwischen der Vorher- und der Nachher-Aufnahme liegen
     * Minuten bis Stunden — Migration, Zaehlabgleich und `verify` dazwischen.
     * `06-verifikation.md` nennt deshalb unter „Normalisierung" ausdruecklich
     * „Zeitstempel, Kommentarzaehler, ‚vor x Stunden'-Angaben".
     *
     * NUR IN TEXTKNOTEN, NIE INNERHALB EINES TAGS. Ein `href` mit einem Datum
     * darin ist Teil des Vertrags; verschiebt es sich, ist das ein Fehler und
     * soll auffallen.
     *
     * WAS BEWUSST STEHEN BLEIBT — was normalisiert wird, kann der Diff nicht
     * mehr finden:
     *   - Eine ALLEINSTEHENDE Uhrzeit. „10:00 – 18:00 Uhr" sind
     *     Oeffnungszeiten und damit Seiteninhalt. Eine Uhrzeit wird nur
     *     zusammen mit dem Datum ersetzt, zu dem sie gehoert.
     *   - Blosse Jahres- und sonstige Zahlen, Versionsangaben im Text.
     *   - Das Substantiv am Kommentarzaehler: aus „3 Kommentare" wird
     *     „ANZAHL Kommentare", nicht „ANZAHL".
     *   - Der Inhalt von `script`, `style`, `pre` und `textarea`. Fuer den
     *     Zerleger sind das gewoehnliche Textknoten; fuer die Seite sind es
     *     Code und vorformatierter Inhalt, in dem ein geaendertes Datum eine
     *     echte Aenderung ist und keine Zeitangabe im Fliesstext.
     *
     * @param string $html Page source without generated CSS and comments.
     * @throws \RuntimeException When the regex engine aborted.
     */
    private static function normalize_text_nodes( string $html ): string {
        $parts = self::split( '/(<[^>]*>)/', $html, PREG_SPLIT_DELIM_CAPTURE, 'normalize_text_nodes()' );

        $out      = '';
        $last_tag = '';
        $opaque   = '';

        foreach ( $parts as $part ) {
            if ( str_starts_with( $part, '<' ) ) {
                $name = self::tag_name( $part );

                if ( '' !== $opaque && '/' . $opaque === $name ) {
                    $opaque = '';
                } elseif ( '' === $opaque && in_array( $name, self::OPAQUE_ELEMENTS, true ) && ! str_ends_with( rtrim( $part, '>' ), '/' ) ) {
                    $opaque = $name;
                }

                $last_tag = $part;
                $out     .= $part;

                continue;
            }

            $out .= '' === $opaque ? self::normalize_text_run( $part, $last_tag ) : $part;
        }

        return $out;
    }

    /**
     * The lower case element name of a tag, closing tags with a leading slash.
     *
     * @param string $tag One tag, angle brackets included.
     */
    private static function tag_name( string $tag ): string {
        $matches = [];

        if ( 1 !== preg_match( '#^<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9-]*)#', $tag, $matches ) ) {
            return '';
        }

        return $matches[1] . strtolower( $matches[2] );
    }

    /**
     * Applies the text normalisations to one text run.
     *
     * @param string $text Text between two tags.
     * @param string $tag  The tag that opened this text run, for context.
     * @throws \RuntimeException When the regex engine aborted.
     */
    private static function normalize_text_run( string $text, string $tag ): string {
        if ( '' === trim( $text ) ) {
            return $text;
        }

        // Ein Zaehler, der als BLOSSE ZAHL in seinem eigenen Element steht
        // (`<span class="comment-count">3</span>`). Ohne den Klassenkontext
        // waere die Zahl von jeder anderen Zahl der Seite nicht zu
        // unterscheiden — deshalb haengt dieser Fall am Elternelement und
        // nicht am Text.
        if (
            1 === preg_match( '/class=[\'"][^\'"]*\b(?:comment|comments|reply|replies)[-_]?count\b/i', $tag )
            && 1 === preg_match( '/^\s*[\d.,\x{00A0}\s]+$/u', $text )
        ) {
            return self::replace( '/[\d.,]+/', 'ANZAHL', $text, 'normalize_text_run()' );
        }

        $replacements = [
            // Relative Zeitangaben, deutsch.
            '/\bvor\s+(?:etwa\s+|ca\.\s*|ungef(?:ä|ae)hr\s+)?(?:\d+|einer|einem|eine|ein|wenigen|mehreren)\s+'
                . '(?:Sekunden?|Minuten?|Stunden?|Tagen?|Tag|Wochen?|Monaten?|Monat|Jahren?|Jahr)\b/iu'
                => 'vor ZEIT',
            '/\b(?:gerade\s+eben|soeben)\b/iu'
                => 'vor ZEIT',

            // Relative Zeitangaben, englisch.
            '/\b(?:\d+|a|an|one|few|several)\s+(?:second|minute|hour|day|week|month|year)s?\s+ago\b/i'
                => 'ZEIT ago',
            '/\b(?:just\s+now|moments\s+ago)\b/i'
                => 'ZEIT ago',

            // ISO 8601, mit optionaler Uhrzeit und Zone.
            '/\b\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?\b/'
                => 'DATUM',

            // 13.08.2026, mit optionaler Uhrzeit. NUR mit vierstelligem Jahr:
            // „6.9.11" ist eine Versionsangabe und kein Datum.
            '/\b(?:0?[1-9]|[12]\d|3[01])\.\s?(?:0?[1-9]|1[0-2])\.\s?(?:19|20)\d{2}'
                . '(?:\s*,?\s*(?:um\s+)?\d{1,2}:\d{2}(?::\d{2})?(?:\s*Uhr)?)?/'
                => 'DATUM',

            // 13. August 2026, mit optionaler Uhrzeit.
            '/\b\d{1,2}\.\s*(?:Januar|Februar|M(?:ä|ae)rz|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)'
                . '\s+\d{4}(?:\s*,?\s*(?:um\s+)?\d{1,2}:\d{2}(?::\d{2})?(?:\s*Uhr)?)?/u'
                => 'DATUM',

            // August 13, 2026, mit optionaler Uhrzeit.
            '/\b(?:January|February|March|April|May|June|July|August|September|October|November|December)'
                . '\s+\d{1,2},\s*\d{4}(?:\s*(?:at\s+)?\d{1,2}:\d{2}(?::\d{2})?\s*(?:am|pm)?)?/i'
                => 'DATUM',

            // 2026/08/13.
            '/\b\d{4}\/\d{2}\/\d{2}\b/'
                => 'DATUM',

            // Kommentarzaehler. Ersetzt wird NUR die Zahl — das Substantiv
            // bleibt stehen, sonst faellt ein Wechsel von „Kommentare" zu
            // „Antworten" nicht mehr auf.
            '/\b(?:\d+|Ein|Eine|Keine|One|No)\s+(Kommentare?|Antworten|Antwort|Comments?|Replies|Reply)\b/iu'
                => 'ANZAHL $1',
        ];

        foreach ( $replacements as $pattern => $replacement ) {
            $text = self::replace( $pattern, $replacement, $text, 'normalize_text_run()' );
        }

        return $text;
    }

    /**
     * Splits a page into tags and text runs.
     *
     * @param string $html Normalised page source.
     * @return array<int, string>
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function tokenize( string $html ): array {
        $parts = self::split(
            '/(<[^>]*>)/',
            $html,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
            'tokenize()'
        );

        $tokens = [];

        foreach ( $parts as $part ) {
            $part = str_starts_with( $part, '<' ) ? $part : trim( $part );

            if ( '' !== $part ) {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }

    /**
     * The `block_id` values a page carries in its markup.
     *
     * @param string $html Page source, ideally with masked inline CSS.
     * @return array<int, string> Lower case, sorted, without duplicates.
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function block_ids( string $html ): array {
        /*
         * DERSELBE ZEICHENSATZ, DEN Styles::block_id() DURCHLAESST. Bis zum
         * 2026-08-31 stand hier die UUID-Form; ueber die drei
         * Produktivdatenbanken haette sie 7124 von 30 674 Kennungen nicht
         * gesehen. Eine Klasse, die das Plugin ausgibt und dieser Vergleich
         * nicht liest, kann von der erwarteten Abweichung 2 nicht gedeckt
         * werden — der Diff meldete sie als unerklaerten Fund.
         *
         * `(?<![\w-])` STATT `\b`: Ein Wortanfang genuegt hier nicht. WordPress
         * gibt Klassen wie `wp-block-creabb-media-grid-image` aus; vor `block-`
         * steht dort ein Bindestrich, und `\b` traefe trotzdem — die halbe
         * Klasse waere dann eine erfundene Kennung. Der Lookbehind schliesst
         * genau diesen Fall aus.
         */
        $pattern = '/(?<![\w-])block-([A-Za-z0-9_-]+)(?![\w-])/';

        $ids = [];

        foreach ( self::match_all( $pattern, $html, 'block_ids()' ) as $id ) {
            $ids[ strtolower( $id ) ] = true;
        }

        $ids = array_keys( $ids );
        sort( $ids );

        return $ids;
    }

    /**
     * Finds the differing regions of two token streams.
     *
     * VERFAHREN: Erst der gemeinsame Anfang und das gemeinsame Ende weg. Bleibt
     * beiderseits gleich viel uebrig — der Normalfall, weil eine hinzugefuegte
     * Klasse die Zahl der Marken nicht aendert —, wird JEDE abweichende Stelle
     * ein eigener Fund. Sonst wird der Rest an gemeinsamen Ankern zerlegt und
     * jedes Stueck einzeln betrachtet.
     *
     * WARUM DIE ANKER SEIN MUESSEN: Ein blosser Rueckfall auf „ein Fund ueber
     * den ganzen Rest" ist unbrauchbar, und zwar in beide Richtungen. Laut:
     * Eine einzige Marke mehr oder weniger irgendwo auf der Seite — ein
     * Kopfelement, eine Einbettung — macht aus hundert einzeln nachweisbaren
     * Klassenaenderungen EINEN Befund, der groesser ist als die Seite selbst;
     * niemand sieht darin mehr, welche der hundert die unerwartete ist. Und
     * still: Ein solcher Sammelfund enthaelt neben der einen zugelassenen
     * Abweichung auch alles andere, was im selben Bereich liegt — die Wache,
     * die die zugelassene Abweichung erkennt, spricht dann fuer den ganzen
     * Klumpen frei. Ein verlorenes `iframe` wuerde so als erwartete Abweichung
     * verbucht und der Lauf bliebe gruen.
     *
     * Anker sind Marken, die in BEIDEN Stroemen genau einmal vorkommen und
     * gleich sind. Aus ihnen wird die laengste aufsteigende Folge genommen; sie
     * teilt den Bereich in Stuecke, die einzeln weiterbehandelt werden. Auf
     * Markup traegt das gut: Ein Grossteil der Marken einer Seite ist einmalig.
     *
     * @param array<int, string> $before Reference tokens.
     * @param array<int, string> $after  Compared tokens.
     * @return array<int, array{index: int, before: array<int, string>, after: array<int, string>}>
     */
    public static function hunks( array $before, array $after ): array {
        $before = array_values( $before );
        $after  = array_values( $after );

        $hunks = [];

        self::diff_region( $before, $after, 0, count( $before ), 0, count( $after ), $hunks, 0 );

        return $hunks;
    }

    /**
     * Compares one region of both streams and appends its findings.
     *
     * @param array<int, string>                                                                   $before Reference tokens.
     * @param array<int, string>                                                                   $after  Compared tokens.
     * @param int                                                                                  $b_lo   First index in `$before`, inclusive.
     * @param int                                                                                  $b_hi   Last index in `$before`, exclusive.
     * @param int                                                                                  $a_lo   First index in `$after`, inclusive.
     * @param int                                                                                  $a_hi   Last index in `$after`, exclusive.
     * @param array<int, array{index: int, before: array<int, string>, after: array<int, string>}> $hunks Collected findings.
     * @param int                                                                                  $depth  Recursion depth.
     */
    private static function diff_region(
        array $before,
        array $after,
        int $b_lo,
        int $b_hi,
        int $a_lo,
        int $a_hi,
        array &$hunks,
        int $depth
    ): void {
        while ( $b_lo < $b_hi && $a_lo < $a_hi && $before[ $b_lo ] === $after[ $a_lo ] ) {
            ++$b_lo;
            ++$a_lo;
        }

        while ( $b_lo < $b_hi && $a_lo < $a_hi && $before[ $b_hi - 1 ] === $after[ $a_hi - 1 ] ) {
            --$b_hi;
            --$a_hi;
        }

        $b_len = $b_hi - $b_lo;
        $a_len = $a_hi - $a_lo;

        if ( 0 === $b_len && 0 === $a_len ) {
            return;
        }

        // Reines Hinzufuegen oder reines Wegfallen — dafuer gibt es keine
        // Anker, und die Stelle ist bereits so eng wie moeglich.
        if ( 0 === $b_len || 0 === $a_len ) {
            $hunks[] = [
                'index'  => $b_lo,
                'before' => array_slice( $before, $b_lo, $b_len ),
                'after'  => array_slice( $after, $a_lo, $a_len ),
            ];

            return;
        }

        // Gleich viele Marken: jede abweichende Stelle wird ein eigener Fund.
        // Das ist die Granularitaet, die die Einordnung braucht — ein Fund mit
        // genau einer Marke auf jeder Seite.
        if ( $b_len === $a_len ) {
            for ( $offset = 0; $offset < $b_len; $offset++ ) {
                if ( $before[ $b_lo + $offset ] === $after[ $a_lo + $offset ] ) {
                    continue;
                }

                $hunks[] = [
                    'index'  => $b_lo + $offset,
                    'before' => [ $before[ $b_lo + $offset ] ],
                    'after'  => [ $after[ $a_lo + $offset ] ],
                ];
            }

            return;
        }

        $anchors = $depth < self::MAX_DIFF_DEPTH
            ? self::anchors( $before, $after, $b_lo, $b_hi, $a_lo, $a_hi )
            : [];

        if ( [] === $anchors ) {
            $hunks[] = [
                'index'  => $b_lo,
                'before' => array_slice( $before, $b_lo, $b_len ),
                'after'  => array_slice( $after, $a_lo, $a_len ),
            ];

            return;
        }

        $b_cursor = $b_lo;
        $a_cursor = $a_lo;

        foreach ( $anchors as $anchor ) {
            self::diff_region( $before, $after, $b_cursor, $anchor[0], $a_cursor, $anchor[1], $hunks, $depth + 1 );

            $b_cursor = $anchor[0] + 1;
            $a_cursor = $anchor[1] + 1;
        }

        self::diff_region( $before, $after, $b_cursor, $b_hi, $a_cursor, $a_hi, $hunks, $depth + 1 );
    }

    /**
     * The common anchors of one region, as an increasing sequence.
     *
     * Ein Anker ist eine Marke, die in beiden Bereichen GENAU EINMAL vorkommt.
     * Mehrfach vorkommende Marken taugen nicht: `</div>` steht auf jeder Seite
     * hundertfach und sagt nichts darueber, welche Stelle welcher entspricht.
     *
     * @param array<int, string> $before Reference tokens.
     * @param array<int, string> $after  Compared tokens.
     * @param int                $b_lo   First index in `$before`, inclusive.
     * @param int                $b_hi   Last index in `$before`, exclusive.
     * @param int                $a_lo   First index in `$after`, inclusive.
     * @param int                $a_hi   Last index in `$after`, exclusive.
     * @return array<int, array{0: int, 1: int}>
     */
    private static function anchors( array $before, array $after, int $b_lo, int $b_hi, int $a_lo, int $a_hi ): array {
        $b_count = [];
        $a_count = [];
        $a_index = [];

        for ( $i = $b_lo; $i < $b_hi; $i++ ) {
            $token             = $before[ $i ];
            $b_count[ $token ] = ( $b_count[ $token ] ?? 0 ) + 1;
        }

        for ( $i = $a_lo; $i < $a_hi; $i++ ) {
            $token             = $after[ $i ];
            $a_count[ $token ] = ( $a_count[ $token ] ?? 0 ) + 1;
            $a_index[ $token ] = $i;
        }

        $pairs = [];

        for ( $i = $b_lo; $i < $b_hi; $i++ ) {
            $token = $before[ $i ];

            if ( 1 !== ( $b_count[ $token ] ?? 0 ) || 1 !== ( $a_count[ $token ] ?? 0 ) ) {
                continue;
            }

            $pairs[] = [ $i, (int) $a_index[ $token ] ];
        }

        return self::longest_increasing( $pairs );
    }

    /**
     * The longest strictly increasing subsequence of the second component.
     *
     * Die Paare kommen nach der ersten Komponente sortiert herein. Gesucht ist
     * die groesste Teilmenge, die auch in der zweiten Komponente aufsteigt —
     * nur eine solche Folge kann eine Zuordnung ohne Ueberkreuzung sein.
     *
     * @param array<int, array{0: int, 1: int}> $pairs Candidate anchors.
     * @return array<int, array{0: int, 1: int}>
     */
    private static function longest_increasing( array $pairs ): array {
        if ( [] === $pairs ) {
            return [];
        }

        $tails     = [];
        $tail_at   = [];
        $preceding = [];

        foreach ( $pairs as $position => $pair ) {
            $low  = 0;
            $high = count( $tails );

            while ( $low < $high ) {
                $middle = intdiv( $low + $high, 2 );

                if ( $tails[ $middle ] < $pair[1] ) {
                    $low = $middle + 1;
                } else {
                    $high = $middle;
                }
            }

            $tails[ $low ]          = $pair[1];
            $tail_at[ $low ]        = $position;
            $preceding[ $position ] = $low > 0 ? $tail_at[ $low - 1 ] : -1;
        }

        $sequence = [];
        $cursor   = $tail_at[ count( $tails ) - 1 ];

        while ( $cursor >= 0 ) {
            $sequence[] = $pairs[ $cursor ];
            $cursor     = $preceding[ $cursor ];
        }

        return array_reverse( $sequence );
    }

    /**
     * The tag allowlist of the `button` block.
     *
     * GEGENSTUECK zu `blocks/button/index.php` (Plan 2): Dort steht dieselbe
     * Liste als Argument von `crea_bootstrap_blocks_tag_name()`, mit `a` als
     * Rueckfall. Aendert sie sich dort, aendert sie sich hier mit — sonst wiese
     * die Erhebung einen Wert als zulaessig aus, den der Block in Wahrheit
     * verwirft.
     *
     * @var array<int, string>
     */
    public const BUTTON_TAGS = [ 'a', 'button', 'div', 'span' ];

    /**
     * Attributes that carry a payload of their own.
     *
     * Ein Element, das eines davon traegt, hinterlaesst beim Filtern KEINE
     * Spur: `wp_kses_post()` nimmt den Tag weg, und mit ihm die Adresse. Ein
     * `<script>alert(1)</script>` verliert nur seine Tags, sein Text bleibt
     * stehen — ein `<iframe src="…">` ist danach vollstaendig fort.
     *
     * @var array<int, string>
     */
    private const PAYLOAD_ATTRIBUTES = [ 'src', 'srcset', 'data', 'value', 'href', 'action', 'poster' ];

    /**
     * Classifies one differing region of the markup.
     *
     * DIE ZWEI ERWARTETEN FAELLE IM RUMPF:
     *
     *   `class-added` — Es unterscheidet sich ausschliesslich das
     *      `class`-Attribut, und die Differenz besteht ausschliesslich aus
     *      hinzugekommenen `creabb-*`-Klassen. Streicht man sie, steht die alte
     *      Klassenliste in derselben Reihenfolge da. Genau das sagt
     *      `06-verifikation.md` zu: „Die creabb-*-Klassen kommen grundsaetzlich
     *      hinzu … verschiebt sich eine areoi-*-Klasse, ist es ein Fehler."
     *   `kses` — Der Unterschied ist genau das, was `wp_kses_post()` entfernt
     *      (erwartete Abweichung 5). Dieser Zweig kommt ZULETZT: Er greift nur
     *      dort, wo sonst `error:markup` stuende, und nimmt der
     *      Klasseneinordnung nichts weg.
     *
     * Es waren einmal drei. Der dritte, `escaping`, ist am 2026-09-01
     * ersatzlos gefallen — E-32 hat die Abweichung als Vertragsgegenstand
     * gestrichen, und der Zweig buchte seither echte Escaping-Verluste als
     * erwartet. Mit ihm ist `same_kind()` weggefallen, seine einzige Wache.
     *
     * Alles andere ist `error:markup` oder `error:class-order`.
     *
     * @param array<int, string> $before Reference tokens of the region.
     * @param array<int, string> $after  Compared tokens of the region.
     * @param int                $index  Token position of the region.
     * @return array<int, array<string, mixed>>
     */
    public static function classify_hunk( array $before, array $after, int $index ): array {
        $left_run  = implode( '', $before );
        $right_run = implode( '', $after );

        if ( 1 !== count( $before ) || 1 !== count( $after ) ) {
            return [
                self::kses_finding( $left_run, $right_run, $index )
                    ?? self::finding(
                        'error',
                        'markup',
                        sprintf(
                            'vorher %d Marke(n), nachher %d: %s → %s',
                            count( $before ),
                            count( $after ),
                            $left_run,
                            $right_run
                        ),
                        $index
                    ),
            ];
        }

        $left  = (string) $before[0];
        $right = (string) $after[0];

        /*
         * HIER STAND EINMAL DIE ERWARTETE ABWEICHUNG 3 — „einfaches statt
         * doppeltem Escaping". Sie ist ersatzlos entfallen, und der Zweig mit
         * ihr.
         *
         * E-32 hat sie am 2026-08-19 nicht bloss entbucht, sondern als
         * VERTRAGSGEGENSTAND gestrichen: `esc_attr()` ruft
         * `_wp_specialchars( …, ENT_QUOTES )` mit `$double_encode = false` und
         * ist damit idempotent. Das Original escapt zweimal, der Nachbau
         * einmal — und beide geben dasselbe aus. Die Abweichung existierte nur
         * im Pruefrahmen, der sie selbst erzeugte. Gemessen an der laufenden
         * Installation, in sieben von sieben Formen.
         *
         * Bein 1 hat die Lehre gezogen (`tests/lib/diff-expectations.php`
         * schreibt sie aus). Bein 2 ist neun Tage spaeter entstanden und hatte
         * sie nicht — der Zweig buchte deshalb, was es nicht gibt: einen echten
         * ESCAPING-VERLUST des Nachbaus. Gemessen wurden `&amp;` → `&`,
         * `&quot;` → `"`, `&lt;script&gt;` → `<script>` im Attributwert und der
         * Attributausbruch `alt="a&quot; onerror=&quot;alert(1)"` →
         * `alt="a" onerror="alert(1)"`. Alle als ERWARTET gebucht, durch
         * `tally()` und `exit_code()` bis zur Schlusszeile „0 Fehler", Exit 0.
         *
         * `same_kind()` half dagegen nicht: Es wehrt den Wechsel Text→Tag ab,
         * nicht den Verlust innerhalb eines Attributwerts — dort ist die Marke
         * auf beiden Seiten ein Tag.
         *
         * Eine Abweichung der Escape-Tiefe ist ab hier das, was sie ist: eine
         * Aenderung am Markup, die angesehen gehoert.
         */

        $left_attributes  = self::tag_attributes( $left );
        $right_attributes = self::tag_attributes( $right );

        if ( null === $left_attributes || null === $right_attributes ) {
            return [
                self::kses_finding( $left_run, $right_run, $index )
                    ?? self::finding( 'error', 'markup', $left . ' → ' . $right, $index ),
            ];
        }

        if ( $left_attributes['tag'] !== $right_attributes['tag'] ) {
            return [
                self::kses_finding( $left_run, $right_run, $index )
                    ?? self::finding( 'error', 'markup', $left . ' → ' . $right, $index ),
            ];
        }

        $left_rest  = $left_attributes['attributes'];
        $right_rest = $right_attributes['attributes'];

        unset( $left_rest['class'], $right_rest['class'] );

        if ( $left_rest !== $right_rest ) {
            return [
                self::kses_finding( $left_run, $right_run, $index )
                    ?? self::finding( 'error', 'markup', $left . ' → ' . $right, $index ),
            ];
        }

        $left_classes  = self::classes( $left_attributes['attributes']['class'] ?? '' );
        $right_classes = self::classes( $right_attributes['attributes']['class'] ?? '' );

        $without_creabb = array_values(
            array_filter(
                $right_classes,
                static fn ( string $name ): bool => ! str_starts_with( $name, 'creabb-' )
            )
        );

        if ( $without_creabb === $left_classes ) {
            return [
                self::finding(
                    'expected',
                    'class-added',
                    implode( ' ', array_diff( $right_classes, $left_classes ) ),
                    $index
                ),
            ];
        }

        return [
            self::finding(
                'error',
                'class-order',
                sprintf( '"%s" → "%s"', implode( ' ', $left_classes ), implode( ' ', $right_classes ) ),
                $index
            ),
        ];
    }

    /**
     * Collects the `type` values the `button` blocks of a carrier hold.
     *
     * Gelesen wird aus den BLOCKBEGRENZERN, nicht aus dem gerenderten Tag:
     * Gefragt ist der Wert, den der Block bekommt, nicht der, den eine der
     * beiden Fassungen daraus macht.
     *
     * WORAUF DAS LAEUFT — und warum nicht auf der Aufnahme. Blockbegrenzer
     * erreichen eine gerenderte Seite nie: `do_blocks()` setzt die Ausgabe aus
     * `render_block()` je geparstem Block zusammen und gibt die Begrenzer nicht
     * wieder aus; die `areoi/*`-Bloecke sind ueberdies dynamisch registriert,
     * ihre Ausgabe ist ausschliesslich die des Rueckrufs. Auf einer Aufnahme
     * lieferte diese Erhebung deshalb immer den Leerstring — und ein leerer
     * Nachweis sieht aus wie ein erbrachter.
     *
     * Gespeist wird sie stattdessen aus dem Bestand, ueber das Inventar von
     * `wp creabb migrate scan`. Das ist zugleich die vollstaendigere Quelle:
     * `06-verifikation.md` verlangt zu Abweichung 4 den Beleg fuer „jeder im
     * BESTAND vorkommende `type`-Wert", nicht fuer die aufgenommenen Seiten.
     *
     * Der Schluessel ist der getrimmte Rohwert. Zwei Schreibweisen desselben
     * Wertes bleiben damit zwei Eintraege — beim Nachweis will man sehen, was
     * tatsaechlich in der Datenbank steht.
     *
     * @param string $content Post content, meta value or option value.
     * @return array<string, int> Value => number of `button` blocks carrying it.
     */
    public static function button_types( string $content ): array {
        $types = [];

        foreach ( self::block_comments( $content ) as $comment ) {
            $name = (string) $comment['name'];

            // Schliessende Begrenzer tragen keine Attribute.
            if ( str_starts_with( $name, '/' ) || ! str_ends_with( $name, '/button' ) ) {
                continue;
            }

            $attributes = is_array( $comment['attributes'] ) ? $comment['attributes'] : [];
            $type       = $attributes['type'] ?? null;
            $type       = is_string( $type ) ? trim( $type ) : '';

            // Ohne Wert greift in beiden Fassungen der Rueckfall `a`. Da gibt
            // es nichts zu belegen.
            if ( '' === $type ) {
                continue;
            }

            $types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
        }

        ksort( $types );

        return $types;
    }

    /**
     * The proof for expected deviation 4 — the tag allowlist of `button`.
     *
     * KEIN VERGLEICH, SONDERN EINE ERHEBUNG. `06-verifikation.md` verlangt zu
     * dieser Abweichung: „Belegen, dass jeder im Bestand vorkommende
     * `type`-Wert auf der Allowlist steht — dann ist der gerenderte Tag
     * identisch und der Diff an dieser Stelle leer." Ein leerer Diff belegt
     * fuer sich genommen nichts; der Nachweis ist diese Aufzaehlung. Sie
     * erscheint deshalb auch dann im Bericht, wenn sich gar nichts geaendert
     * hat — je vorkommendem Wert eine Zeile, mit dem Wert und seiner Zahl.
     *
     * @param array<string, int> $types Value => count, from `button_types()`.
     * @return array<int, array<string, mixed>>
     */
    public static function classify_button_tags( array $types ): array {
        $findings = [];

        foreach ( $types as $type => $count ) {
            $type  = (string) $type;
            $count = (int) $count;

            if ( in_array( strtolower( $type ), self::BUTTON_TAGS, true ) ) {
                $findings[] = self::finding(
                    'expected',
                    'tag-allowlist',
                    sprintf(
                        'button.type="%s" (%dx) steht auf der Allowlist — der gerenderte Tag ist identisch',
                        $type,
                        $count
                    )
                );

                continue;
            }

            $findings[] = self::finding(
                'error',
                'tag-allowlist',
                sprintf(
                    'button.type="%s" (%dx) steht NICHT auf der Allowlist (%s) — der Nachbau rendert dort <a>',
                    $type,
                    $count,
                    implode( ', ', self::BUTTON_TAGS )
                )
            );
        }

        return $findings;
    }

    /**
     * Rejected CSS grid values of one block at one breakpoint.
     *
     * Die erwartete Deklaration ist die des ORIGINALS — Rohwert plus Einheit,
     * ungeprueft (`class.areoi.styles.php:355`, `:362`, `:367`, `:383`).
     *
     * `row_cols` steht bewusst NICHT hier: Beide Seiten ziehen die
     * endstaendigen Ziffern mit demselben Muster, es gibt dort keinen Wert,
     * den nur der Nachbau abweist.
     *
     * @param string               $slug       Block slug without namespace.
     * @param array<string, mixed> $attributes Flat attribute values.
     * @param string               $suffix     Breakpoint suffix including the underscore.
     * @return array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}>
     */
    private static function rejected_grid_values( string $slug, array $attributes, string $suffix ): array {
        $entries = [];

        if ( 'column' === $slug ) {
            $value = $attributes[ 'grid_row' . $suffix ] ?? null;

            if ( ! empty( $value ) && null === \Creationell\BootstrapBlocks\Styles::css_number( $value ) ) {
                $entries[] = [
                    'attribute'   => 'grid_row' . $suffix,
                    'value'       => self::scalar_text( $value ),
                    'property'    => 'grid-row',
                    'reason'      => 'keine Zahl',
                    'declaration' => 'grid-row: ' . self::scalar_text( $value ),
                ];
            }

            return $entries;
        }

        if ( 'row' !== $slug ) {
            return [];
        }

        foreach (
            [
                'grid_gap'     => '--bs-gap',
                'grid_row_gap' => 'row-gap',
            ] as $family => $property
        ) {
            $value = $attributes[ $family . '_dimension' . $suffix ] ?? null;

            if ( empty( $value ) || null !== \Creationell\BootstrapBlocks\Styles::css_number( $value ) ) {
                continue;
            }

            $unit = $attributes[ $family . '_unit' . $suffix ] ?? null;
            $unit = ( is_scalar( $unit ) && '' !== (string) $unit ) ? (string) $unit : 'px';

            $entries[] = [
                'attribute'   => $family . '_dimension' . $suffix,
                'value'       => self::scalar_text( $value ),
                'property'    => $property,
                'reason'      => 'keine Zahl',
                'declaration' => $property . ': ' . self::scalar_text( $value ) . $unit,
            ];
        }

        $rows = $attributes[ 'grid_rows' . $suffix ] ?? null;

        if ( ! empty( $rows ) && null === \Creationell\BootstrapBlocks\Styles::css_number( $rows ) ) {
            $entries[] = [
                'attribute'   => 'grid_rows' . $suffix,
                'value'       => self::scalar_text( $rows ),
                'property'    => '--bs-rows',
                'reason'      => 'keine Zahl',
                'declaration' => '--bs-rows: ' . self::scalar_text( $rows ),
            ];
        }

        return $entries;
    }

    /**
     * The attribute values the value check of the rebuild rejects.
     *
     * GRUNDLAGE FUER ERWARTETE ABWEICHUNG 7. Gelaufen wird dieselbe Pruefung,
     * die `Styles::declarations()` beim Erzeugen des CSS fuehrt — ueber
     * `Styles::css_number()`, nicht ueber eine zweite Fassung derselben Regeln.
     * Nur so sagt das Ergebnis wirklich aus, ob der Generator diesen Wert
     * verworfen hat.
     *
     * SCHLUESSEL: `<uuid>@<breakpoint>`, dieselbe Form wie in `css_rules()` —
     * ein abgewiesenes `padding_top_md` erklaert nur die fehlende
     * `min-width: 768px`-Regel und keine andere.
     *
     * NICHT ERFASST: ein `block_id` ohne UUID-Form. Das Original schreibt ihn
     * ungeprueft in den Selektor; `css_rules()` liest ein solches Gebilde auf
     * keiner der beiden Seiten als Regel, es entsteht also auch kein Befund,
     * den er erklaeren muesste. Dieselbe Klasse von Werten meldet
     * `wp creabb doctor` vor dem Lauf.
     *
     * WORAUF DAS LAEUFT: auf `post_content` und den uebrigen Traegern, nicht
     * auf der Aufnahme — aus demselben Grund wie bei `button_types()`. Auf einer
     * gerenderten Seite gibt es keine Blockbegrenzer, die Erhebung fiele auf den
     * Leerstring, und JEDE im Nachher fehlende CSS-Regel wuerde dann zum
     * blockierenden Fehler, statt als abgewiesener Wert erklaert zu werden.
     *
     * @param string $content Post content, meta value or option value.
     * @return array<string, array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}>>
     */
    public static function rejected_values( string $content ): array {
        // Ohne die Wertpruefung des Generators wird hier NICHTS erklaert: Die
        // fehlende Regel bleibt dann ein Fehler. Fehlschlag zur sicheren Seite.
        if ( ! class_exists( '\Creationell\BootstrapBlocks\Styles' ) ) {
            return [];
        }

        $rejected = [];

        foreach ( self::block_comments( $content ) as $comment ) {
            if ( str_starts_with( (string) $comment['name'], '/' ) ) {
                continue;
            }

            $attributes = is_array( $comment['attributes'] ) ? $comment['attributes'] : [];
            $uuid       = \Creationell\BootstrapBlocks\Styles::block_id( $attributes['block_id'] ?? null );

            if ( null === $uuid ) {
                continue;
            }

            $uuid = strtolower( $uuid );

            foreach ( \Creationell\BootstrapBlocks\Styles::breakpoints() as $name => $min_width ) {
                $suffix  = '_' . (string) $name;
                $key     = $uuid . '@' . (string) (int) $min_width;
                $entries = [];

                $height = $attributes[ 'height_dimension' . $suffix ] ?? null;

                // Bei der HOEHE prueft das Original mit empty() — diese
                // Eigenheit steht so in Styles::declarations().
                if ( ! empty( $height ) && null === \Creationell\BootstrapBlocks\Styles::css_number( $height ) ) {
                    /*
                     * `declaration` ist die Deklaration, die DAS ORIGINAL aus
                     * diesem Wert erzeugt — nachgelesen in
                     * `class.areoi.styles.php:320`: `'height: ' . $wert .
                     * $einheit`, wobei die Einheit der Rohwert von
                     * `height_unit_<bp>` ist und `px`, wenn er leer ist.
                     *
                     * Ohne sie deckt eine Ablehnung jede beliebige Deklaration
                     * DERSELBEN Eigenschaft — auch eine, die zu einem ganz
                     * anderen Wert gehoert und einen echten Regelverlust
                     * bedeutet.
                     */
                    $unit = $attributes[ 'height_unit' . $suffix ] ?? null;
                    $unit = ( is_scalar( $unit ) && '' !== (string) $unit ) ? (string) $unit : 'px';

                    $entries[] = [
                        'attribute'   => 'height_dimension' . $suffix,
                        'value'       => self::scalar_text( $height ),
                        'property'    => 'height',
                        'reason'      => 'keine Zahl',
                        'declaration' => 'height: ' . self::scalar_text( $height ) . $unit,
                    ];
                }

                foreach ( [ 'padding', 'margin' ] as $box ) {
                    foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                        $attribute = $box . '_' . $side . $suffix;
                        $value     = $attributes[ $attribute ] ?? null;

                        // Bewusst NICHT empty(): "0" ist ein gueltiger Wert.
                        if ( null === $value || '' === $value || false === $value ) {
                            continue;
                        }

                        if ( null !== \Creationell\BootstrapBlocks\Styles::css_number( $value ) ) {
                            continue;
                        }

                        // Wie oben: die Form des Originals, `class.areoi.styles.php:329`
                        // — `$box . '-' . $side . ': ' . $wert . $einheit`.
                        $entries[] = [
                            'attribute'   => $attribute,
                            'value'       => self::scalar_text( $value ),
                            'property'    => $box . '-' . $side,
                            'reason'      => 'keine Zahl',
                            'declaration' => $box . '-' . $side . ': ' . self::scalar_text( $value )
                                . \Creationell\BootstrapBlocks\Styles::spacing_unit(),
                        ];
                    }
                }

                if ( [] !== $entries ) {
                    // Dieselbe block_id kann mehrfach vorkommen — sie ist nicht
                    // eindeutig. Die Eintraege werden deshalb angehaengt.
                    $rejected[ $key ] = array_merge( $rejected[ $key ] ?? [], $entries );
                }

                /*
                 * DIE GRID-WERTE, seit E-146 den Zweig gebaut hat.
                 *
                 * Ohne sie bliebe eine Regel, die wegen eines abgewiesenen
                 * Grid-Werts fehlt, ein unerklaerter Fehler — die erwartete
                 * Abweichung 7 verlangt aber, dass zu JEDER fehlenden Regel
                 * der abgewiesene Wert genannt werden kann.
                 *
                 * Der Schluessel traegt denselben Zusatz wie in `css_rules()`:
                 * `row` schreibt unter `.block-<id>.grid`, `column` unter den
                 * einfachen Selektor. Ein Eintrag unter dem falschen Schluessel
                 * erklaerte nichts und faellt niemandem auf.
                 */
                $slug = \Creationell\BootstrapBlocks\Styles::block_slug(
                    [ 'blockName' => (string) $comment['name'] ]
                );

                $grid_entries = self::rejected_grid_values( $slug, $attributes, $suffix );

                if ( [] !== $grid_entries ) {
                    $grid_key = $uuid
                        . \Creationell\BootstrapBlocks\Styles::grid_selector_suffix( $slug )
                        . '@' . (string) (int) $min_width;

                    $rejected[ $grid_key ] = array_merge( $rejected[ $grid_key ] ?? [], $grid_entries );
                }
            }
        }

        return $rejected;
    }

    /**
     * Reads the generated stylesheet into one entry per rule.
     *
     * SCHLUESSEL: `<uuid>@<breakpoint>`. Derselbe Block an einem anderen
     * Breakpoint ist eine EIGENE Regel — sonst verschwaende eine fehlende
     * `min-width: 992px`-Regel hinter der vorhandenen `min-width: 0px`-Regel.
     *
     * Verglichen wird gegen die whitespace-normalisierte Form: Der Nachbau
     * minifiziert nicht mehr (erwartete Abweichung 6), und das allein ist keine
     * Abweichung.
     *
     * @param string $css Generated stylesheet.
     * @return array<string, string>
     */
    public static function css_rules( string $css ): array {
        $rules    = [];
        $length   = strlen( $css );
        $offset   = 0;
        $media    = '';
        $depth    = 0;
        $media_at = -1;

        while ( $offset < $length ) {
            if ( '@' === $css[ $offset ] && 0 === substr_compare( $css, '@media', $offset, 6, true ) ) {
                $brace = strpos( $css, '{', $offset );

                if ( false === $brace ) {
                    break;
                }

                $head    = substr( $css, $offset, $brace - $offset );
                $matches = [];

                $media    = preg_match( '/min-width\s*:\s*(\d+)/i', $head, $matches ) ? $matches[1] : '';
                $media_at = $depth;

                ++$depth;
                $offset = $brace + 1;

                continue;
            }

            $matches = [];

            if (
                '.' === $css[ $offset ]
                && preg_match(
                    // Derselbe Zeichensatz wie in block_ids() und in
                    // Styles::block_id(). Der Selektor ist hier auf `^`
                    // verankert, ein Lookbehind braucht es also nicht.
                    //
                    // `.grid` GEHOERT DAZU. Im CSS-Grid-Modus erzeugt das
                    // Original fuer `areoi/row` eine ZWEITE Regel unter dem
                    // zusammengesetzten Selektor `.block-<id>.grid` —
                    // `--bs-columns`, `--bs-gap`, `row-gap`, `--bs-rows`
                    // (`class.areoi.styles.php:369`). Ohne diesen Zweig war
                    // die gesamte Grid-Regelmenge fuer den Vergleich
                    // unsichtbar: Der Zeiger lief ueber sie hinweg, und weder
                    // ein Verlust noch eine Aenderung fiel auf.
                    //
                    // `areoi/column` schreibt sein `grid-row` dagegen unter
                    // den EINFACHEN Selektor (`:385`) — dort faellt es mit der
                    // Abstandsregel desselben Blocks zusammen, und genau
                    // dafuer haengt `css_rules()` seit #31 an, statt zu
                    // ueberschreiben.
                    '/^\.block-([A-Za-z0-9_-]+)(\.grid)?\s*\{/',
                    substr( $css, $offset, 128 ),
                    $matches
                )
            ) {
                $open   = (int) strpos( $css, '{', $offset );
                $inner  = 1;
                $cursor = $open + 1;

                while ( $cursor < $length && $inner > 0 ) {
                    if ( '{' === $css[ $cursor ] ) {
                        ++$inner;
                    } elseif ( '}' === $css[ $cursor ] ) {
                        --$inner;
                    }

                    ++$cursor;
                }

                $body = substr( $css, $open + 1, $cursor - $open - 2 );

                /*
                 * ANHAENGEN, NICHT ZUWEISEN.
                 *
                 * Dieselbe `block_id` kann auf einer Seite mehrfach vorkommen —
                 * sie ist nicht eindeutig. Beide Erzeuger legen dann ZWEI Regeln
                 * mit demselben Selektor in denselben `@media`-Block:
                 * `Styles::rules()` haengt je Blockvorkommen ein
                 * `.block-<id> { … }` an, ohne zusammenzufassen, und
                 * `Styles::stylesheet()` packt alle Regeln eines Breakpoints in
                 * EINEN Block. Das Original macht es genauso
                 * (`class.areoi.styles.php:335`, `add_block_style()` rekursiv).
                 *
                 * Eine Zuweisung liesse die erste Regel lautlos aus dem
                 * Vergleich fallen. Ueber alle drei Dumps gemessen — 1624
                 * Traeger, 28 992 Bloecke — passiert das auf 17 Traegern mit
                 * 24 Regeln, 10 davon mit ABWEICHENDER Deklaration. Die
                 * Kaskade waere damit nicht unvollstaendig nachgebildet,
                 * sondern falsch: Im Browser gewinnt bei gleicher Spezifitaet
                 * die spaetere Deklaration je EIGENSCHAFT, nicht die spaetere
                 * Regel als Ganzes.
                 *
                 * Die Schluesselform bleibt unveraendert, damit
                 * `declaration_list()`, `dropped_declarations()` und
                 * `covered_by_rejection()` unberuehrt sind. Die Reihenfolge ist
                 * auf beiden Seiten deterministisch — derselbe rekursive
                 * Durchlauf ueber dieselben Bloecke —, es entsteht kein
                 * Rauschen.
                 */
                // Der Zusatz geht in den SCHLUESSEL, nicht in die Kennung:
                // `.block-x` und `.block-x.grid` sind verschiedene Regeln, und
                // eine Aenderung an der einen darf nicht wie eine an der
                // anderen aussehen. `block_id_of_key()` holt die Kennung
                // wieder heraus.
                $modifier     = isset( $matches[2] ) ? strtolower( (string) $matches[2] ) : '';
                $key          = strtolower( $matches[1] ) . $modifier . '@' . $media;
                $declarations = self::normalize_declarations( $body );

                if ( isset( $rules[ $key ] ) && '' !== $rules[ $key ] && '' !== $declarations ) {
                    $rules[ $key ] .= '; ' . $declarations;
                } elseif ( ! isset( $rules[ $key ] ) || '' === $rules[ $key ] ) {
                    $rules[ $key ] = $declarations;
                }

                $offset = $cursor;

                continue;
            }

            if ( '{' === $css[ $offset ] ) {
                ++$depth;
            } elseif ( '}' === $css[ $offset ] ) {
                --$depth;

                if ( $depth === $media_at ) {
                    $media    = '';
                    $media_at = -1;
                }
            }

            ++$offset;
        }

        return $rules;
    }

    /**
     * The `block_id` behind a rule key.
     *
     * Der Schluessel von `css_rules()` heisst `<block_id>[.modifier]@<breakpoint>`.
     * Der Modifikator gehoert zum SELEKTOR, nicht zur Kennung: `.block-x` und
     * `.block-x.grid` sind zwei verschiedene Regeln desselben Blocks, und wer
     * fragt, ob die Kennung auf der Seite vorkommt, meint den Block.
     *
     * @param string $key Rule key from `css_rules()`.
     */
    public static function block_id_of_key( string $key ): string {
        $vor = (string) strstr( $key, '@', true );

        if ( '' === $vor ) {
            $vor = $key;
        }

        $punkt = strpos( $vor, '.' );

        return false === $punkt ? $vor : substr( $vor, 0, $punkt );
    }

    /**
     * Compares two generated stylesheets.
     *
     * @param string                                                                                                                    $before   Reference stylesheet.
     * @param string                                                                                                                    $after    Compared stylesheet.
     * @param array<int, string>                                                                                                        $page_ids `block_id` values the page carries.
     * @param array<string, array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}>> $rejected Rejected values per `<uuid>@<breakpoint>`, from `rejected_values()`.
     * @return array<int, array<string, mixed>>
     */
    public static function compare_css( string $before, string $after, array $page_ids, array $rejected = [] ): array {
        $left  = self::css_rules( $before );
        $right = self::css_rules( $after );

        $on_page = [];

        foreach ( $page_ids as $id ) {
            $on_page[ strtolower( (string) $id ) ] = true;
        }

        $findings = [];

        foreach ( $left as $key => $declarations ) {
            $uuid = self::block_id_of_key( (string) $key );

            if ( ! array_key_exists( $key, $right ) ) {
                // ERWARTETE ABWEICHUNG 2: Das Original erzeugt Regeln fuer ALLE
                // wp_block-Posts der Installation, auch fuer solche, die auf der
                // Seite gar nicht vorkommen. Der Nachbau laesst sie weg. Fehlt
                // dagegen eine Regel zu einer block_id, die auf der Seite steht,
                // ist es ein Fehler.
                if ( ! isset( $on_page[ $uuid ] ) ) {
                    $findings[] = self::finding( 'expected', 'css-absent-block', (string) $key );

                    continue;
                }

                // ERWARTETE ABWEICHUNG 7: … es sei denn, JEDE Deklaration der
                // weggefallenen Regel gehoert zu einem Wert, den die
                // Wertpruefung des Nachbaus abgewiesen hat. Dann erzeugt sie
                // planmaessig keine Regel mehr.
                $entries = $rejected[ (string) $key ] ?? [];

                $findings[] = self::covered_by_rejection( self::declaration_list( $declarations ), $entries )
                    ? self::finding(
                        'expected',
                        'css-rejected-value',
                        sprintf( '%s: %s', (string) $key, self::rejection_detail( $entries ) )
                    )
                    : self::finding( 'error', 'css-missing-rule', (string) $key );

                continue;
            }

            if ( $declarations !== $right[ $key ] ) {
                // Eine Regel kann auch nur SCHRUMPFEN. Erwartet ist das genau
                // dann, wenn nichts hinzugekommen und nichts umgestellt wurde
                // und jede weggefallene Eigenschaft zu einem abgewiesenen Wert
                // desselben Blocks an demselben Breakpoint gehoert. Ein
                // GEAENDERTER Wert bleibt ein Fehler — eine still veraenderte
                // Laenge ist eine sichtbare Layoutaenderung.
                $entries = $rejected[ (string) $key ] ?? [];
                $dropped = self::dropped_declarations( $declarations, $right[ $key ] );

                if ( null !== $dropped && self::covered_by_rejection( $dropped, $entries ) ) {
                    $findings[] = self::finding(
                        'expected',
                        'css-rejected-value',
                        sprintf(
                            '%s: "%s" faellt weg — %s',
                            (string) $key,
                            implode( '; ', $dropped ),
                            self::rejection_detail( $entries )
                        )
                    );

                    continue;
                }

                $findings[] = self::finding(
                    'error',
                    'css-declaration',
                    sprintf( '%s: "%s" → "%s"', (string) $key, $declarations, $right[ $key ] )
                );
            }
        }

        foreach ( array_keys( $right ) as $key ) {
            if ( ! array_key_exists( $key, $left ) ) {
                $findings[] = self::finding( 'error', 'css-extra-rule', (string) $key );
            }
        }

        return $findings;
    }

    /**
     * The block delimiters of a carrier, in document order.
     *
     * @param string $content Post content, meta value or option value.
     * @return array<int, array{name: string, attributes: mixed, raw: string}>
     */
    private static function block_comments( string $content ): array {
        $matches = [];

        $pattern = '#<!--\s+(/?)wp:([a-z0-9-]+/[a-z0-9_-]+)(?:\s+(\{(?:[^{}]++|(?3))*\}))?\s*/?-->#i';

        // BEWACHT, weil dieses Muster REKURSIV ist und ueber ganze Traeger
        // laeuft. Ein Abbruch der Engine liefert `false` — von „kein Block
        // gefunden" nicht zu unterscheiden. Beide Erhebungen, die hier haengen,
        // sind Nachweise: Ein leerer Nachweis sieht aus wie ein erbrachter, und
        // die Abnahme wuerde unterschrieben, als waere die Allowlist belegt.
        if ( false === preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
            self::abort( 'block_comments()' );
        }

        if ( [] === $matches ) {
            return [];
        }

        $comments = [];

        foreach ( $matches as $match ) {
            $attributes = [];

            if ( isset( $match[3] ) && '' !== $match[3] ) {
                $decoded = json_decode( $match[3], true );

                if ( is_array( $decoded ) ) {
                    $attributes = isset( $decoded['blockstudio']['attributes'] ) && is_array( $decoded['blockstudio']['attributes'] )
                        ? $decoded['blockstudio']['attributes']
                        : $decoded;
                }
            }

            $comments[] = [
                'name'       => ( '/' === $match[1] ? '/' : '' ) . strtolower( $match[2] ),
                'attributes' => $attributes,
                'raw'        => $match[0],
            ];
        }

        return $comments;
    }

    /**
     * Splits a tag token into tag name and attributes.
     *
     * @param string $token Token.
     * @return array{tag: string, attributes: array<string, string>}|null Null when it is not a tag.
     */
    private static function tag_attributes( string $token ): ?array {
        $matches = [];

        if ( ! preg_match( '#^<\s*(/?)([a-z0-9:-]+)(.*?)/?>$#is', $token, $matches ) ) {
            return null;
        }

        $attributes = [];
        $pairs      = [];

        preg_match_all(
            '/([a-z0-9_:.-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/i',
            (string) $matches[3],
            $pairs,
            PREG_SET_ORDER
        );

        foreach ( $pairs as $pair ) {
            $value = '';

            foreach ( [ 2, 3, 4 ] as $group ) {
                if ( isset( $pair[ $group ] ) && '' !== $pair[ $group ] ) {
                    $value = $pair[ $group ];

                    break;
                }
            }

            $attributes[ strtolower( $pair[1] ) ] = $value;
        }

        return [
            'tag'        => $matches[1] . strtolower( $matches[2] ),
            'attributes' => $attributes,
        ];
    }

    /**
     * Splits a class attribute into its classes, order preserved.
     *
     * @param string $attribute Value of the class attribute.
     * @return array<int, string>
     */
    private static function classes( string $attribute ): array {
        $parts = preg_split( '/\s+/', trim( $attribute ) );

        if ( ! is_array( $parts ) ) {
            return [];
        }

        return array_values( array_filter( $parts, static fn ( string $item ): bool => '' !== $item ) );
    }

    /**
     * Normalises the declarations of one rule.
     *
     * @param string $body Declarations between the braces.
     */
    private static function normalize_declarations( string $body ): string {
        $parts = explode( ';', $body );
        $clean = [];

        foreach ( $parts as $part ) {
            $part = trim( (string) preg_replace( '/\s+/', ' ', $part ) );
            $part = (string) preg_replace( '/\s*:\s*/', ': ', $part );

            if ( '' !== $part ) {
                $clean[] = $part;
            }
        }

        return implode( '; ', $clean );
    }

    /**
     * The proof for expected deviation 5 — `wp_kses_post()` on `button.text`.
     *
     * WARUM DAS NICHT ZU GROSSZUEGIG IST: Der Zweig greift nur, wenn die
     * Vorher-Marken NACH `wp_kses_post()` genau den Nachher-Marken entsprechen.
     * Verloren gegangenes Markup, das der Filter gar nicht anfasst — ein
     * fehlendes `<div>`, ein fehlendes `class`-Attribut —, bliebe nach dem
     * Filter stehen, und der Vergleich schluege fehl. Entfernt der Nachbau
     * MEHR als der Filter, ebenso.
     *
     * Der Zweig ist nicht auf `button`-Bloecke eingegrenzt — im Rumpf einer
     * Seite ist die Herkunft einer Marke nicht mehr zu sehen. Er braucht es
     * auch nicht: Was er durchlaesst, ist per Konstruktion genau die Wirkung
     * von `wp_kses_post()`.
     *
     * Verglichen wird OHNE Leerraum. `tokenize()` trimmt Textmarken ohnehin,
     * und Leerraum zwischen Tags ist nach `06-verifikation.md` keine
     * Abweichung.
     *
     * Ohne WordPress gibt es kein `wp_kses_post()`. Dann liefert diese Methode
     * `null` und der Befund bleibt `error:markup` — Fehlschlag zur sicheren
     * Seite.
     *
     * @param string $before Reference region, tokens concatenated.
     * @param string $after  Compared region, tokens concatenated.
     * @param int    $index  Token position of the region.
     * @return array<string, mixed>|null The finding, or null when it does not apply.
     */
    private static function kses_finding( string $before, string $after, int $index ): ?array {
        if ( ! function_exists( 'wp_kses_post' ) || '' === trim( $before ) ) {
            return null;
        }

        $filtered = (string) wp_kses_post( $before );

        if ( $filtered === $before ) {
            return null;
        }

        $squeeze = static fn ( string $value ): string => (string) preg_replace( '/\s+/', '', $value );

        if ( $squeeze( $filtered ) !== $squeeze( $after ) ) {
            return null;
        }

        $matches = [];
        $removed = [];

        preg_match_all( '#</?[a-z0-9]+\b[^>]*>#i', $before, $matches );

        foreach ( $matches[0] as $tag ) {
            if ( ! str_contains( $filtered, (string) $tag ) ) {
                $removed[ (string) $tag ] = true;
            }
        }

        // WOFUER DIESER ZWEIG DA IST — und wofuer nicht. Erwartete Abweichung 5
        // betrifft GENAU EIN Attribut: den Text eines `button`-Blocks, den das
        // Original als einziges Textattribut roh ausgibt und der Nachbau durch
        // `wp_kses_post()` schickt. Das ist Inline-Markup in einer Beschriftung.
        //
        // Ohne die folgende Wache deckt der Zweig etwas ganz anderes mit ab.
        // Verschwindet irgendwo auf der Seite ein `<iframe src="…">`, so
        // entfernt `wp_kses_post()` genau diesen Tag auch aus der Vorher-Marke —
        // der gefilterte Text ist dann gleich dem Nachher-Text, und der Verlust
        // geht als ERWARTETE Abweichung durch. Bilanz gruen, Einbettung weg.
        //
        // Die Grenze verlaeuft nicht am Elementnamen, sondern an der Nutzlast:
        // `wp_kses_post()` loescht keinen Text, es nimmt nur Tags weg. Bleibt
        // der Inhalt stehen — `<script>alert(1)</script>` wird zu `alert(1)` —,
        // ist der Filter die Erklaerung. Steckt der Inhalt dagegen in einem
        // Attribut, ist er danach spurlos fort, und das ist ein Verlust.
        //
        // UND DIE NUTZLAST STEHT SELTEN UNTER IHREM NACKTEN NAMEN. Jeder
        // gaengige Lazyloader schiebt sie hinter ein `data-`: `data-src`,
        // `data-srcset`, `data-lazy-src`. Ein Muster auf `\ssrc\s*=` trifft
        // davon keines — vor `src` steht ein Bindestrich statt Leerraum, und
        // hinter `data` einer statt eines Gleichheitszeichens. Ein verlorenes
        // `<iframe data-src>` ginge damit als erwartete Abweichung durch.
        // Die drei geprueften Installationen fuehren autoptimize,
        // speed-booster-pack, ewww-image-optimizer und litespeed-cache.
        //
        // `(?:data-[a-z0-9_:.-]*)?` nimmt jedes Herstellerpraefix mit und
        // bleibt trotzdem eng: `data-cfasync` traegt keine Nutzlast und
        // matcht auch keinen der sieben Namen.
        foreach ( array_keys( $removed ) as $tag ) {
            foreach ( self::PAYLOAD_ATTRIBUTES as $attribute ) {
                if ( 1 === preg_match( '/\s(?:data-[a-z0-9_:.-]*)?' . $attribute . '\s*=/i', (string) $tag ) ) {
                    return null;
                }
            }
        }

        return self::finding(
            'expected',
            'kses',
            sprintf(
                'wp_kses_post() entfernt %s aus "%s"',
                [] === $removed ? 'Attribute' : implode( ' ', array_keys( $removed ) ),
                self::excerpt( $before )
            ),
            $index
        );
    }

    /**
     * Shortens a value for a report line.
     *
     * @param string $value Value.
     */
    private static function excerpt( string $value ): string {
        $value = trim( (string) preg_replace( '/\s+/', ' ', $value ) );

        return mb_strlen( $value ) > 160 ? mb_substr( $value, 0, 157 ) . '…' : $value;
    }

    /**
     * Renders an attribute value for a report line.
     *
     * @param mixed $value Raw attribute value.
     */
    private static function scalar_text( mixed $value ): string {
        return is_scalar( $value ) ? self::excerpt( (string) $value ) : gettype( $value );
    }

    /**
     * Splits normalised declarations into a list.
     *
     * @param string $declarations Output of `normalize_declarations()`.
     * @return array<int, string>
     */
    private static function declaration_list( string $declarations ): array {
        if ( '' === trim( $declarations ) ) {
            return [];
        }

        $parts = array_map( 'trim', explode( ';', $declarations ) );

        return array_values( array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
    }

    /**
     * The declarations the compared rule no longer has.
     *
     * @param string $before Reference declarations.
     * @param string $after  Compared declarations.
     * @return array<int, string>|null Dropped declarations, or null when something
     *                                 was added, changed or reordered.
     */
    private static function dropped_declarations( string $before, string $after ): ?array {
        $left    = self::declaration_list( $before );
        $right   = self::declaration_list( $after );
        $dropped = [];
        $cursor  = 0;

        foreach ( $left as $declaration ) {
            if ( isset( $right[ $cursor ] ) && $right[ $cursor ] === $declaration ) {
                ++$cursor;

                continue;
            }

            $dropped[] = $declaration;
        }

        // Der Rest muss aufgegangen sein. Sonst ist etwas hinzugekommen,
        // geaendert oder umgestellt worden — und das erklaert kein abgewiesener
        // Wert.
        return count( $right ) === $cursor ? $dropped : null;
    }

    /**
     * Whether every dropped declaration belongs to a rejected value.
     *
     * @param array<int, string>                                                                                         $dropped Dropped declarations.
     * @param array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}> $entries Rejected values of that rule.
     */
    private static function covered_by_rejection( array $dropped, array $entries ): bool {
        if ( [] === $dropped || [] === $entries ) {
            return false;
        }

        $expected = [];

        foreach ( $entries as $entry ) {
            $expected[ self::normalize_declarations( (string) $entry['declaration'] ) ] = true;
        }

        foreach ( $dropped as $declaration ) {
            if ( ! isset( $expected[ self::normalize_declarations( $declaration ) ] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Names the rejected values of one rule.
     *
     * @param array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}> $entries Rejected values.
     */
    private static function rejection_detail( array $entries ): string {
        $parts = [];

        foreach ( $entries as $entry ) {
            $parts[] = sprintf(
                '%s="%s" (%s)',
                (string) $entry['attribute'],
                (string) $entry['value'],
                (string) $entry['reason']
            );
        }

        return implode( ', ', $parts );
    }

    /**
     * Compares one URL: generated CSS and rendered markup.
     *
     * Reihenfolge der Befunde: erst das generierte CSS, dann der Rumpf. Der
     * Rumpf kommt zuletzt, weil er der laengste Teil ist und der andere sonst
     * unterginge.
     *
     * WAS HIER NICHT MEHR STEHT — und warum. Der Plan liess an dieser Stelle
     * drei weitere Erhebungen laufen: den Vergleich der Blockkommentare, die
     * `type`-Werte der `button`-Bloecke und die abgewiesenen Attributwerte,
     * alle drei auf der Vorher-AUFNAHME.
     *
     * Auf einer Aufnahme gibt es keine Blockbegrenzer. `do_blocks()` setzt die
     * Ausgabe aus `render_block()` je geparstem Block zusammen und gibt die
     * Begrenzer nicht wieder aus; die `areoi/*`-Bloecke sind ueberdies dynamisch
     * registriert. Alle drei lieferten dort immer nichts — und zwei davon sind
     * NACHWEISE, deren Leerstand aussieht wie ein erbrachter Nachweis.
     *
     * Deshalb:
     *   - Der Vergleich der Blockkommentare entfaellt. `06-verifikation.md`
     *     weist ihn ausdruecklich Bein 1 zu („geprueft wird das in Bein 1,
     *     nicht hier"), und dort ist er ueber den ganzen Bestand gefahren.
     *   - Die abgewiesenen Werte kommen als `$rejected` herein, erhoben ueber
     *     den Bestand.
     *   - Der Allowlist-Nachweis (`classify_button_tags()`) haengt gar nicht an
     *     einer URL. Er ist eine Aussage ueber den BESTAND und erscheint
     *     deshalb einmal in der Bilanz, nicht je Seite wiederholt.
     *
     * DAS ERZEUGTE CSS WIRD HIER GESCHNITTEN, NICHT VON AUSSEN GEREICHT. Beide
     * Plugins sind waehrend der Uebergangsphase aktiv und erzeugen Regeln mit
     * denselben Selektoren; ein verketteter String laesst in `css_rules()` das
     * spaeter gedruckte gewinnen — auf beiden Seiten das Original. Der Vergleich
     * haelt dann das Original gegen sich selbst und meldet „identisch".
     * Einzelheiten im Docblock von `HANDLE_ORIGINAL`.
     *
     * Weil hier aus dem HTML geschnitten wird und das vollstaendige HTML in
     * jeder Aufnahme liegt, werden auch BESTEHENDE Aufnahmen richtig bewertet —
     * es muss keine Seite neu aufgenommen werden.
     *
     * @param string                                                                                                                    $before_html Raw source of the reference page.
     * @param string                                                                                                                    $after_html  Raw source of the compared page.
     * @param array<string, array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}>> $rejected Rejected values, surveyed over the stock.
     * @return array<int, array<string, mixed>>
     */
    public static function compare(
        string $before_html,
        string $after_html,
        array $rejected = []
    ): array {
        $page_ids = self::block_ids( self::mask_inline_css( $before_html ) );

        $before_css = self::extract_inline_css( $before_html, self::HANDLE_ORIGINAL );
        $after_css  = self::extract_inline_css( $after_html, self::HANDLE_REBUILD );

        // Erst der Waechter: fehlt eine der beiden Seiten ganz, vergleicht
        // `compare_css()` leer gegen leer und meldet „identisch".
        $findings = self::css_reference_findings( $before_html );

        // ERWARTETE ABWEICHUNG 7: welcher Attributwert erklaert eine fehlende
        // Regel — und welcher nicht.
        $findings = array_merge(
            $findings,
            self::compare_css( $before_css, $after_css, $page_ids, $rejected )
        );

        $reference = self::tokenize( self::normalize_html( $before_html ) );
        $compared  = self::tokenize( self::normalize_html( $after_html ) );

        foreach ( self::hunks( $reference, $compared ) as $hunk ) {
            $findings = array_merge(
                $findings,
                self::classify_hunk( $hunk['before'], $hunk['after'], $hunk['index'] )
            );
        }

        return $findings;
    }

    /**
     * Counts expected deviations and errors.
     *
     * @param array<int, array<string, mixed>> $findings Findings of one URL.
     * @return array{expected:int, errors:int}
     */
    public static function tally( array $findings ): array {
        $tally = [
            'expected' => 0,
            'errors'   => 0,
        ];

        foreach ( $findings as $finding ) {
            if ( 'error' === $finding['status'] ) {
                ++$tally['errors'];
                continue;
            }

            ++$tally['expected'];
        }

        return $tally;
    }

    /**
     * Renders the report for one URL.
     *
     * @param string                           $url      URL the findings belong to.
     * @param array<int, array<string, mixed>> $findings Findings of that URL.
     * @return array<int, string>
     */
    public static function report_lines( string $url, array $findings ): array {
        $lines = [ '## ' . $url ];

        if ( [] === $findings ) {
            $lines[] = '   identisch (nach Normalisierung)';

            return $lines;
        }

        foreach ( $findings as $finding ) {
            $lines[] = sprintf(
                '   [%s] %s%s: %s',
                'error' === $finding['status'] ? 'FEHLER  ' : 'erwartet',
                $finding['kind'],
                $finding['position'] >= 0 ? sprintf( ' @%d', $finding['position'] ) : '',
                $finding['detail']
            );
        }

        return $lines;
    }

    /**
     * Pairs two snapshot indexes by URL.
     *
     * @param array<string, mixed> $before_index Decoded `index.json` of the reference run.
     * @param array<string, mixed> $after_index  Decoded `index.json` of the compared run.
     * @return array{pairs:array<int, array{url:string, before:array<string,mixed>, after:array<string,mixed>}>, missing:array<int,string>, extra:array<int,string>}
     */
    public static function pair_indexes( array $before_index, array $after_index ): array {
        $by_url = [];

        foreach ( (array) ( $after_index['entries'] ?? [] ) as $entry ) {
            if ( is_array( $entry ) && isset( $entry['url'] ) ) {
                $by_url[ (string) $entry['url'] ] = $entry;
            }
        }

        $pairs   = [];
        $missing = [];
        $seen    = [];

        foreach ( (array) ( $before_index['entries'] ?? [] ) as $entry ) {
            if ( ! is_array( $entry ) || ! isset( $entry['url'] ) ) {
                continue;
            }

            $url          = (string) $entry['url'];
            $seen[ $url ] = true;

            if ( ! isset( $by_url[ $url ] ) ) {
                $missing[] = $url;
                continue;
            }

            $pairs[] = [
                'url'    => $url,
                'before' => $entry,
                'after'  => $by_url[ $url ],
            ];
        }

        $extra = [];

        foreach ( array_keys( $by_url ) as $url ) {
            if ( ! isset( $seen[ $url ] ) ) {
                $extra[] = (string) $url;
            }
        }

        return [
            'pairs'   => $pairs,
            'missing' => $missing,
            'extra'   => $extra,
        ];
    }

    /**
     * Renders the closing balance over all compared URLs.
     *
     * Erwartete Abweichungen und Fehler werden GETRENNT gezaehlt. Eine
     * Summenzahl waere eine Falschaussage: Die erwarteten Abweichungen sind
     * der Nachweis, dass der Nachbau tut, was er soll — die Fehler sind das
     * Gegenteil.
     *
     * NICHT UEBERSETZT, und das ist Absicht. Diese Zeile und die Berichtszeilen
     * aus `report_lines()` sind ein maschinenlesbares Erzeugnis: Die
     * Eval-Skripte von Bein 2 und die Nachweisablage von Bein 3 suchen darin
     * nach `[erwartet]` und `[FEHLER`. Waere die Form uebersetzbar, haenge das
     * Ergebnis der Abnahme an der Locale der Instanz, auf der sie faehrt.
     * Dieselbe Trennung fuehrt `wp creabb doctor`: Seine `status`-Token
     * (`ok`/`warn`/`error`) sind fest, seine Meldungen uebersetzt.
     *
     * @param array<int, array{url:string, findings:array<int, array<string, mixed>>}> $reports Per-URL reports.
     */
    public static function summary_line( array $reports ): string {
        $totals = self::totals( $reports );

        return sprintf(
            '%d URLs, %d erwartete Abweichungen, %d Fehler',
            count( $reports ),
            $totals['expected'],
            $totals['errors']
        );
    }

    /**
     * Sums the tally over all reports.
     *
     * @param array<int, array{url:string, findings:array<int, array<string, mixed>>}> $reports Per-URL reports.
     * @return array{expected:int, errors:int}
     */
    public static function totals( array $reports ): array {
        $totals = [
            'expected' => 0,
            'errors'   => 0,
        ];

        foreach ( $reports as $report ) {
            $tally               = self::tally( $report['findings'] );
            $totals['expected'] += $tally['expected'];
            $totals['errors']   += $tally['errors'];
        }

        return $totals;
    }

    /**
     * Exit code of a diff run.
     *
     * 0 nur, wenn mindestens eine URL verglichen wurde und keine einzige nicht
     * erwartete Abweichung uebrig blieb. 2 steht fuer „nichts verglichen" —
     * ein leerer Lauf ist kein gruener Lauf.
     *
     * @param array<int, array{url:string, findings:array<int, array<string, mixed>>}> $reports Per-URL reports.
     */
    public static function exit_code( array $reports ): int {
        if ( [] === $reports ) {
            return 2;
        }

        foreach ( $reports as $report ) {
            if ( self::tally( $report['findings'] )['errors'] > 0 ) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * The exit code of a whole run, with the reasons that raised it.
     *
     * WARUM DIESE ENTSCHEIDUNG HIER STEHT UND NICHT IM KOMMANDO. Im Kommando
     * fuehrt sie keine Suite aus: Eine Mutation, die die Eskalation entfernt,
     * ueberlebte jeden Lauf von `composer test`, und der Mangel fiele erst bei
     * der Abnahme auf — als gruener Lauf ueber eine unvollstaendige Aufnahme.
     * Dieselbe Auslagerung hat Block 3 fuer die Entscheidungen von `verify`
     * und `rollback` gefuehrt.
     *
     * DREI GRUENDE HEBEN DEN CODE, und alle drei sind derselbe Satz aus
     * `06-verifikation.md`: „jede betroffene URL, nicht eine Stichprobe."
     *
     *   1. Eine URL steht nur in einer der beiden Aufnahmen.
     *   2. Eine Aufnahme hat weniger Seiten aufgenommen, als angefordert waren.
     *      Diesen Fall sieht die Paarbildung NICHT: Faellt dieselbe URL in
     *      beiden Laeufen aus, steht sie in keinem der beiden Verzeichnisse und
     *      taucht weder unter `missing` noch unter `extra` auf.
     *   3. Ein Vergleich hat einen Fehlerbefund geliefert.
     *
     * @param array<int, array{url:string, findings:array<int, array<string, mixed>>}> $reports Per-URL reports.
     * @param array{missing: array<int, string>, extra: array<int, string>}            $paired  Result of `pair_indexes()`.
     * @param array<string, array<string, mixed>>                                      $indexes Snapshot indexes, keyed by label.
     * @return array{code:int, reasons:array<int, string>}
     */
    public static function run_exit_code( array $reports, array $paired, array $indexes ): array {
        $code    = self::exit_code( $reports );
        $reasons = [];

        if ( [] !== $paired['missing'] ) {
            $code      = max( $code, 1 );
            $reasons[] = sprintf(
                '%d URL(s) fehlen in der zweiten Aufnahme',
                count( $paired['missing'] )
            );
        }

        if ( [] !== $paired['extra'] ) {
            $code      = max( $code, 1 );
            $reasons[] = sprintf(
                '%d URL(s) stehen nur in der zweiten Aufnahme',
                count( $paired['extra'] )
            );
        }

        foreach ( $indexes as $label => $index ) {
            $requested = isset( $index['requested'] ) ? (int) $index['requested'] : 0;
            $captured  = isset( $index['captured'] ) ? (int) $index['captured'] : 0;

            // Ein Verzeichnis ohne die beiden Zahlen stammt aus einer aelteren
            // Fassung. Es wird NICHT stillschweigend als vollstaendig
            // behandelt — dann waere die Pruefung genau dort blind, wo sie
            // gebraucht wird.
            if ( ! isset( $index['requested'], $index['captured'] ) ) {
                $code      = max( $code, 1 );
                $reasons[] = sprintf(
                    'Aufnahme "%s" nennt nicht, wie viele URLs angefordert waren — Vollstaendigkeit nicht pruefbar',
                    (string) $label
                );

                continue;
            }

            if ( $captured < $requested ) {
                $code      = max( $code, 1 );
                $reasons[] = sprintf(
                    'Aufnahme "%s" ist unvollstaendig: %d von %d URLs aufgenommen',
                    (string) $label,
                    $captured,
                    $requested
                );
            }
        }

        return [
            'code'    => $code,
            'reasons' => $reasons,
        ];
    }

    /**
     * Surveys the whole stock for the two proofs that need block delimiters.
     *
     * ERWARTETE ABWEICHUNG 4 und 7 aus `06-verifikation.md`. Beide sind
     * Aussagen ueber den BESTAND, nicht ueber eine Seite: Abweichung 4 verlangt
     * den Beleg fuer „jeder im Bestand vorkommende `type`-Wert", Abweichung 7
     * erklaert eine fehlende CSS-Regel durch einen Wert, den die Wertpruefung
     * des Nachbaus abweist.
     *
     * Deshalb laeuft die Erhebung EINMAL fuer den ganzen Lauf und nicht je
     * aufgenommener URL — und ueber die Traeger, nicht ueber die Aufnahmen.
     *
     * `iterable` UND NICHT `array`: Der Aufrufer reicht einen Generator ueber
     * alle Traeger herein. Ein Array zu verlangen hiesse, den gesamten Bestand
     * vorher in den Speicher zu holen — im gemessenen Bestand sind das bis zu
     * 18 713 Blockvorkommen je Installation.
     *
     * phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Begruendung im Block darueber.
     *
     * @param iterable<int, array{source: string, id: string, text: string}> $carriers Carrier texts.
     * @return array{button_types: array<string, int>, rejected: array<string, array<int, array{attribute: string, value: string, property: string, reason: string, declaration: string}>>}
     * @throws \RuntimeException When the regex engine aborted.
     */
    public static function survey( iterable $carriers ): array {
        // phpcs:enable Squiz.Commenting.FunctionComment.IncorrectTypeHint
        $types    = [];
        $rejected = [];

        foreach ( $carriers as $carrier ) {
            $text = (string) $carrier['text'];

            foreach ( self::button_types( $text ) as $type => $count ) {
                $types[ (string) $type ] = ( $types[ (string) $type ] ?? 0 ) + (int) $count;
            }

            foreach ( self::rejected_values( $text ) as $key => $entries ) {
                // Derselbe Block kann in mehreren Traegern stehen — etwa in
                // einem Beitrag und in dessen Revision. Die Eintraege werden
                // zusammengelegt, nicht ueberschrieben: Sonst haenge es an der
                // Reihenfolge der Traeger, welcher abgewiesene Wert eine
                // fehlende Regel erklaert.
                $rejected[ (string) $key ] = array_merge( $rejected[ (string) $key ] ?? [], $entries );
            }
        }

        ksort( $types );
        ksort( $rejected );

        return [
            'button_types' => $types,
            'rejected'     => $rejected,
        ];
    }
}
