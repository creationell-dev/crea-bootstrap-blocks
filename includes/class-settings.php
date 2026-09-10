<?php
/**
 * Settings registration for CreaBootstrapBlocks.
 *
 * EINE Array-Option, EIN Sanitize-Callback. Das Einstellungs-Backend des
 * Originals (Dashboard, Layout, Classes, Components, Content, Customize, Forms,
 * dazu Export, Import und Reset ueber ungeschuetzte AJAX-Endpunkte) wird nicht
 * nachgebaut; an seine Stelle treten fuenf Schalter.
 *
 * Der Leseweg auf die Option wohnt NICHT hier, sondern in `helpers.php` — er
 * laeuft im Frontend bei jedem Seitenaufruf und darf nicht vom Admin-Umfeld
 * abhaengen.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The five checkbox fields with their tab membership.
 *
 * Diese Tabelle ist die EINZIGE Quelle dafuer, welcher Schalter in welchem Tab
 * wohnt. Sie wird zweimal gelesen: von der Registrierung, die daraus Sections
 * und Felder baut, und vom Sanitizer, der daraus ableitet, welche Schluessel ein
 * Absenden ueberhaupt anfassen darf. Zwei getrennt gepflegte Listen liefen
 * auseinander, und das Auseinanderlaufen waere still.
 *
 * @return array<int, array<string, string>>
 */
function crea_bootstrap_blocks_settings_fields(): array {
    return [
        [
            'key'     => 'bootstrap_css',
            'tab'     => 'assets',
            'section' => 'crea_bootstrap_blocks_assets',
            'label'   => __( 'Load Bootstrap CSS', 'crea-bootstrap-blocks' ),
            'help'    => __( 'Off by default. Leave it off when your theme already loads Bootstrap.', 'crea-bootstrap-blocks' ),
        ],
        [
            'key'     => 'bootstrap_js',
            'tab'     => 'assets',
            'section' => 'crea_bootstrap_blocks_assets',
            'label'   => __( 'Load Bootstrap JavaScript bundle', 'crea-bootstrap-blocks' ),
            'help'    => __( 'The bundle includes Popper, which dropdowns, tooltips and popovers require.', 'crea-bootstrap-blocks' ),
        ],
        [
            'key'     => 'bootstrap_icons',
            'tab'     => 'assets',
            'section' => 'crea_bootstrap_blocks_assets',
            'label'   => __( 'Load Bootstrap Icons', 'crea-bootstrap-blocks' ),
            'help'    => __( 'Switchable separately: many themes ship Bootstrap but no icon font.', 'crea-bootstrap-blocks' ),
        ],
        [
            'key'     => 'legacy_classes',
            'tab'     => 'compatibility',
            'section' => 'crea_bootstrap_blocks_compatibility',
            'label'   => __( 'Also output the legacy areoi-* classes', 'crea-bootstrap-blocks' ),
            'help'    => __( 'Turn this off only once the theme of this site no longer styles against areoi-* selectors.', 'crea-bootstrap-blocks' ),
        ],
        [
            'key'     => 'legacy_blocks',
            'tab'     => 'compatibility',
            'section' => 'crea_bootstrap_blocks_compatibility',
            'label'   => __( 'Register areoi/* blocks as aliases', 'crea-bootstrap-blocks' ),
            'help'    => __( 'Keeps content that has not been migrated yet renderable. Alias blocks are hidden from the inserter.', 'crea-bootstrap-blocks' ),
        ],

        /*
         * OHNE EINTRAG HIER GAEBE ES DEN SCHALTER NICHT WIRKLICH: Der Sanitizer
         * laesst nur durch, was in dieser Feldtabelle steht und zum gesendeten
         * Tab gehoert. Ein Default allein waere ein Schalter, den niemand
         * umlegen kann.
         */
        [
            'key'     => 'inserter_previews',
            'tab'     => 'compatibility',
            'section' => 'crea_bootstrap_blocks_compatibility',
            'label'   => __( 'Show a preview image for each block in the inserter', 'crea-bootstrap-blocks' ),
            'help'    => __( 'A schematic drawing instead of the rendered block, as the previous plugin did. Affects the editor only.', 'crea-bootstrap-blocks' ),
        ],
    ];
}

/**
 * The settings keys that belong to one tab.
 *
 * Ein unbekannter Tab liefert eine LEERE Liste. Genau darauf beruht die
 * Allowlist-Pruefung im Sanitizer: Was nicht in der Feldtabelle steht, schaltet
 * auch nichts frei.
 *
 * @param string $tab Tab ID.
 * @return array<int, string>
 */
function crea_bootstrap_blocks_settings_tab_keys( string $tab ): array {
    $keys = [];

    if ( '' === $tab ) {
        return $keys;
    }

    foreach ( crea_bootstrap_blocks_settings_fields() as $field ) {
        if ( $field['tab'] === $tab ) {
            $keys[] = $field['key'];
        }
    }

    return $keys;
}

/**
 * Cleans the submitted settings.
 *
 * Die EINZIGE Stelle, an der etwas in die Option kommt. Vier Eigenheiten sind
 * bedeutungstragend:
 *
 * - Es wird ueber die DEFAULTS iteriert, nicht ueber die Eingabe. Ein fremder
 *   Schluessel kommt damit nie in die Datenbank, auch nicht aus einem
 *   praeparierten Formular. Das Transportfeld `_tab` steht ebenfalls nicht in
 *   den Defaults und wird deshalb ebenfalls nicht gespeichert.
 * - Der Ausgangspunkt ist der GESPEICHERTE Stand, nicht „alles Fehlende ist
 *   false". Gerendert werden nur die Sections des aktiven Tabs, gesendet also
 *   auch nur dessen Felder. Wer im Tab „Assets" speichert, sendet nur die
 *   `bootstrap_*`-Felder; `legacy_classes` und `legacy_blocks` (Default `true`)
 *   fielen sonst still auf `false`, und auf einer Bestandsseite verschwaenden
 *   damit die `areoi-*`-Klassen und der `areoi/*`-Alias. Das ist der
 *   Layoutbruch, den Regel 3 des Kompatibilitaetsvertrags ausschliesst.
 * - Innerhalb des aktiven Tabs wird ein fehlender Schluessel sehr wohl `false`.
 *   Ein nicht angehaktes Kaestchen sendet der Browser gar nicht erst mit;
 *   wuerde es dort uebergangen, liesse sich ein einmal gesetzter Schalter nie
 *   wieder ausschalten.
 * - Ein per KONSTANTE festgenagelter Schluessel bleibt davon ausgenommen. Sein
 *   Kaestchen ist `disabled`, wird also ebenfalls nicht gesendet — und im
 *   eigenen Tab waere „nicht gesendet" sonst „aus". Begruendung an der
 *   Fundstelle in der Schleife.
 *
 * Welcher Tab aktiv war, sagt das versteckte Feld `_tab` aus dem Formular.
 * `sanitize_key()` macht daraus einen harmlosen Bezeichner, die Feldtabelle
 * entscheidet als Allowlist, ob er ueberhaupt Schluessel freischaltet. Ist der
 * Tab unbekannt oder fehlt er, wird NICHTS ueberschrieben ausser den
 * tatsaechlich gesendeten Schluesseln — niemals stillschweigend auf `false`
 * gesetzt.
 *
 * @param mixed $input Raw form input.
 * @return array<string, bool>
 */
function crea_bootstrap_blocks_sanitize_settings( mixed $input ): array {
    $defaults = crea_bootstrap_blocks_default_settings();
    $stored   = crea_bootstrap_blocks_get_settings();
    $clean    = [];

    foreach ( $defaults as $key => $default_value ) {
        $clean[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : $default_value;
    }

    if ( ! is_array( $input ) ) {
        return $clean;
    }

    $tab      = isset( $input['_tab'] ) && is_string( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
    $tab_keys = crea_bootstrap_blocks_settings_tab_keys( $tab );

    foreach ( array_keys( $clean ) as $key ) {
        /*
         * Was eine Konstante festnagelt, aendert das Formular nicht. Das
         * Kaestchen dazu wird `disabled` gerendert (siehe
         * `crea_bootstrap_blocks_render_checkbox()`) — und ein `disabled`-Feld
         * sendet der Browser gar nicht erst mit. Ohne diese Ausnahme traefe den
         * Schluessel im eigenen Tab dieselbe Regel wie ein abgewaehltes
         * Kaestchen: Der gespeicherte Wert fiele auf `false`. Solange die
         * Konstante steht, faellt das nicht auf, denn sie hat beim Lesen
         * Vorrang. Wird sie spaeter aus der `wp-config.php` entfernt, ist der
         * Schalter still umgesprungen — bei `legacy_classes` auf einer
         * Bestandsseite genau der Layoutbruch, den Regel 3 des
         * Kompatibilitaetsvertrags ausschliesst.
         *
         * Bewahrt wird der GESPEICHERTE Wert, nicht der der Konstante: Die
         * Konstante uebersteuert beim Lesen, sie soll die Wahl des Betreibers
         * in der Datenbank nicht ueberschreiben.
         */
        if ( defined( 'CREA_BOOTSTRAP_BLOCKS_' . strtoupper( $key ) ) ) {
            continue;
        }

        if ( in_array( $key, $tab_keys, true ) ) {
            $clean[ $key ] = ! empty( $input[ $key ] );

            continue;
        }

        // Ausserhalb des aktiven Tabs zaehlt nur, was tatsaechlich gesendet
        // wurde. Alles andere behaelt seinen gespeicherten Wert.
        if ( array_key_exists( $key, $input ) ) {
            $clean[ $key ] = ! empty( $input[ $key ] );
        }
    }

    return $clean;
}

/**
 * Registers the option, the sections and the fields.
 *
 * Der Tab-Trick: Jede Section haengt an einer EIGENEN Seite
 * `crea-bootstrap-blocks-tab-<id>`, gerendert wird nur die des aktiven Tabs.
 * Das Formular ist trotzdem eines und speichert die ganze Option — deshalb
 * schickt es den aktiven Tab als verstecktes Feld mit, und deshalb setzt der
 * Sanitizer auf dem gespeicherten Stand auf statt auf den Defaults.
 *
 * Der Tab „Diagnose" bekommt KEINE Section: Er zeigt nur an und speichert
 * nichts.
 */
function crea_bootstrap_blocks_settings_init(): void {
    register_setting(
        'crea_bootstrap_blocks_settings',
        'crea_bootstrap_blocks_settings',
        [
            'type'              => 'array',
            'sanitize_callback' => 'crea_bootstrap_blocks_sanitize_settings',
            'default'           => crea_bootstrap_blocks_default_settings(),
            'show_in_rest'      => false,
        ]
    );

    add_settings_section(
        'crea_bootstrap_blocks_assets',
        __( 'Bootstrap assets', 'crea-bootstrap-blocks' ),
        'crea_bootstrap_blocks_assets_section_intro',
        'crea-bootstrap-blocks-tab-assets'
    );

    add_settings_section(
        'crea_bootstrap_blocks_compatibility',
        __( 'Backwards compatibility', 'crea-bootstrap-blocks' ),
        'crea_bootstrap_blocks_compatibility_section_intro',
        'crea-bootstrap-blocks-tab-compatibility'
    );

    foreach ( crea_bootstrap_blocks_settings_fields() as $field ) {
        add_settings_field(
            $field['key'],
            $field['label'],
            'crea_bootstrap_blocks_render_checkbox',
            'crea-bootstrap-blocks-tab-' . $field['tab'],
            $field['section'],
            [
                'key'       => $field['key'],
                'label'     => $field['label'],
                'help'      => $field['help'],
                'label_for' => 'crea-bootstrap-blocks-' . $field['key'],
            ]
        );
    }
}

add_action( 'admin_init', 'crea_bootstrap_blocks_settings_init' );

/**
 * Intro text of the assets section.
 */
function crea_bootstrap_blocks_assets_section_intro(): void {
    echo '<p>' . esc_html__(
        'All three switches are off by default. Bootstrap then comes from the theme, exactly as before.',
        'crea-bootstrap-blocks'
    ) . '</p>';
}

/**
 * Intro text of the compatibility section.
 */
function crea_bootstrap_blocks_compatibility_section_intro(): void {
    echo '<p>' . esc_html__(
        'These switches keep existing pages working. A constant of the same name in wp-config.php overrides the setting.',
        'crea-bootstrap-blocks'
    ) . '</p>';
}

/**
 * Renders one checkbox field.
 *
 * Ist die zugehoerige Konstante gesetzt, wird das Kaestchen gesperrt und der
 * Vorrang benannt. Ein bedienbares Kaestchen, das nichts bewirkt, waere die
 * schlechtere Loesung.
 *
 * @param array<string, mixed> $args Field arguments.
 */
function crea_bootstrap_blocks_render_checkbox( array $args ): void {
    $key      = isset( $args['key'] ) ? (string) $args['key'] : '';
    $constant = 'CREA_BOOTSTRAP_BLOCKS_' . strtoupper( $key );
    $forced   = defined( $constant );
    $settings = crea_bootstrap_blocks_get_settings();
    $value    = ! empty( $settings[ $key ] );

    printf(
        '<input type="checkbox" id="%1$s" name="crea_bootstrap_blocks_settings[%2$s]" value="1"%3$s%4$s />',
        esc_attr( 'crea-bootstrap-blocks-' . $key ),
        esc_attr( $key ),
        checked( $value, true, false ),
        $forced ? ' disabled="disabled"' : ''
    );

    if ( isset( $args['help'] ) && '' !== (string) $args['help'] ) {
        echo '<p class="description">' . esc_html( (string) $args['help'] ) . '</p>';
    }

    if ( $forced ) {
        printf(
            '<p class="description"><strong>%s</strong> %s</p>',
            esc_html__( 'Overridden:', 'crea-bootstrap-blocks' ),
            esc_html(
                sprintf(
                    /* translators: %s: name of a PHP constant. */
                    __( 'The constant %s in wp-config.php takes precedence over this setting.', 'crea-bootstrap-blocks' ),
                    $constant
                )
            )
        );
    }
}
