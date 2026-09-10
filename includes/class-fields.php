<?php
/**
 * The plugin's own Blockstudio field types.
 *
 * Blockstudios eigene Feldtypen `select`, `radio`, `color` und `files` speichern
 * `{"value":…,"label":…}`, `{"value":…,"name":…,"slug":…}` bzw.
 * `{"id":…,"url":…,"name":…}` und sind damit
 * fuer Vertragsattribute unbrauchbar (S-5). Die seit Blockstudio 7.5 vorhandene
 * Feldtyp-API `bs_register_field_type()` speichert Werte eigener Feldtypen
 * dagegen direkt — das ist der Ausweg.
 *
 * Die Konvertierungshelfer in dieser Datei sind zugleich die REFERENZ fuer das
 * JavaScript-Control: `tests/test-field-color.php` vergleicht die Ausgabe des
 * Controls gegen `Fields::color_from_rgb()`.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the custom field types and converts colour values.
 */
final class Fields {

    /**
     * Script handle of the `creabb/select` editor control.
     */
    public const SELECT_SCRIPT_HANDLE = 'crea-bootstrap-blocks-field-select';

    /**
     * Script handle of the `creabb/color` editor control.
     */
    public const COLOR_SCRIPT_HANDLE = 'crea-bootstrap-blocks-field-color';

    /**
     * Script handle of the `creabb/media` editor control.
     */
    public const MEDIA_SCRIPT_HANDLE = 'crea-bootstrap-blocks-field-media';

    /**
     * Shared dependencies of all three editor controls.
     *
     * `blockstudio-blocks` stellt `window.blockstudio.registerFieldType()`
     * bereit, `wp-element` und `wp-components` liefern `createElement` und die
     * Controls. Mehr wird nicht gebraucht — es gibt keinen Bundler und keinen
     * Build-Schritt.
     *
     * AUCH DAS MEDIENFELD BEKOMMT KEIN `media-views`. Das Skript brachte zwar
     * `wp.media` mit, aber nicht die Unterstrich-Vorlagen des Auswahldialogs —
     * die druckt `wp_print_media_templates()`, eingehaengt von
     * `wp_enqueue_media()`. Der Knopf oeffnete dann einen leeren Dialog,
     * waehrend der Rueckfall auf das Zahlenfeld unerreichbar waere. So
     * dagegen ist die blosse Existenz von `wp.media` ein verlaesslicher
     * Anzeiger: Sie faellt mit den Vorlagen zusammen.
     *
     * @var array<int, string>
     */
    private const SCRIPT_DEPS = [ 'blockstudio-blocks', 'wp-components', 'wp-element' ];

    /**
     * Whether init() has already run.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Registers the field type hook. Safe to call more than once.
     *
     * PRIORITAET 5 IST ERGEBNISBESTIMMEND: Blockstudios eigene Discovery haengt
     * auf `init` PHP_INT_MAX - 1, die WP-Registrierung auf PHP_INT_MAX
     * (register.php:43). Die eigenen Feldtypen muessen der Discovery bekannt
     * sein, bevor sie die Attribute baut — sie stehen deshalb vor
     * `crea_bootstrap_blocks_register_blocks()` (Prioritaet 10).
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;

        add_action( 'init', [ self::class, 'register' ], 5 );
        add_filter( 'blockstudio/fields/paths', [ self::class, 'field_paths' ], 10, 1 );
    }

    /**
     * Clears the idempotency latch so init() registers again. TEST HELPER.
     *
     * Diese Datei ruft am Ende selbst `init()` auf — beim `require_once` einer
     * Suite haengt der Hook also schon und `$initialized` ist true. Eine Suite,
     * die ihre Hook-Aufzeichnung zuruecksetzt und die Registrierung beobachten
     * will, braucht deshalb diesen Griff. Im Betrieb wird er nie aufgerufen.
     */
    public static function reset_for_tests(): void {
        self::$initialized = false;
    }

    /**
     * Registers the editor scripts, their translations and the three custom field types.
     *
     * Fehlt `bs_register_field_type()` — Blockstudio nicht geladen oder aelter
     * als 7.5 —, passiert nichts weiter als ein Logeintrag. Kein Fatal: Der
     * Rest des Plugins bleibt bedienbar, und der Versions-Guard in der
     * Hauptdatei zeigt die Admin-Notice.
     */
    public static function register(): void {
        if ( ! function_exists( 'bs_register_field_type' ) ) {
            if ( function_exists( 'crea_bootstrap_blocks_log' ) ) {
                crea_bootstrap_blocks_log( 'Fields::register() skipped: bs_register_field_type() is not available.' );
            }

            return;
        }

        $version = defined( 'CREA_BOOTSTRAP_BLOCKS_VERSION' )
            ? (string) constant( 'CREA_BOOTSTRAP_BLOCKS_VERSION' )
            : '0.0.0';

        wp_register_script(
            self::SELECT_SCRIPT_HANDLE,
            self::asset_url( 'assets/js/fields/select.js' ),
            self::SCRIPT_DEPS,
            $version,
            true
        );

        wp_register_script(
            self::COLOR_SCRIPT_HANDLE,
            self::asset_url( 'assets/js/fields/color.js' ),
            self::SCRIPT_DEPS,
            $version,
            true
        );

        wp_register_script(
            self::MEDIA_SCRIPT_HANDLE,
            self::asset_url( 'assets/js/fields/media.js' ),
            self::SCRIPT_DEPS,
            $version,
            true
        );

        /*
         * DIE UEBERSETZUNGEN DER CONTROLS. OHNE DIESEN AUFRUF BLEIBEN SIE
         * ENGLISCH, EGAL WIE VOLLSTAENDIG DIE KATALOGE SIND.
         *
         * WordPress liefert JavaScript-Uebersetzungen NICHT ueber die
         * ausgelieferte `.l10n.php` aus, sondern ueber eine eigene `.json` je
         * Skript: `load_script_textdomain()` bildet aus dem Quellpfad des
         * Handles — `assets/js/fields/color.js` — einen MD5 und sucht danach
         * `crea-bootstrap-blocks-<locale>-<md5>.json` im uebergebenen
         * Verzeichnis. Genau so benennt `wp i18n make-json` seine Ausgabe;
         * beides am 2026-09-08 gegeneinander gemessen.
         *
         * Bis dahin kam `wp_set_script_translations()` im ganzen Plugin nicht
         * vor. `wp.i18n.__()` fand deshalb nichts nachzuschlagen und gab den
         * englischen Ausgangstext zurueck — auch dann, wenn der String in
         * beiden Katalogen uebersetzt gestanden haette.
         *
         * ALLE DREI HANDLES, auch `creabb/select`, das heute keinen eigenen
         * Text zeichnet. Die Zuordnung darf nicht davon abhaengen, welches
         * Control gerade eine Beschriftung traegt: Der erste Text, den jemand
         * dort einbaut, waere sonst wieder englisch — und wieder ohne Meldung.
         *
         * ZWEI EIGENHEITEN DES AUFRUFS. Der dritte Parameter ist ein
         * DATEISYSTEMPFAD, nicht der relative Pfad unter `WP_PLUGIN_DIR`, den
         * `load_plugin_textdomain()` nimmt. Und `wp-i18n` gehoert deshalb
         * nicht in SCRIPT_DEPS: `WP_Scripts::set_translations()` traegt es von
         * sich aus in die Abhaengigkeiten des Handles nach.
         */
        $languages = self::languages_path();

        foreach ( [ self::SELECT_SCRIPT_HANDLE, self::COLOR_SCRIPT_HANDLE, self::MEDIA_SCRIPT_HANDLE ] as $handle ) {
            wp_set_script_translations( $handle, 'crea-bootstrap-blocks', $languages );
        }

        // `creabb/select` ersetzt `select` und `radio`. Es speichert den ROHEN
        // Optionswert als String statt `{"value":…,"label":…}` (S-5) und ist
        // damit der einzige zulaessige Weg, ein Vertragsattribut mit einer
        // Auswahl zu bedienen (Regel 1.4).
        bs_register_field_type(
            'creabb/select',
            [
                'attribute'     => 'string',
                'default'       => '',
                'editor_script' => self::SELECT_SCRIPT_HANDLE,
                'storage'       => [
                    'type'        => 'string',
                    'rest_schema' => [ 'type' => 'string' ],
                ],
            ]
        );

        // `creabb/color` ersetzt `color`. Es speichert das vollstaendige
        // Alt-Farbobjekt mit sechs Schluesseln in konstanter Reihenfolge
        // (Regel 1.6) statt `{"value":…,"name":…,"slug":…}`.
        //
        // Der Registry-Default steht hier auf null; der tatsaechliche
        // Vertragsdefault wird je Attribut in `Contract::filter_block_meta()`
        // erzwungen — er ist von Block zu Block uneinheitlich
        // (`background_color` hat `{"rgb":{…}}` ohne `hex`, `pattern_color`
        // `{"hex":"#fff"}` ohne `rgb`).
        bs_register_field_type(
            'creabb/color',
            [
                'attribute'     => 'object',
                'default'       => null,
                'editor_script' => self::COLOR_SCRIPT_HANDLE,
                'storage'       => [
                    'type'        => 'object',
                    'rest_schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => true,
                    ],
                ],
            ]
        );

        /*
         * `creabb/media` ersetzt `files` an der einen Stelle, an der ein
         * Vertragsattribut eine Mediathek-Auswahl braucht:
         * `media-grid-image.id`. Es speichert AUSSCHLIESSLICH die Anhang-ID
         * als Zahl — der eingebaute Typ `files` legte
         * {"id":…,"url":…,"name":…} ab, der Bestand traegt dort `600`.
         *
         * ES WEICHT DIE SPERRLISTE AUS REGEL 1.4 NICHT AUF. Gesperrt sind
         * Feldtypen, die den Wert NORMALISIEREN, bevor sie ihn ablegen; dieser
         * legt genau die Form ab, die der Vertrag ohnehin verlangt
         * (`{"type":"number"}`). Attributname, -typ und Default bleiben
         * unveraendert, migrierte Inhalte sind nicht beruehrt — dieselbe
         * Begruendung wie bei `creabb/select` und `creabb/color`.
         *
         * KEIN `default`, UND DAS IST ABSICHT. Die beiden anderen Feldtypen
         * setzen hier einen (`''` beziehungsweise `null`);
         * `media-grid-image.id` gehoert zu den 13 Attributen OHNE Default,
         * weil der Block von `core/image` abstammt. „Kein Default" ist ein
         * eigener Zustand (siehe `Contract::filter_block_meta()`), und
         * `Field_Type_Registry::build_attribute()` uebernaehme einen hier
         * gesetzten Registry-Default in genau dem Fall, in dem das Feld
         * keinen mitbringt.
         */
        bs_register_field_type(
            'creabb/media',
            [
                'attribute'     => 'number',
                'editor_script' => self::MEDIA_SCRIPT_HANDLE,
                'storage'       => [
                    'type'        => 'number',
                    'rest_schema' => [ 'type' => 'number' ],
                ],
            ]
        );
    }

    /**
     * Adds our own field library to Blockstudio's field discovery.
     *
     * `Build::register_filtered_custom_fields()` wertet diesen Filter je
     * Discovery-Lauf aus (build.php:1177), auch wenn die
     * Dateisystem-Discovery aus dem Cache kam. Beide Blockverzeichnisse —
     * `blocks/` und `blocks-legacy/` — sehen die Bibliothek deshalb ohne
     * weiteres Zutun.
     *
     * WAS DIESER FILTER HEUTE NOCH NICHT LEISTET, UND WARUM ER TROTZDEM STEHT.
     * Blockstudio durchsucht von sich aus `<Build-Verzeichnis>/fields`
     * (`Build::discover_local_custom_fields()`, build.php:1121), und
     * `Build::init()` laeuft hier mit `dir => …/blocks`. `creabb-spacing` wird
     * also ohnehin gefunden; der Filter registriert es ein zweites Mal, was
     * folgenlos bleibt, weil `Field_Registry::register()` nach dem Namen
     * schluesselt. Sein realer Nutzen beginnt beim ZWEITEN Build-Verzeichnis:
     * `blocks-legacy/` (Block F) hat kein eigenes `fields/`, und die
     * Aliasbloecke referenzieren dieselbe Gruppe. Wer den Filter heute als
     * redundant streicht, bricht sie — und wer ihn fuer die tragende Saeule
     * haelt, sucht einen Fehler an der falschen Stelle.
     *
     * Der Pfad wird nicht doppelt eingetragen: Ein zweiter Discovery-Lauf im
     * selben Request wuerde den Baustein sonst zweimal registrieren.
     *
     * DER PARAMETER IST `mixed`, NICHT `array`. Der Filter startet zwar mit
     * `array()` (build.php:1177), aber jeder fremde Callback dazwischen darf
     * zurueckgeben, was er will. Mit `declare(strict_types=1)` waere eine
     * `array`-Signatur bei einem Nicht-Array ein TypeError mitten in der
     * Discovery — also ein Fatal statt eines uebergangenen Fremdwerts.
     *
     * Nicht-Strings fliegen heraus, weil dieser Callback nur zurueckgeben
     * soll, was der Filtervertrag zusagt: eine Liste von Pfaden. NICHT, weil
     * Blockstudio daran scheiterte — `Discovery_Sources::for_paths()` filtert
     * selbst (`if ( is_string( $path ) && '' !== $path )`,
     * discovery-sources.php:46). Der Preis ist bekannt und klein: Ein
     * nachfolgender Callback sieht die Nicht-Strings nicht mehr und bekommt
     * neu vergebene Indizes.
     *
     * @param mixed $paths Discovery paths collected so far.
     * @return array<int, string>
     */
    public static function field_paths( mixed $paths ): array {
        $own = rtrim( CREA_BOOTSTRAP_BLOCKS_DIR, '/' ) . '/blocks/fields';

        if ( ! is_array( $paths ) ) {
            return [ $own ];
        }

        $paths = array_values( array_filter( $paths, 'is_string' ) );

        if ( in_array( $own, $paths, true ) ) {
            return $paths;
        }

        $paths[] = $own;

        return $paths;
    }

    /**
     * Absolute URL of a file inside the plugin directory.
     *
     * Der Rueckfallweg ueber `plugins_url()` greift, falls die Konstante einmal
     * nicht gesetzt ist — die Feldtypen duerfen nicht daran scheitern.
     *
     * @param string $relative Path relative to the plugin root, without leading slash.
     */
    private static function asset_url( string $relative ): string {
        if ( defined( 'CREA_BOOTSTRAP_BLOCKS_URL' ) ) {
            return rtrim( (string) constant( 'CREA_BOOTSTRAP_BLOCKS_URL' ), '/' ) . '/' . $relative;
        }

        return plugins_url( $relative, dirname( __DIR__ ) . '/crea-bootstrap-blocks.php' );
    }

    /**
     * Absolute filesystem path of the plugin's language directory.
     *
     * `wp_set_script_translations()` verlangt einen DATEISYSTEMPFAD; das
     * unterscheidet es von `load_plugin_textdomain()` in `includes/i18n.php`,
     * das einen relativen Pfad unterhalb von `WP_PLUGIN_DIR` bekommt. Wer die
     * beiden verwechselt, sucht die `.json` unter
     * `crea-bootstrap-blocks/languages/crea-bootstrap-blocks/languages`.
     *
     * Der Rueckfallweg ohne die Konstante entspricht dem von `asset_url()`:
     * Die Feldtypen duerfen an einer fehlenden Konstante nicht scheitern.
     */
    private static function languages_path(): string {
        if ( defined( 'CREA_BOOTSTRAP_BLOCKS_DIR' ) ) {
            return rtrim( (string) constant( 'CREA_BOOTSTRAP_BLOCKS_DIR' ), '/' ) . '/languages';
        }

        return dirname( __DIR__ ) . '/languages';
    }

    /**
     * Clamps a channel value to an integer between 0 and 255.
     *
     * @param mixed $value Raw value.
     */
    private static function clamp_byte( mixed $value ): int {
        if ( ! is_numeric( $value ) ) {
            return 0;
        }

        return max( 0, min( 255, (int) round( (float) $value ) ) );
    }

    /**
     * Clamps alpha to 0…1.
     *
     * @param mixed $value Raw value.
     */
    private static function clamp_alpha( mixed $value ): float {
        if ( ! is_numeric( $value ) ) {
            return 1.0;
        }

        return max( 0.0, min( 1.0, (float) $value ) );
    }

    /**
     * Hue in degrees, rounded to a whole number.
     *
     * @param int $r Red 0-255.
     * @param int $g Green 0-255.
     * @param int $b Blue 0-255.
     */
    public static function rgb_to_hue( int $r, int $g, int $b ): int {
        /*
         * DER TEILER IST `255.0`, NICHT `255`, UND DAS IST ERGEBNISBESTIMMEND.
         *
         * PHPs `/` gibt bei zwei Ganzzahlen mit aufgehender Division wieder eine
         * GANZZAHL zurueck: `0 / 255` ist `int(0)`, `255 / 255` ist `int(1)`.
         * Damit waere `$delta` fuer Schwarz und fuer Weiss `int(0)`, die Wache
         * `0.0 === $delta` griffe nicht — und die naechste Zeile teilte durch
         * null. Gemessen: Der Plancode mit `255` wirft fuer `rgb(0,0,0)` einen
         * DivisionByZeroError, fuer Weiss ebenso in `color_from_rgb()`. Ein
         * Float-Teiler macht jedes Zwischenergebnis zum Float und die Wache
         * wieder wirksam.
         */
        $rn    = $r / 255.0;
        $gn    = $g / 255.0;
        $bn    = $b / 255.0;
        $max   = max( $rn, $gn, $bn );
        $min   = min( $rn, $gn, $bn );
        $delta = $max - $min;

        if ( 0.0 === $delta ) {
            return 0;
        }

        if ( $max === $rn ) {
            $hue = 60 * fmod( ( $gn - $bn ) / $delta, 6 );
        } elseif ( $max === $gn ) {
            $hue = 60 * ( ( $bn - $rn ) / $delta + 2 );
        } else {
            $hue = 60 * ( ( $rn - $gn ) / $delta + 4 );
        }

        if ( $hue < 0 ) {
            $hue += 360;
        }

        return (int) round( $hue );
    }

    /**
     * Hex notation: eight digits while alpha is below 1, six otherwise.
     *
     * @param int   $r Red 0-255.
     * @param int   $g Green 0-255.
     * @param int   $b Blue 0-255.
     * @param float $a Alpha 0-1.
     */
    public static function rgb_to_hex( int $r, int $g, int $b, float $a ): string {
        $hex = sprintf( '#%02x%02x%02x', self::clamp_byte( $r ), self::clamp_byte( $g ), self::clamp_byte( $b ) );

        if ( $a >= 1 ) {
            return $hex;
        }

        return $hex . sprintf( '%02x', (int) round( $a * 255 ) );
    }

    /**
     * Builds the complete legacy colour object.
     *
     * DIE SCHLUESSELREIHENFOLGE IST VERTRAGSBESTANDTEIL (Regel 1.6):
     * hex, rgb, hsv, hsl, source, oldHue. `json_encode()` behaelt die
     * Einfuegereihenfolge bei.
     *
     * `oldHue` merkte sich in react-color den zuletzt bunten Farbton, damit ein
     * Zug auf Schwarz, Weiss oder Grau den Farbtonregler nicht auf 0 Grad
     * zuruecksetzt.
     *
     * @param array<string, mixed> $rgb          { r, g, b, a }.
     * @param string               $source       Input path, always `hex` for our control.
     * @param int|null             $previous_hue Previously stored oldHue, if any.
     * @return array<string, mixed>
     */
    public static function color_from_rgb( array $rgb, string $source = 'hex', ?int $previous_hue = null ): array {
        $r = self::clamp_byte( $rgb['r'] ?? 0 );
        $g = self::clamp_byte( $rgb['g'] ?? 0 );
        $b = self::clamp_byte( $rgb['b'] ?? 0 );
        $a = self::clamp_alpha( $rgb['a'] ?? 1 );

        // Float-Teiler, siehe die Begruendung in `rgb_to_hue()`. Ohne ihn teilt
        // Weiss hier durch `2 - $max - $min`, also durch null.
        $rn    = $r / 255.0;
        $gn    = $g / 255.0;
        $bn    = $b / 255.0;
        $max   = max( $rn, $gn, $bn );
        $min   = min( $rn, $gn, $bn );
        $delta = $max - $min;

        $hue = self::rgb_to_hue( $r, $g, $b );

        $hsv_s = 0.0 === $max ? 0 : (int) round( ( $delta / $max ) * 100 );
        $hsv_v = (int) round( $max * 100 );

        $lightness = ( $max + $min ) / 2;

        if ( 0.0 === $delta ) {
            $hsl_s = 0;
        } elseif ( $lightness > 0.5 ) {
            $hsl_s = (int) round( ( $delta / ( 2 - $max - $min ) ) * 100 );
        } else {
            $hsl_s = (int) round( ( $delta / ( $max + $min ) ) * 100 );
        }

        $hsl_l = (int) round( $lightness * 100 );

        $old_hue = $hue;

        if ( 0 === $hsl_s && null !== $previous_hue ) {
            $old_hue = $previous_hue;
        }

        return [
            'hex'    => self::rgb_to_hex( $r, $g, $b, $a ),
            'rgb'    => [
                'r' => $r,
                'g' => $g,
                'b' => $b,
                'a' => $a,
            ],
            'hsv'    => [
                'h' => $hue,
                's' => $hsv_s,
                'v' => $hsv_v,
                'a' => $a,
            ],
            'hsl'    => [
                'h' => $hue,
                's' => $hsl_s,
                'l' => $hsl_l,
                'a' => $a,
            ],
            'source' => $source,
            'oldHue' => $old_hue,
        ];
    }
}

Fields::init();
