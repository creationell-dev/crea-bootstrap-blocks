<?php
/**
 * The diagnostics tab — read only.
 *
 * Diese Datei schreibt NICHTS: keine schreibende Optionsfunktion, keine
 * schreibende Datenbankmethode, kein Dateizugriff ausser `is_dir()`. Das
 * Original hatte an vergleichbarer Stelle einen Options-Export ohne
 * Capability- und Nonce-Check und einen SCSS-Recompile ohne beides; hier gibt
 * es schlicht nichts auszuloesen.
 *
 * Die Namen der verbotenen Aufrufe stehen bewusst NICHT in diesem Kommentar:
 * `tests/test-admin-settings.php` sucht sie als Zeichenketten im Quelltext
 * dieser Datei, und eine Erwaehnung im Fliesstext waere fuer diese Suche ein
 * Treffer wie jeder andere.
 *
 * Zwei Zeilen fragen die Datenbank. Fuer sie gilt ohne Ausnahme: JEDER Wert
 * geht durch `$wpdb->prepare()`, auch jedes LIKE-Muster, und jedes Muster wird
 * vorher durch `$wpdb->esc_like()` entschaerft. Beides ist noetig und keines
 * ersetzt das andere: `esc_like()` schuetzt vor `%` und `_` im Suchtext,
 * `prepare()` vor Injektion. Das Alt-Plugin setzte Suchtexte ungebunden in
 * seine SQL-Strings.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Whether the global `$wpdb` is present and usable for the read queries.
 *
 * Geprueft wird auf die drei Methoden, die hier tatsaechlich benutzt werden,
 * nicht auf `instanceof wpdb`. So bleibt die Diagnose unter dem Test-Harness
 * pruefbar, und sie ueberlebt einen frueh eingehaengten Aufruf, bei dem
 * `$wpdb` noch nicht steht.
 */
function crea_bootstrap_blocks_diagnose_db_ready(): bool {
    global $wpdb;

    if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
        return false;
    }

    foreach ( [ 'prepare', 'esc_like', 'get_var' ] as $method ) {
        if ( ! method_exists( $wpdb, $method ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Counts posts whose content contains at least one of the given substrings.
 *
 * Zaehlt ZEILEN in `wp_posts`, nicht Fundstellen im Text: Ein Beitrag mit zehn
 * `areoi/*`-Bloecken zaehlt einmal. Revisionen und Papierkorb bleiben aussen
 * vor — sie werden nicht ausgeliefert und wuerden die Zahl aufblaehen.
 *
 * @param array<int, string> $needles Literal substrings to look for.
 * @return int|null Number of posts, or null when the database is unavailable.
 */
function crea_bootstrap_blocks_diagnose_content_count( array $needles ): ?int {
    global $wpdb;

    if ( [] === $needles || ! crea_bootstrap_blocks_diagnose_db_ready() ) {
        return null;
    }

    $conditions = [];
    $values     = [ 'revision', 'trash' ];

    foreach ( $needles as $needle ) {
        $conditions[] = 'post_content LIKE %s';
        $values[]     = '%' . $wpdb->esc_like( $needle ) . '%';
    }

    /*
     * Drei weitere Sniffs sind an dieser EINEN Abfrage abgeschaltet, jeder aus
     * einem eigenen Grund:
     *
     * - `PreparedSQLPlaceholders.ReplacementsWrongNumber` zaehlt die
     *   Platzhalter im statischen Teil des SQL-Strings und findet zwei. Die
     *   uebrigen entstehen erst im `implode()` ueber `$conditions`, ihre Zahl
     *   haengt an der Zahl der Suchmuster. Zur Laufzeit stimmt die Bilanz
     *   immer: `$values` fuehrt genau `2 + count( $needles )` Werte fuer genau
     *   so viele Platzhalter. Der Sniff kann das statisch nicht sehen — die
     *   Meldung bleibt auch bei entpackter Uebergabe (`...$values`) stehen.
     * - `DirectDatabaseQuery.DirectQuery` — es gibt keine Core-Funktion, die
     *   Beitraege nach einer Teilzeichenkette in `post_content` zaehlt.
     *   `WP_Query` mit `s` sucht anders und laedt die Beitraege dazu.
     * - `DirectDatabaseQuery.NoCaching` — die Zeile ist eine Diagnose. Ein
     *   zwischengespeicherter Wert waere hier die falsche Antwort, nicht die
     *   schnellere: Gefragt ist der Zustand JETZT. Die Abfrage laeuft nur beim
     *   Aufruf des Diagnose-Tabs, nie im Frontend.
     */
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Der Tabellenname stammt aus $wpdb; jeder Wert, auch jedes LIKE-Muster, geht als Parameter durch prepare(). Die drei uebrigen Sniffs sind im Block darueber begruendet.
    $sql = $wpdb->prepare(
        'SELECT COUNT(*) FROM ' . $wpdb->posts
            . ' WHERE post_type != %s AND post_status != %s AND ( ' . implode( ' OR ', $conditions ) . ' )',
        $values
    );

    if ( ! is_string( $sql ) ) {
        return null;
    }

    $count = $wpdb->get_var( $sql );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    return null === $count ? null : (int) $count;
}

/**
 * Formats a count that may be unavailable.
 *
 * @param int|null $count Result of a counting query.
 */
function crea_bootstrap_blocks_diagnose_count_label( ?int $count ): string {
    if ( null === $count ) {
        return __( 'not determinable (no database handle)', 'crea-bootstrap-blocks' );
    }

    return (string) $count;
}

/**
 * Formats the state of one legacy option.
 *
 * `get_option()` bekommt `null` als Standardwert. Nur so laesst sich „nicht
 * gesetzt" von einem gespeicherten `false` oder einem leeren String
 * unterscheiden — und genau diese Unterscheidung ist die Aussage der Zeile.
 *
 * @param string $option Option name.
 */
function crea_bootstrap_blocks_diagnose_option_state( string $option ): string {
    $value = get_option( $option, null );

    if ( null === $value ) {
        return __( 'not set', 'crea-bootstrap-blocks' );
    }

    if ( is_bool( $value ) ) {
        return $value
            ? __( 'on', 'crea-bootstrap-blocks' )
            : __( 'off', 'crea-bootstrap-blocks' );
    }

    if ( is_scalar( $value ) ) {
        return (string) $value;
    }

    return __( 'set, but not a scalar value', 'crea-bootstrap-blocks' );
}

/**
 * Theme folders that still carry a legacy `all-bootstrap-blocks/` directory.
 *
 * Das Alt-Plugin liess Templates aus `<theme>/all-bootstrap-blocks/`
 * ueberladen. Ein solcher Ordner ueberlebt die Deinstallation des Alt-Plugins
 * und ist der haeufigste Grund fuer Markup, das sich nach der Migration nicht
 * erklaeren laesst. Rein lesend: `is_dir()`, sonst nichts.
 *
 * @return array<int, string> Absolute paths, child theme first.
 */
function crea_bootstrap_blocks_diagnose_theme_overrides(): array {
    $found = [];

    foreach ( [ 'get_stylesheet_directory', 'get_template_directory' ] as $getter ) {
        if ( ! function_exists( $getter ) ) {
            continue;
        }

        $directory = (string) $getter();

        if ( '' === $directory ) {
            continue;
        }

        $candidate = rtrim( $directory, '/\\' ) . '/all-bootstrap-blocks';

        if ( is_dir( $candidate ) && ! in_array( $candidate, $found, true ) ) {
            $found[] = $candidate;
        }
    }

    return $found;
}

/**
 * The diagnostic rows.
 *
 * @return array<int, array{0:string,1:string}> List of [ label, value ].
 */
function crea_bootstrap_blocks_diagnose_rows(): array {
    $rows = [];

    $rows[] = [
        __( 'Plugin version', 'crea-bootstrap-blocks' ),
        defined( 'CREA_BOOTSTRAP_BLOCKS_VERSION' ) ? (string) constant( 'CREA_BOOTSTRAP_BLOCKS_VERSION' ) : '—',
    ];

    $rows[] = [
        __( 'Blockstudio version', 'crea-bootstrap-blocks' ),
        defined( 'BLOCKSTUDIO_VERSION' )
            ? (string) constant( 'BLOCKSTUDIO_VERSION' )
            : __( 'not active', 'crea-bootstrap-blocks' ),
    ];

    $rows[] = [
        __( 'Required Blockstudio version', 'crea-bootstrap-blocks' ),
        defined( 'CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO' )
            ? (string) constant( 'CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO' )
            : '7.6',
    ];

    $rows[] = [ __( 'PHP version', 'crea-bootstrap-blocks' ), PHP_VERSION ];

    foreach (
        [
            'bootstrap_css'   => __( 'Bootstrap CSS', 'crea-bootstrap-blocks' ),
            'bootstrap_js'    => __( 'Bootstrap JavaScript', 'crea-bootstrap-blocks' ),
            'bootstrap_icons' => __( 'Bootstrap Icons', 'crea-bootstrap-blocks' ),
            'legacy_classes'  => __( 'Legacy classes areoi-*', 'crea-bootstrap-blocks' ),
            'legacy_blocks'   => __( 'Legacy blocks areoi/*', 'crea-bootstrap-blocks' ),
        ] as $key => $label
    ) {
        $constant = 'CREA_BOOTSTRAP_BLOCKS_' . strtoupper( $key );
        $state    = crea_bootstrap_blocks_setting_enabled( $key )
            ? __( 'on', 'crea-bootstrap-blocks' )
            : __( 'off', 'crea-bootstrap-blocks' );

        if ( defined( $constant ) ) {
            $state .= ' (' . __( 'set by constant', 'crea-bootstrap-blocks' ) . ')';
        }

        $rows[] = [ $label, $state ];
    }

    $rows[] = [
        __( 'Posts with areoi/* blocks', 'crea-bootstrap-blocks' ),
        crea_bootstrap_blocks_diagnose_count_label(
            crea_bootstrap_blocks_diagnose_content_count( [ 'wp:areoi/' ] )
        ),
    ];

    /*
     * VIER Muster, weil zwei nicht reichen. Ein blosses `%bi-%` ist keine
     * Alternative: Es traefe jedes Wort mit dieser Buchstabenfolge im
     * Fliesstext — `Obi-Wan`, `bi-weekly`.
     *
     * - `"bi-` faengt eine AUSDRUECKLICH gewaehlte Icon-Klasse, die als
     *   Attributwert im Blockkommentar steht (`"icon":"bi-star"`).
     * - `class="bi ` faengt handgeschriebenes Markup der aelteren Form
     *   `class="bi bi-star"`.
     * - `wp:areoi/icon` faengt den Icon-Block unabhaengig von seinen
     *   Attributen. Notwendig, weil die beiden Muster oben den haeufigsten Fall
     *   VERPASSEN: Steht das Icon auf seinem Default, serialisiert Gutenberg das
     *   Attribut gar nicht, und die gespeicherte Klassenliste des Alt-Plugins
     *   lautet `class="text-primary bi-activity"` — `style` steht dort VOR
     *   `icon` (`blocks/icon/block.js`, `save()`), vor dem `bi` also ein
     *   Leerzeichen und kein Anfuehrungszeichen. Der Block impliziert die
     *   Icon-Schrift zwingend: `icon` hat den nichtleeren Default
     *   `bi-activity`, und `blocks/icon.php` gibt den gespeicherten Inhalt
     *   unveraendert zurueck.
     * - `"include_icon":true` faengt den Button mit Icon. Dort steht die Klasse
     *   ueberhaupt nicht im Inhalt: `blocks/button.php` rendert das `<i>`
     *   serverseitig. Das Attribut hat den Default `false` und wird deshalb
     *   serialisiert, sobald es an ist.
     *
     * Ohne die beiden letzten Muster zeigt die Zeile auf einer Bestandsseite
     * eine zu kleine Zahl, der Betreiber laesst „Load Bootstrap Icons" aus, und
     * nach der Migration fehlt die Icon-Schrift: die Glyphen sind unsichtbar.
     */
    $rows[] = [
        __( 'Posts using Bootstrap Icons classes', 'crea-bootstrap-blocks' ),
        crea_bootstrap_blocks_diagnose_count_label(
            crea_bootstrap_blocks_diagnose_content_count(
                [ '"bi-', 'class="bi ', 'wp:areoi/icon', '"include_icon":true' ]
            )
        ),
    ];

    $legacy_option_label = __( 'Legacy option', 'crea-bootstrap-blocks' );

    foreach (
        [
            'areoi-dashboard-global-display-units',
            'areoi-customize-options-enable-cssgrid',
            'areoi-customize-options-force-flex',
        ] as $legacy_option
    ) {
        $rows[] = [
            $legacy_option_label . ': ' . $legacy_option,
            crea_bootstrap_blocks_diagnose_option_state( $legacy_option ),
        ];
    }

    // Die sechs Breakpoint-Optionen bekommen EINE Zeile: Sechs Zeilen mit
    // sechsmal „not set" waeren die Regel, nicht die Ausnahme, und verdeckten
    // den Fall, auf den es ankommt — dass ueberhaupt einer gesetzt ist.
    $breakpoints = [];

    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $breakpoint ) {
        $value = get_option( 'areoi-layout-grid-grid-breakpoint-' . $breakpoint, null );

        if ( null !== $value && is_scalar( $value ) ) {
            $breakpoints[] = $breakpoint . ' = ' . (string) $value;
        }
    }

    $rows[] = [
        $legacy_option_label . ': areoi-layout-grid-grid-breakpoint-*',
        [] === $breakpoints
            ? __( 'not set', 'crea-bootstrap-blocks' )
            : implode( ', ', $breakpoints ),
    ];

    $overrides = crea_bootstrap_blocks_diagnose_theme_overrides();

    $rows[] = [
        __( 'Legacy theme override folder', 'crea-bootstrap-blocks' ),
        [] === $overrides
            ? __( 'none', 'crea-bootstrap-blocks' )
            : implode( ', ', $overrides ),
    ];

    return $rows;
}

/**
 * Renders the diagnostics tab.
 *
 * Beide Zellen sind reiner Text in einem Elementinhalt, also `esc_html()` —
 * der kontextrichtige Escaper an dieser Stelle. Eine der Zeilen fuehrt einen
 * Dateisystempfad, eine andere einen Optionswert aus der Datenbank; beide sind
 * Fremddaten und werden genauso behandelt wie alles andere.
 */
function crea_bootstrap_blocks_render_diagnose(): void {
    echo '<table class="widefat striped" style="max-width:48rem">';
    echo '<tbody>';

    foreach ( crea_bootstrap_blocks_diagnose_rows() as $row ) {
        printf(
            '<tr><th scope="row">%s</th><td>%s</td></tr>',
            esc_html( $row[0] ),
            esc_html( $row[1] )
        );
    }

    echo '</tbody></table>';
}
