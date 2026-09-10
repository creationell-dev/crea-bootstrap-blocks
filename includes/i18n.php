<?php
/**
 * Translation loading for CreaBootstrapBlocks.
 *
 * Die Textdomain steht in jedem Aufruf als String-Literal, nie als Konstante:
 * `wp i18n make-pot` liest den Quelltext statisch und extrahiert nichts, wenn
 * an der Stelle der Domain ein Bezeichner steht. Genau daran ist das Alt-Plugin
 * gescheitert — es besitzt ueberhaupt keine Uebersetzungsdateien.
 *
 * Ausgeliefert wird das moderne `.l10n.php`-Format (WordPress 6.5+).
 * `load_plugin_textdomain()` bedient es ohne Zutun: `load_textdomain()` fragt
 * den Translation-Controller, der zuerst nach `<domain>-<locale>.l10n.php`
 * sucht und erst danach nach `.mo`.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the language directory relative to WP_PLUGIN_DIR.
 *
 * Bewusst ohne Rueckgriff auf eine Plugin-Konstante: Diese Datei laesst sich
 * damit auch isoliert testen, und der Pfad bleibt richtig, wenn der
 * Plugin-Ordner abweichend benannt ist.
 *
 * @return string For example `crea-bootstrap-blocks/languages`.
 */
function crea_bootstrap_blocks_languages_rel_path(): string {
    $main_file = dirname( __DIR__ ) . '/crea-bootstrap-blocks.php';

    return dirname( plugin_basename( $main_file ) ) . '/languages';
}

/**
 * Loads the plugin text domain.
 *
 * Haengt an `plugins_loaded` — frueher waere es ein `_doing_it_wrong()` seit
 * WordPress 6.7, spaeter kaemen die Admin-Notices des Umgebungschecks
 * unuebersetzt heraus.
 */
function crea_bootstrap_blocks_load_textdomain(): void {
    load_plugin_textdomain(
        'crea-bootstrap-blocks',
        false,
        crea_bootstrap_blocks_languages_rel_path()
    );
}

add_action( 'plugins_loaded', 'crea_bootstrap_blocks_load_textdomain', 10 );
