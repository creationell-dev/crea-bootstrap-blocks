<?php
/**
 * WP-CLI: captures and compares rendered pages (06-verifikation.md, leg 2).
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
 * `wp creabb snapshot`
 */
class Snapshot_Command {

    /**
     * Prints every URL whose rendering is affected by the migration.
     *
     * Sucht `wp:areoi/` UND `wp:creabb/`, damit der Befehl vor und nach der
     * Migration lauffaehig ist. Fuer die Abnahme wird trotzdem EINE vor der
     * Migration erzeugte Liste fuer beide Aufnahmen benutzt — nur so
     * vergleicht der Diff dieselben Seiten.
     *
     * WOHER DIE TRAEGER KOMMEN: aus `Migration_Support::snapshot()`, dem
     * Inventar, das auch Bein 1 fuehrt. Es gibt im Repo genau EINE Stelle, an
     * der die Traegermenge entsteht; eine zweite Abfrage mit einem eigenen
     * Muster liesse die Differenz genau dort stehen, wo niemand hinsieht.
     * Damit kommen `wp_postmeta` und die E-113-Behandlung einer kaputt
     * serialisierten Option gratis mit.
     *
     * DIE AUSGABE DIESES BEFEHLS IST ZUGLEICH DIE EINGABE VON BEIN 3
     * (`bin/creabb-shot.php plan --urls=…`). Format: eine absolute URL je
     * Zeile, `#` leitet einen Kommentar ein.
     *
     * ## OPTIONS
     *
     * [--out=<file>]
     * : Write the list to this file instead of stdout.
     *
     * [--max-terms=<number>]
     * : Upper bound for taxonomy archive URLs. Default: 50.
     *
     * [--skip-archives]
     * : Skip archive and taxonomy URLs even when a template is affected.
     *
     * ## EXAMPLES
     *
     *     wp creabb snapshot urls --out=urls.txt
     *     wp creabb snapshot urls --max-terms=200 --out=urls.txt
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function urls( array $args, array $assoc_args ): void {
        unset( $args );

        $max_terms = isset( $assoc_args['max-terms'] ) ? max( 0, (int) $assoc_args['max-terms'] ) : 50;

        // `--skip-archives`, NICHT `--no-archives`. WP-CLI zerlegt jedes
        // Argument der Form `--no-x` in den Schluessel `x` mit dem Wert false
        // (Configurator.php), waehrend die Synopsis den Flag unter dem Namen
        // `no-archives` fuehrt. Die Folge ist doppelt falsch: `isset(
        // $assoc_args['no-archives'] )` ist nie wahr, und der Befehl bricht
        // vorher schon mit „unknown --archives parameter" ab. Die positive Form
        // ist im Repo bereits etabliert — `--skip-revisions` und
        // `--skip-backup` in `class-migrate-command.php`.
        $archives = ! isset( $assoc_args['skip-archives'] );

        $inventory = Migration_Support::snapshot( false );

        foreach ( $inventory['errors'] as $message ) {
            WP_CLI::warning( (string) $message );
        }

        $urls         = [];
        $reusable     = [];
        $templates    = false;
        $public_types = get_post_types( [ 'public' => true ], 'names' );

        foreach ( $inventory['rows'] as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['source'], $row['id'] ) ) {
                continue;
            }

            $source = (string) $row['source'];

            if ( ! str_starts_with( $source, 'posts:' ) ) {
                continue;
            }

            $post_type = substr( $source, strlen( 'posts:' ) );
            $post_id   = (int) $row['id'];

            if ( 'wp_block' === $post_type ) {
                $reusable[] = $post_id;

                continue;
            }

            if ( in_array( $post_type, Snapshot_Urls::NON_RENDERING_POST_TYPES, true ) ) {
                $templates = true;

                continue;
            }

            if ( isset( $public_types[ $post_type ] ) && 'publish' === get_post_status( $post_id ) ) {
                $permalink = get_permalink( $post_id );

                if ( is_string( $permalink ) ) {
                    $urls[] = $permalink;
                }
            }
        }

        // Reusable Blocks haben keine eigene URL — gesucht wird, wer sie einbindet.
        foreach ( $reusable as $block_id ) {
            foreach ( $this->reusable_hosts( $block_id ) as $host_id ) {
                $permalink = get_permalink( $host_id );

                if ( is_string( $permalink ) ) {
                    $urls[] = $permalink;
                }
            }
        }

        // Traeger ohne eigene Adresse. Ein Block-Widget erscheint seitenweit,
        // die Startseite genuegt als Nachweis; ein Metawert gehoert zu einem
        // Beitrag, dessen Adresse dieses Werkzeug aus der `meta_id` nicht
        // ableiten kann. Beides wird GENANNT statt stillschweigend weggelassen
        // — eine Liste, die schweigt, sieht vollstaendig aus.
        $other = Snapshot_Urls::other_carriers( $inventory['rows'] );

        if ( isset( $other['options'] ) ) {
            $templates = true;
        }

        foreach ( $other as $kind => $count ) {
            WP_CLI::warning(
                sprintf(
                    /* translators: 1: number of carriers, 2: source kind, e.g. postmeta */
                    __(
                        '%1$d carrier(s) of kind "%2$s" have no URL of their own. They render inside pages; check that those pages are in the list.',
                        'crea-bootstrap-blocks'
                    ),
                    $count,
                    $kind
                )
            );
        }

        if ( $templates && $archives ) {
            $urls = array_merge( $urls, $this->archive_urls( $max_terms ) );
        }

        $urls = Snapshot_Urls::unique_urls( $urls );

        if ( [] === $urls ) {
            WP_CLI::warning( __( 'No affected URL found.', 'crea-bootstrap-blocks' ) );
        }

        if ( isset( $assoc_args['out'] ) ) {
            $out = (string) $assoc_args['out'];

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Dieses Kommando laeuft unter WP-CLI ohne WP_Filesystem; das Ziel nennt der Aufrufer.
            if ( false === file_put_contents( $out, implode( "\n", $urls ) . "\n" ) ) {
                WP_CLI::error(
                    sprintf(
                        /* translators: %s: file path */
                        __( 'Could not write: %s', 'crea-bootstrap-blocks' ),
                        $out
                    )
                );
            }

            WP_CLI::success(
                sprintf(
                    /* translators: 1: number of URLs, 2: file path */
                    __( '%1$d URLs written to %2$s', 'crea-bootstrap-blocks' ),
                    count( $urls ),
                    $out
                )
            );

            return;
        }

        foreach ( $urls as $url ) {
            WP_CLI::line( $url );
        }
    }

    /**
     * Captures the rendered HTML and the generated inline CSS of every URL.
     *
     * Aufgenommen wird UNVERAENDERT. Normalisiert wird erst beim Vergleich —
     * sonst laesst sich ein Befund nie mehr an der Quelle nachsehen.
     *
     * ## OPTIONS
     *
     * --out=<directory>
     * : Directory the snapshot is written to. Created when missing.
     *
     * --urls=<file>
     * : File with one URL per line, as produced by `wp creabb snapshot urls`.
     *
     * [--timeout=<seconds>]
     * : Request timeout per URL. Default: 60.
     *
     * ## EXAMPLES
     *
     *     wp creabb snapshot create --out=snapshots/vorher --urls=urls.txt
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function create( array $args, array $assoc_args ): void {
        unset( $args );

        $out     = (string) ( $assoc_args['out'] ?? '' );
        $list    = (string) ( $assoc_args['urls'] ?? '' );
        $timeout = isset( $assoc_args['timeout'] ) ? max( 1, (int) $assoc_args['timeout'] ) : 60;

        if ( '' === $out ) {
            WP_CLI::error( __( '--out=<directory> is required.', 'crea-bootstrap-blocks' ) );
        }

        if ( '' === $list || ! is_readable( $list ) ) {
            WP_CLI::error( __( '--urls=<file> is required and must be readable.', 'crea-bootstrap-blocks' ) );
        }

        $lines = file( $list, FILE_IGNORE_NEW_LINES );
        $urls  = Snapshot_Urls::unique_urls( is_array( $lines ) ? $lines : [] );

        if ( [] === $urls ) {
            WP_CLI::error( __( 'The URL list is empty.', 'crea-bootstrap-blocks' ) );
        }

        if ( ! wp_mkdir_p( $out ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: directory path */
                    __( 'Could not create directory: %s', 'crea-bootstrap-blocks' ),
                    $out
                )
            );
        }

        $entries = [];
        $failed  = [];

        /*
         * KEIN FORTSCHRITTSBALKEN. `WP_CLI\Utils\make_progress_bar()` gibt ein
         * `cli\progress\Bar` zurueck, das in `wp-cli-tools-stubs.php` steht —
         * einer Datei, die `wp-cli-stubs.php` nicht einbindet. Jeder Aufruf
         * darauf ist damit ein Fehler der Stufe 8, und `composer check` waere
         * rot. Die bestehenden Kommandos dieses Plugins kommen ohne Balken aus;
         * eine Zeile je URL ist in einem Protokoll ohnehin brauchbarer als ein
         * Balken, der nach dem Lauf verschwunden ist.
         */
        foreach ( $urls as $url ) {
            $response = wp_remote_get(
                $url,
                [
                    'timeout'     => $timeout,
                    'redirection' => 5,
                    'sslverify'   => false,
                    'user-agent'  => 'crea-bootstrap-blocks-snapshot',
                ]
            );

            if ( is_wp_error( $response ) ) {
                $failed[] = $url;

                WP_CLI::warning( sprintf( '%s — %s', $url, $response->get_error_message() ) );

                continue;
            }

            $status = (int) wp_remote_retrieve_response_code( $response );
            $html   = (string) wp_remote_retrieve_body( $response );

            /*
             * `extract_inline_css()` bricht bei einem Abbruch der Regex-Engine
             * ab, statt still den Leerstring zu liefern. Das gilt hier je URL:
             * Eine Seite, deren Inline-CSS nicht ausgeschnitten werden konnte,
             * faellt als nicht aufgenommen aus — sie darf nicht mit einer
             * leeren `.css` danebenstehen und spaeter als „identisch" gelten.
             */
            try {
                $css = Snapshot_Diff::extract_inline_css( $html );
            } catch ( \RuntimeException $error ) {
                $failed[] = $url;

                WP_CLI::warning( sprintf( '%s — %s', $url, $error->getMessage() ) );

                continue;
            }

            if ( 200 !== $status ) {
                $failed[] = $url;

                WP_CLI::warning( sprintf( '%s — HTTP %d', $url, $status ) );
            }

            $entry = Snapshot_Urls::index_entry( $url, $status, $html, $css );

            $this->write_snapshot_file( $out . '/' . (string) $entry['html'], $html );
            $this->write_snapshot_file( $out . '/' . (string) $entry['css'], $css );

            $entries[] = $entry;

            WP_CLI::log( sprintf( '%s — HTTP %d, %d Byte', $url, $status, strlen( $html ) ) );
        }

        $index = Snapshot_Urls::index( $entries, count( $urls ), $failed, (string) home_url(), gmdate( 'c' ) );

        $this->write_snapshot_file( $out . '/index.json', (string) wp_json_encode( $index, JSON_PRETTY_PRINT ) );

        if ( [] !== $failed ) {
            WP_CLI::error(
                sprintf(
                    /* translators: 1: number of failed URLs, 2: number of URLs */
                    __( '%1$d of %2$d URLs could not be captured — the snapshot is incomplete.', 'crea-bootstrap-blocks' ),
                    count( $failed ),
                    count( $urls )
                )
            );
        }

        WP_CLI::success(
            sprintf(
                /* translators: 1: number of URLs, 2: directory */
                __( '%1$d URLs captured into %2$s', 'crea-bootstrap-blocks' ),
                count( $entries ),
                $out
            )
        );
    }

    /**
     * Writes one snapshot file or aborts.
     *
     * Direkter Dateizugriff ist hier richtig: Das ist ein WP-CLI-Werkzeug mit
     * einem vom Aufrufer bestimmten Zielpfad, kein Laufzeitschreibzugriff des
     * Plugins (09-spike-und-bauplan.md, Teil 10).
     *
     * @param string $path     Target path.
     * @param string $contents File contents.
     */
    private function write_snapshot_file( string $path, string $contents ): void {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Begruendung im Block darueber: CLI-Werkzeug ohne WP_Filesystem, Zielpfad vom Aufrufer.
        if ( false === file_put_contents( $path, $contents ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: file path */
                    __( 'Could not write: %s', 'crea-bootstrap-blocks' ),
                    $path
                )
            );
        }
    }

    /**
     * Compares two snapshots and reports every deviation.
     *
     * Erwartete Abweichungen werden einzeln als solche ausgewiesen
     * (06-verifikation.md, Nummern 1 bis 7); alles andere ist ein Fehler und
     * faerbt den Exit-Code.
     *
     * ## OPTIONS
     *
     * <before>
     * : Directory of the reference snapshot.
     *
     * <after>
     * : Directory of the compared snapshot.
     *
     * [--format=<format>]
     * : `text` (default) or `json`.
     *
     * [--only-errors]
     * : Print URLs with errors only. The balance still counts everything.
     *
     * ## EXAMPLES
     *
     *     wp creabb snapshot diff snapshots/vorher snapshots/nachher
     *
     * @when after_wp_load
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function diff( array $args, array $assoc_args ): void {
        if ( count( $args ) < 2 ) {
            WP_CLI::error( __( 'Two snapshot directories are required.', 'crea-bootstrap-blocks' ) );
        }

        $before_dir = rtrim( $args[0], '/' );
        $after_dir  = rtrim( $args[1], '/' );
        $format     = (string) ( $assoc_args['format'] ?? 'text' );
        $only_error = isset( $assoc_args['only-errors'] );

        $before_index = $this->read_index( $before_dir );
        $after_index  = $this->read_index( $after_dir );

        $paired = Snapshot_Diff::pair_indexes( $before_index, $after_index );

        /*
         * DIE ERHEBUNG UEBER DEN BESTAND, einmal fuer den ganzen Lauf. Sie
         * erklaert die erwartete Abweichung 7 — eine im Nachher fehlende
         * CSS-Regel, deren Wert die Wertpruefung des Nachbaus nicht besteht.
         * Sie kommt aus dem Inventar und nicht aus der Aufnahme: In einer
         * gerenderten Seite stehen keine Blockbegrenzer, die Erhebung bliebe
         * dort leer, und JEDE solche Luecke wuerde zum blockierenden Fehler.
         */
        $survey = Snapshot_Diff::survey( Migration_Support::carrier_texts( false ) );

        $reports = [];

        foreach ( $paired['pairs'] as $pair ) {
            /*
             * Die beiden `.css`-Dateien der Aufnahme werden hier NICHT gelesen.
             * Sie stehen als lesbares Beiwerk daneben und tragen beide Handles
             * verkettet; fuer den Vergleich waere das die falsche Form.
             * `compare()` schneidet stattdessen aus dem HTML — VORHER das
             * Stylesheet des Originals, NACHHER das des Nachbaus. Damit werden
             * auch bestehende Aufnahmen richtig bewertet.
             */
            $findings = Snapshot_Diff::compare(
                $this->read_snapshot_file( $before_dir . '/' . (string) $pair['before']['html'] ),
                $this->read_snapshot_file( $after_dir . '/' . (string) $pair['after']['html'] ),
                $survey['rejected']
            );

            $reports[] = [
                'url'      => (string) $pair['url'],
                'findings' => $findings,
            ];
        }

        /*
         * DER ALLOWLIST-NACHWEIS, einmal fuer den ganzen Lauf. Er ist eine
         * Aussage ueber den Bestand und keine Abweichung einer Seite — deshalb
         * steht er als eigener Bericht ohne URL und nicht je Seite wiederholt.
         * Er erscheint auch dann, wenn der Diff leer ist: Genau das IST seine
         * Aussage. `06-verifikation.md` sagt dazu, ein leerer Diff belege fuer
         * sich genommen nichts.
         */
        $reports[] = [
            'url'      => '(Bestand)',
            'findings' => Snapshot_Diff::classify_button_tags( $survey['button_types'] ),
        ];

        $verdict = Snapshot_Diff::run_exit_code(
            $reports,
            $paired,
            [
                $before_dir => $before_index,
                $after_dir  => $after_index,
            ]
        );

        if ( 'json' === $format ) {
            /*
             * NUR JSON, NICHTS DAHINTER. Der Plan gab Leerzeile und Bilanz
             * hinter dem Dokument aus — damit ist die Ausgabe insgesamt kein
             * gueltiges JSON mehr und `| jq` scheitert. Die Bilanz gehoert
             * deshalb IN das Dokument. `wp creabb migrate scan` haelt es
             * genauso.
             */
            $totals = Snapshot_Diff::totals( $reports );

            WP_CLI::line(
                (string) wp_json_encode(
                    [
                        'urls'     => count( $reports ),
                        'expected' => $totals['expected'],
                        'errors'   => $totals['errors'],
                        'missing'  => $paired['missing'],
                        'extra'    => $paired['extra'],
                        'reasons'  => $verdict['reasons'],
                        'exit'     => $verdict['code'],
                        'reports'  => $reports,
                    ],
                    JSON_PRETTY_PRINT
                )
            );

            if ( 0 !== $verdict['code'] ) {
                WP_CLI::halt( $verdict['code'] );
            }

            return;
        }

        foreach ( $paired['missing'] as $url ) {
            WP_CLI::warning(
                sprintf(
                    /* translators: %s: URL */
                    __( 'Missing in the second snapshot: %s', 'crea-bootstrap-blocks' ),
                    $url
                )
            );
        }

        foreach ( $paired['extra'] as $url ) {
            WP_CLI::warning(
                sprintf(
                    /* translators: %s: URL */
                    __( 'Only in the second snapshot: %s', 'crea-bootstrap-blocks' ),
                    $url
                )
            );
        }

        foreach ( $reports as $report ) {
            if ( $only_error && 0 === Snapshot_Diff::tally( $report['findings'] )['errors'] ) {
                continue;
            }

            foreach ( Snapshot_Diff::report_lines( $report['url'], $report['findings'] ) as $line ) {
                WP_CLI::line( $line );
            }
        }

        WP_CLI::line( '' );
        WP_CLI::line( Snapshot_Diff::summary_line( $reports ) );

        foreach ( $verdict['reasons'] as $reason ) {
            WP_CLI::warning( $reason );
        }

        if ( 0 !== $verdict['code'] ) {
            WP_CLI::halt( $verdict['code'] );
        }

        WP_CLI::success( __( 'No unexpected deviation.', 'crea-bootstrap-blocks' ) );
    }

    /**
     * Reads one file of a snapshot or aborts.
     *
     * Der Rueckgabewert wird GEPRUEFT, nicht nach `(string)` gecastet: `false`
     * wuerde dabei zum Leerstring, und eine unlesbare Aufnahme ergaebe einen
     * Vergleich gegen nichts — der als „identisch" durchginge.
     *
     * @param string $path Path inside a snapshot directory.
     */
    private function read_snapshot_file( string $path ): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Gelesen wird eine lokale Aufnahmedatei, kein entfernter URL; dieses CLI-Werkzeug hat kein WP_Filesystem.
        $contents = is_readable( $path ) ? file_get_contents( $path ) : false;

        if ( ! is_string( $contents ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: file path */
                    __( 'Snapshot file not readable: %s', 'crea-bootstrap-blocks' ),
                    $path
                )
            );
        }

        return (string) $contents;
    }

    /**
     * Reads and decodes the index of one snapshot directory.
     *
     * @param string $directory Snapshot directory.
     * @return array<string, mixed>
     */
    private function read_index( string $directory ): array {
        $path  = $directory . '/index.json';
        $index = json_decode( $this->read_snapshot_file( $path ), true );

        if ( ! is_array( $index ) ) {
            WP_CLI::error(
                sprintf(
                    /* translators: %s: file path */
                    __( 'Snapshot index is not valid JSON: %s', 'crea-bootstrap-blocks' ),
                    $path
                )
            );
        }

        return $index;
    }

    /**
     * The published posts that embed one reusable block.
     *
     * @param int $block_id Post ID of the `wp_block`.
     * @return array<int, int>
     */
    private function reusable_hosts( int $block_id ): array {
        global $wpdb;

        // `instanceof \wpdb` (E-90): Ohne diese Wache ist `$wpdb` fuer die
        // statische Analyse `mixed`, und jeder Aufruf darauf bleibt ungeprueft.
        if ( ! $wpdb instanceof \wpdb ) {
            return [];
        }

        $like = '%' . $wpdb->esc_like( '"ref":' . $block_id ) . '%';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Einmalige Erhebung eines CLI-Kommandos; ein Cache brächte hier nichts und verfaelschte den Nachweis.
        $hosts = $wpdb->get_col(
            $wpdb->prepare(
                // DER TABELLENNAME GEHT ALS `%i`, nicht interpoliert (E-89):
                // Nur so bleibt das erste Argument von `prepare()` ein literaler
                // String, wie ihn die statische Analyse auf Stufe 8 verlangt.
                'SELECT ID FROM %i WHERE post_status = %s AND post_type != %s AND post_content LIKE %s',
                $wpdb->posts,
                'publish',
                'revision',
                $like
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        $ids = [];

        foreach ( (array) $hosts as $host_id ) {
            $ids[] = (int) $host_id;
        }

        return $ids;
    }

    /**
     * Collects archive and taxonomy URLs affected templates render on.
     *
     * @param int $max_terms Upper bound for term archive URLs.
     * @return array<int, string>
     */
    private function archive_urls( int $max_terms ): array {
        $urls = [ home_url( '/' ) ];

        $page_for_posts = (int) get_option( 'page_for_posts' );

        if ( $page_for_posts > 0 ) {
            $permalink = get_permalink( $page_for_posts );

            if ( is_string( $permalink ) ) {
                $urls[] = $permalink;
            }
        }

        $archive_types = get_post_types(
            [
                'public'      => true,
                'has_archive' => true,
            ],
            'names'
        );

        foreach ( $archive_types as $post_type ) {
            $link = get_post_type_archive_link( (string) $post_type );

            if ( is_string( $link ) ) {
                $urls[] = $link;
            }
        }

        if ( $max_terms < 1 ) {
            return $urls;
        }

        $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

        if ( [] === $taxonomies ) {
            return $urls;
        }

        $terms = get_terms(
            [
                'taxonomy'   => array_values( $taxonomies ),
                'hide_empty' => true,
                'number'     => $max_terms,
            ]
        );

        if ( ! is_array( $terms ) ) {
            return $urls;
        }

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );

            if ( is_string( $link ) ) {
                $urls[] = $link;
            }
        }

        if ( count( $terms ) >= $max_terms ) {
            WP_CLI::warning(
                sprintf(
                    /* translators: %d: term limit */
                    __(
                        'Term archive limit of %d reached — raise --max-terms for a complete list.',
                        'crea-bootstrap-blocks'
                    ),
                    $max_terms
                )
            );
        }

        return $urls;
    }
}
