<?php
/**
 * Frontend asset registration.
 *
 * Drei Aufgaben: das eigene Stylesheet `assets/css/blocks.css` samt dem
 * generierten Inline-CSS, die drei optionalen Bootstrap-Assets, und
 * `assets/js/frontend.js` — das Verhalten der interaktiven Bloecke.
 *
 * Blockspezifisches CSS gehoert NICHT hierher. Blockstudio laedt
 * `blocks/<name>/style.css` bedarfsgerecht, sobald der Block auf der Seite
 * vorkommt — das ersetzt `AREOI_Styles::traverse_block_styles()` vollstaendig
 * (die Funktion nimmt ihre Dedupe-Arrays by value, die Rekursion propagiert
 * also nicht zurueck).
 *
 * `assets/js/frontend.js` ist dagegen KEIN blockspezifisches Skript, sondern
 * das Gegenstueck zu `assets/js/bootstrap-extra.js` des Originals: Es sucht
 * seine Angriffspunkte im ganzen Dokument, weil ein Verweis `href="#kennung"`
 * ueberall stehen darf — auch in einem gewoehnlichen Absatz, der auf ein Modal
 * zeigt. Ein Skript je Block koennte das nicht leisten.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the plugin's frontend assets.
 */
final class Assets {

    /**
     * Handle of the plugin's own stylesheet.
     *
     * Das generierte Inline-CSS haengt an DIESEM Handle. Das Original haengt es
     * an `areoi-style-index` und laedt dieses Handle auf einer konfigurierbaren
     * Prioritaet, das Inline-CSS aber auf der festen 10 — steht die
     * Basisprioritaet hoeher, geht das Inline-CSS verloren. Der Wettlauf wird
     * nicht nachgebaut: Beides passiert hier im selben Callback, in dieser
     * Reihenfolge.
     */
    public const HANDLE_BLOCKS = 'crea-bootstrap-blocks';

    /**
     * Handle of the editor interface stylesheet.
     *
     * EIGENES HANDLE, NICHT `HANDLE_BLOCKS`. Die beiden laden in verschiedene
     * Dokumente — `blocks.css` in das iframe der Leinwand, diese Datei in die
     * Editoroberflaeche darum herum. Ein gemeinsames Handle liesse WordPress
     * das zweite Enqueue als Dublette verwerfen, und die Seitenleiste bliebe
     * unkorrigiert: ein Fehlschlag, den kein Fehler meldet.
     */
    public const HANDLE_EDITOR_UI = 'crea-bootstrap-blocks-editor-ui';

    /**
     * Handle of the vendored Bootstrap stylesheet.
     *
     * Drei getrennte Schalter, drei getrennte Handles — das Original hatte fuer
     * dieselbe Aufgabe ein achtteiliges Einstellungs-Backend.
     */
    public const HANDLE_BOOTSTRAP_CSS = 'crea-bootstrap-blocks-bootstrap';

    /** Handle of the vendored Bootstrap JS bundle. */
    public const HANDLE_BOOTSTRAP_JS = 'crea-bootstrap-blocks-bootstrap-js';

    /** Handle of the vendored Bootstrap Icons stylesheet. */
    public const HANDLE_BOOTSTRAP_ICONS = 'crea-bootstrap-blocks-bootstrap-icons';

    /**
     * Handle of the plugin's frontend behaviour script.
     *
     * Ersetzt `assets/js/bootstrap-extra.js` des Originals, das dort per
     * `wp_add_inline_script()` am Bootstrap-Handle haengt. Hier ist es eine
     * eigene Datei mit eigenem Handle: Das Bootstrap-JS des Plugins ist
     * abschaltbar und standardmaessig AUS, das Verhalten der Bloecke darf davon
     * aber nicht abhaengen — die Reiterlogik kommt ohne Bootstrap aus.
     */
    public const HANDLE_FRONTEND = 'crea-bootstrap-blocks-frontend';

    /**
     * Whether init() has already run.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Registers the enqueue callback. Safe to call more than once.
     *
     * `wp_enqueue_scripts` ist derselbe Hook, auf dem das Original laeuft. Er
     * feuert aus `wp_head()` heraus — also NACH dem Filter `template_include`,
     * auf dem `locate_block_template()` `$_wp_current_template_content` setzt.
     * Der Einsammelpfad sieht das FSE-Template damit bereits.
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;

        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ], 10 );
        add_action( 'enqueue_block_assets', [ self::class, 'enqueue_editor' ], 10 );
        add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_ui' ], 10 );
    }

    /**
     * Enqueues the sidebar corrections into the editor interface.
     *
     * DER DRITTE HOOK, UND ER IST NICHT DERSELBE WIE DER ZWEITE.
     * `enqueue_block_assets` landet im iframe der Leinwand — dort wird der
     * Beitrag gerendert, und dorthin gehoert `blocks.css`. Die SEITENLEISTE
     * liegt ausserhalb dieses iframes; eine Regel aus der Leinwand trifft sie
     * nie. Was ihre Bedienbarkeit betrifft, braucht deshalb
     * `enqueue_block_editor_assets`.
     *
     * WAS OHNE IHN KAPUTT IST, gemessen am 2026-09-08 im echten Editor an
     * allen drei Blocktypen der Startseite: Die Reiterleiste der sechs
     * Breakpoints brauchte 298 px in 280 px Sicht — `XXL` ragte 18 px hinaus
     * und war nur teilweise anklickbar. Mit den ausgeschriebenen Namen war es
     * schlimmer; da endete die Leiste hinter „Gross".
     *
     * ER LAEDT NUR IM EDITOR, nicht auf jeder Adminseite: Der Hook feuert
     * ausschliesslich dort, wo ein Blockeditor laeuft. Und er laedt KEIN
     * Bootstrap und kein erzeugtes Inline-CSS — beides gehoert in die
     * Leinwand, wo der Beitrag steht, und wuerde die Editoroberflaeche selbst
     * umgestalten.
     */
    public static function enqueue_editor_ui(): void {
        wp_enqueue_style(
            self::HANDLE_EDITOR_UI,
            self::asset_url( 'assets/css/editor-ui.css' ),
            [],
            CREA_BOOTSTRAP_BLOCKS_VERSION
        );
    }

    /**
     * Enqueues the block styles into the editor canvas.
     *
     * WARUM ES DIESEN ZWEITEN HOOK BRAUCHT
     *
     * `wp_enqueue_scripts` feuert im Adminbereich nie. Bis zum 2026-09-02 lud
     * die Editor-Leinwand deshalb NICHTS aus diesem Plugin — kein
     * `blocks.css`, kein Bootstrap, kein erzeugtes Inline-CSS. An der
     * laufenden Instanz gemessen: 95 Stylesheets in der Leinwand, davon
     * **null** aus diesem Plugin, und `.creabb-element` rechnete
     * `position: static` statt des `relative` aus `blocks.css:24` — auf dem
     * alle Hintergrundschichten und der vollflaechige Anker aufbauen.
     *
     * Die Folge traf vor allem die Abstandsfelder: Sie schreiben nichts ins
     * Markup, sondern erzeugen Regeln auf `.block-<kennung>`. Ohne das
     * Stylesheet in der Leinwand bewirkte ein „Innenabstand oben = 40" im
     * Editor sichtbar gar nichts — bei 1320 der 1729 Felder. Das Alt-Plugin
     * zeigte die Vorschau sofort (`class.areoi.styles.php:18` haengt an
     * `admin_enqueue_scripts`), der Nachbau war hier also ein Rueckschritt,
     * kein Gleichstand.
     *
     * KEIN SCREENSHOT- UND KEIN DOM-DIFF SIEHT DAS: beide messen das Frontend.
     *
     * `enqueue_block_assets` STATT `enqueue_block_editor_assets`: Nur der
     * erste landet im iframe der Leinwand, in dem der Beitrag gerendert wird;
     * der zweite laedt in die Editor-Oberflaeche drumherum, wo die Regeln
     * nichts treffen. Der Hook feuert allerdings AUCH im Frontend — dort
     * uebernimmt `enqueue()` bereits, weshalb hier auf `is_admin()` geprueft
     * wird. Ein zweiter Lauf waere nicht falsch (die Handles sind dieselben),
     * aber er haenge das Inline-CSS ein zweites Mal an.
     *
     * OHNE `frontend.js`. Das Verhaltensskript verdrahtet Reiter, Modale und
     * Popover; im Editor kaeme es Gutenbergs eigener Bedienung in die Quere.
     * Das Original hielt es genauso — sein Admin-Hook laedt ausschliesslich
     * CSS.
     */
    public static function enqueue_editor(): void {
        if ( ! is_admin() ) {
            return;
        }

        self::enqueue_bootstrap_css();
        self::enqueue_bootstrap_icons();

        wp_enqueue_style(
            self::HANDLE_BLOCKS,
            self::asset_url( 'assets/css/blocks.css' ),
            self::blocks_style_deps(),
            CREA_BOOTSTRAP_BLOCKS_VERSION
        );

        $stylesheet = Styles::stylesheet();

        if ( '' !== $stylesheet ) {
            wp_add_inline_style( self::HANDLE_BLOCKS, $stylesheet );
        }
    }

    /**
     * Enqueues everything the frontend needs.
     *
     * Reihenfolge: erst die optionalen Vendor-Assets, dann blocks.css mit seiner
     * Abhaengigkeit darauf, dann das generierte Inline-CSS. Damit stehen die
     * Korrekturen aus blocks.css hinter Bootstrap — was das Original ueber einen
     * Prioritaets-Wettlauf zwischen zwei Hooks zu erreichen versuchte.
     */
    public static function enqueue(): void {
        self::enqueue_bootstrap_css();
        self::enqueue_bootstrap_js();
        self::enqueue_bootstrap_icons();
        self::enqueue_frontend();

        wp_enqueue_style(
            self::HANDLE_BLOCKS,
            self::asset_url( 'assets/css/blocks.css' ),
            self::blocks_style_deps(),
            CREA_BOOTSTRAP_BLOCKS_VERSION
        );

        $stylesheet = Styles::stylesheet();

        // Ein leerer String erzeugte ein leeres <style>-Element im Kopf jeder
        // Seite, die keine Massangaben verwendet.
        if ( '' !== $stylesheet ) {
            wp_add_inline_style( self::HANDLE_BLOCKS, $stylesheet );
        }
    }

    /**
     * Enqueues the frontend behaviour script.
     *
     * WARUM UNBEDINGT, OHNE SCHALTER UND OHNE BLOCKPRUEFUNG. Genauso haelt es
     * `assets/css/blocks.css` eine Zeile weiter unten, und genauso hielt es das
     * Original: Es haengte `bootstrap-extra.js` an sein Bootstrap-Handle, das
     * auf jeder Seite lief. Eine Abfrage auf vorhandene Bloecke waere zur Zeit
     * von `wp_enqueue_scripts` ohnehin unzuverlaessig — Widgets, Reusable
     * Blocks und FSE-Templates bringen Bloecke mit, die `has_block()` auf dem
     * Beitragsinhalt nicht sieht.
     *
     * DIE ABHAENGIGKEIT HAENGT AM SCHALTER. Kommt Bootstrap aus dem Plugin, muss
     * es davor stehen — sonst ist `window.bootstrap` beim Lauf noch nicht da.
     * Kommt es vom Theme, kuemmert sich das Theme um die Reihenfolge; dieselbe
     * Arbeitsteilung wie beim Stylesheet. Die Datei kommt in beiden Faellen
     * ohne Bootstrap zurecht und laesst dann nur die Bootstrap-Teile aus.
     *
     * `defer` im Fussbereich: Das Skript bindet sich an vorhandenes Markup und
     * braucht den Aufbau der Seite nicht zu blockieren.
     */
    private static function enqueue_frontend(): void {
        wp_enqueue_script(
            self::HANDLE_FRONTEND,
            self::asset_url( 'assets/js/frontend.js' ),
            self::enabled( 'bootstrap_js' ) ? [ self::HANDLE_BOOTSTRAP_JS ] : [],
            CREA_BOOTSTRAP_BLOCKS_VERSION,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }

    /**
     * Dependencies of the plugin stylesheet.
     *
     * `blocks.css` enthaelt Korrekturen an Bootstrap-Komponenten (Button-Group,
     * Card, Modal, Carousel). Sie greifen nur, wenn Bootstrap davor steht —
     * deshalb die Abhaengigkeit, sobald das Bootstrap-CSS aus dem Plugin kommt.
     * Kommt es vom Theme, kuemmert sich das Theme um die Reihenfolge.
     *
     * @return string[]
     */
    private static function blocks_style_deps(): array {
        return self::enabled( 'bootstrap_css' ) ? [ self::HANDLE_BOOTSTRAP_CSS ] : [];
    }

    /**
     * Enqueues the vendored Bootstrap stylesheet when switched on.
     */
    private static function enqueue_bootstrap_css(): void {
        if ( ! self::enabled( 'bootstrap_css' ) ) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE_BOOTSTRAP_CSS,
            self::asset_url( 'assets/vendor/bootstrap/bootstrap.min.css' ),
            [],
            CREA_BOOTSTRAP_BLOCKS_VERSION
        );
    }

    /**
     * Enqueues the vendored Bootstrap JS bundle when switched on.
     *
     * Das BUNDLE, nicht `bootstrap.min.js`: Es bringt Popper mit, den Dropdown,
     * Tooltip und Popover brauchen. Es stammt aus demselben Archiv wie das CSS —
     * das Alt-Plugin lieferte CSS 5.3.3 gegen JS 5.0.2 aus.
     *
     * `defer` im Footer: Bootstrap-JS bindet sich ueber data-Attribute an
     * bereits vorhandenes Markup und braucht den DOM nicht zu blockieren.
     */
    private static function enqueue_bootstrap_js(): void {
        if ( ! self::enabled( 'bootstrap_js' ) ) {
            return;
        }

        wp_enqueue_script(
            self::HANDLE_BOOTSTRAP_JS,
            self::asset_url( 'assets/vendor/bootstrap/bootstrap.bundle.min.js' ),
            [],
            CREA_BOOTSTRAP_BLOCKS_VERSION,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }

    /**
     * Enqueues the vendored Bootstrap Icons when switched on.
     *
     * Die Webfonts liegen unter `assets/vendor/bootstrap/icons/fonts/` und
     * werden NICHT eigens registriert: `bootstrap-icons.min.css` verweist mit
     * einem relativen `url(fonts/…)` darauf. Deshalb muss die CSS-Datei in
     * ihrem Verzeichnis bleiben — ein Verschieben brich die Schriften.
     *
     * Getrennt vom Bootstrap-CSS schaltbar, weil ein Theme haeufig Bootstrap
     * mitbringt, aber keine Icons.
     */
    private static function enqueue_bootstrap_icons(): void {
        if ( ! self::enabled( 'bootstrap_icons' ) ) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE_BOOTSTRAP_ICONS,
            self::asset_url( 'assets/vendor/bootstrap/icons/bootstrap-icons.min.css' ),
            [],
            CREA_BOOTSTRAP_BLOCKS_VERSION
        );
    }

    /**
     * Reads one of the three Bootstrap switches.
     *
     * Alle drei haben Default AUS (Vertragsregel 4.3): Ein Plugin-Update darf
     * auf keiner Bestandsseite plötzlich ein zweites Bootstrap in den Kopf
     * haengen. Die Konstante `CREA_BOOTSTRAP_BLOCKS_BOOTSTRAP_<KEY>` hat Vorrang
     * vor der Option.
     *
     * @param string $key Settings key (`bootstrap_css`, `bootstrap_js`, `bootstrap_icons`).
     * @return bool Whether the switch is on.
     */
    private static function enabled( string $key ): bool {
        return crea_bootstrap_blocks_setting_enabled( $key, false );
    }

    /**
     * Builds an absolute URL for a plugin-relative path.
     *
     * @param string $relative Path relative to the plugin root.
     * @return string Absolute URL.
     */
    private static function asset_url( string $relative ): string {
        return CREA_BOOTSTRAP_BLOCKS_URL . ltrim( $relative, '/' );
    }
}

// Die Include-Liste in crea-bootstrap-blocks.php laedt diese Datei genau einmal;
// init() ist zusaetzlich gegen einen zweiten Aufruf gesichert. Ein zusaetzlicher
// Aufruf aus einem Bootstrapper ist nicht noetig — ohne die Sperre waere er ein
// zweites `wp_enqueue_scripts` und damit ein zweites Mal Inline-CSS im Kopf.
Assets::init();
