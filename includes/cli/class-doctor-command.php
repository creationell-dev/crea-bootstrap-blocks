<?php
/**
 * WP-CLI: `wp creabb doctor` — the read-only pre-flight checklist.
 *
 * DAS KOMMANDO SCHREIBT NICHTS. Es liest, stellt fest und bricht mit einem
 * Exit-Code ungleich 0 ab, wenn eine Pruefung fehlschlaegt. Jede einzelne
 * Pruefung existiert, weil ihr Fehlschlag sonst erst NACH der Migration
 * auffiele — und dann auf Produktivdaten.
 *
 * AUFBAU: Die Pruefungen selbst sind reine `public static`-Methoden. Sie
 * bekommen fertige Fakten und geben eine Zeile zurueck; sie lesen weder
 * Datenbank noch Dateisystem noch Optionen. Nur so laesst sich jede einzelne
 * gegen einen konstruierten Zustand halten (`tests/test-cli-doctor-checks.php`).
 * Die Erhebung der Fakten liegt getrennt davon in privaten Methoden, die
 * ausschliesslich `__invoke()` benutzt.
 *
 * ZEILENFORM: [ 'check' => …, 'status' => 'ok'|'warn'|'error', 'message' => … ].
 * `status` ist maschinenlesbar und wird NIE uebersetzt; `check` und `message`
 * sind Ausgabetext und laufen durch `__()`.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks\CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Creationell\BootstrapBlocks\Contract;
use WP_CLI;

/**
 * `wp creabb doctor`
 */
class Doctor_Command {

    /**
     * Runs the pre-flight checklist and prints one row per check.
     *
     * Exit code 1 as soon as one row carries `error`, 0 otherwise. Rows with
     * `warn` are information: they change nothing about the exit code.
     *
     * DIESES KOMMANDO SCHREIBT NICHTS. Es liest Optionen, Postinhalte,
     * Themedateien und die eigenen `block.json` — mehr nicht. Wer hier einen
     * Schreibaufruf ergaenzt, bricht die Zusage, mit der `doctor` VOR jedem
     * Backup laufen darf; `tests/test-cli-doctor-invoke.php` haelt das fest.
     *
     * ## OPTIONS
     *
     * [--backup=<path>]
     * : Backup target the migration would write to. Defaults to the uploads directory.
     *
     * [--format=<format>]
     * : Output format (table, json, yaml, csv).
     *
     * ## EXAMPLES
     *
     *     wp creabb doctor
     *     wp creabb doctor --backup=/var/backups/dump.sql.gz --format=json
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

        $contents  = self::block_content_haystack();
        $icon_text = self::icon_content_haystack();

        $found_blocks = [];

        foreach ( $contents as $content ) {
            foreach ( self::areoi_block_names( $content ) as $name ) {
                $found_blocks[ $name ] = true;
            }
        }

        $bi_hits   = 0;
        $icon_hits = 0;

        foreach ( $icon_text as $content ) {
            $bi_hits   += self::bi_class_hits( $content );
            $icon_hits += self::button_icon_attribute_hits( $content );
        }

        $theme_haystack = self::theme_haystack();
        $has_selector   = false;

        foreach ( $theme_haystack as $text ) {
            if ( self::css_has_areoi_selector( $text ) ) {
                $has_selector = true;

                break;
            }
        }

        $legacy_classes = function_exists( 'crea_bootstrap_blocks_setting_enabled' )
            ? \crea_bootstrap_blocks_setting_enabled( 'legacy_classes', true )
            : true;

        $icons_switch = function_exists( 'crea_bootstrap_blocks_setting_enabled' )
            ? \crea_bootstrap_blocks_setting_enabled( 'bootstrap_icons' )
            : false;

        $backup_argument = isset( $assoc_args['backup'] ) ? (string) $assoc_args['backup'] : '';

        if ( '' === $backup_argument ) {
            $backup_argument = self::default_backup_path();
        }

        $backup_directory = self::backup_directory( $backup_argument );

        // DIE REIHENFOLGE IST FESTGELEGT und Teil der Zusage: Wer die Ausgabe
        // zweier Laeufe nebeneinanderlegt, soll Zeile fuer Zeile vergleichen
        // koennen. Sie lautet: Blockstudio, Alt-Plugin, Gegenstuecke,
        // Feldtypen, Theme-Overrides, Theme-Templates, Alt-Optionen, Icons,
        // Legacy-Klassen, Backup-Ziel.
        //
        // Das Alt-Plugin steht auf Platz 2, weil es wie Blockstudio eine
        // Umgebungsvoraussetzung ist und keine Eigenschaft des Inhalts. Die
        // Nummern der uebrigen Zeilen bleiben, wie sie sind — die Methode
        // heisst im Docblock „Check 1b", nach demselben Muster wie das
        // nachtraeglich eingefuegte „Check 4b".
        $checks = [
            self::blockstudio_check(
                defined( 'BLOCKSTUDIO_VERSION' ) ? (string) constant( 'BLOCKSTUDIO_VERSION' ) : null,
                defined( 'CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO' )
                    ? (string) constant( 'CREA_BOOTSTRAP_BLOCKS_MIN_BLOCKSTUDIO' )
                    : '7.6'
            ),
            self::legacy_plugin_check( self::legacy_plugin_active() ),
            self::counterpart_check( array_keys( $found_blocks ), self::registered_block_names() ),
            self::field_type_check( self::field_type_survey() ),
            self::theme_override_check( self::theme_override_directories() ),
            // Direkt hinter den Theme-Overrides, weil beide Zeilen dasselbe
            // Ziel haben: Sie melden, was im Theme-Repo zu tun ist. Die
            // Fundstelle „Theme-Dateien templates/*.html, parts/*.html" liegt
            // NICHT in der Datenbank; kein Lauf von `migrate run` erreicht sie.
            self::theme_template_check( self::theme_template_files_with_areoi() ),
            self::legacy_options_check( self::legacy_option_values() ),
            self::icons_check(
                $bi_hits,
                $icon_hits,
                (bool) $icons_switch,
                self::haystack_provides_icons( $theme_haystack )
            ),
            self::legacy_class_check( $has_selector, (bool) $legacy_classes ),

            /*
             * `WordPress.WP.AlternativeFunctions` schlaegt bei `is_writable()`
             * an und schlaegt WP_Filesystem vor. Das geht am Fall vorbei: Diese
             * Zeile fragt, ob der SPAETERE Backup-Lauf sein Archiv ablegen kann,
             * und der schreibt aus WP-CLI heraus mit den Rechten des laufenden
             * Prozesses. WP_Filesystem antwortete ueber einen Transport, den es
             * in WP-CLI nicht gibt (FTP, SSH), waere ohne
             * `WP_Filesystem()`-Initialisierung gar nicht verfuegbar und sagte
             * damit etwas anderes aus als die Frage verlangt. `doctor` schreibt
             * selbst nichts — hier wird nur gefragt.
             */
            self::backup_target_check(
                $backup_directory,
                is_dir( $backup_directory ),
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Begruendung im Block darueber.
                is_writable( $backup_directory ),
                self::path_inside_public_root( $backup_directory, self::public_roots() )
            ),
        ];

        /**
         * Filters the rows `wp creabb doctor` prints.
         *
         * @since 1.0.0
         *
         * @param array<int, array{check: string, status: string, message: string}> $checks The rows.
         */
        $checks = (array) \apply_filters( 'crea_bootstrap_blocks_doctor_checks', $checks );

        $has_error = false;

        foreach ( $checks as $row ) {
            if ( is_array( $row ) && isset( $row['status'] ) && 'error' === $row['status'] ) {
                $has_error = true;

                break;
            }
        }

        \WP_CLI\Utils\format_items( $format, $checks, [ 'check', 'status', 'message' ] );

        if ( $has_error ) {
            WP_CLI::halt( 1 );
        }
    }

    /**
     * Check 1 — Blockstudio is active and new enough.
     *
     * Ohne Blockstudio registriert das Plugin keinen einzigen Block. Eine
     * Migration in diesem Zustand ersetzt gueltiges `areoi/*`-Markup durch
     * `creabb/*`-Markup, fuer das es keinen registrierten Blocktyp gibt — der
     * Editor zeigt dann „Dieser Block enthaelt unerwarteten oder ungueltigen
     * Inhalt" und verwirft die Attribute beim naechsten Speichern.
     *
     * @param string|null $version Value of BLOCKSTUDIO_VERSION, or null when absent.
     * @param string      $minimum Required minimum version.
     * @return array{check: string, status: string, message: string}
     */
    public static function blockstudio_check( ?string $version, string $minimum ): array {
        $label = __( 'Blockstudio', 'crea-bootstrap-blocks' );

        if ( null === $version || '' === $version ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: %s: required minimum Blockstudio version. */
                    __( 'Blockstudio is not active. Version %s or newer is required — without it not a single block is registered.', 'crea-bootstrap-blocks' ),
                    $minimum
                ),
            ];
        }

        if ( version_compare( $version, $minimum, '<' ) ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: 1: installed Blockstudio version, 2: required minimum version. */
                    __( 'Blockstudio %1$s is installed, %2$s or newer is required.', 'crea-bootstrap-blocks' ),
                    $version,
                    $minimum
                ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'ok',
            'message' => sprintf(
                /* translators: %s: installed Blockstudio version. */
                __( 'active, version %s', 'crea-bootstrap-blocks' ),
                $version
            ),
        ];
    }

    /**
     * Check 1b — is the old plugin still active?
     *
     * WARUM DAS GEFRAGT GEHOERT. `05-migration.md` schreibt die Reihenfolge je
     * Seite vor: Referenzaufnahme (Schritt 2) und Vergleichsaufnahme
     * (Schritt 6) entstehen BEIDE, waehrend das Alt-Plugin noch aktiv ist;
     * erst Schritt 7 schaltet es ab. Keine der uebrigen Zeilen fragt danach,
     * und im ganzen Produktivcode kommt weder `is_plugin_active()` noch
     * `active_plugins` vor.
     *
     * DER STILLE FALL IST „NICHT AKTIV". Wer das Alt-Plugin vor der
     * Referenzaufnahme abschaltet, bekommt trotzdem eine Seite, die aussieht
     * wie vorher: `Legacy::is_in_scope()` rendert `areoi/*` weiter, weil
     * `legacy_blocks` per Default an ist und `blocks-legacy/` die 47
     * Aliasbloecke registriert. Die „Vorher"-Aufnahme stammt dann nicht vom
     * Original, sondern von DIESEM Plugin — und `wp creabb snapshot diff`
     * vergleicht den Nachbau gegen sich selbst.
     *
     * BEIDE ZUSTAENDE SIND `warn`, KEINER `error`. Ein `error` auf „aktiv"
     * machte Schritt 3 unmoeglich, ein `error` auf „fehlt" jeden Lauf nach
     * Schritt 7. Die Zeile sagt, WO man steht, und nennt die Folge — sie
     * verbietet nichts.
     *
     * @param bool $active Whether the old plugin is active.
     * @return array{check: string, status: string, message: string}
     */
    public static function legacy_plugin_check( bool $active ): array {
        $label = __( 'Old plugin (all-bootstrap-blocks)', 'crea-bootstrap-blocks' );

        if ( $active ) {
            return [
                'check'   => $label,
                'status'  => 'warn',
                'message' => __(
                    'active — this is the state migration and the before/after captures are meant to run in. After the last step it has to be switched off.',
                    'crea-bootstrap-blocks'
                ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'warn',
            'message' => __(
                'NOT active — a "before" capture taken now renders through the areoi/* alias blocks of THIS plugin and would compare the rebuild against itself.',
                'crea-bootstrap-blocks'
            ),
        ];
    }

    /**
     * Check 2 — every `areoi/*` block in the content has a `creabb/*` counterpart.
     *
     * Ohne Gegenstueck entstuende eine TEILMIGRATION: Ein Teil des Contents
     * traegt danach Blocknamen, die niemand registriert hat. Der Alias-Fallback
     * hilft dort nicht — er faengt den umgekehrten Fall ab (nicht migrierter
     * `areoi/*`-Bestand), nicht ein migriertes `creabb/*` ohne Blocktyp.
     *
     * @param array<int, mixed> $found_blocks      Block names found in the content.
     * @param array<int, mixed> $registered_blocks Names of all registered block types.
     * @return array{check: string, status: string, message: string}
     */
    public static function counterpart_check( array $found_blocks, array $registered_blocks ): array {
        $label = __( 'Block counterparts', 'crea-bootstrap-blocks' );

        $found = [];

        foreach ( $found_blocks as $name ) {
            if ( is_string( $name ) && str_starts_with( $name, 'areoi/' ) ) {
                $found[ $name ] = true;
            }
        }

        if ( [] === $found ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'no areoi/* block found in the content', 'crea-bootstrap-blocks' ),
            ];
        }

        $registered = [];

        foreach ( $registered_blocks as $name ) {
            if ( is_string( $name ) ) {
                $registered[ $name ] = true;
            }
        }

        $missing = [];

        foreach ( array_keys( $found ) as $name ) {
            $counterpart = 'creabb/' . substr( $name, strlen( 'areoi/' ) );

            if ( ! isset( $registered[ $counterpart ] ) ) {
                $missing[] = $counterpart;
            }
        }

        sort( $missing );

        if ( [] === $missing ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => sprintf(
                    /* translators: %d: number of distinct areoi/* block types found. */
                    __( 'all %d areoi/* block types found in the content have a registered creabb/* counterpart', 'crea-bootstrap-blocks' ),
                    count( $found )
                ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'error',
            'message' => sprintf(
                /* translators: %s: comma separated list of missing block names. */
                __( 'no registered counterpart for: %s. Migrating now would leave those blocks without a block type (partial migration).', 'crea-bootstrap-blocks' ),
                implode( ', ', $missing )
            ),
        ];
    }

    /**
     * Check 3 — no locked field type points at a contract attribute (rule 1.4).
     *
     * Diese Pruefung schaut nicht in den Content, sondern in die eigenen
     * `block.json`. Sie ist die einzige, die einen Fehler im NEUEN Plugin
     * abfaengt statt einen Zustand der Zielinstallation: Ein `select` an
     * `col_xs` speichert {"value":"col-12","label":"12"} statt "col-12", ein
     * `color` an `background_color` ein dreischluessliges statt des
     * sechsschluessligen Altobjekts (S-5). Der Attributname stimmt dabei — der
     * Fehler faellt erst auf, wenn eine Redakteurin den Block einmal angefasst
     * hat, und dann ist der Altwert weg.
     *
     * Die Verstossliste kommt fertig herein; erhoben wird sie in
     * `Contract::field_type_violations()`.
     *
     * @param array{inspected?: int, attributes?: int, expected?: int, violations?: array<int, array<string, mixed>>, worst_block?: string, worst_block_deficit?: int} $survey Result of `field_type_survey()`.
     * @return array{check: string, status: string, message: string}
     */
    public static function field_type_check( array $survey ): array {
        $label = __( 'Field types (contract rule 1.4)', 'crea-bootstrap-blocks' );

        $inspected  = isset( $survey['inspected'] ) ? (int) $survey['inspected'] : 0;
        $attributes = isset( $survey['attributes'] ) ? (int) $survey['attributes'] : 0;
        $expected   = isset( $survey['expected'] ) ? (int) $survey['expected'] : 0;
        $violations = isset( $survey['violations'] ) && is_array( $survey['violations'] )
            ? $survey['violations']
            : [];

        $worst_block         = isset( $survey['worst_block'] ) && is_string( $survey['worst_block'] )
            ? $survey['worst_block']
            : '';
        $worst_block_deficit = isset( $survey['worst_block_deficit'] ) ? (int) $survey['worst_block_deficit'] : 0;

        /*
         * ZUERST: HAT SIE UEBERHAUPT HINGESEHEN?
         *
         * Ohne diesen Zweig meldete die Zeile fuer 47 gelesene Dateien und fuer
         * null gelesene dieselbe gruene Meldung — byteglich nachgemessen. Sie
         * ist laut ihrem eigenen Docblock die EINZIGE der Pruefungen, die einen
         * Fehler im neuen Plugin abfaengt statt einen Zustand der
         * Zielinstallation; ausgerechnet sie konnte nicht sagen, ob sie
         * hingesehen hat.
         */
        if ( 0 === $inspected ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => __(
                    'Rule 1.4 is UNCHECKED on this installation: not a single block.json could be read. This says nothing about the field types — it says the check did not run.',
                    'crea-bootstrap-blocks'
                ),
            ];
        }

        if ( $expected > 0 && $inspected < $expected ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: 1: number of block.json files read, 2: number of contract files found. */
                    __( 'Only %1$d of %2$d block.json could be read — the rest was skipped silently and is unchecked.', 'crea-bootstrap-blocks' ),
                    $inspected,
                    $expected
                ),
            ];
        }

        /*
         * DANN: HAT SIE AUCH ETWAS GEFUNDEN?
         *
         * `$inspected` zaehlt DATEIEN, nicht Felder. Ein Feldaufloeser, der in
         * jeder gelesenen `block.json` alle Felder verliert (etwa ein kaputter
         * `group`-Zweig ohne `id`), laesst `$inspected` unveraendert bei 47 —
         * die Zeile blieb bislang gruen, waehrend `$attributes` auf einen
         * Bruchteil fiel. Gemessen an der echten Erhebung liefern 47 Bloecke
         * heute 1729 Attribute (36,8 je Block); der historische Regelverstoss
         * E-92 liess diese Zahl auf 937 fallen, ohne dass eine einzige Datei
         * fehlte. Die Schranke `$inspected * 22` liegt bei 1034 fuer 47
         * Bloecke — unterhalb des gesunden Werts, oberhalb des historischen
         * Ausfalls, mit Abstand zu beiden Seiten.
         */
        if ( $inspected > 0 && $attributes < $inspected * 22 ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: 1: number of blocks read, 2: number of attributes found. */
                    __( '%1$d blocks were inspected but only %2$d attributes were resolved. A field resolver is losing fields.', 'crea-bootstrap-blocks' ),
                    $inspected,
                    $attributes
                ),
            ];
        }

        /*
         * UND: HAT SIE ES AUCH IN JEDEM EINZELNEN BLOCK GESEHEN?
         *
         * Die Summe oben sieht einen Ausfall nicht, der auf EINEN Block
         * beschraenkt bleibt: Verliert nur `column` seine Abstandsgruppe (66
         * von 1729 Attributen), faellt die Summe von 1729 auf 1663 — weit
         * ueber der Schranke `$inspected * 22` (1034). Gemessen an allen 47
         * Bloecken liegt der groesste HEUTIGE, gesunde Fehlbetrag
         * (Vertragsattribute minus aufgeloeste Felder) bei 8 (`list-group`,
         * 17 minus 9 — bewusst unbelegte Attribute nach E-92, kein Defekt).
         * Ein Block, der seine Abstandsgruppe verliert, faellt dagegen auf
         * einen Fehlbetrag von 66 bis 72, je nach Blockgroesse. Die Schranke
         * `20` liegt weit oberhalb des groessten gesunden Werts und weit
         * unterhalb jedes gemessenen Ausfalls — mit Abstand zu beiden Seiten,
         * ohne einen einzigen echten Block faelschlich zu melden
         * (Editor-UI, 2026-09-07).
         *
         * EIN PROZENTSATZ WAERE HIER FALSCH: Legitime Verhaeltnisse streuen
         * ueber die 47 Bloecke von 33 % (`modal-body`, 1 von 3) bis 98 %
         * (`row`, 116 von 118) — ein `column` mit verlorener Abstandsgruppe
         * laege bei rund 44 % (55 von 126) und damit HOEHER als das gesunde
         * `modal-body`. Kein Prozentsatz trennt beide Faelle; der absolute
         * Fehlbetrag tut es.
         */
        if ( '' !== $worst_block && $worst_block_deficit > 20 ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: 1: block name, 2: number of missing attributes for that block alone. */
                    __( '%1$s alone is missing %2$d attributes compared to its own contract. A field resolver may have collapsed for this one block.', 'crea-bootstrap-blocks' ),
                    $worst_block,
                    $worst_block_deficit
                ),
            ];
        }

        if ( [] === $violations ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => sprintf(
                    /* translators: 1: number of block.json files read, 2: number of contract attributes compared. */
                    __( 'no locked field type points at a contract attribute — %1$d block.json, %2$d attributes compared', 'crea-bootstrap-blocks' ),
                    $inspected,
                    $attributes
                ),
            ];
        }

        $lines = [];

        foreach ( $violations as $violation ) {
            $reason = isset( $violation['reason'] ) && is_string( $violation['reason'] )
                ? $violation['reason']
                : 'locked';

            $lines[] = sprintf(
                '%s.%s = %s (%s)',
                isset( $violation['block'] ) && is_string( $violation['block'] ) ? $violation['block'] : '?',
                isset( $violation['attribute'] ) && is_string( $violation['attribute'] ) ? $violation['attribute'] : '?',
                isset( $violation['field_type'] ) && is_string( $violation['field_type'] ) ? $violation['field_type'] : '?',
                'numeric' === $reason
                    ? __( 'numeric field on a non-numeric contract attribute', 'crea-bootstrap-blocks' )
                    : __( 'locked field type', 'crea-bootstrap-blocks' )
            );
        }

        // Sortiert, damit zwei Laeufe dieselbe Meldung erzeugen und ein Diff
        // ueber die Ausgabe nur echte Aenderungen zeigt.
        sort( $lines );

        return [
            'check'   => $label,
            'status'  => 'error',
            'message' => sprintf(
                /* translators: %s: list of offending block.json fields. */
                __( 'These fields change the stored value form and break the data contract: %s. Fix the block.json before migrating.', 'crea-bootstrap-blocks' ),
                implode( ' | ', $lines )
            ),
        ];
    }

    /**
     * Check 4 — no `all-bootstrap-blocks/` directory in the active theme.
     *
     * Das Alt-Plugin laedt Renderdateien aus einem gleichnamigen Ordner im
     * Theme und laesst sich damit blockweise ueberschreiben. Nach der Migration
     * liefe ein solcher Override ins Leere: Der Block heisst dann `creabb/*`
     * und sucht seine Vorlage nicht mehr dort. Das Ergebnis ist stiller
     * Layoutbruch genau an den Stellen, die jemand bewusst angepasst hat.
     *
     * @param array<int, string> $found_directories Existing override directories.
     * @return array{check: string, status: string, message: string}
     */
    public static function theme_override_check( array $found_directories ): array {
        $label = __( 'Theme render overrides', 'crea-bootstrap-blocks' );

        $found = [];

        foreach ( $found_directories as $directory ) {
            if ( is_string( $directory ) && '' !== $directory ) {
                $found[] = $directory;
            }
        }

        if ( [] === $found ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'no all-bootstrap-blocks/ directory in the active theme', 'crea-bootstrap-blocks' ),
            ];
        }

        sort( $found );

        return [
            'check'   => $label,
            'status'  => 'error',
            'message' => sprintf(
                /* translators: %s: list of directory paths. */
                __( 'The theme overrides render files of the old plugin: %s. Those overrides stop taking effect once the blocks are named creabb/*. Port them first.', 'crea-bootstrap-blocks' ),
                implode( ', ', $found )
            ),
        ];
    }

    /**
     * The four render-relevant legacy options with their defaults.
     *
     * Von den Optionen des achtteiligen Alt-Backends beeinflussen genau vier
     * Familien die Ausgabe (`04-kompatibilitaets-vertrag.md`). Weil sie in allen
     * drei geprueften Projekten auf Default stehen, werden ihre Werte im Nachbau
     * HARTKODIERT — je mit einem `crea_bootstrap_blocks_*`-Filter als Ausweg.
     * Diese Pruefung ist die Absicherung dieser Entscheidung: Weicht eine
     * Installation ab, rendert der Nachbau anders als das Original, und zwar
     * ohne dass es jemandem auffiele.
     *
     * Alle Werte als String, weil WordPress Optionen als String zurueckgibt und
     * die beiden Schalter je nach Herkunft `''`, `'0'`, `false` oder `0` sein
     * koennen.
     *
     * @return array<string, string>
     */
    public static function legacy_option_defaults(): array {
        return [
            'areoi-dashboard-global-display-units'   => 'px',
            'areoi-layout-grid-grid-breakpoint-xs'   => '0',
            'areoi-layout-grid-grid-breakpoint-sm'   => '576',
            'areoi-layout-grid-grid-breakpoint-md'   => '768',
            'areoi-layout-grid-grid-breakpoint-lg'   => '992',
            'areoi-layout-grid-grid-breakpoint-xl'   => '1200',
            'areoi-layout-grid-grid-breakpoint-xxl'  => '1400',
            'areoi-customize-options-enable-cssgrid' => '',
            'areoi-customize-options-force-flex'     => '',
        ];
    }

    /**
     * Check 5 — the four render-relevant legacy options are on their defaults.
     *
     * @param array<string, mixed> $options Stored values, keyed by option name.
     *                                      A missing key or null means "not set".
     * @return array{check: string, status: string, message: string}
     */
    public static function legacy_options_check( array $options ): array {
        $label      = __( 'Legacy options', 'crea-bootstrap-blocks' );
        $deviations = [];
        $honoured   = [];

        foreach ( self::legacy_option_defaults() as $name => $expected ) {
            if ( ! array_key_exists( $name, $options ) ) {
                continue;
            }

            $value = $options[ $name ];

            // `false` ist der Rueckgabewert von get_option() fuer eine nicht
            // vorhandene Option — sie steht damit auf Default, nicht daneben.
            if ( null === $value || false === $value ) {
                continue;
            }

            if ( self::is_boolean_legacy_option( $name ) ) {
                if ( ! self::is_falsy( $value ) ) {
                    $zeile = sprintf(
                        '%s = %s (%s; %s)',
                        $name,
                        self::scalar_text( $value ),
                        __( 'default: off', 'crea-bootstrap-blocks' ),
                        self::legacy_option_remedy( $name )
                    );

                    if ( self::legacy_option_is_honoured( $name ) ) {
                        $honoured[] = $zeile;
                    } else {
                        $deviations[] = $zeile;
                    }
                }

                continue;
            }

            if ( self::comparable_value( $name, $value ) !== $expected ) {
                $deviations[] = sprintf(
                    '%s = %s (%s %s; %s)',
                    $name,
                    self::scalar_text( $value ),
                    __( 'default:', 'crea-bootstrap-blocks' ),
                    $expected,
                    self::legacy_option_remedy( $name )
                );
            }
        }

        if ( [] === $deviations && [] === $honoured ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'all four render-relevant legacy options are on their defaults', 'crea-bootstrap-blocks' ),
            ];
        }

        sort( $deviations );
        sort( $honoured );

        /*
         * EINE ABWEICHUNG, DIE DER NACHBAU UMSETZT, BLOCKIERT NICHT (E-146).
         *
         * Bis zum 2026-09-01 stand hier fuer JEDE Abweichung `error`, und das
         * Kommando hielt mit Exit 1. Fuer `…-enable-cssgrid` war das schaerfer
         * als noetig und zugleich gefaehrlich: Die einzige Abhilfe, die die
         * Zeile nannte, war der Filter — und mit gesetztem Filter steht
         * Grid-Markup auf BEIDEN Seiten des Abnahmevergleichs, die
         * Markuppruefung wird gruen, und nur die CSS-Haelfte weicht ab. Der
         * Bearbeiter waere der eigenen Empfehlung in einen Zustand gefolgt,
         * den kein Abnahmebein prueft.
         *
         * Seit der Nachbau die Option LIEST, ist ihr Wert kein Hindernis mehr,
         * sondern ein Umstand, den man kennen sollte: `warn`.
         */
        if ( [] === $deviations ) {
            return [
                'check'   => $label,
                'status'  => 'warn',
                'message' => sprintf(
                    /* translators: %s: list of legacy options the rebuild honours. */
                    __( 'These options deviate from the default, and the rebuild reads them: %s. Nothing to do — but expect the pages to render in that mode.', 'crea-bootstrap-blocks' ),
                    implode( ' | ', $honoured )
                ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'error',
            'message' => sprintf(
                /* translators: 1: list of deviating options, 2: options the rebuild honours, or a dash. */
                __( 'These options deviate from the default the rebuild hard-codes: %1$s. Adjust them before migrating; the way out is named per line. Read and honoured by the rebuild: %2$s.', 'crea-bootstrap-blocks' ),
                implode( ' | ', $deviations ),
                [] === $honoured ? '—' : implode( ' | ', $honoured )
            ),
        ];
    }

    /**
     * Whether the rebuild reads a legacy option instead of hard-coding it.
     *
     * @param string $name Option name.
     */
    private static function legacy_option_is_honoured( string $name ): bool {
        return 'areoi-customize-options-enable-cssgrid' === $name;
    }

    /**
     * Check 6 — Bootstrap Icons usage in the content.
     *
     * DIESE ZEILE IST STEUERINFORMATION, kein Mangel. Der Befund entscheidet,
     * ob der Icon-Schalter (Design → CreaBootstrapBlocks, Tab Assets) auf der
     * Zielseite gesetzt werden muss. Sie bricht nur unter EINER Bedingung ab:
     * Es gibt Treffer, und weder der Schalter noch das aktive Theme liefert die
     * Icon-Schrift. Dann fehlten nach der Migration sichtbare Symbole.
     *
     * @param int  $bi_hits              Occurrences of `bi-*` classes in the content.
     * @param int  $icon_attribute_hits  Button blocks carrying an icon attribute.
     * @param bool $icons_switch_on      Whether the plugin's icon switch is on.
     * @param bool $theme_provides_icons Whether the active theme ships the icon font.
     * @return array{check: string, status: string, message: string}
     */
    public static function icons_check(
        int $bi_hits,
        int $icon_attribute_hits,
        bool $icons_switch_on,
        bool $theme_provides_icons
    ): array {
        $label = __( 'Bootstrap Icons', 'crea-bootstrap-blocks' );

        if ( $bi_hits <= 0 && $icon_attribute_hits <= 0 ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'no bi- class and no icon attribute in the content', 'crea-bootstrap-blocks' ),
            ];
        }

        $summary = sprintf(
            /* translators: 1: number of bi- class occurrences, 2: number of button blocks with an icon attribute. */
            __( '%1$d bi- class occurrence(s), %2$d button block(s) with an icon attribute', 'crea-bootstrap-blocks' ),
            $bi_hits,
            $icon_attribute_hits
        );

        if ( $icons_switch_on || $theme_provides_icons ) {
            $sources = [];

            if ( $icons_switch_on ) {
                $sources[] = __( 'plugin switch', 'crea-bootstrap-blocks' );
            }

            if ( $theme_provides_icons ) {
                $sources[] = __( 'active theme', 'crea-bootstrap-blocks' );
            }

            return [
                'check'   => $label,
                'status'  => 'warn',
                'message' => sprintf(
                    /* translators: 1: finding summary, 2: comma separated list of icon sources. */
                    __( '%1$s — the icon font is delivered by: %2$s. Check the rendering of those blocks after the migration.', 'crea-bootstrap-blocks' ),
                    $summary,
                    implode( ', ', $sources )
                ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'error',
            'message' => sprintf(
                /* translators: %s: finding summary. */
                __( '%s — but neither the plugin switch (Appearance → CreaBootstrapBlocks, tab Assets) nor the active theme delivers the icon font. Turn the switch on or add the font to the theme before migrating.', 'crea-bootstrap-blocks' ),
                $summary
            ),
        ];
    }

    /**
     * The comparable form of a stored option value.
     *
     * DIE EINHEIT AM BREAKPOINT IST KEINE ABWEICHUNG. Die Einstellungsseite des
     * Alt-Plugins legt ihre Breakpoint-Defaults MIT Einheit ab — `576px`,
     * `768px`, `992px`, `1200px`, `1400px`; nur `xs` steht als `0` da. Sein
     * Renderer streift die Einheit vor der Verwendung wieder ab
     * (`str_replace( 'px', '', … )`), beide Schreibweisen erzeugen also
     * dieselbe Media-Query.
     *
     * Ohne diese Normalisierung meldete Pruefung 5 auf einer voellig
     * unveraenderten Installation fuenf Abweichungen und sperrte jede
     * Migration. Abgestreift wird ausschliesslich bei den Breakpoints: Bei
     * der Abstandseinheit ist `px` der Wert selbst.
     *
     * @param string $name  Option name.
     * @param mixed  $value Stored value.
     */
    private static function comparable_value( string $name, mixed $value ): string {
        $text = self::scalar_text( $value );

        if ( ! str_starts_with( $name, 'areoi-layout-grid-grid-breakpoint-' ) ) {
            return $text;
        }

        $trimmed = trim( $text );

        return str_ends_with( $trimmed, 'px' )
            ? rtrim( substr( $trimmed, 0, -2 ) )
            : $trimmed;
    }

    /**
     * The way out for one deviating legacy option.
     *
     * DER FILTER WIRD BEIM NAMEN GENANNT — oder es wird gesagt, dass es keinen
     * gibt. Eine pauschale Zusage „setzen Sie den passenden
     * crea_bootstrap_blocks_*-Filter" schickte den Bearbeiter bei
     * `force-flex` ins Leere: Diese Option verschiebt im Original den DEFAULT
     * des Attributs `is_flex`, und dafuer gibt es im Nachbau bewusst keinen
     * Ersatz. Jeder Block, dessen `is_flex` nie ausdruecklich gespeichert
     * wurde, rendert nach der Migration ohne Flex — das ist der Grund, warum
     * diese Zeile abbricht.
     *
     * @param string $name Option name.
     */
    private static function legacy_option_remedy( string $name ): string {
        $filter = '';

        if ( 'areoi-dashboard-global-display-units' === $name ) {
            $filter = 'crea_bootstrap_blocks_spacing_unit';
        } elseif ( str_starts_with( $name, 'areoi-layout-grid-grid-breakpoint-' ) ) {
            $filter = 'crea_bootstrap_blocks_breakpoints';
        } elseif ( 'areoi-customize-options-enable-cssgrid' === $name ) {
            // Seit E-146 liest der Nachbau die Option selbst. Der Filter bleibt
            // als Uebersteuerung, ist aber keine ABHILFE mehr — es gibt nichts
            // abzuhelfen.
            return __( 'read by the rebuild; override with the filter crea_bootstrap_blocks_css_grid', 'crea-bootstrap-blocks' );
        }

        if ( '' !== $filter ) {
            return sprintf(
                /* translators: %s: filter hook name. */
                __( 'filter: %s', 'crea-bootstrap-blocks' ),
                $filter
            );
        }

        return __( 'no filter — the rebuild has no equivalent; this option shifts the default of the is_flex attribute, so set is_flex explicitly on the affected blocks or accept the change', 'crea-bootstrap-blocks' );
    }

    /**
     * Whether a legacy option is one of the two boolean switches.
     *
     * @param string $name Option name.
     */
    private static function is_boolean_legacy_option( string $name ): bool {
        return 'areoi-customize-options-enable-cssgrid' === $name
            || 'areoi-customize-options-force-flex' === $name;
    }

    /**
     * Whether a stored option value counts as "off".
     *
     * WordPress liefert je nach Schreibweg `''`, `'0'`, `0`, `false` oder
     * `null`; alle fuenf bedeuten dasselbe.
     *
     * @param mixed $value Stored value.
     */
    private static function is_falsy( mixed $value ): bool {
        if ( is_string( $value ) ) {
            return '' === $value || '0' === $value || 'false' === strtolower( $value );
        }

        return empty( $value );
    }

    /**
     * A comparable text form of a stored option value.
     *
     * @param mixed $value Stored value.
     */
    private static function scalar_text( mixed $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? '1' : '';
        }

        if ( is_scalar( $value ) ) {
            return (string) $value;
        }

        /*
         * `WordPress.WP.AlternativeFunctions` schlaegt hier an und schlaegt
         * `wp_json_encode()` vor. Das geht am Fall vorbei: Diese Methode ist
         * rein und muss OHNE WordPress laufen — die Suite
         * `tests/test-cli-doctor-checks.php` faehrt sie ohne Bootstrap, und
         * `wp_json_encode()` gaebe es dort nicht. Kodiert wird ausserdem kein
         * Ausgabetext, sondern die Textform eines unerwartet zusammengesetzten
         * Optionswerts fuer die Diagnosezeile.
         */
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Begruendung im Block darueber.
        return (string) json_encode( $value );
    }

    /**
     * Check 7 — no `areoi` selector in the active theme, or the legacy classes are on.
     *
     * Themes stylen direkt gegen `.areoi-element`, `.areoi-element.container`
     * und `.areoi-has-url`; eines der Bestandsthemes sogar mit ausdruecklicher
     * Specificity-Begruendung im SCSS. Solange das so ist, muessen die Bloecke
     * beide Klassensaetze ausgeben. Faellt der Schalter zu frueh, verliert die
     * Seite ihr Layout — und zwar vollstaendig, nicht in Nuancen.
     *
     * @param bool $theme_has_areoi_selector Whether the active theme styles against `.areoi*`.
     * @param bool $legacy_classes_enabled   Whether the legacy class output is on.
     * @return array{check: string, status: string, message: string}
     */
    public static function legacy_class_check( bool $theme_has_areoi_selector, bool $legacy_classes_enabled ): array {
        $label = __( 'Legacy CSS classes', 'crea-bootstrap-blocks' );

        if ( ! $theme_has_areoi_selector ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'the active theme carries no areoi selector', 'crea-bootstrap-blocks' ),
            ];
        }

        if ( $legacy_classes_enabled ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'the active theme styles against areoi-* and the legacy classes are being emitted', 'crea-bootstrap-blocks' ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'error',
            'message' => __( 'The active theme styles against areoi-* selectors, but the legacy classes are switched off. Turn CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES back on (or the matching option) and only switch it off once the theme has been ported to creabb-*.', 'crea-bootstrap-blocks' ),
        ];
    }

    /**
     * The directory a backup argument addresses.
     *
     * `--backup=/pfad/dump.sql.gz` meint das Verzeichnis `/pfad`,
     * `--backup=/pfad/` meint `/pfad` selbst. Geprueft werden die Schreibrechte
     * am Verzeichnis, nicht an der noch nicht existierenden Datei.
     *
     * @param string $path Value of the `--backup` argument.
     */
    public static function backup_directory( string $path ): string {
        $trimmed = rtrim( $path, '/' );

        if ( '' === $trimmed ) {
            return '/';
        }

        if ( str_ends_with( $path, '/' ) ) {
            return $trimmed;
        }

        return dirname( $path );
    }

    /**
     * Resolves `.` and `..` in a path WITHOUT touching the file system.
     *
     * `realpath()` waere hier falsch: Das Backup-Verzeichnis muss nicht
     * existieren, wenn `doctor` laeuft, und `realpath()` gibt fuer einen
     * nicht existierenden Pfad `false` zurueck. Ein Cast daraus waere der
     * Leerstring — und ein Leerstring liegt unter JEDER Wurzel.
     *
     * @param string $path Absolute or relative path.
     */
    public static function normalize_path( string $path ): string {
        $absolute = str_starts_with( $path, '/' );
        $out      = [];

        foreach ( explode( '/', str_replace( '\\', '/', $path ) ) as $segment ) {
            if ( '' === $segment || '.' === $segment ) {
                continue;
            }

            if ( '..' === $segment ) {
                if ( [] !== $out && '..' !== end( $out ) ) {
                    array_pop( $out );

                    continue;
                }

                if ( $absolute ) {
                    continue;
                }
            }

            $out[] = $segment;
        }

        return ( $absolute ? '/' : '' ) . implode( '/', $out );
    }

    /**
     * Whether a directory lies at or below one of the publicly served roots.
     *
     * WARUM DAS GEPRUEFT WIRD: `default_backup_path()` legt den Dump nach
     * `wp_upload_dir()['basedir']`, und der Rueckfall zeigt sogar auf
     * `ABSPATH`. Beide werden vom Webserver ausgeliefert. Am 2026-09-10 auf
     * einer echten Instanz nachgemessen: Eine Datei
     * `wp-content/uploads/probe.sql.gz` antwortete anonym mit HTTP 200 und
     * gab ihren Inhalt heraus. Ein Migrationsbackup ist ein VOLLSTAENDIGER
     * Datenbankabzug — Benutzer, Passworthashes, Bestellungen. Sein Name ist
     * dazu vorhersagbar, weil die Anleitung `backup-JJJJ-MM-TT.sql.gz`
     * vorschlaegt.
     *
     * Der Vergleich ist LEXIKALISCH und beruehrt das Dateisystem nicht
     * (siehe `normalize_path()`). Ein Symlink, der aus dem Baum hinausfuehrt,
     * wird deshalb nicht erkannt — die Prueflinie faellt damit auf die
     * sichere Seite: Sie meldet eher zu viel als zu wenig.
     *
     * @param string             $directory    Directory that would receive the dump.
     * @param array<int, string> $public_roots Directories the web server serves.
     */
    public static function path_inside_public_root( string $directory, array $public_roots ): bool {
        $needle = self::normalize_path( $directory );

        if ( '' === $needle ) {
            return false;
        }

        foreach ( $public_roots as $root ) {
            $haystack = self::normalize_path( (string) $root );

            if ( '' === $haystack ) {
                continue;
            }

            if ( $needle === $haystack || str_starts_with( $needle . '/', $haystack . '/' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check 8 — the backup target is writable, and not served to the public.
     *
     * KEIN LAUF OHNE RUECKWEG. Diese Zeile steht am Ende der Liste, weil sie
     * die einzige ist, die nichts ueber die Installation aussagt — sie sichert
     * nur zu, dass `migrate run` sein Backup ueberhaupt ablegen kann. Ohne sie
     * faellt der fehlende Schreibzugriff erst auf, wenn die Ersetzung bereits
     * laeuft.
     *
     * WARUM ES NICHT BEIM SCHREIBRECHT BLEIBT: Ein beschreibbares Verzeichnis
     * ist nicht schon ein geeignetes. Bis zum 2026-09-10 meldete diese Zeile
     * `ok — beschreibbar: …/wp-content/uploads` und empfahl damit ein
     * Verzeichnis, aus dem jeder Fremde den Dump herunterladen kann.
     *
     * WARUM `warn` UND NICHT `error`: Auf geteiltem Webspace gibt es haeufig
     * gar kein beschreibbares Verzeichnis ausserhalb des Webbaums. Ein `error`
     * hielte den Lauf dort auf und draengte zu `--skip-backup` — also dazu,
     * den Rueckweg ganz wegzulassen. Ein Backup an schlechter Stelle ist
     * besser als keines; es muss nur hinterher weg.
     *
     * @param string $directory            Directory that would receive the dump.
     * @param bool   $directory_exists     Result of `is_dir()`.
     * @param bool   $directory_writable   Result of `is_writable()`.
     * @param bool   $publicly_reachable   Whether the web server serves that directory.
     * @return array{check: string, status: string, message: string}
     */
    public static function backup_target_check( string $directory, bool $directory_exists, bool $directory_writable, bool $publicly_reachable ): array {
        $label = __( 'Backup target', 'crea-bootstrap-blocks' );

        if ( ! $directory_exists ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: %s: directory path. */
                    __( 'The backup directory %s does not exist. Create it — there is no migration run without a way back.', 'crea-bootstrap-blocks' ),
                    $directory
                ),
            ];
        }

        if ( ! $directory_writable ) {
            return [
                'check'   => $label,
                'status'  => 'error',
                'message' => sprintf(
                    /* translators: %s: directory path. */
                    __( 'The backup directory %s is not writable. Fix the permissions — there is no migration run without a way back.', 'crea-bootstrap-blocks' ),
                    $directory
                ),
            ];
        }

        if ( $publicly_reachable ) {
            return [
                'check'   => $label,
                'status'  => 'warn',
                'message' => sprintf(
                    /* translators: %s: directory path. */
                    __( 'writable, but the web server serves %s — a database dump placed there can be downloaded by anyone who guesses its name. Choose a directory outside the web root, or delete the dump the moment the migration is accepted.', 'crea-bootstrap-blocks' ),
                    $directory
                ),
            ];
        }

        return [
            'check'   => $label,
            'status'  => 'ok',
            'message' => sprintf(
                /* translators: %s: directory path. */
                __( 'writable, outside the web root: %s', 'crea-bootstrap-blocks' ),
                $directory
            ),
        ];
    }

    /**
     * The directories this installation serves over HTTP.
     *
     * Beide Konstanten, nicht nur `ABSPATH`: `wp-content` darf ausserhalb des
     * WordPress-Verzeichnisses liegen (`WP_CONTENT_DIR`), und genau dort steht
     * das Upload-Verzeichnis, in das der Vorgabepfad zeigt. Wer nur `ABSPATH`
     * prueft, uebersieht die verschobene Installation — also gerade die, bei
     * der jemand ueber den Aufbau nachgedacht hat.
     *
     * @return array<int, string>
     */
    public static function public_roots(): array {
        return self::public_roots_from(
            defined( 'ABSPATH' ) ? (string) constant( 'ABSPATH' ) : null,
            defined( 'WP_CONTENT_DIR' ) ? (string) constant( 'WP_CONTENT_DIR' ) : null
        );
    }

    /**
     * The pure half of `public_roots()`.
     *
     * E-135: Eine Entscheidung, die nur Konstanten liest, fuehrt keine Suite
     * aus — jede Mutation daran ueberlebt `composer test`. Genau das ist am
     * 2026-09-10 passiert: Die Mutation, die `WP_CONTENT_DIR` aus der Liste
     * strich, blieb am Leben, weil die Konstante im Pruefstand gar nicht
     * definiert ist. Die Entscheidung steht deshalb hier, die Erhebung
     * darueber.
     *
     * @param string|null $abspath     Value of `ABSPATH`, or null when undefined.
     * @param string|null $content_dir Value of `WP_CONTENT_DIR`, or null when undefined.
     * @return array<int, string>
     */
    public static function public_roots_from( ?string $abspath, ?string $content_dir ): array {
        $roots = [];

        if ( null !== $abspath && '' !== $abspath ) {
            $roots[] = $abspath;
        }

        if ( null !== $content_dir && '' !== $content_dir ) {
            $roots[] = $content_dir;
        }

        return $roots;
    }

    /**
     * The `areoi/*` block names a content string contains.
     *
     * Nur BLOCKBEGRENZER zaehlen — oeffnende, selbstschliessende und
     * schliessende. Der Blockname im Fliesstext ist kein Fund; sonst meldete
     * ein Blogbeitrag ueber die Migration selbst einen Treffer.
     *
     * @param string $content Post content or option value.
     * @return array<int, string> Sorted, without duplicates.
     */
    public static function areoi_block_names( string $content ): array {
        $matches = [];

        if ( ! preg_match_all( '#<!--\s*/?wp:(areoi/[a-z0-9][a-z0-9-]*)#i', $content, $matches ) ) {
            return [];
        }

        $names = [];

        foreach ( $matches[1] as $name ) {
            $names[ strtolower( $name ) ] = true;
        }

        $names = array_keys( $names );
        sort( $names );

        return $names;
    }

    /**
     * Occurrences of Bootstrap Icon classes in a content string.
     *
     * Der Lookbehind verhindert Treffer mitten im Wort („Kombi-Nation"); das
     * blosse `bi` ohne Symbolnamen ist die Basisklasse und wird nicht gezaehlt.
     *
     * @param string $content Post content or option value.
     */
    public static function bi_class_hits( string $content ): int {
        return (int) preg_match_all( '/(?<![\w-])bi-[a-z0-9]+(?:-[a-z0-9]+)*/i', $content );
    }

    /**
     * Button blocks in a content string that carry an icon attribute.
     *
     * Beide Speicherformen zaehlen: der flache Bestandsblock und der bereits
     * migrierte mit `blockstudio.attributes`-Container. Die Attributnamen
     * stammen aus der `block.json` des Originals: `include_icon` schaltet die
     * Ausgabe, `icon` traegt den Symbolnamen.
     *
     * GRENZE: Die Klammernbilanz im Muster kommt an ihre Grenze, wenn ein
     * Attributwert selbst eine geschweifte Klammer enthaelt. Bei Icon- und
     * Klassenwerten kommt das nicht vor; die Zeile ist ohnehin ein Bericht und
     * keine Abbruchbedingung fuer sich.
     *
     * @param string $content Post content or option value.
     */
    public static function button_icon_attribute_hits( string $content ): int {
        $matches = [];

        if ( ! preg_match_all(
            '#<!--\s+wp:(?:areoi|creabb)/button\s+(\{(?:[^{}]++|(?1))*\})\s*/?-->#',
            $content,
            $matches
        ) ) {
            return 0;
        }

        $hits = 0;

        foreach ( $matches[1] as $json ) {
            $data = json_decode( $json, true );

            if ( ! is_array( $data ) ) {
                continue;
            }

            $attributes = $data;

            if ( isset( $data['blockstudio']['attributes'] ) && is_array( $data['blockstudio']['attributes'] ) ) {
                $attributes = $data['blockstudio']['attributes'];
            }

            $include = $attributes['include_icon'] ?? null;
            $icon    = $attributes['icon'] ?? null;

            if ( ! empty( $include ) || ( is_string( $icon ) && '' !== $icon ) ) {
                ++$hits;
            }
        }

        return $hits;
    }

    /**
     * Whether a stylesheet styles against an `areoi` selector.
     *
     * Gesucht wird der KLASSENSELEKTOR, nicht das Wort: Ein Kommentar, der das
     * Alt-Plugin erwaehnt, ist kein Grund, die Legacy-Klassen festzuhalten.
     *
     * @param string $css Contents of a stylesheet or theme file.
     */
    public static function css_has_areoi_selector( string $css ): bool {
        return 1 === preg_match( '/(?:^|[\s,{>+~(])\.areoi[a-z0-9_-]*/im', $css );
    }

    /**
     * Whether any of the given file contents ships the Bootstrap Icons font.
     *
     * @param array<int, string> $haystacks Theme file contents.
     */
    public static function haystack_provides_icons( array $haystacks ): bool {
        foreach ( $haystacks as $text ) {
            if ( is_string( $text ) && false !== stripos( $text, 'bootstrap-icons' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check 4b — theme block templates that still carry `areoi/*`.
     *
     * `05-migration.md` fuehrt `templates/*.html` und `parts/*.html` des Themes
     * als eigene Fundstelle mit der Behandlung „manuell im Theme-Repo aendern".
     * Diese Dateien liegen im Dateisystem, nicht in der Datenbank: `migrate
     * run` erreicht sie nicht, `migrate verify` sieht sie nicht, und der
     * Alias-Fallback laesst sie stillschweigend weiterrendern, bis er faellt.
     *
     * NIE `error`. Ein Abbruch waere hier falsch — die Datenbankmigration ist
     * von diesen Dateien nicht betroffen, und wer sie zuerst im Theme-Repo
     * aendern muesste, kaeme sonst nie zum Lauf. Die Zeile ist ein Merkposten
     * fuer Schritt 8 der Reihenfolge pro Seite.
     *
     * @param array<int, string> $found_files Theme template files containing `areoi/`.
     * @return array{check: string, status: string, message: string}
     */
    public static function theme_template_check( array $found_files ): array {
        $label = __( 'Theme block templates', 'crea-bootstrap-blocks' );

        $found = [];

        foreach ( $found_files as $file ) {
            if ( is_string( $file ) && '' !== $file ) {
                $found[] = $file;
            }
        }

        if ( [] === $found ) {
            return [
                'check'   => $label,
                'status'  => 'ok',
                'message' => __( 'no areoi/ block in templates/*.html or parts/*.html of the active theme', 'crea-bootstrap-blocks' ),
            ];
        }

        sort( $found );

        return [
            'check'   => $label,
            'status'  => 'warn',
            'message' => sprintf(
                /* translators: %s: comma separated list of file paths. */
                __( 'These theme files carry areoi/ blocks and are NOT in the database — no migration run touches them: %s. Rename the blocks in the theme repository and roll the theme out.', 'crea-bootstrap-blocks' ),
                implode( ', ', $found )
            ),
        ];
    }

    /**
     * Every content string that can carry `areoi/*` block markup.
     *
     * Die Abfrage grenzt ueber ein LIKE auf den Blockbegrenzer ein, damit nicht
     * die gesamte `wp_posts` durch den Speicher wandert. Revisionen sind
     * bewusst eingeschlossen — sie sind eine Fundstelle wie jede andere
     * (`05-migration.md`).
     *
     * DIE MENGE MUSS DIE DER MIGRATION SEIN. `migrate run` migriert neben
     * `wp_posts` und `widget_block` auch `wp_postmeta`
     * (`05-migration.md`, Fundstellentabelle). Fehlte Postmeta hier, koennte
     * ein Blocktyp, der ausschliesslich in einer Meta-Zeile liegt, durch
     * Pruefung 2 rutschen — doctor waere gruen, und die Migration braeche an
     * genau diesem Blocktyp ab.
     *
     * @return array<int, string>
     */
    private static function block_content_haystack(): array {
        return array_values(
            array_merge(
                self::posts_like( 'wp:areoi/' ),
                self::postmeta_like( 'wp:areoi/' ),
                self::widget_block_contents()
            )
        );
    }

    /**
     * Every content string that can carry an icon reference.
     *
     * JEDE ZEILE HOECHSTENS EINMAL. Die drei Abfragen ueberlappen sich: Ein
     * Beitrag mit einem `areoi/button` UND einer `bi-`-Klasse kommt aus zwei
     * von ihnen zurueck. Ohne die Zusammenfuehrung ueber den Zeilenschluessel
     * zaehlte Pruefung 6 seine Symbole doppelt und meldete eine Zahl, die es
     * auf der Seite nicht gibt. Am Urteil (`ok`/`warn`/`error`) aendert das
     * nichts — an der Meldung schon, und die liest ein Mensch.
     *
     * @return array<int, string>
     */
    private static function icon_content_haystack(): array {
        return array_values(
            array_merge(
                self::posts_like( 'bi-' ),
                self::posts_like( 'wp:areoi/button' ),
                self::posts_like( 'wp:creabb/button' ),
                self::widget_block_contents()
            )
        );
    }

    /**
     * Post contents matching a LIKE needle, keyed by row.
     *
     * DER SCHLUESSEL IST DIE ZEILE, nicht die laufende Nummer. Die Aufrufer
     * fuehren mehrere Abfragen zusammen, die sich ueberlappen; nur ueber einen
     * stabilen Zeilenschluessel faellt derselbe Beitrag dabei genau einmal an.
     * Ein Inhaltsvergleich taugte dafuer nicht — Revisionen tragen denselben
     * Inhalt wie ihr Elternbeitrag und sind trotzdem eigene Fundstellen.
     *
     * @param string $needle Literal substring to search for.
     * @return array<string, string>
     */
    private static function posts_like( string $needle ): array {
        global $wpdb;

        /*
         * `instanceof \wpdb` statt `is_object()`: Die Wache ist damit nicht nur
         * strenger — sie erhaelt auch den Typ. Nach `is_object()` weiss die
         * statische Analyse nur noch `object` und haelt jeden folgenden Aufruf
         * von `prepare()`, `esc_like()` und `get_col()` fuer undefiniert.
         */
        if ( ! $wpdb instanceof \wpdb ) {
            return [];
        }

        $like = '%' . $wpdb->esc_like( $needle ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ID, post_content FROM %i WHERE post_content LIKE %s',
                $wpdb->posts,
                $like
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $contents = [];

        foreach ( $rows as $row ) {
            if ( is_array( $row ) && isset( $row['ID'], $row['post_content'] ) ) {
                $contents[ 'post:' . (string) $row['ID'] ] = (string) $row['post_content'];
            }
        }

        return $contents;
    }

    /**
     * Meta values matching a LIKE needle.
     *
     * Aufbau wie `posts_like()`: `prepare()` um die Abfrage, `esc_like()` um
     * das Muster, und der Tabellenname ueber den Identifier-Platzhalter `%i`
     * statt interpoliert — nur so bleibt das erste Argument von `prepare()` ein
     * literaler String, wie ihn die statische Analyse verlangt. Drittplugins legen Blockmarkup in `wp_postmeta` ab; die
     * Migration fasst diese Zeilen an, also muessen sie auch in der Pruefung
     * vorkommen.
     *
     * @param string $needle Literal substring to search for.
     * @return array<string, string>
     */
    private static function postmeta_like( string $needle ): array {
        global $wpdb;

        /*
         * `instanceof \wpdb` statt `is_object()`: Die Wache ist damit nicht nur
         * strenger — sie erhaelt auch den Typ. Nach `is_object()` weiss die
         * statische Analyse nur noch `object` und haelt jeden folgenden Aufruf
         * von `prepare()`, `esc_like()` und `get_col()` fuer undefiniert.
         */
        if ( ! $wpdb instanceof \wpdb ) {
            return [];
        }

        $like = '%' . $wpdb->esc_like( $needle ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT meta_id, meta_value FROM %i WHERE meta_value LIKE %s',
                $wpdb->postmeta,
                $like
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $values = [];

        foreach ( $rows as $row ) {
            if ( is_array( $row ) && isset( $row['meta_id'], $row['meta_value'] ) ) {
                $values[ 'meta:' . (string) $row['meta_id'] ] = (string) $row['meta_value'];
            }
        }

        return $values;
    }

    /**
     * Block markup stored in the `widget_block` option.
     *
     * Die Option ist ein SERIALISIERTES Array. Sie wird ueber die
     * WordPress-API gelesen, nie ueber ein rohes SQL — und hier ohnehin nur
     * gelesen.
     *
     * @return array<string, string>
     */
    private static function widget_block_contents(): array {
        $option = \get_option( 'widget_block', [] );

        if ( ! is_array( $option ) ) {
            return [];
        }

        $contents = [];

        foreach ( $option as $key => $widget ) {
            if ( is_array( $widget ) && isset( $widget['content'] ) && is_string( $widget['content'] ) ) {
                $contents[ 'widget:' . (string) $key ] = $widget['content'];
            }
        }

        return $contents;
    }

    /**
     * Names of every registered block type.
     *
     * @return array<int, string>
     */
    private static function registered_block_names(): array {
        if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
            return [];
        }

        return array_map( 'strval', array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() ) );
    }

    /**
     * Whether the old plugin is active on this installation.
     *
     * ZWEI WEGE, WEIL EINER NICHT REICHT. `is_plugin_active()` fragt gegen den
     * ORDNERNAMEN — ein abweichend benannter Ordner rutscht durch. Die
     * Konstante `AREOI__VERSION` setzt das Alt-Plugin dagegen beim Laden,
     * unabhaengig davon, wie sein Verzeichnis heisst.
     */
    private static function legacy_plugin_active(): bool {
        if ( defined( 'AREOI__VERSION' ) ) {
            return true;
        }

        if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
            require_once constant( 'ABSPATH' ) . 'wp-admin/includes/plugin.php';
        }

        return function_exists( 'is_plugin_active' )
            && is_plugin_active( 'all-bootstrap-blocks/areoi.php' );
    }

    /**
     * Rule 1.4 across all of our own `block.json` files — findings AND counts.
     *
     * WARUM DIESE FUNKTION ZAEHLT, STATT NUR ZU FINDEN. Sie lieferte frueher
     * nur die Verstoesse. Pruefung 3 meldete daraufhin fuer 47 gelesene Dateien
     * und fuer NULL gelesene dieselbe gruene Zeile — nachgemessen byteglich.
     * Sieben stumme Nullpfade fuehren dorthin; der gefaehrlichste ist eine
     * fehlende Vertragsdatei, denn dann bleibt ein echter `select`-Verstoss auf
     * einem Vertragsattribut gruen, obwohl Blockstudio den Block samt Feld
     * registriert. Der Schaden ist irreversibel: Ein `select` an `col_xs`
     * speichert `{"value":"col-12","label":"12"}` statt `"col-12"`, und das
     * faellt erst auf, wenn jemand den Block einmal angefasst hat.
     *
     * Gezaehlt wird HINTER den Wachen — eine Datei, die uebersprungen wurde,
     * gilt nicht als geprueft.
     *
     * WARUM SIE `public` IST UND EINEN WURZELPFAD NIMMT: Eine Entscheidung, die
     * nur im Kommando steht, fuehrt keine Suite aus (E-135). Vorher ueberlebte
     * jede Mutation an dieser Stelle `composer test`.
     *
     * WARUM `blocks-legacy/` NICHT MITGEPRUEFT WIRD: Die 47 Aliasdateien
     * erzeugt `bin/build-aliases.php` aus den kanonischen; sie tragen dieselben
     * Felder, und `tests/test-generated-artifacts.php` haelt den Generator
     * gegen sein eingechecktes Ergebnis. Eine zweite Pruefung derselben Daten
     * verdoppelte die Laufzeit und die Meldungen, ohne einen Fall zu decken.
     *
     * WARUM ZUSAETZLICH `worst_block` UND `worst_block_deficit`: `attributes`
     * ist eine SUMME ueber alle Bloecke und sieht einen Ausfall nicht, der auf
     * EINEN Block beschraenkt bleibt — ein Block, der seine Abstandsgruppe
     * verliert (66 von 1729 Attributen), verschwindet in der Summe fast
     * spurlos. Je Block wird deshalb zusaetzlich `Contract::table()` (dieselbe
     * Tabelle, die `field_type_violations()` ohnehin schon laedt) gegen die
     * tatsaechlich aufgeloesten Felder gehalten; gefuehrt wird der groesste
     * Fehlbetrag (Vertragsattribute minus aufgeloeste Felder) samt Blockname.
     *
     * @param string|null $root Plugin root; defaults to the plugin directory.
     * @return array{inspected: int, attributes: int, expected: int, violations: array<int, array<string, mixed>>, worst_block: string, worst_block_deficit: int}
     */
    public static function field_type_survey( ?string $root = null ): array {
        $empty = [
            'inspected'           => 0,
            'attributes'          => 0,
            'expected'            => 0,
            'violations'          => [],
            'worst_block'         => '',
            'worst_block_deficit' => 0,
        ];

        if ( null === $root ) {
            if ( ! defined( 'CREA_BOOTSTRAP_BLOCKS_DIR' ) ) {
                return $empty;
            }

            $root = (string) constant( 'CREA_BOOTSTRAP_BLOCKS_DIR' );
        }

        $root = rtrim( $root, '/' );

        if ( ! class_exists( Contract::class ) ) {
            return $empty;
        }

        // Der Erwartungswert kommt aus dem Vertragsverzeichnis, nicht aus einer
        // fest eingetragenen 47: Mit dem naechsten Block waere die Zahl falsch,
        // und eine falsche Erwartung ist schlimmer als keine.
        $contracts = glob( $root . '/blocks/_contracts/*.json' );
        $expected  = is_array( $contracts ) ? count( $contracts ) : 0;

        $files = glob( $root . '/blocks/*/block.json' );

        if ( ! is_array( $files ) ) {
            $empty['expected'] = $expected;

            return $empty;
        }

        $violations          = [];
        $inspected           = 0;
        $attributes          = 0;
        $worst_block         = '';
        $worst_block_deficit = 0;

        foreach ( $files as $file ) {
            if ( ! is_readable( $file ) ) {
                continue;
            }

            /*
             * `WordPress.WP.AlternativeFunctions` schlaegt hier an und schlaegt
             * `wp_remote_get()` vor. Das geht am Fall vorbei: Gelesen wird eine
             * `block.json`, die MIT DEM PLUGIN AUSGELIEFERT wird, kein
             * entfernter URL. Die Wache davor (`is_readable()`) und der
             * Plausibilitaetstest danach (`is_array()`) bleiben bestehen.
             */
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Begruendung im Block darueber.
            $data = json_decode( (string) file_get_contents( $file ), true );

            if ( ! is_array( $data ) ) {
                continue;
            }

            $name = isset( $data['name'] ) && is_string( $data['name'] ) ? $data['name'] : '';

            $fields = isset( $data['blockstudio']['attributes'] ) && is_array( $data['blockstudio']['attributes'] )
                ? $data['blockstudio']['attributes']
                : [];

            if ( '' === $name || [] === $fields ) {
                continue;
            }

            ++$inspected;

            $resolved    = count( Contract::flatten_fields( $fields, '', '{id}', $root ) );
            $attributes += $resolved;

            $contract_count = count( Contract::table( $name ) );

            if ( $contract_count > 0 ) {
                $deficit = max( 0, $contract_count - $resolved );

                if ( $deficit > $worst_block_deficit ) {
                    $worst_block_deficit = $deficit;
                    $worst_block         = $name;
                }
            }

            foreach ( Contract::field_type_violations( $name, $fields ) as $violation ) {
                $violations[] = $violation;
            }
        }

        return [
            'inspected'           => $inspected,
            'attributes'          => $attributes,
            'expected'            => $expected,
            'violations'          => $violations,
            'worst_block'         => $worst_block,
            'worst_block_deficit' => $worst_block_deficit,
        ];
    }

    /**
     * Stored values of the four render-relevant legacy options.
     *
     * Optionen, die es nicht gibt, kommen als `null` zurueck und werden gar
     * nicht erst uebergeben — „nicht gesetzt" heisst „auf Default".
     *
     * @return array<string, mixed>
     */
    private static function legacy_option_values(): array {
        $values = [];

        foreach ( array_keys( self::legacy_option_defaults() ) as $name ) {
            $stored = \get_option( $name, null );

            if ( null !== $stored ) {
                $values[ $name ] = $stored;
            }
        }

        return $values;
    }

    /**
     * Directories of the active theme and its parent.
     *
     * @return array<int, string>
     */
    private static function theme_directories(): array {
        if ( ! function_exists( 'wp_get_theme' ) ) {
            return [];
        }

        $theme  = \wp_get_theme();
        $dirs   = [ (string) $theme->get_stylesheet_directory() ];
        $parent = $theme->parent();

        if ( $parent instanceof \WP_Theme ) {
            $dirs[] = (string) $parent->get_stylesheet_directory();
        }

        return array_values( array_unique( array_filter( $dirs ) ) );
    }

    /**
     * Existing `all-bootstrap-blocks/` override directories.
     *
     * NUR DAS AKTIVE THEME, NICHT DAS ELTERNTHEMA. Gemessen am Original:
     * `blocks/index.php` sucht seine Render-Overrides mit
     * `file_exists( get_stylesheet_directory() . '/all-bootstrap-blocks/' . $block_folder . '.php' )`
     * und sonst nirgends — das Elternthema wird nie befragt. Ein gleichnamiger
     * Ordner dort hat also noch nie gewirkt und kann durch die Migration auch
     * nichts verlieren. Ihn zu melden hiesse, einen Lauf wegen einer Datei
     * abzubrechen, die niemand liest.
     *
     * (Die Lightspeed-Integration des Originals befragt zusaetzlich das
     * Elternthema. Sie wird ausdruecklich nicht nachgebaut.)
     *
     * @return array<int, string>
     */
    private static function theme_override_directories(): array {
        if ( ! function_exists( 'get_stylesheet_directory' ) ) {
            return [];
        }

        $candidate = rtrim( (string) \get_stylesheet_directory(), '/' ) . '/all-bootstrap-blocks';

        return is_dir( $candidate ) ? [ $candidate ] : [];
    }

    /**
     * Theme files that can carry an `areoi` selector or an icon reference.
     *
     * Bewusst BEGRENZT: `style.css`, `functions.php`, die CSS-Dateien im
     * Themewurzelverzeichnis und die unter `assets/css/`. Ein rekursiver Lauf
     * ueber ein ganzes Theme liefe in Bilder, Schriften und `node_modules` —
     * und dieser Befund ist ein Hinweis, keine Beweisfuehrung.
     *
     * @return array<int, string>
     */
    private static function theme_haystack(): array {
        $contents = [];

        foreach ( self::theme_directories() as $directory ) {
            $files = [ $directory . '/style.css', $directory . '/functions.php' ];

            foreach ( [ $directory . '/*.css', $directory . '/assets/css/*.css' ] as $pattern ) {
                $matched = glob( $pattern );

                if ( is_array( $matched ) ) {
                    $files = array_merge( $files, $matched );
                }
            }

            foreach ( array_unique( $files ) as $file ) {
                if ( ! is_readable( $file ) || filesize( $file ) > 4194304 ) {
                    continue;
                }

                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Gelesen wird eine lokale Themedatei, kein entfernter URL; die Wachen davor bleiben bestehen.
                $contents[] = (string) file_get_contents( $file );
            }
        }

        return $contents;
    }

    /**
     * Theme block template files that still carry an `areoi/` block.
     *
     * Genau die zwei Verzeichnisse aus der Fundstellentabelle in
     * `05-migration.md` — `templates/` und `parts/` —, je Theme und
     * Elterntheme, per `glob()` und nicht rekursiv: FSE legt seine Templates
     * flach in diesen beiden Ordnern ab.
     *
     * Gesucht wird der Blockbegrenzer `<!-- wp:areoi/`, nicht der blosse
     * String `areoi` — sonst meldete jede Datei mit einer `areoi-*`-Klasse im
     * Markup einen Treffer, und die Zeile waere Rauschen.
     *
     * @return array<int, string> Absolute paths, unsorted.
     */
    private static function theme_template_files_with_areoi(): array {
        $found = [];

        foreach ( self::theme_directories() as $directory ) {
            foreach ( [ $directory . '/templates/*.html', $directory . '/parts/*.html' ] as $pattern ) {
                $matched = glob( $pattern );

                if ( ! is_array( $matched ) ) {
                    continue;
                }

                foreach ( $matched as $file ) {
                    if ( ! is_readable( $file ) || filesize( $file ) > 4194304 ) {
                        continue;
                    }

                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Gelesen wird eine lokale Themedatei, kein entfernter URL; die Wachen davor bleiben bestehen.
                    $contents = (string) file_get_contents( $file );

                    if ( [] !== self::areoi_block_names( $contents ) ) {
                        $found[] = (string) $file;
                    }
                }
            }
        }

        return array_values( array_unique( $found ) );
    }

    /**
     * The backup path used when `--backup` is omitted.
     */
    private static function default_backup_path(): string {
        if ( function_exists( 'wp_upload_dir' ) ) {
            $uploads = \wp_upload_dir();

            if ( is_array( $uploads ) && isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ) {
                return $uploads['basedir'] . '/crea-bootstrap-blocks-backup.sql.gz';
            }
        }

        return ABSPATH . 'crea-bootstrap-blocks-backup.sql.gz';
    }
}
