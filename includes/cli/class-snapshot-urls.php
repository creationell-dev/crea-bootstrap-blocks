<?php
/**
 * Pure helpers for the URL list and the snapshot index.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks\CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * File naming and list handling for `wp creabb snapshot`.
 */
class Snapshot_Urls {

    /**
     * Builds a readable, collision-free file stem for one URL.
     *
     * Der Pruefsummen-Anteil ist nicht Kosmetik: Zwei URLs, die sich nur im
     * Query unterscheiden, wuerden sonst dieselbe Datei ueberschreiben, und
     * der Diff verglich spaeter zwei Seiten, die nie zusammengehoert haben.
     *
     * @param string $url Absolute URL.
     */
    public static function slug( string $url ): string {
        $stem = (string) preg_replace( '#^https?://#i', '', trim( $url ) );
        $stem = strtolower( (string) preg_replace( '#[^A-Za-z0-9]+#', '-', $stem ) );
        $stem = trim( $stem, '-' );

        if ( '' === $stem ) {
            $stem = 'root';
        }

        if ( strlen( $stem ) > 60 ) {
            $stem = rtrim( substr( $stem, 0, 60 ), '-' );
        }

        return $stem . '-' . substr( md5( $url ), 0, 8 );
    }

    /**
     * Trims, filters and de-duplicates a URL list, preserving order.
     *
     * @param array<int, string> $urls Raw lines.
     * @return array<int, string>
     */
    public static function unique_urls( array $urls ): array {
        $clean = [];

        foreach ( $urls as $url ) {
            $url = trim( $url );

            if ( '' === $url || str_starts_with( $url, '#' ) ) {
                continue;
            }

            if ( ! preg_match( '#^https?://#i', $url ) ) {
                continue;
            }

            $clean[ $url ] = true;
        }

        return array_keys( $clean );
    }

    /**
     * The post IDs among the carriers of the stock inventory.
     *
     * WARUM AUS DEM INVENTAR UND NICHT AUS EINER EIGENEN ABFRAGE. Die
     * Traegermenge entsteht im Repo an EINER Stelle, in
     * `Migration_Support::carriers()`. Ihr Docblock sagt, warum: Suchte der
     * Lauf mit einem anderen Muster als das Inventar, bliebe die Differenz
     * genau dort stehen, wo niemand hinsieht. Bein 1 und Bein 2 sehen so
     * dieselbe Menge — samt `wp_postmeta`, samt der E-113-Behandlung einer
     * kaputt serialisierten Option.
     *
     * WARUM NUR `posts:`. Das Feld `id` bedeutet je Quelle etwas anderes: bei
     * `posts:` die Post-ID, bei `postmeta:` die `meta_id`, bei `options:` den
     * Optionsnamen. Wer das nicht trennt, reicht eine `meta_id` an
     * `get_permalink()` — und trifft damit einen beliebigen fremden Beitrag
     * oder nichts. Gemessen an einer echten Trägerzeile je Quelle lieferte die
     * blinde Fassung `[ 12, 9184 ]`, wobei 9184 eine `meta_id` war.
     *
     * @param array<int, array<string, mixed>> $rows Rows of `Migration_Support::snapshot()`.
     * @return array<int, int> Ascending, without duplicates.
     */
    public static function post_ids_from_rows( array $rows ): array {
        $ids = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['source'], $row['id'] ) ) {
                continue;
            }

            if ( ! str_starts_with( (string) $row['source'], 'posts:' ) ) {
                continue;
            }

            $id = (int) $row['id'];

            if ( $id > 0 ) {
                $ids[ $id ] = true;
            }
        }

        $ids = array_keys( $ids );
        sort( $ids );

        return $ids;
    }

    /**
     * Post types that never carry a front-end URL of their own.
     *
     * Wiederverwendbare Bloecke und die drei FSE-Typen werden eingebettet, nicht
     * aufgerufen. `get_permalink()` liefert fuer sie zwar eine Adresse — sie
     * fuehrt aber auf eine 404-Seite.
     *
     * DIESELBE REGEL STEHT IN `class-snapshot-command.php` und wird von dort
     * benutzt; sie liegt hier, weil eine Entscheidung, die nur im Kommando
     * steht, von keiner Suite ausgefuehrt wird (E-135).
     *
     * @var array<int, string>
     */
    public const NON_RENDERING_POST_TYPES = [ 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ];

    /**
     * Whether a carrier source names a post type that can have its own URL.
     *
     * Eine Quelle, die gar kein `posts:` ist, hat erst recht keine — bei
     * `postmeta:` bedeutet `id` eine `meta_id`, bei `options:` einen
     * Optionsnamen.
     *
     * @param string $source Source label from `Migration_Support::source_label()`.
     */
    public static function renders_own_url( string $source ): bool {
        if ( ! str_starts_with( $source, 'posts:' ) ) {
            return false;
        }

        return ! in_array(
            substr( $source, strlen( 'posts:' ) ),
            self::NON_RENDERING_POST_TYPES,
            true
        );
    }

    /**
     * The post IDs among the carriers whose post type can carry a URL.
     *
     * WOZU NEBEN `post_ids_from_rows()`. Jene Funktion liefert die Traeger, um
     * ueber sie zu BERICHTEN — ihr Vertrag ist von
     * `tests/test-snapshot-urls.php` festgenagelt, und sie soll auch den
     * `wp_block`-Traeger nennen, der auf keiner Seite steht. Wer aber eine Seite
     * ABRUFEN will, braucht die kleinere Menge.
     *
     * Der Unterschied war nicht theoretisch: Ueber die drei Bestaende waeren
     * 24 von 133 Traegern (18,0 %) als Probe ein 404-Abruf gewesen. Dass heute
     * auf allen drei Instanzen die NIEDRIGSTE Kennung ein veroeffentlichter
     * Beitrag ist, war Glueck — ein einziger Statuswechsel kippt es.
     *
     * Der `post_status` bleibt bewusst DRAUSSEN: Er ist keine Eigenschaft der
     * Zeile, sondern eine Abfrage an WordPress. Diese Funktion bleibt damit
     * ohne WordPress pruefbar.
     *
     * @param array<int, array<string, mixed>> $rows Rows of `Migration_Support::snapshot()`.
     * @return array<int, int> Ascending, without duplicates.
     */
    public static function renderable_post_ids_from_rows( array $rows ): array {
        $ids = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['source'], $row['id'] ) ) {
                continue;
            }

            if ( ! self::renders_own_url( (string) $row['source'] ) ) {
                continue;
            }

            $id = (int) $row['id'];

            if ( $id > 0 ) {
                $ids[ $id ] = true;
            }
        }

        $ids = array_keys( $ids );
        sort( $ids );

        return $ids;
    }

    /**
     * The carriers that do NOT map to a URL of their own, by source kind.
     *
     * WARUM DAS GEMELDET WIRD statt still zu bleiben. Ein `widget_block` oder
     * ein Metawert traegt Blockmarkup, hat aber keine eigene Adresse — er
     * erscheint auf Seiten, die ihn einbinden. Ob diese Seiten in der Liste
     * stehen, kann dieses Werkzeug nicht wissen. Eine Liste, die solche
     * Traeger stillschweigend weglaesst, sieht vollstaendig aus; deshalb nennt
     * `wp creabb snapshot urls` ihre Zahl und ihre Art.
     *
     * @param array<int, array<string, mixed>> $rows Rows of `Migration_Support::snapshot()`.
     * @return array<string, int> Source kind => number of carriers.
     */
    public static function other_carriers( array $rows ): array {
        $kinds = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['source'] ) ) {
                continue;
            }

            $source = (string) $row['source'];

            if ( str_starts_with( $source, 'posts:' ) ) {
                continue;
            }

            $kind           = strtok( $source, ':' );
            $kind           = false === $kind ? $source : $kind;
            $kinds[ $kind ] = ( $kinds[ $kind ] ?? 0 ) + 1;
        }

        ksort( $kinds );

        return $kinds;
    }

    /**
     * Builds the snapshot index.
     *
     * WARUM `requested` UND `failed` DARIN STEHEN. Ohne sie enthaelt das
     * Verzeichnis nur, was aufgenommen WURDE — und sieht damit immer
     * vollstaendig aus. Eine URL, die in BEIDEN Laeufen ausgefallen ist
     * (Zeitueberschreitung, 502, Netzwerkfehler), stuende dann in keinem der
     * beiden Verzeichnisse; die Paarbildung faende sie weder unter `missing`
     * noch unter `extra`, der Vergleich meldete null Fehler und der Lauf endete
     * mit Exit 0. `06-verifikation.md` sagt aber „jede betroffene URL, nicht
     * eine Stichprobe" — und genau diese Zusage waere still gebrochen.
     *
     * Mit der angeforderten Zahl im Verzeichnis kann `snapshot diff` sie gegen
     * die Zahl der Eintraege halten und eskalieren.
     *
     * DER ZEITSTEMPEL WIRD HEREINGEREICHT, nicht hier geholt: Eine Funktion,
     * die die Uhr liest, ist nicht vergleichbar pruefbar.
     *
     * @param array<int, array<string, mixed>> $entries   Captured entries.
     * @param int                              $requested Number of URLs the run was asked to capture.
     * @param array<int, string>               $failed    URLs that could not be captured.
     * @param string                           $home      Home URL of the installation.
     * @param string                           $generated ISO 8601 timestamp of the run.
     * @return array<string, mixed>
     */
    public static function index( array $entries, int $requested, array $failed, string $home, string $generated ): array {
        return [
            'generated' => $generated,
            'home'      => $home,
            'requested' => $requested,
            'captured'  => count( $entries ),
            'failed'    => array_values( $failed ),
            'entries'   => array_values( $entries ),
        ];
    }

    /**
     * Builds one entry of the snapshot index.
     *
     * @param string $url    Captured URL.
     * @param int    $status HTTP status code.
     * @param string $html   Captured page source.
     * @param string $css    Extracted inline CSS.
     * @return array<string, mixed>
     */
    public static function index_entry( string $url, int $status, string $html, string $css ): array {
        $slug = self::slug( $url );

        return [
            'url'        => $url,
            'slug'       => $slug,
            'status'     => $status,
            'html'       => $slug . '.html',
            'css'        => $slug . '.css',
            'bytes_html' => strlen( $html ),
            'bytes_css'  => strlen( $css ),
        ];
    }
}
