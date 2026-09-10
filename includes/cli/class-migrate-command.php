<?php
/**
 * WP-CLI: `wp creabb migrate` — inventory, run, verify, rollback.
 *
 * NUR UNTERBEFEHLE. Diese Klasse hat kein `__invoke()`;
 * `WP_CLI::add_command( 'creabb migrate', new self() )` registriert sie deshalb
 * als zusammengesetzten Befehl und leitet dessen Unterbefehle per Reflection
 * aus den oeffentlichen Methoden ab. Oeffentlich ist hier deshalb
 * ausschliesslich, was auch als Kommando gedacht ist: `scan`, `run`, `verify`,
 * `rollback`. Jeder reine Helfer steht in `Migration_Support`, jede interne
 * Schreibmethode ist `private`.
 *
 * REVISIONEN GEHOEREN ZUM STANDARDUMFANG. `05-migration.md` fuehrt sie in der
 * Fundstellentabelle als „ersetzen"; abgeschaltet werden sie ausschliesslich
 * ueber `--skip-revisions`, und dieser Schalter gilt fuer `scan`, `run` und
 * `verify` gleichermassen. Ein `run` ohne Revisionen und ein `verify` mit
 * ihnen wuerden verschiedene Mengen betrachten — `verify` waere nach einem
 * planmaessigen Lauf rot, und die Abnahme haenge an einem Widerspruch.
 *
 * SERIALISIERTE WERTE werden NIE per rohem SQL angefasst. Sie werden ueber die
 * WordPress-API gelesen, im PHP-Wert ersetzt und zurueckgeschrieben.
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
 * `wp creabb migrate`
 */
class Migrate_Command {

    /**
     * Takes stock of every source without writing anything.
     *
     * ## OPTIONS
     *
     * [--skip-revisions]
     * : Leave `post_type = revision` out. Revisions are scanned by default.
     *
     * [--format=<format>]
     * : Output format (table, json, yaml, csv). Default: table.
     *
     * [--by=<grouping>]
     * : `source` (default) prints one row per source, namespace and block type,
     * `carrier` prints one row per post, meta row or option.
     *
     * ## EXAMPLES
     *
     *     wp creabb migrate scan
     *     wp creabb migrate scan --format=json > vor-migration.json
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function scan( array $args, array $assoc_args ): void {
        $format   = (string) ( $assoc_args['format'] ?? 'table' );
        $grouping = (string) ( $assoc_args['by'] ?? 'source' );

        // Revisionen sind Standardumfang (05-migration.md, Fundstellentabelle).
        // Derselbe Schalter wie bei `run` und `verify`, damit alle drei
        // Unterbefehle dieselbe Traegermenge sehen.
        $snapshot = Migration_Support::snapshot( ! isset( $assoc_args['skip-revisions'] ) );

        // Jeder Traeger, dessen Attribut-JSON nicht lesbar ist, wird von der
        // Ersetzung uebersprungen. Das gehoert vor den Lauf, nicht dahinter.
        foreach ( $snapshot['errors'] as $message ) {
            WP_CLI::warning( $message );
        }

        if ( 'carrier' === $grouping ) {
            // Die Schliesser stehen in eigenen Spalten. Ein Traeger, in dem nur
            // noch ein verwaister `<!-- /wp:areoi/… -->` steht, haette sonst
            // ueberall die Zahl null — und niemand saehe, warum `verify`
            // nachher rot meldet.
            \WP_CLI\Utils\format_items(
                $format,
                $snapshot['rows'],
                [ 'source', 'id', 'areoi', 'areoi_close', 'creabb', 'creabb_close' ]
            );

            return;
        }

        $items = [];

        foreach ( $snapshot['counts'] as $source => $branches ) {
            foreach ( $branches as $namespace => $blocks ) {
                foreach ( $blocks as $block => $count ) {
                    $items[] = [
                        'source' => $source,
                        'ns'     => $namespace,
                        'block'  => $block,
                        'count'  => $count,
                    ];
                }
            }
        }

        if ( [] === $items ) {
            WP_CLI::success( __( 'No areoi/* and no creabb/* block found.', 'crea-bootstrap-blocks' ) );

            return;
        }

        \WP_CLI\Utils\format_items( $format, $items, [ 'source', 'ns', 'block', 'count' ] );

        if ( 'table' === $format ) {
            WP_CLI::log( '' );
            WP_CLI::log(
                sprintf(
                    /* translators: 1: number of carriers, 2: number of block entries. */
                    __( '%1$d carriers, %2$d block entries.', 'crea-bootstrap-blocks' ),
                    count( $snapshot['rows'] ),
                    count( $snapshot['attributes'] )
                )
            );
        }
    }
    /**
     * Migrates every source from `areoi/*` to `creabb/*`.
     *
     * ## OPTIONS
     *
     * [--backup=<path>]
     * : Where the database dump is written. Required unless --skip-backup or --dry-run.
     *
     * [--skip-backup]
     * : Run without taking a backup. There is no way back from here.
     *
     * [--dry-run]
     * : Rewrite in memory only: same carriers, same reconciliation, no write.
     *
     * [--skip-revisions]
     * : Leave `post_type = revision` untouched. Revisions are migrated by
     * default; `verify` checks them by default too, so a run with this flag
     * needs `wp creabb migrate verify --skip-revisions` to match.
     *
     * ## EXAMPLES
     *
     *     wp creabb migrate run --dry-run
     *     wp creabb migrate run --backup=/var/backups/dump.sql.gz
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function run( array $args, array $assoc_args ): void {
        // KEIN `global $wpdb;` hier. Die gesamte Datenbankarbeit liegt in den
        // drei privaten Schreibmethoden, und jede fuehrt ihr eigenes `global`.
        // Eine ungenutzte Zeile an dieser Stelle behauptete, run() schriebe
        // selbst — im Trockenlauf tut es das ausdruecklich nicht.
        $dry_run = isset( $assoc_args['dry-run'] );

        // Revisionen sind Standardumfang. `verify` prueft sie ebenfalls
        // standardmaessig; waeren sie hier opt-in, meldete `verify` nach jedem
        // planmaessigen Lauf die unmigrierten Revisionen und sperrte die
        // Abnahme (05-migration.md, „Fundstellen" und `migrate verify`).
        $revisions = ! isset( $assoc_args['skip-revisions'] );

        // 1 — doctor erneut ausfuehren. Der Zustand kann sich seit dem letzten
        // Lauf geaendert haben, und ein einziger roter Punkt genuegt.
        WP_CLI::log( __( '1/7 doctor', 'crea-bootstrap-blocks' ) );

        // doctor prueft unter anderem die Schreibrechte des Backup-Ziels. Ohne
        // den eigenen --backup-Wert pruefte er sein Default-Verzeichnis
        // (`default_backup_path()`) — also ein anderes Verzeichnis als das, in
        // das dieser Lauf gleich schreibt. Der Wert wird durchgereicht, bevor
        // `backup_decision()` ihn auswertet, weil doctor VOR Schritt 2 laeuft.
        $doctor_command  = 'creabb doctor';
        $backup_argument = isset( $assoc_args['backup'] ) ? (string) $assoc_args['backup'] : '';

        if ( '' !== $backup_argument ) {
            $doctor_command .= ' --backup=' . escapeshellarg( $backup_argument );
        }

        $doctor = WP_CLI::runcommand(
            $doctor_command,
            [
                'return'     => 'all',
                'exit_error' => false,
            ]
        );

        WP_CLI::log( (string) $doctor->stdout );

        if ( 0 !== (int) $doctor->return_code ) {
            WP_CLI::error( __( 'doctor reports an error. Nothing has been written.', 'crea-bootstrap-blocks' ) );
        }

        // 2 — Backup.
        $backup = Migration_Support::backup_decision( $assoc_args );

        if ( ! $backup['ok'] ) {
            WP_CLI::error( $backup['message'] );
        }

        WP_CLI::log( '2/7 ' . $backup['message'] );

        if ( '' !== $backup['path'] ) {
            $this->write_backup( $backup['path'] );
        }

        // 3 — Vorher-Inventar.
        WP_CLI::log( __( '3/7 inventory before', 'crea-bootstrap-blocks' ) );

        $before = Migration_Support::snapshot( $revisions );

        $before_counts = Migration_Support::flatten_counts( $before['counts'], 'areoi' );

        WP_CLI::log(
            sprintf(
                /* translators: 1: number of carriers, 2: number of blocks. */
                __( '    %1$d carriers, %2$d blocks', 'crea-bootstrap-blocks' ),
                count( $before['rows'] ),
                array_sum( $before_counts )
            )
        );

        // Ein Traeger, dessen Attribut-JSON nicht lesbar ist — oder dessen
        // Begrenzer das Muster nicht trifft — wird von der Ersetzung
        // uebersprungen und bliebe als areoi-Rest stehen. Gemeldet wird das in
        // JEDEM Fall, und zwar hier: Dem Abgleich in Schritt 6 faellt derselbe
        // Befund erst NACH dem Schreiben auf.
        foreach ( $before['errors'] as $message ) {
            WP_CLI::warning( $message );
        }

        // ABGEBROCHEN WIRD NUR IM SCHREIBENDEN ZWEIG. Genau wegen solcher
        // Traeger laesst man einen `--dry-run` laufen; braeche er an dieser
        // Stelle ab, bekaeme man die erste Fundstelle zu sehen und nie die
        // Liste — und den Abgleich, den der Trockenlauf eigentlich vorfuehren
        // soll, ueberhaupt nicht. Der Trockenlauf rechnet deshalb weiter,
        // nimmt diese Beanstandungen in seine Schlussbilanz auf und endet dort
        // mit einem Exit-Code ungleich 0. Der echte Lauf bricht hier ab,
        // bevor ein Byte geschrieben ist.
        if ( [] !== $before['errors'] && ! $dry_run ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %d: number of findings. */
                    __( '%d carriers cannot be read. Nothing has been written.', 'crea-bootstrap-blocks' ),
                    count( $before['errors'] )
                )
            );
        }

        // 4 — Ersetzen.
        if ( $dry_run ) {
            WP_CLI::log( __( '4/7 dry run — rewriting in memory, writing nothing', 'crea-bootstrap-blocks' ) );

            $would = 0;

            foreach ( $before['rows'] as $row ) {
                $would += (int) $row['areoi'];
            }

            WP_CLI::log(
                sprintf(
                    /* translators: %d: number of block delimiters that would change. */
                    __( '    %d block delimiters would be rewritten', 'crea-bootstrap-blocks' ),
                    $would
                )
            );

            // 5 — Nachher-Inventar, ausschliesslich im Speicher: dieselben
            // Traeger, dieselbe rewrite_value(), kein Schreibaufruf.
            WP_CLI::log( __( '5/7 inventory after (simulated)', 'crea-bootstrap-blocks' ) );

            $simulated = Migration_Support::simulate( $revisions );

            // 6 — Und derselbe Abgleich. Ein Trockenlauf, der weniger prueft
            // als der Lauf, taugt nicht als Vorprobe.
            WP_CLI::log( __( '6/7 reconciliation (simulated)', 'crea-bootstrap-blocks' ) );

            // DIE BEANSTANDUNGEN DES VORHER-INVENTARS GEHOEREN HIER MIT HINEIN.
            // Sie waren oben der Grund, aus dem der echte Lauf abbricht; der
            // Trockenlauf hat sie stattdessen nur gemeldet und weitergerechnet.
            // Faenden sie in die Schlussbilanz nicht zurueck, endete ein
            // `--dry-run` ueber unlesbaren Traegern mit „bestanden" und
            // Exit-Code 0 — und das waere die gefaehrlichste aller Auskuenfte.
            //
            // `array_unique()`, weil derselbe Traeger von `snapshot()` und von
            // `simulate()` gemeldet wird: Beide laufen ueber `carriers()` und
            // dieselbe `rewrite_value()`. Ohne die Entdopplung stuende jeder
            // Befund zweimal in der Liste und die Zahl im Schlusssatz waere um
            // den Faktor zwei zu gross.
            $dry_findings = array_values(
                array_unique(
                    array_merge(
                        $before['errors'],
                        $simulated['errors'],
                        Migration_Support::reconcile_inventory( $before, $simulated )
                    )
                )
            );

            if ( [] !== $dry_findings ) {
                foreach ( $dry_findings as $finding ) {
                    // Was oben schon als Warnung stand, wird nicht zweimal
                    // ausgegeben — gezaehlt wird es trotzdem.
                    if ( in_array( $finding, $before['errors'], true ) ) {
                        continue;
                    }

                    WP_CLI::warning( $finding );
                }

                WP_CLI::error(
                    sprintf(
                        /* translators: %d: number of findings. */
                        __( 'Dry run: %d findings — see the warnings above. Nothing was written, and a real run would not pass.', 'crea-bootstrap-blocks' ),
                        count( $dry_findings )
                    )
                );
            }

            WP_CLI::success(
                __( 'Dry run finished: the reconciliation passes. Nothing was written.', 'crea-bootstrap-blocks' )
            );

            return;
        }

        // DIE ZWEITE SPERRE VOR DEM ERSTEN SCHREIBZUGRIFF, und ohne sie ist die
        // Zusage dieses Befehls falsch. Das Vorher-Inventar oben erhebt seine
        // Beanstandungen ueber `Migrator::findings()` und
        // `broken_meta_finding()`. Beide urteilen ueber den INHALT. Zwei Faelle
        // entstehen erst im SCHREIBVORGANG und sind fuer beide unsichtbar:
        //
        // 1. Ein Objekt, das nicht angefasst werden darf — ein Enum, eine
        // `__PHP_Incomplete_Class`, eine gescheiterte readonly-Zuweisung.
        // Das sieht nur `rewrite_value()`, weil nur sie in den PHP-Wert
        // hinabsteigt; `findings()` liest Blockbegrenzer aus einem String.
        // 2. Ein Attribut-Container, den `json_encode()` nicht schreiben kann.
        // Der Docblock von `findings()` sagt selbst, dass es diese Meldung
        // nicht fuehrt: Sie ist eine Aussage ueber den Schreibvorgang.
        //
        // GEMESSEN: Ein serialisiertes Objekt einer nicht geladenen Klasse mit
        // Blockmarkup darin wird vom LIKE geladen, `unserialize()` gelingt,
        // `findings()` liefert nichts, die Blockzaehlung stimmt. Das
        // Vorher-Inventar ist sauber, `run()` schriebe — und erst
        // `rewrite_meta_value()` in `migrate_postmeta()` beanstandete. Da hat
        // `migrate_posts()` bereits committed: `wp_posts` migriert,
        // `wp_postmeta` zurueckgerollt. Genau die halb migrierte Installation,
        // die dieser Plan verhindern soll — und dieselben Objekte fuehrt die
        // Suite dieser Aufgabe als realen `wp_postmeta`-Bestand.
        //
        // WARUM `simulate()` UND NICHT EINE ZWEITE PRUEFUNG IN `snapshot()`:
        // `simulate()` faehrt je Traegerart bereits exakt die Funktion, die
        // auch die Schreibmethode faehrt — `rewrite_meta_value()` fuer
        // Postmeta, `rewrite_value()` sonst. Es gibt hier also nichts, was von
        // der Schreibmethode wegdriften koennte. Dieselbe Pruefung in
        // `snapshot()` muesste diese Fallunterscheidung ein zweites Mal fuehren
        // und traefe alle vier Aufrufer: `scan`, `verify` und `run` ZWEIMAL.
        // Der vollstaendige Umschreiblauf fiele damit viermal statt einmal an —
        // im Nachher-Inventar und im reinen Lesebefehl `scan` ohne jeden
        // Nutzen. Aufgabe 11 hat den Umschreiblauf aus `snapshot()` aus genau
        // diesem Grund herausgenommen; er kommt hier nicht durch die
        // Hintertuer zurueck.
        WP_CLI::log( __( '    probing the rewrite of every carrier', 'crea-bootstrap-blocks' ) );

        $probe = Migration_Support::simulate( $revisions );

        // NUR DIE BEANSTANDUNGEN, NICHT DER ABGLEICH. Der Abgleich verglaenge
        // gegen einen Stand, den es noch nicht gibt; er steht in Schritt 6.
        // Hier steht allein die Frage, ob das Umschreiben ueberhaupt
        // durchlaeuft.
        if ( [] !== $probe['errors'] ) {
            foreach ( $probe['errors'] as $message ) {
                WP_CLI::warning( $message );
            }

            WP_CLI::error(
                sprintf(
                    /* translators: %d: number of findings. */
                    __( '%d carriers cannot be rewritten. Nothing has been written.', 'crea-bootstrap-blocks' ),
                    count( $probe['errors'] )
                )
            );
        }

        WP_CLI::log( __( '4/7 rewriting', 'crea-bootstrap-blocks' ) );

        $errors  = [];
        $written = 0;

        // NACH JEDER Schreibmethode wird geprueft. Die drei teilen sich eine
        // Referenz auf $errors; ohne diese Pruefung liefe migrate_options() —
        // das keine Transaktion hat und mit update_option() schreibt — auch
        // dann noch los, wenn wp_posts bereits zurueckgerollt wurde. Das
        // Ergebnis waere eine halb migrierte Installation.
        $written += $this->migrate_posts( $revisions, $errors );
        $this->halt_on_write_errors( $errors, $backup['path'] );

        $written += $this->migrate_postmeta( $errors );
        $this->halt_on_write_errors( $errors, $backup['path'] );

        $written += $this->migrate_options( $errors );
        $this->halt_on_write_errors( $errors, $backup['path'] );

        WP_CLI::log(
            sprintf(
                /* translators: %d: number of rewritten carriers. */
                __( '    %d carriers rewritten', 'crea-bootstrap-blocks' ),
                $written
            )
        );

        // 5 — Nachher-Inventar.
        WP_CLI::log( __( '5/7 inventory after', 'crea-bootstrap-blocks' ) );

        $after        = Migration_Support::snapshot( $revisions );
        $after_counts = Migration_Support::flatten_counts( $after['counts'], 'creabb' );

        WP_CLI::log(
            sprintf(
                /* translators: 1: number of carriers, 2: number of blocks. */
                __( '    %1$d carriers, %2$d blocks', 'crea-bootstrap-blocks' ),
                count( $after['rows'] ),
                array_sum( $after_counts )
            )
        );

        // 6 — Abgleich. Drei Pruefungen, alle ueber ALLE betroffenen Bloecke:
        // Zaehlung je Fundstelle, harte Bedingung auf den areoi-Rest,
        // Schluessel- und Wertevergleich je block_id.
        WP_CLI::log( __( '6/7 reconciliation', 'crea-bootstrap-blocks' ) );

        $findings = array_merge(
            $after['errors'],
            Migration_Support::reconcile_inventory( $before, $after )
        );

        if ( [] !== $findings ) {
            foreach ( $findings as $finding ) {
                WP_CLI::warning( $finding );
            }

            WP_CLI::error(
                sprintf(
                    /* translators: 1: number of findings, 2: backup path. */
                    __( 'Reconciliation failed with %1$d findings. Restore the backup: %2$s', 'crea-bootstrap-blocks' ),
                    count( $findings ),
                    '' !== $backup['path'] ? $backup['path'] : __( '(none taken)', 'crea-bootstrap-blocks' )
                )
            );
        }

        WP_CLI::log(
            sprintf(
                /* translators: 1: number of block types, 2: number of block entries. */
                __( '    %1$d block types and %2$d block entries match exactly', 'crea-bootstrap-blocks' ),
                count( $before_counts ),
                count( $before['attributes'] )
            )
        );

        // 7 — Caches.
        WP_CLI::log( __( '7/7 flushing caches', 'crea-bootstrap-blocks' ) );

        \wp_cache_flush();

        // WAS DIESER SCHRITT LEERT — UND WAS NICHT. Beides gehoert hierher,
        // weil der naechste Schritt der Abnahme ein Rendervergleich ist und ein
        // Vergleich gegen einen alten Cache den Vorzustand misst.
        //
        // GELEERT WIRD der WordPress-Objektcache (die Zeile oben) und der
        // STATIC-PRERENDER-CACHE von Blockstudio: fertiges HTML je URL, auf
        // Platte unter `wp-content/blockstudio/cache/sites/<site>/…`,
        // ausgeliefert lange bevor ein Block gerendert wird. Er ist nach einer
        // Content-Migration der einzige Blockstudio-Cache, der wirklich
        // veraltet ist — und er faellt hier NICHT von selbst: Sein
        // Invalidierungshaken ist `save_post`, und die drei Schreibmethoden
        // gehen per UPDATE an der Post-API vorbei. Selbst wenn sie das nicht
        // taeten, stiege `Static_Prerender_Runtime::handle_content_changed()`
        // sofort wieder aus; die Methode kehrt zurueck, sobald ein
        // WP-CLI-Runner den Prozess besitzt
        // (`blockstudio/includes/classes/static-prerender-runtime.php`,
        // `wp_cli_is_running()`). Geraeumt wird deshalb ausdruecklich:
        // `Static_Prerender_Runtime::purge()` loescht die drei Namensraeume
        // `static-prerender`, `static-prerender-state` und
        // `static-prerender-queue` und gibt die Zahl der Dateien zurueck; die
        // Early-Serve-Ablage (`static-prerender-artifact`) faellt NICHT
        // darunter, obwohl sie noch VOR WordPress ausliefert, und wird deshalb
        // ueber `Static_Prerender_Early_Serve::remove_current_site()` mit
        // abgeraeumt. Blockstudio baut den Eintrag beim naechsten `admin_init`
        // aus dem migrierten Content neu auf.
        //
        // NICHT GELEERT WIRD der Build-Cache (`Blockstudio\Build_Cache`, Scopes
        // `runtime` und `editor-assets`) — er ist nach einer Content-Migration
        // auch nicht veraltet. Sein Schluessel besteht aus Blockstudio-Version,
        // Einstellungen, Feldtypen, aktiven Plugins, Theme und den mtimes der
        // Blockdateien; Post-Content geht darin nicht ein. Ein fruehrer Entwurf
        // rief hier `Build_Cache::invalidate_populate_cache()` und nannte das
        // „den Blockstudio-Cache leeren". Das war doppelt falsch: Die Methode
        // bumpt nur eine Options-Version fuer datenbankgestuetzte
        // Populate-Daten und kehrt vorher zurueck, wenn der Cache abgeschaltet
        // ist ODER kein registrierter Block Populate-Daten deklariert — und
        // keiner der creabb-Bloecke tut das. Der Aufruf war ein sicherer
        // Leerlauf mit beruhigender Meldung.
        //
        // EBENFALLS NICHT GELEERT werden Seiten-Caches ausserhalb von
        // Blockstudio: Hosting-Cache, Reverse-Proxy, CDN, Cache-Plugins
        // Dritter. Die erreicht dieser Befehl nicht, und er behauptet es auch
        // nicht — Punkt 8 der Abnahme-Checkliste laesst sie ein Mensch pruefen.
        //
        // DIE GUARDS SCHWEIGEN NICHT. Blockstudio ist eine Fremdabhaengigkeit
        // und darf seine Interna umbauen. Greift ein Guard nicht, steht das als
        // Warnung am Ende des Laufs — ein Guard, der stillschweigend nichts
        // tut, waere schlimmer als gar keiner.
        //
        // DIE ANALYSE KENNT DIE BEIDEN KLASSEN NICHT und faltet
        // `method_exists( 'Literal', … )` deshalb zu „immer falsch" zusammen —
        // sie meldete einen Fehler fuer genau die Wache, die diesen Aufruf
        // gegen einen Umbau bei Blockstudio absichert. Die Literale bleiben
        // trotzdem stehen: Nur so ist am Aufruf selbst zu sehen, welche
        // Methode geprueft wird, und die Suite haelt genau das fest.
        $this->flush_prerender_cache();

        WP_CLI::success( __( 'Migration finished. Next: wp creabb migrate verify', 'crea-bootstrap-blocks' ) );
    }

    /**
     * Reports every `areoi/*` block that is left.
     *
     * ## OPTIONS
     *
     * [--skip-revisions]
     * : Leave `post_type = revision` out of the check. Revisions are checked by
     * default — a revision is a source like any other. Pass this only when the
     * run was started with `--skip-revisions` as well.
     *
     * [--format=<format>]
     * : Output format for the per-source table.
     *
     * ## EXAMPLES
     *
     *     wp creabb migrate verify
     *
     * Gezaehlt werden OEFFNENDE UND SCHLIESSENDE Begrenzer (05-migration.md):
     * Ein verwaister `<!-- /wp:areoi/… -->` ist ein Treffer und sperrt die
     * Abnahme, auch wenn kein einziger oeffnender Begrenzer mehr dasteht.
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function verify( array $args, array $assoc_args ): void {
        // DERSELBE SCHALTER WIE BEI `run`. Ein hartkodiertes `true` waere eine
        // Falschaussage im Docblock und schlimmer noch ein Widerspruch im
        // Ablauf: `verify` betrachtete dann eine andere Menge als der Lauf, den
        // es abnimmt. Standard ist beidesmal „mit Revisionen".
        $revisions = ! isset( $assoc_args['skip-revisions'] );
        $snapshot  = Migration_Support::snapshot( $revisions );

        $hits = [];

        foreach ( $snapshot['counts'] as $source => $blocks ) {
            $hits[ $source ] = 0;
        }

        // OEFFNENDE UND SCHLIESSENDE BEGRENZER. `05-migration.md` sagt es
        // woertlich: „Gezaehlt werden oeffnende UND schliessende Begrenzer —
        // ein verwaister `<!-- /wp:areoi/… -->` ist ein Treffer." Ein Traeger,
        // in dem nur noch ein Schliesser steht, ist ein areoi-Rest in der
        // Datenbank; im Editor erscheint der zugehoerige Block als „unerwarteter
        // oder ungueltiger Inhalt", und beim naechsten Speichern ist er weg.
        // Zaehlte `verify` nur die Oeffner, gaebe es die Freigabe fuer genau
        // diesen Zustand.
        foreach ( $snapshot['rows'] as $row ) {
            $source = (string) $row['source'];

            // DER ALTE NAME IM CONTAINER ZAEHLT MIT (E-102). Er ist kein
            // Begrenzer und faellt deshalb durch beide Zaehler — aber
            // Blockstudio liest ihn zuerst, und auf die gruene Meldung dieses
            // Befehls hin wird der Legacy-Alias abgeschaltet.
            $hits[ $source ] = ( $hits[ $source ] ?? 0 )
                + (int) $row['areoi']
                + (int) $row['areoi_close']
                + (int) ( $row['areoi_name'] ?? 0 );
        }

        $summary = Migration_Support::verify_summary( $hits, $revisions );

        WP_CLI::log( $summary['message'] );

        /*
         * UND DIE BEANSTANDUNGEN DES INVENTARS. `snapshot()` sammelt jeden
         * Traeger, den es nicht lesen konnte — unlesbare Attribut-JSON, ein
         * nicht getroffener Begrenzer, ein kaputt serialisierter Metawert, ein
         * Regex-Fehler. Ein solcher Traeger taucht in der Zaehlung mit NULL
         * auf; `verify` meldete ohne diese Auswertung gruen fuer einen
         * Bestand, den es gar nicht ansehen konnte — und auf diese Meldung hin
         * wird der Legacy-Alias abgeschaltet.
         */
        foreach ( $snapshot['errors'] as $message ) {
            WP_CLI::warning( (string) $message );
        }

        if ( 'ok' !== $summary['status'] || [] !== $snapshot['errors'] ) {
            WP_CLI::halt( 1 );
        }

        WP_CLI::success( __( 'verify passed.', 'crea-bootstrap-blocks' ) );
    }

    /**
     * Restores a dump written by `migrate run`.
     *
     * DER NOTAUSGANG. Er spielt eine ganze Datenbank ein und wird deshalb
     * ausdruecklich bestaetigt; `--yes` ueberspringt die Rueckfrage nur, wenn
     * jemand das ausdruecklich will.
     *
     * ## OPTIONS
     *
     * --from=<path>
     * : The dump to restore, gzipped or plain.
     *
     * [--yes]
     * : Skip the confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     wp creabb migrate rollback --from=/var/backups/dump.sql.gz
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function rollback( array $args, array $assoc_args ): void {
        $path = isset( $assoc_args['from'] ) ? (string) $assoc_args['from'] : '';

        $decision = Migration_Support::rollback_decision( $path, is_file( $path ), is_readable( $path ) );

        if ( ! $decision['ok'] ) {
            WP_CLI::error( $decision['message'] );
        }

        WP_CLI::confirm(
            sprintf(
                /* translators: %s: dump path. */
                __( 'This replaces the entire database with %s. Continue?', 'crea-bootstrap-blocks' ),
                $path
            ),
            $assoc_args
        );

        $plain = $path;

        if ( str_ends_with( $path, '.gz' ) ) {
            $plain = rtrim( sys_get_temp_dir(), '/' ) . '/creabb-rollback-' . getmypid() . '.sql';

            /*
             * GESTREAMT, nicht am Stueck: dieselbe Begruendung wie beim
             * Schreiben des Backups. WP_Filesystem kennt keinen Datenstrom.
             */
            // phpcs:disable WordPress.WP.AlternativeFunctions -- Begruendung im Block darueber.
            $in  = gzopen( $path, 'rb' );
            $out = fopen( $plain, 'wb' );

            if ( false === $in || false === $out ) {
                if ( false !== $in ) {
                    gzclose( $in );
                }

                if ( false !== $out ) {
                    fclose( $out );
                }

                WP_CLI::error( __( 'The dump could not be unpacked.', 'crea-bootstrap-blocks' ) );

                return;
            }

            while ( ! gzeof( $in ) ) {
                $chunk = gzread( $in, 262144 );

                // EIN ABGESCHNITTENES ARCHIV DARF NICHT IMPORTIERT WERDEN.
                // `gzread()` liefert `false`, wenn der Datenstrom mitten im
                // Block endet — ohne diese Wache schriebe der Rueckweg die
                // halbe Datenbank zurueck und meldete Erfolg.
                if ( false === $chunk ) {
                    gzclose( $in );
                    fclose( $out );
                    wp_delete_file( $plain );

                    WP_CLI::error(
                        sprintf(
                            /* translators: %s: dump path. */
                            __( 'The dump %s is truncated — nothing was imported.', 'crea-bootstrap-blocks' ),
                            $path
                        )
                    );

                    return;
                }

                if ( false === fwrite( $out, $chunk ) ) {
                    gzclose( $in );
                    fclose( $out );
                    wp_delete_file( $plain );

                    WP_CLI::error( __( 'The dump could not be unpacked — nothing was imported.', 'crea-bootstrap-blocks' ) );

                    return;
                }
            }

            gzclose( $in );
            fclose( $out );
            // phpcs:enable WordPress.WP.AlternativeFunctions
        }

        /*
         * UND EIN LEERER ODER OFFENSICHTLICH FALSCHER DUMP EBENSO WENIG. Der
         * Import ersetzt die gesamte Datenbank; was danach fehlt, ist weg. Die
         * Probe ist billig und faengt den haeufigsten Fall: eine Datei, die aus
         * einem abgebrochenen `db export` stammt.
         */
        $probe = self::dump_head( $plain );

        if ( '' === $probe || ( ! str_contains( $probe, 'CREATE TABLE' ) && ! str_contains( $probe, 'INSERT INTO' ) ) ) {
            if ( $plain !== $path ) {
                wp_delete_file( $plain );
            }

            WP_CLI::error(
                sprintf(
                    /* translators: %s: dump path. */
                    __( 'The dump %s does not look like a database dump — nothing was imported.', 'crea-bootstrap-blocks' ),
                    $path
                )
            );

            return;
        }

        WP_CLI::runcommand(
            'db import ' . escapeshellarg( $plain ),
            [
                'return'     => 'all',
                'exit_error' => true,
            ]
        );

        if ( $plain !== $path ) {
            wp_delete_file( $plain );
        }

        \wp_cache_flush();

        /*
         * UND DERSELBE PRERENDER-CACHE WIE NACH DEM LAUF. Der Rueckweg ist eine
         * Inhaltsaenderung wie jede andere: Blockstudio haelt fertiges HTML je
         * URL auf Platte und liefert es aus, bevor ein Block gerendert wird.
         * Ohne diesen Schritt zeigte die Seite nach dem Rollback weiter genau
         * das Nachher-HTML, das der Rollback gerade verworfen hat — und der
         * Rendervergleich der Abnahme maesse den falschen Zustand.
         */
        $this->flush_prerender_cache();

        WP_CLI::success(
            sprintf(
                /* translators: %s: dump path. */
                __( 'Restored: %s', 'crea-bootstrap-blocks' ),
                $path
            )
        );
    }

    /**
     * Empties Blockstudio's static prerender cache.
     *
     * ZWEI AUFRUFER, EINE FASSUNG: Schritt 7 von `run()` und der Rueckweg
     * `rollback()`. Beide aendern den Inhalt der Datenbank, und beide muessen
     * denselben Cache raeumen — sonst liefert die Seite danach das HTML des
     * jeweils verworfenen Zustands, und der Rendervergleich der Abnahme misst
     * den falschen.
     *
     * BEIDE WACHEN SCHWEIGEN NICHT. Blockstudio ist eine Fremdabhaengigkeit
     * und darf seine Interna umbauen; ein Guard, der stillschweigend nichts
     * tut, waere schlimmer als gar keiner.
     */
    private function flush_prerender_cache(): void {
        if (
            class_exists( '\Blockstudio\Static_Prerender_Runtime' )
            // @phpstan-ignore function.impossibleType (Fremdklasse; die Wache ist Absicht, siehe oben.)
            && method_exists( '\Blockstudio\Static_Prerender_Runtime', 'purge' )
        ) {
            WP_CLI::log(
                sprintf(
                    /* translators: %d: number of removed cache files. */
                    __( '    Blockstudio static prerender cache: %d files removed', 'crea-bootstrap-blocks' ),
                    (int) \Blockstudio\Static_Prerender_Runtime::purge()
                )
            );
        } else {
            WP_CLI::warning(
                __(
                    'Blockstudio\Static_Prerender_Runtime::purge() is missing — the static prerender cache was NOT flushed. Run `wp bs prerender purge` or empty wp-content/blockstudio/cache by hand before comparing renderings.',
                    'crea-bootstrap-blocks'
                )
            );
        }

        if (
            class_exists( '\Blockstudio\Static_Prerender_Early_Serve' )
            // @phpstan-ignore function.impossibleType (Fremdklasse; die Wache ist Absicht, siehe oben.)
            && method_exists( '\Blockstudio\Static_Prerender_Early_Serve', 'remove_current_site' )
        ) {
            \Blockstudio\Static_Prerender_Early_Serve::remove_current_site();
        } else {
            WP_CLI::warning(
                __(
                    'Blockstudio\Static_Prerender_Early_Serve::remove_current_site() is missing — early serving may keep delivering the old HTML before WordPress loads. Check the advanced-cache drop-in and wp-content/blockstudio/cache by hand.',
                    'crea-bootstrap-blocks'
                )
            );
        }
    }

    /**
     * Writes the database dump, gzipped when the path says so.
     *
     * @param string $path Target path.
     */
    private function write_backup( string $path ): void {
        $directory = \dirname( $path );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Gefragt wird, ob DIESER Prozess sein Backup ablegen kann; WP_Filesystem antwortete ueber einen Transport, den es unter WP-CLI nicht gibt.
        if ( ! is_dir( $directory ) || ! is_writable( $directory ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: directory path. */
                    __( 'The backup directory %s is missing or not writable.', 'crea-bootstrap-blocks' ),
                    $directory
                )
            );
        }

        /*
         * EIN VORHANDENES BACKUP WIRD NICHT UEBERSCHRIEBEN.
         *
         * Der Fall, um den es geht: Der erste Lauf bricht ab, NACHDEM
         * `migrate_posts()` committed hat. Die Fehlermeldung nennt den Weg
         * zurueck — genau diesen Pfad. Wer den Lauf danach mit demselben
         * `--backup` wiederholt, ueberschreibt als Erstes das Backup, und zwar
         * mit einem Dump des halb migrierten Zustands. Der Rueckweg ist dann
         * weg, bevor irgendjemand ihn gebraucht hat.
         *
         * Der Ausweg ist ein anderer Pfad, nicht ein Schalter: Ein Backup, das
         * man ueberschreiben darf, ist keines.
         */
        if ( file_exists( $path ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: backup path. */
                    __( 'The backup %s already exists. Choose a different path — overwriting it would destroy the way back from an earlier run.', 'crea-bootstrap-blocks' ),
                    $path
                )
            );

            return;
        }

        $plain = str_ends_with( $path, '.gz' ) ? substr( $path, 0, -3 ) : $path;

        if ( $plain !== $path && file_exists( $plain ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: path of the uncompressed dump. */
                    __( 'The uncompressed dump %s already exists. Remove it or choose a different path.', 'crea-bootstrap-blocks' ),
                    $plain
                )
            );

            return;
        }

        WP_CLI::runcommand(
            'db export ' . escapeshellarg( $plain ),
            [
                'return'     => 'all',
                'exit_error' => true,
            ]
        );

        if ( $plain === $path ) {
            return;
        }

        /*
         * GESTREAMT, NICHT AM STUECK GELESEN. Ein Dump einer Produktivdatenbank
         * ist ohne Weiteres mehrere hundert Megabyte gross; `file_get_contents()`
         * darauf traefe `memory_limit`, und zwar genau dann, wenn das Backup am
         * dringendsten gebraucht wird.
         *
         * `WordPress.WP.AlternativeFunctions` schlaegt fuer die vier Aufrufe
         * WP_Filesystem vor. Das geht am Fall vorbei: WP_Filesystem kennt keinen
         * Datenstrom, sondern nur ganze Dateien, und antwortete je nach
         * Transport (FTP, SSH) ueber eine Verbindung, die es unter WP-CLI gar
         * nicht gibt.
         */
        // phpcs:disable WordPress.WP.AlternativeFunctions -- Begruendung im Block darueber.
        $in  = fopen( $plain, 'rb' );
        $out = gzopen( $path, 'wb9' );

        if ( false === $in || false === $out ) {
            if ( false !== $in ) {
                fclose( $in );
            }

            if ( false !== $out ) {
                gzclose( $out );
            }

            WP_CLI::error( __( 'The dump could not be compressed.', 'crea-bootstrap-blocks' ) );

            return;
        }

        $written = 0;

        while ( ! feof( $in ) ) {
            $chunk = (string) fread( $in, 262144 );
            $bytes = gzwrite( $out, $chunk );

            // GESCHRIEBEN HEISST NICHT ANGEKOMMEN. Eine volle Platte oder ein
            // Quota lassen `gzwrite()` weniger schreiben, als hineingegeben
            // wurde — ohne Ausnahme, ohne Rueckgabewert, den jemand ansieht.
            if ( false === $bytes || $bytes < strlen( $chunk ) ) {
                fclose( $in );
                gzclose( $out );

                WP_CLI::error(
                    sprintf(
                        /* translators: %s: backup path. */
                        __( 'The dump could not be compressed completely — %s is truncated. The uncompressed dump is kept.', 'crea-bootstrap-blocks' ),
                        $path
                    )
                );

                return;
            }

            $written += strlen( $chunk );
        }

        fclose( $in );
        gzclose( $out );

        /*
         * DER KLARTEXT FAELLT ERST, WENN DAS ARCHIV NACHWEISLICH VOLLSTAENDIG
         * IST. Regel M4 verlangt einen Rueckweg — ein abgeschnittenes Archiv
         * neben einem geloeschten Klartext ist keiner, und es faellt erst auf,
         * wenn jemand es braucht. Nachgezaehlt wird deshalb, was sich wieder
         * herauslesen laesst.
         */
        $verified = self::gz_byte_count( $path );

        if ( null === $verified || $verified !== $written ) {
            WP_CLI::error(
                sprintf(
                    /* translators: 1: backup path, 2: expected byte count, 3: byte count read back. */
                    __( 'The backup %1$s does not read back completely: %2$d bytes written, %3$d bytes read. The uncompressed dump is kept.', 'crea-bootstrap-blocks' ),
                    $path,
                    $written,
                    (int) $verified
                )
            );

            return;
        }

        wp_delete_file( $plain );
        // phpcs:enable WordPress.WP.AlternativeFunctions
    }

    /**
     * The first kilobytes of a dump, for a plausibility check.
     *
     * Gelesen wird ein Ausschnitt, nicht die Datei: Ein Dump ist ohne Weiteres
     * mehrere hundert Megabyte gross, und die Frage — sieht das ueberhaupt nach
     * einem Dump aus — beantwortet der Anfang.
     *
     * @param string $path Path of the dump.
     */
    private static function dump_head( string $path ): string {
        // phpcs:disable WordPress.WP.AlternativeFunctions -- Ein Ausschnitt aus einem sehr grossen Datenstrom; WP_Filesystem kennt nur ganze Dateien.
        $handle = fopen( $path, 'rb' );

        if ( false === $handle ) {
            return '';
        }

        $head = (string) fread( $handle, 65536 );

        fclose( $handle );
        // phpcs:enable WordPress.WP.AlternativeFunctions

        return $head;
    }

    /**
     * Reads a gzip archive back and counts its uncompressed bytes.
     *
     * Der Nachweis, dass ein Archiv vollstaendig ist. Er kostet einen zweiten
     * Durchlauf ueber die Datei und ist genau das wert: Ein abgeschnittenes
     * Backup faellt sonst erst auf, wenn es gebraucht wird.
     *
     * @param string $path Path of the archive.
     * @return int|null Byte count, or null when the archive cannot be read to its end.
     */
    private static function gz_byte_count( string $path ): ?int {
        // phpcs:disable WordPress.WP.AlternativeFunctions -- Ein Datenstrom; dieselbe Begruendung wie beim Schreiben.
        $handle = gzopen( $path, 'rb' );

        if ( false === $handle ) {
            return null;
        }

        $bytes = 0;

        while ( ! gzeof( $handle ) ) {
            $chunk = gzread( $handle, 262144 );

            if ( false === $chunk ) {
                gzclose( $handle );

                return null;
            }

            $bytes += strlen( $chunk );
        }

        gzclose( $handle );
        // phpcs:enable WordPress.WP.AlternativeFunctions

        return $bytes;
    }

    /**
     * Rewrites `wp_posts.post_content`, in one transaction.
     *
     * @param bool               $include_revisions Whether revisions are migrated.
     * @param array<int, string> $errors            Findings, by reference.
     * @return int Number of rewritten rows.
     */
    private function migrate_posts( bool $include_revisions, array &$errors ): int {
        global $wpdb;

        // DASSELBE MUSTER WIE DAS INVENTAR, und zwar der leerraumfreie Teil
        // `wp:areoi/`. Er trifft den oeffnenden UND den schliessenden Begrenzer
        // und ist unabhaengig davon, wie viel Leerraum hinter `<!--` steht —
        // der Blockparser des Cores erlaubt dort `\s+`. Ein enger gefasstes
        // Muster liesse Traeger ungeschrieben stehen, die `verify` danach als
        // Rest meldete.
        $patterns = Migration_Support::legacy_like_patterns();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_type, post_content FROM {$wpdb->posts}
                 WHERE post_content LIKE %s",
                ...$patterns
            )
        );

        if ( [] === (array) $posts ) {
            return 0;
        }

        $written = 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( 'START TRANSACTION' );

        foreach ( (array) $posts as $post ) {
            if ( 'revision' === (string) $post->post_type && ! $include_revisions ) {
                continue;
            }

            $result = Migration_Support::rewrite_value( (string) $post->post_content );

            if ( [] !== $result['errors'] ) {
                foreach ( $result['errors'] as $message ) {
                    $errors[] = sprintf( 'posts #%d: %s', (int) $post->ID, $message );
                }

                continue;
            }

            if ( 0 === $result['changed'] ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $updated = $wpdb->update(
                $wpdb->posts,
                [ 'post_content' => (string) $result['value'] ],
                [ 'ID' => (int) $post->ID ]
            );

            if ( false === $updated ) {
                $errors[] = sprintf( 'posts #%d: UPDATE fehlgeschlagen', (int) $post->ID );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query( 'ROLLBACK' );

                return 0;
            }

            \clean_post_cache( (int) $post->ID );
            ++$written;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaktionssteuerung; sie laesst sich weder vorbereiten noch cachen.
        if ( [] === $errors ) {
            $wpdb->query( 'COMMIT' );
        } else {
            $wpdb->query( 'ROLLBACK' );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        return [] === $errors ? $written : 0;
    }

    /**
     * Rewrites `wp_postmeta.meta_value`, in one transaction.
     *
     * KEIN ROHES UPDATE AUF meta_value. Ein Metawert kann serialisiert sein —
     * bei „Drittplugins, die Blockmarkup in Meta ablegen" (05-migration.md) ist
     * er es, sobald ein Array im Spiel ist. Dann steht der Blockkommentar als
     * Zeichenkette INNERHALB der Serialisierung, und der Attribut-Container
     * veraendert seine Bytelaenge (S-4). Ein zurueckgeschriebener Rohstring
     * traegt danach falsche `s:<n>:`-Praefixe und ist nicht mehr
     * deserialisierbar — und der Zaehlabgleich merkt es NICHT, weil die
     * LIKE-Suche den Blockkommentar im kaputten String weiter findet und die
     * Attributkarte aus derselben flachgelegten Textform kommt. Bein 1 meldete
     * gruen auf einen zerstoerten Metawert.
     *
     * Deshalb: auspacken mit `rewrite_meta_value()`, ersetzen im PHP-Wert,
     * zurueckschreiben ueber `update_metadata_by_mid()`. Die Meta-API
     * serialisiert selbst (`maybe_serialize()`) und leert den Metacache des
     * Posts gleich mit — das frueher noetige `wp_cache_delete()` entfaellt
     * dadurch.
     *
     * `update_post_meta()` waere der falsche Weg: Ohne vierten Parameter
     * schreibt es JEDE Zeile dieses Schluessels an diesem Post und faltete
     * mehrfach vorkommende Schluessel auf einen Wert zusammen —
     * derselbe Datenverlust an anderer Stelle. `update_metadata_by_mid()`
     * trifft genau die Zeile, die die Suche gefunden hat.
     *
     * @param array<int, string> $errors Findings, by reference.
     * @return int Number of rewritten rows.
     */
    private function migrate_postmeta( array &$errors ): int {
        global $wpdb;

        // Oeffnende UND schliessende Form in einem Muster, dasselbe wie das
        // Inventar: der leerraumfreie Teil `wp:areoi/`.
        $patterns = Migration_Support::legacy_like_patterns();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_value LIKE %s",
                ...$patterns
            )
        );

        if ( [] === (array) $rows ) {
            return 0;
        }

        $written = 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( 'START TRANSACTION' );

        foreach ( (array) $rows as $row ) {
            $result = Migration_Support::rewrite_meta_value( (string) $row->meta_value );

            if ( [] !== $result['errors'] ) {
                foreach ( $result['errors'] as $message ) {
                    $errors[] = sprintf( 'postmeta #%d: %s', (int) $row->meta_id, $message );
                }

                continue;
            }

            if ( 0 === $result['changed'] ) {
                continue;
            }

            // DIE META-API, NICHT $wpdb->update(). Sie serialisiert den Wert
            // wieder — mit stimmigen Laengenpraefixen — und leert den Cache.
            $updated = \update_metadata_by_mid( 'post', (int) $row->meta_id, $result['value'] );

            if ( true !== $updated ) {
                $errors[] = sprintf(
                    'postmeta #%d: update_metadata_by_mid() fehlgeschlagen',
                    (int) $row->meta_id
                );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query( 'ROLLBACK' );

                return 0;
            }

            ++$written;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaktionssteuerung; sie laesst sich weder vorbereiten noch cachen.
        if ( [] === $errors ) {
            $wpdb->query( 'COMMIT' );
        } else {
            $wpdb->query( 'ROLLBACK' );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        return [] === $errors ? $written : 0;
    }

    /**
     * Rewrites the serialized options.
     *
     * KEIN SQL. `get_option()` liefert den ausgepackten PHP-Wert,
     * `update_option()` serialisiert ihn wieder — nur so bleiben die
     * Laengenpraefixe stimmig.
     *
     * DIESE METHODE HAT KEINE TRANSAKTION. `wp_options` wird ueber die
     * WordPress-API geschrieben, und die kennt kein ROLLBACK. Zwei Vorkehrungen
     * treten an dessen Stelle: Sie laeuft ueberhaupt nicht los, wenn eine
     * fruehere Schreibmethode etwas zu beanstanden hatte — sonst stuende eine
     * migrierte Option neben einer zurueckgerollten Tabelle —, und sie merkt
     * sich jeden alten Wert, um ihn im Fehlerfall von Hand zurueckzuschreiben.
     *
     * @param array<int, string> $errors Findings, by reference.
     * @return int Number of rewritten options.
     */
    private function migrate_options( array &$errors ): int {
        if ( [] !== $errors ) {
            return 0;
        }

        $written = 0;
        $restore = [];

        foreach ( Migration_Support::block_option_names() as $name ) {
            $value = \get_option( $name );

            if ( false === $value ) {
                continue;
            }

            $result = Migration_Support::rewrite_value( $value );

            if ( [] !== $result['errors'] ) {
                foreach ( $result['errors'] as $message ) {
                    $errors[] = sprintf( 'options:%s: %s', $name, $message );
                }

                break;
            }

            if ( 0 === $result['changed'] ) {
                continue;
            }

            $restore[ $name ] = $value;

            if ( false === \update_option( $name, $result['value'] ) ) {
                $errors[] = sprintf( 'options:%s: update_option() fehlgeschlagen', $name );

                break;
            }

            ++$written;
        }

        if ( [] !== $errors ) {
            // Handrollback. Er kann selbst scheitern; dann bleibt das Backup.
            foreach ( $restore as $name => $previous ) {
                \update_option( $name, $previous );
            }

            return 0;
        }

        return $written;
    }

    /**
     * Ends the run when a write method has left a finding.
     *
     * @param array<int, string> $errors      Findings collected so far.
     * @param string             $backup_path Path of the backup, empty when none was taken.
     */
    private function halt_on_write_errors( array $errors, string $backup_path ): void {
        if ( [] === $errors ) {
            return;
        }

        foreach ( $errors as $message ) {
            WP_CLI::warning( $message );
        }

        WP_CLI::error(
            sprintf(
                /* translators: 1: number of findings, 2: backup path. */
                __( '%1$d carriers could not be rewritten. Restore the backup: %2$s', 'crea-bootstrap-blocks' ),
                count( $errors ),
                '' !== $backup_path ? $backup_path : __( '(none taken)', 'crea-bootstrap-blocks' )
            )
        );
    }
}
