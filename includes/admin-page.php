<?php
/**
 * The plugin's admin page under Appearance.
 *
 * `add_submenu_page( 'themes.php', … )` — Design → CreaBootstrapBlocks,
 * Capability `manage_options`. Kein eigener Menuepunkt auf oberster Ebene: Das
 * Plugin liefert Layoutbausteine, es ist kein eigenes Arbeitsgebiet.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The three tabs, in display order.
 *
 * @return array<string, string> Tab ID => label.
 */
function crea_bootstrap_blocks_admin_tabs(): array {
    return [
        'assets'        => __( 'Assets', 'crea-bootstrap-blocks' ),
        'compatibility' => __( 'Compatibility', 'crea-bootstrap-blocks' ),
        'diagnose'      => __( 'Diagnostics', 'crea-bootstrap-blocks' ),
    ];
}

/**
 * The active tab.
 *
 * Ein unbekannter Wert faellt auf `assets` zurueck. Der Parameter fliesst in
 * einen Seitennamen fuer `do_settings_sections()`; ein ungeprueft
 * durchgereichter Wert waere dort eine offene Flanke.
 */
function crea_bootstrap_blocks_current_tab(): string {
    $tabs = crea_bootstrap_blocks_admin_tabs();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reine Anzeigeauswahl, kein Schreibvorgang.
    $requested = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';

    return isset( $tabs[ $requested ] ) ? $requested : 'assets';
}

/**
 * Registers the submenu entry.
 */
function crea_bootstrap_blocks_admin_menu(): void {
    add_submenu_page(
        'themes.php',
        __( 'CreaBootstrapBlocks', 'crea-bootstrap-blocks' ),
        __( 'CreaBootstrapBlocks', 'crea-bootstrap-blocks' ),
        'manage_options',
        'crea-bootstrap-blocks',
        'crea_bootstrap_blocks_admin_page'
    );
}

add_action( 'admin_menu', 'crea_bootstrap_blocks_admin_menu' );

/**
 * Renders the admin page.
 *
 * Der Capability-Check steht hier ein zweites Mal. `add_submenu_page()` schuetzt
 * den Menuepunkt, nicht den Callback — ein direkter Aufruf von
 * `themes.php?page=crea-bootstrap-blocks` liefe sonst durch.
 */
function crea_bootstrap_blocks_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tabs    = crea_bootstrap_blocks_admin_tabs();
    $current = crea_bootstrap_blocks_current_tab();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'CreaBootstrapBlocks', 'crea-bootstrap-blocks' ) . '</h1>';

    echo '<h2 class="nav-tab-wrapper">';
    foreach ( $tabs as $id => $label ) {
        printf(
            '<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
            esc_url( admin_url( 'themes.php?page=crea-bootstrap-blocks&tab=' . $id ) ),
            $id === $current ? ' nav-tab-active' : '',
            esc_html( $label )
        );
    }
    echo '</h2>';

    if ( 'diagnose' === $current ) {
        crea_bootstrap_blocks_render_diagnose();
        echo '</div>';

        return;
    }

    echo '<form action="options.php" method="post">';
    settings_fields( 'crea_bootstrap_blocks_settings' );

    /*
     * Der aktive Tab faehrt als verstecktes Feld mit. Ohne ihn wuesste der
     * Sanitizer nicht, welche Schluessel dieses Absenden ueberhaupt anfassen
     * darf: Gerendert werden nur die Sections des aktiven Tabs, gesendet also
     * auch nur dessen Felder. Der Wert ist ein Transportfeld und wird nicht
     * gespeichert — er steht nicht in den Defaults, ueber die der Sanitizer
     * iteriert.
     */
    printf(
        '<input type="hidden" name="crea_bootstrap_blocks_settings[_tab]" value="%s" />',
        esc_attr( $current )
    );

    do_settings_sections( 'crea-bootstrap-blocks-tab-' . $current );
    submit_button();
    echo '</form>';

    echo '</div>';
}
