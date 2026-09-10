<?php
/**
 * Plugin Name: CreaBootstrapBlocks
 * Plugin URI: https://github.com/creationell-dev/crea-bootstrap-blocks
 * Description: Rückwärtskompatibler Ersatz für All Bootstrap Blocks auf Basis von Blockstudio.
 * Version: 1.0.0
 * Stable tag: 1.0.0
 * Author: creationell® – die Werbeagentur
 * Author URI: https://www.creationell.de/
 * Requires at least: 6.9
 * Tested up to: 7.1
 * Requires PHP: 8.3
 * Requires Plugins: blockstudio
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crea-bootstrap-blocks
 * Domain Path: /languages
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'WPINC' ) ) {
    die;
}

/*
 * --- Konstanten ----------------------------------------------------------
 *
 * DIR und URL tragen beide einen abschliessenden Schraegstrich — jede
 * Verwendung im Plugin haengt einen relativen Pfad ohne fuehrenden Schraegstrich
 * daran.
 *
 * NICHT hier definiert werden CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES,
 * …_LEGACY_BLOCKS und die drei …_BOOTSTRAP_*. Sie haben laut Spezifikation
 * Vorrang vor der Backend-Option; das koennen sie nur, solange sie
 * ausschliesslich aus der wp-config.php stammen. Ihre Standardwerte stehen in
 * crea_bootstrap_blocks_default_settings().
 */
define( 'CREA_BOOTSTRAP_BLOCKS_VERSION', '1.0.0' );
define( 'CREA_BOOTSTRAP_BLOCKS_FILE', __FILE__ );
define( 'CREA_BOOTSTRAP_BLOCKS_BASENAME', plugin_basename( __FILE__ ) );
define( 'CREA_BOOTSTRAP_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREA_BOOTSTRAP_BLOCKS_URL', plugin_dir_url( __FILE__ ) );
define( 'CREA_BOOTSTRAP_BLOCKS_MIN_PHP', '8.3' );
define( 'CREA_BOOTSTRAP_BLOCKS_MIN_WP', '6.9' );
define( 'CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO', '7.6' );

if ( ! defined( 'CREA_BOOTSTRAP_BLOCKS_DEBUG' ) ) {
    define( 'CREA_BOOTSTRAP_BLOCKS_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Returns the first unmet requirement, or null when everything is in place.
 *
 * Die Abhaengigkeit auf Blockstudio ist doppelt abgesichert: `Requires Plugins`
 * im Header blockt bereits die Aktivierung, wenn der Plugin-Ordner
 * `blockstudio` fehlt oder inaktiv ist (S-8). Dieser Check kommt trotzdem
 * dazu — er prueft die VERSION und faengt einen abweichend benannten
 * Plugin-Ordner ab.
 *
 * @return string|null Untranslated diagnostic text, or null.
 */
function crea_bootstrap_blocks_environment_problem(): ?string {
    if ( version_compare( PHP_VERSION, CREA_BOOTSTRAP_BLOCKS_MIN_PHP, '<' ) ) {
        return sprintf(
            'PHP %s is required, this site runs PHP %s.',
            CREA_BOOTSTRAP_BLOCKS_MIN_PHP,
            PHP_VERSION
        );
    }

    if ( version_compare( (string) get_bloginfo( 'version' ), CREA_BOOTSTRAP_BLOCKS_MIN_WP, '<' ) ) {
        return sprintf(
            'WordPress %s is required, this site runs WordPress %s.',
            CREA_BOOTSTRAP_BLOCKS_MIN_WP,
            (string) get_bloginfo( 'version' )
        );
    }

    if ( ! defined( 'BLOCKSTUDIO_VERSION' ) ) {
        return 'Blockstudio ' . CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO
            . ' or newer is required, but Blockstudio is not active.';
    }

    if ( version_compare( (string) constant( 'BLOCKSTUDIO_VERSION' ), CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO, '<' ) ) {
        return sprintf(
            'Blockstudio %s is required, this site runs Blockstudio %s.',
            CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO,
            (string) constant( 'BLOCKSTUDIO_VERSION' )
        );
    }

    return null;
}

/**
 * Prints the environment notice.
 *
 * Bewusst KEIN Fatal und KEINE Selbstdeaktivierung: Das Plugin bleibt geladen,
 * die Einstellungsseite bleibt erreichbar, und die Diagnose kann sagen, was
 * fehlt. Ohne Blockstudio registriert lediglich die Blockregistrierung nichts.
 */
function crea_bootstrap_blocks_environment_notice(): void {
    $problem = crea_bootstrap_blocks_environment_problem();

    if ( null === $problem || ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    printf(
        '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
        esc_html__( 'CreaBootstrapBlocks:', 'crea-bootstrap-blocks' ),
        esc_html( $problem )
    );
}

add_action( 'admin_notices', 'crea_bootstrap_blocks_environment_notice' );

/*
 * --- Include-Liste --------------------------------------------------------
 *
 * Die Reihenfolge ist bedeutsam. `helpers.php` steht zuerst: Der Logger und die
 * Optionslesehilfe werden von jedem weiteren Modul benutzt. `i18n.php` folgt
 * unmittelbar, damit die Textdomain vor allem Uebrigen bereitsteht.
 * `class-settings.php` steht vor `admin-page.php`, diese vor
 * `admin-diagnose.php`.
 *
 * Die Partials unter `blocks/_partials/` sind reine Funktionsbibliotheken fuer
 * die Blocktemplates. Sie stehen hinter den `includes/`-Modulen, weil sie deren
 * Renderhelfer benutzen, und vor `includes/class-blocks.php`: Dieser Eintrag
 * bleibt der LETZTE der Liste, weil `tests/test-plugin-load.php` an seiner
 * Deklaration misst, ob `crea_bootstrap_blocks_loaded` wirklich nach allen
 * Modulen feuert.
 *
 * Jede Datei registriert ihre Hooks beim Laden selbst; es gibt bewusst keinen
 * zentralen Bootstrapper, der sie ein zweites Mal anhaengen koennte.
 */
$crea_bootstrap_blocks_includes = [
    'includes/helpers.php',
    'includes/i18n.php',
    'includes/lifecycle.php',
    'includes/class-settings.php',
    'includes/admin-page.php',
    'includes/admin-diagnose.php',
    'includes/class-contract.php',
    'includes/class-fields.php',
    'includes/class-legacy.php',
    'includes/class-styles.php',
    'includes/class-assets.php',
    'includes/class-previews.php',
    'includes/class-abilities.php',
    'includes/cli/bootstrap.php',
    'blocks/_partials/patterns.php',
    'blocks/_partials/background.php',
    'blocks/_partials/link-overlay.php',
    'includes/class-blocks.php',
];

foreach ( $crea_bootstrap_blocks_includes as $crea_bootstrap_blocks_include ) {
    $crea_bootstrap_blocks_path = CREA_BOOTSTRAP_BLOCKS_DIR . $crea_bootstrap_blocks_include;

    if ( is_readable( $crea_bootstrap_blocks_path ) ) {
        require_once $crea_bootstrap_blocks_path;
    }
}

unset( $crea_bootstrap_blocks_include, $crea_bootstrap_blocks_path );

register_activation_hook( __FILE__, 'crea_bootstrap_blocks_activate' );
register_deactivation_hook( __FILE__, 'crea_bootstrap_blocks_deactivate' );

/**
 * Fires after all CreaBootstrapBlocks modules are loaded.
 *
 * @since 1.0.0
 */
do_action( 'crea_bootstrap_blocks_loaded' );
