<?php
/**
 * WP-CLI: `wp creabb cleanup` — removes the leftovers of the old plugin.
 *
 * DER EINZIGE BEFEHL DIESES PLANS, DER LOESCHT. Er tut es unter zwei
 * Bedingungen: `wp creabb migrate verify` ist sauber, und der Optionsname
 * passt auf eines der drei Praefixe des Alt-Plugins. Die zweite Bedingung ist
 * nicht Kosmetik — eine zu weit gefasste Namenspruefung („enthaelt areoi")
 * loeschte fremde Optionen mit.
 *
 * Die Alt-Optionen sind nach der Migration Datenmuell (05-migration.md). Sie
 * vorher zu loeschen naehme dem Alt-Plugin die Grundlage fuer einen Rollback
 * auf Anwendungsebene; deshalb der Riegel.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks\CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_CLI;

/**
 * `wp creabb cleanup`
 */
class Cleanup_Command {

    /**
     * The option name prefixes of the old plugin.
     *
     * @return array<int, string>
     */
    public static function legacy_option_prefixes(): array {
        return [ 'areoi-', 'areoi_', 'all_bootstrap_blocks_' ];
    }

    /**
     * Whether an option belongs to the old plugin.
     *
     * @param string $name Option name.
     */
    public static function is_legacy_option( string $name ): bool {
        foreach ( self::legacy_option_prefixes() as $prefix ) {
            if ( str_starts_with( $name, $prefix ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The legacy option names actually present in `wp_options`.
     *
     * NICHT `wp_load_alloptions()`. Dieser Helfer liefert ausschliesslich
     * Optionen mit `autoload = 'yes'`. Das Alt-Plugin legt aber auch Optionen
     * ohne Autoload an — die tauchten im Loeschplan nie auf, und der Befehl
     * meldete „No legacy option left", waehrend der Datenmuell stehen bleibt.
     * Gefragt wird deshalb direkt `wp_options`, je Praefix eine Abfrage mit
     * `prepare()` und `esc_like()`.
     *
     * Je Praefix eine eigene Abfrage, damit die SQL-Zeichenkette literal
     * bleibt: Eine zusammengesetzte OR-Kette muesste die Platzhalter zur
     * Laufzeit erzeugen, und genau dort entstehen die Fehler, die `prepare()`
     * verhindern soll. Das Praefix ist links verankert, die Abfrage laeuft
     * ueber den Index auf `option_name`.
     *
     * @return array<int, string> Option names, without duplicates.
     */
    private static function legacy_option_names(): array {
        global $wpdb;

        /*
         * `instanceof \wpdb` statt `is_object()` (E-90): Die Wache ist damit
         * nicht nur strenger — sie erhaelt auch den Typ. Nach `is_object()`
         * weiss die statische Analyse nur noch `object` und haelt jeden
         * folgenden Aufruf fuer undefiniert.
         */
        if ( ! $wpdb instanceof \wpdb ) {
            return [];
        }

        $names = [];

        foreach ( self::legacy_option_prefixes() as $prefix ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    // Der Tabellenname als `%i`, nicht interpoliert (E-89).
                    'SELECT option_name FROM %i WHERE option_name LIKE %s',
                    $wpdb->options,
                    $wpdb->esc_like( $prefix ) . '%'
                )
            );

            if ( ! is_array( $rows ) ) {
                continue;
            }

            foreach ( $rows as $name ) {
                $names[] = (string) $name;
            }
        }

        return array_values( array_unique( $names ) );
    }

    /**
     * Builds the deletion plan.
     *
     * @param array<int, string> $present  Option names present in the installation.
     * @param bool               $verified Whether `migrate verify` is clean.
     * @return array{status: string, delete: array<int, string>, message: string}
     */
    public static function plan( array $present, bool $verified ): array {
        if ( ! $verified ) {
            return [
                'status'  => 'error',
                'delete'  => [],
                'message' => __( 'wp creabb migrate verify is not clean. The legacy options stay until it is — they are the fallback of the old plugin.', 'crea-bootstrap-blocks' ),
            ];
        }

        $delete = [];

        foreach ( $present as $name ) {
            if ( is_string( $name ) && self::is_legacy_option( $name ) ) {
                $delete[] = $name;
            }
        }

        sort( $delete );

        if ( [] === $delete ) {
            return [
                'status'  => 'ok',
                'delete'  => [],
                'message' => __( 'No legacy option left. Nothing to do.', 'crea-bootstrap-blocks' ),
            ];
        }

        return [
            'status'  => 'ok',
            'delete'  => $delete,
            'message' => sprintf(
                /* translators: %d: number of options. */
                __( '%d legacy options can be removed.', 'crea-bootstrap-blocks' ),
                count( $delete )
            ),
        ];
    }

    /**
     * Removes the legacy options of the old plugin.
     *
     * ## OPTIONS
     *
     * [--legacy-options]
     * : Remove the `areoi-*`, `areoi_*` and `all_bootstrap_blocks_*` options.
     *
     * [--dry-run]
     * : List what would be removed and remove nothing.
     *
     * [--force]
     * : Remove them even though `migrate verify` is not clean. Do not use this
     * before the acceptance of the migration is signed off.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp creabb cleanup --legacy-options --dry-run
     *     wp creabb cleanup --legacy-options
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        if ( ! isset( $assoc_args['legacy-options'] ) ) {
            WP_CLI::error( __( 'Nothing selected. Currently the only subject is --legacy-options.', 'crea-bootstrap-blocks' ) );
        }

        $verified = isset( $assoc_args['force'] );

        if ( ! $verified ) {
            $verify = WP_CLI::runcommand(
                'creabb migrate verify',
                [
                    'return'     => 'all',
                    'exit_error' => false,
                ]
            );

            $verified = ( 0 === (int) $verify->return_code );
        }

        $plan = self::plan( self::legacy_option_names(), $verified );

        WP_CLI::log( $plan['message'] );

        if ( 'ok' !== $plan['status'] ) {
            WP_CLI::halt( 1 );
        }

        if ( [] === $plan['delete'] ) {
            return;
        }

        foreach ( $plan['delete'] as $name ) {
            WP_CLI::log( '  ' . $name );
        }

        if ( isset( $assoc_args['dry-run'] ) ) {
            WP_CLI::success( __( 'Dry run. Nothing was removed.', 'crea-bootstrap-blocks' ) );

            return;
        }

        WP_CLI::confirm( __( 'Remove these options?', 'crea-bootstrap-blocks' ), $assoc_args );

        $removed = 0;

        foreach ( $plan['delete'] as $name ) {
            if ( \delete_option( $name ) ) {
                ++$removed;
            }
        }

        WP_CLI::success(
            sprintf(
                /* translators: %d: number of removed options. */
                __( '%d legacy options removed.', 'crea-bootstrap-blocks' ),
                $removed
            )
        );
    }
}
