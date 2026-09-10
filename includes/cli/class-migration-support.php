<?php
/**
 * Pure helpers behind `wp creabb migrate` — sources, inventory, reconciliation.
 *
 * DIESE KLASSE IST KEIN KOMMANDO. Sie wird nirgends per
 * `WP_CLI::add_command()` registriert, und genau darin liegt ihr Zweck:
 * `add_command()` leitet die Unterbefehle eines zusammengesetzten Befehls per
 * Reflection aus den oeffentlichen Methoden des uebergebenen Objekts ab und
 * listet sie in `wp help`. Ein Helfer, der in `Migrate_Command` public stuende,
 * waere damit ein aufrufbares Kommando ohne `## OPTIONS` und ohne Absicht.
 * `Snapshot_Urls` und `Snapshot_Diff` tragen die reinen Teile von
 * `wp creabb snapshot` aus demselben Grund.
 *
 * AUFBAU WIE BEI doctor: Was ohne Datenbank auskommt, ist eine reine
 * `public static`-Methode und wird in `tests/test-cli-migrate-*.php` einzeln
 * gemessen. Was eine Datenbank braucht, liegt hier ebenfalls, wird aber gegen
 * eine laufende Instanz gemessen (`tests/eval/migrate-sources.php`).
 *
 * FUNDSTELLEN (05-migration.md): `wp_posts.post_content` fuer jeden Post-Type
 * einschliesslich `wp_block`, `wp_template`, `wp_template_part`,
 * `wp_navigation` und `revision`; `wp_postmeta.meta_value`; die serialisierte
 * Option `widget_block`.
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

use Creationell\BootstrapBlocks\Migrator;

/**
 * Pure helpers of `wp creabb migrate`. Never registered as a command.
 */
class Migration_Support {

    /**
     * The options that can carry block markup.
     *
     * @return array<int, string>
     */
    public static function block_option_names(): array {
        return [ 'widget_block' ];
    }

    /**
     * The label of one source, as it appears in every report of this plan.
     *
     * @param string $kind Source kind: `posts`, `postmeta` or `options`.
     * @param string $key  Post type, meta key or option name.
     */
    public static function source_label( string $kind, string $key ): string {
        return $kind . ':' . $key;
    }

    /**
     * Rewrites every string inside a value, however deeply it is nested.
     *
     * Arrays behalten ihre Schluessel und deren Reihenfolge, Objekte bleiben
     * Objekte. Beides ist Bedingung dafuer, dass der zurueckgeschriebene Wert
     * dieselbe Serialisierung ergibt wie zuvor — nur mit anderem Inhalt.
     *
     * EIN OBJEKT WIRD NUR ANGEFASST, WENN SICH ETWAS AENDERT. Was aus
     * `wp_postmeta` kommt, hat ein Drittplugin dort abgelegt: Ein Enum ist
     * nicht klonbar, eine readonly-Eigenschaft nicht neu zuweisbar, eine
     * unvollstaendig entpackte Klasse gar nicht ansprechbar. Ein
     * bedingungsloser Klon mit anschliessender Neuzuweisung JEDER Eigenschaft
     * wirft an solchen Werten einen Error — mitten im Lauf, moeglicherweise mit
     * einer bereits committeten Tabelle daneben.
     *
     * @param mixed $value Option value, meta value or post content.
     * @return array{value: mixed, changed: int, errors: array<int, string>}
     */
    public static function rewrite_value( mixed $value ): array {
        $changed = 0;
        $errors  = [];

        if ( is_string( $value ) ) {
            $result = Migrator::rewrite( $value );

            if ( [] !== $result['errors'] ) {
                $errors = $result['errors'];
            }

            if ( $result['content'] !== $value ) {
                ++$changed;
            }

            return [
                'value'   => $result['content'],
                'changed' => $changed,
                'errors'  => $errors,
            ];
        }

        if ( is_array( $value ) ) {
            $out = [];

            foreach ( $value as $key => $item ) {
                $inner       = self::rewrite_value( $item );
                $out[ $key ] = $inner['value'];
                $changed    += $inner['changed'];
                $errors      = array_merge( $errors, $inner['errors'] );
            }

            return [
                'value'   => $out,
                'changed' => $changed,
                'errors'  => $errors,
            ];
        }

        if ( is_object( $value ) ) {
            // WAS AUS `wp_postmeta` KOMMT, IST NICHT UNBEDINGT EIN stdClass.
            // `maybe_unserialize()` liefert, was ein Drittplugin dort abgelegt
            // hat. Ein Enum laesst sich nicht klonen — `clone` wirft einen
            // Error, und der beendet `wp creabb migrate run` mitten im Lauf,
            // moeglicherweise mit einer bereits committeten Tabelle daneben.
            // Eine unvollstaendig entpackte Klasse (`__PHP_Incomplete_Class`,
            // die Klasse ist nicht geladen) vertraegt keinen
            // Eigenschaftszugriff. Beide werden UNVERAENDERT durchgereicht und
            // beanstandet: Ein Mensch muss sie ansehen.
            if ( $value instanceof \UnitEnum || $value instanceof \__PHP_Incomplete_Class ) {
                return [
                    'value'   => $value,
                    'changed' => 0,
                    'errors'  => [
                        sprintf(
                            'Objekt vom Typ %s nicht anfassbar, Wert unveraendert gelassen',
                            get_debug_type( $value )
                        ),
                    ],
                ];
            }

            // ERST SAMMELN, DANN SCHREIBEN, UND NUR WAS SICH GEAENDERT HAT.
            // Die fruehere Fassung klonte bedingungslos und wies danach JEDE
            // Eigenschaft neu zu, auch unveraenderte — eine readonly-Eigenschaft
            // wirft dabei einen Error, obwohl an ihr gar nichts zu tun war.
            $touched = [];

            foreach ( get_object_vars( $value ) as $key => $item ) {
                $inner  = self::rewrite_value( $item );
                $errors = array_merge( $errors, $inner['errors'] );

                if ( $inner['value'] === $item ) {
                    continue;
                }

                $touched[ $key ] = $inner['value'];
                $changed        += $inner['changed'];
            }

            if ( [] === $touched ) {
                return [
                    'value'   => $value,
                    'changed' => 0,
                    'errors'  => $errors,
                ];
            }

            // GANZ ODER GAR NICHT. Scheitert das Klonen oder eine der
            // Zuweisungen, wird das halbfertige Objekt VERWORFEN und der
            // Originalwert zurueckgegeben. Ein Objekt, in dem die eine Haelfte
            // migriert ist und die andere nicht, waere schlimmer als eines, das
            // gar nicht migriert wurde — und `changed` bliebe auf 0, damit der
            // Zaehlabgleich es findet.
            try {
                $out = clone $value;

                foreach ( $touched as $key => $item ) {
                    $out->$key = $item;
                }
            } catch ( \Throwable $error ) {
                $errors[] = sprintf(
                    'Objekt vom Typ %s nicht umschreibbar, Wert unveraendert gelassen: %s',
                    get_debug_type( $value ),
                    $error->getMessage()
                );

                return [
                    'value'   => $value,
                    'changed' => 0,
                    'errors'  => $errors,
                ];
            }

            return [
                'value'   => $out,
                'changed' => $changed,
                'errors'  => $errors,
            ];
        }

        return [
            'value'   => $value,
            'changed' => 0,
            'errors'  => [],
        ];
    }

    /**
     * How many carriers are loaded from the database at once.
     *
     * 200 Traeger sind auch bei sehr grossen Seiten eine ueberschaubare Menge
     * Content im Speicher und immer noch wenige Abfragen. Die Zahl steht als
     * Konstante da, damit sie an einer Stelle steht — nicht, damit sie
     * gedreht wird.
     */
    private const CARRIER_BATCH = 200;

    /**
     * The LIKE pattern that finds a carrier with `areoi/*` markup left.
     *
     * OHNE `<!-- `, UND ZWAR ABSICHTLICH. Der Blockparser des Cores trifft
     * einen Begrenzer mit dem Muster
     * `/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?…/`
     * (`wp-includes/class-wp-block-parser.php`) — zwischen `<!--` und `wp:`
     * steht dort `\s+`, also EIN ODER MEHR Leerraumzeichen, Zeilenumbruch
     * eingeschlossen. Genau dasselbe erlaubt `Migrator::pattern()`. Ein
     * LIKE-Muster `<!-- wp:areoi/` mit genau einem Leerzeichen ist damit ENGER
     * als der Begrenzer, den der Plan umschreibt: Ein Traeger mit zwei
     * Leerzeichen oder einem Zeilenumbruch wuerde gar nicht erst geladen —
     * nicht gezaehlt, nicht umgeschrieben, nicht berichtet —, und `verify`
     * meldete gruen auf einen `areoi/*`-Kommentar, der in der Datenbank steht.
     * Auf diese Meldung hin wird der Legacy-Alias abgeschaltet.
     *
     * Gesucht wird deshalb auf dem leerraumfreien Teil `wp:areoi/`. Der trifft
     * den oeffnenden `<!-- wp:areoi/column -->` und den schliessenden
     * `<!-- /wp:areoi/column -->` gleichermassen — der Schraegstrich steht
     * DAVOR — und ist unabhaengig davon, wie viel Leerraum der Editor gesetzt
     * hat. EIN Muster je Namensraum genuegt damit.
     *
     * DAS TOR DARF WEIT SEIN. `LIKE` waehlt nur aus, welche Zeilen ueberhaupt
     * geladen werden; gezaehlt und umgeschrieben wird danach ausschliesslich
     * ueber die Muster aus `Migrator`. Ein Traeger, in dem `wp:areoi/` bloss im
     * Fliesstext steht, kostet eine geladene Zeile und faellt in der Zaehlung
     * heraus. Ein Traeger, den das Tor nicht laedt, ist dagegen unrettbar
     * unsichtbar.
     *
     * OEFFENTLICH, WEIL DIE SCHREIBMETHODEN DASSELBE MUSTER BRAUCHEN
     * (Aufgabe 12). Suchte der Lauf mit einem anderen Muster als das Inventar,
     * bliebe die Differenz genau dort stehen, wo niemand hinsieht. Ein
     * Unterbefehl wird daraus nicht — diese Klasse wird nirgends per
     * `add_command()` registriert (Regel C1).
     *
     * @return array<int, string>
     */
    public static function legacy_like_patterns(): array {
        global $wpdb;

        return [
            '%' . $wpdb->esc_like( 'wp:' . Migrator::OLD_NAMESPACE ) . '%',
        ];
    }

    /**
     * The two LIKE patterns that find a carrier of either namespace.
     *
     * Der alte Namensraum kommt aus `legacy_like_patterns()` — es gibt nur
     * eine Fassung davon —, der neue steht hier daneben. Beide werden
     * gebraucht: Das Inventar fuehrt zwei getrennte Zweige und muss auch die
     * bereits migrierten Traeger sehen (Regel M3).
     *
     * @return array<int, string>
     */
    private static function like_patterns(): array {
        global $wpdb;

        return array_merge(
            self::legacy_like_patterns(),
            [
                '%' . $wpdb->esc_like( 'wp:' . Migrator::NEW_NAMESPACE ) . '%',
            ]
        );
    }

    /**
     * Enumerates every carrier that holds `areoi/*` or `creabb/*` markup.
     *
     * EINE EINZIGE STELLE, an der die Traegermenge entsteht. Das Inventar
     * (`snapshot()`) und der Trockenlauf (`simulate()`, Aufgabe 12) laufen
     * ueber genau dieselbe Liste; ein Trockenlauf ueber eine andere Menge
     * pruefte etwas anderes als der Lauf, den er vorwegnehmen soll.
     *
     * EIN GENERATOR, UND ZWAR AUS EINEM BETRIEBSGRUND. Ein einziges
     * `get_results()` ueber alle Traeger hielte den gesamten betroffenen
     * Content gleichzeitig im Speicher — bei 18 713 Bloecken, der groessten
     * gemessenen Menge einer einzelnen Installation, ein Vielfaches dessen, was ein PHP-Prozess unter `memory_limit` traegt,
     * und `run()` erhebt das Inventar zweimal. Erhoben wird deshalb zuerst nur
     * die ID-Liste; der Content kommt in Stapeln von CARRIER_BATCH nach und
     * wird nach jedem Stapel wieder frei.
     *
     * SPEICHERBEDARF: eine Ganzzahl je Traeger fuer die ID-Liste, dazu
     * hoechstens CARRIER_BATCH Traegerinhalte gleichzeitig. Was bleibt, ist die
     * Attributkarte ueber alle Bloecke, die die Aufrufer aufbauen — sie kann
     * nicht gestapelt werden, weil Bein 1 sie vor und nach dem Lauf
     * vergleicht, traegt aber nur Attributwerte und keinen Content.
     *
     * Fuer die Aufrufer aendert sich nichts: `foreach` ueber einen Generator
     * sieht aus wie `foreach` ueber ein Array.
     *
     * `value` ist der Wert in seiner echten Form — String bei Posts und
     * Postmeta, ausgepackter PHP-Wert bei Optionen. `text` ist die zum Zaehlen
     * flachgelegte Textform.
     *
     * `kind` IST KEINE ZIERDE, SONDERN DIE WEICHE. `wp_postmeta` wird anders
     * umgeschrieben als `wp_posts` und `wp_options`: nur dort kann ein
     * SERIALISIERTER Wert stehen, und nur dort geht der Weg ueber
     * `rewrite_meta_value()` statt ueber `rewrite_value()` (Aufgabe 12). Die
     * Traegerart aus `source` zurueckzurechnen hiesse, einen Praefix aus einer
     * Beschriftung zu parsen; sie steht deshalb als eigener Schluessel da.
     * `snapshot()` und `simulate()` waehlen danach dieselbe Funktion, die auch
     * die Schreibmethode waehlt — ein Trockenlauf, der fuer Postmeta einen
     * anderen Weg faehrt als der Lauf, pruefte das Falsche.
     *
     * @param bool $include_revisions Whether `post_type = revision` is enumerated.
     * @return \Generator<int, array{kind: string, source: string, id: string, value: mixed, text: string}>
     */
    private static function carriers( bool $include_revisions ): \Generator {
        global $wpdb;

        $patterns = self::like_patterns();

        // DIE KLAMMERN UM DIE ODER-KETTE SIND PFLICHT. `AND` bindet in SQL
        // staerker als `OR`; ohne sie fiele der Revisionsfilter nur auf das
        // letzte Muster, und die Migration liefe ueber Revisionen, die
        // ausdruecklich ausgenommen sind.
        $where = '( post_content LIKE %s OR post_content LIKE %s )';
        $args  = $patterns;

        if ( ! $include_revisions ) {
            $where .= ' AND post_type != %s';
            $args[] = 'revision';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Begruendung im Block unten: Der Tabellenname geht als %i, die zusammengesetzten Teile sind Literale dieser Datei, und die Zahl der Platzhalter haengt an der Zeilenzahl.
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                // DER TABELLENNAME GEHT ALS `%i`, nicht interpoliert (E-89):
                // Nur so bleibt das erste Argument von `prepare()` ein literaler
                // String, wie ihn die statische Analyse auf Stufe 8 verlangt.
                //
                // Die Platzhalterliste wird dagegen ZUSAMMENGESETZT, und das geht
                // nicht anders: Ihre Laenge haengt an der Zahl der Zeilen. Sie
                // besteht ausschliesslich aus Literalen dieser Datei — kein Wert
                // aus der Datenbank oder von der Kommandozeile erreicht sie.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Begruendung im Block darueber.
                'SELECT ID FROM %i WHERE ' . $where,
                $wpdb->posts,
                ...$args
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        foreach ( array_chunk( array_map( 'intval', (array) $post_ids ), self::CARRIER_BATCH ) as $chunk ) {
            $placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wie oben.
            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Der Tabellenname geht als %i; die Platzhalterliste besteht nur aus Literalen dieser Datei.
                    'SELECT ID, post_type, post_content FROM %i WHERE ID IN ( ' . $placeholders . ' )',
                    $wpdb->posts,
                    ...$chunk
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

            foreach ( (array) $posts as $post ) {
                $content = (string) $post->post_content;

                yield [
                    'kind'   => 'posts',
                    'source' => self::source_label( 'posts', (string) $post->post_type ),
                    'id'     => (string) $post->ID,
                    'value'  => $content,
                    'text'   => $content,
                ];
            }

            unset( $posts );
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wie oben.
        $meta_ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT meta_id FROM %i WHERE meta_value LIKE %s OR meta_value LIKE %s',
                $wpdb->postmeta,
                ...$patterns
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        foreach ( array_chunk( array_map( 'intval', (array) $meta_ids ), self::CARRIER_BATCH ) as $chunk ) {
            $placeholders = implode( ', ', array_fill( 0, count( $chunk ), '%d' ) );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wie oben.
            $meta = $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Der Tabellenname geht als %i; die Platzhalterliste besteht nur aus Literalen dieser Datei.
                    'SELECT meta_id, post_id, meta_key, meta_value FROM %i WHERE meta_id IN ( ' . $placeholders . ' )',
                    $wpdb->postmeta,
                    ...$chunk
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

            foreach ( (array) $meta as $row ) {
                // DER ROHE SPALTENWERT, ABSICHTLICH. Gezaehlt wird auf dem
                // Text, wie er in der Spalte steht; ein serialisierter Wert
                // traegt das Blockmarkup darin woertlich, und die
                // Laengenpraefixe stehen ausserhalb der Begrenzer. Zahlen und
                // Attributkarte sind deshalb dieselben wie nach dem
                // Aus- und Wiedereinpacken. GESCHRIEBEN wird trotzdem NIE auf
                // dieser Form — dafuer gibt es `rewrite_meta_value()` und die
                // Meta-API (Aufgabe 12).
                $value = (string) $row->meta_value;

                yield [
                    'kind'   => 'postmeta',
                    'source' => self::source_label( 'postmeta', (string) $row->meta_key ),
                    'id'     => (string) $row->meta_id,
                    'value'  => $value,
                    'text'   => $value,
                ];
            }

            unset( $meta );
        }

        foreach ( self::block_option_names() as $option_name ) {
            /*
             * `false` HEISST ZWEIERLEI, UND DER UNTERSCHIED IST ALLES.
             * `get_option()` gibt `maybe_unserialize( $value )` heraus, und
             * `maybe_unserialize()` liefert `false`, wenn `unserialize()`
             * scheitert. „Option nicht vorhanden" und „Option kaputt
             * serialisiert" sehen damit gleich aus. Wer nur auf `false` prueft,
             * ueberspringt die kaputte Option ohne ein Wort — sie faellt aus
             * dem Inventar, aus der Zaehlung und aus jeder Beanstandung, und
             * `verify` meldete danach „in keiner Fundstelle steht noch ein
             * areoi/*-Block" fuer eine Option, die einen traegt.
             *
             * Unterschieden wird ueber den Standardwert: Ein eigener Marker
             * kommt nur zurueck, wenn die Zeile wirklich fehlt.
             */
            $marker = '__cbb_option_absent__';
            $value  = \get_option( $option_name, $marker );

            if ( $marker === $value ) {
                continue;
            }

            if ( false === $value ) {
                $broken = self::broken_option_finding( $option_name );

                if ( [] !== $broken ) {
                    yield [
                        'kind'   => 'options',
                        'source' => self::source_label( 'options', $option_name ),
                        'id'     => $option_name,
                        'value'  => $value,
                        'text'   => '',
                        'broken' => $broken,
                    ];
                }

                continue;
            }

            yield [
                'kind'   => 'options',
                'source' => self::source_label( 'options', $option_name ),
                'id'     => $option_name,
                'value'  => $value,
                'text'   => self::flatten_strings( $value ),
            ];
        }
    }

    /**
     * The finding for an option whose serialization cannot be read.
     *
     * Dieselbe Frage wie `broken_meta_finding()`, nur fuer `wp_options`: Steht
     * in der Spalte ein serialisierter Wert, den `unserialize()` nicht
     * auspacken kann? `b:0;` IST die Serialisierung von `false` und damit in
     * Ordnung; alles andere ist ein kaputter Wert, den ein Mensch ansehen muss.
     *
     * Gelesen wird der ROHE Spaltenwert — `get_option()` hat ihn bereits durch
     * `maybe_unserialize()` geschickt und kann die Frage nicht mehr
     * beantworten.
     *
     * @param string $option_name Option name.
     * @return array<int, string>
     */
    public static function broken_option_finding( string $option_name ): array {
        global $wpdb;

        if ( ! $wpdb instanceof \wpdb ) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $raw = $wpdb->get_var(
            $wpdb->prepare(
                // Der Tabellenname als `%i`, nicht interpoliert (E-89).
                'SELECT option_value FROM %i WHERE option_name = %s',
                $wpdb->options,
                $option_name
            )
        );

        if ( ! is_string( $raw ) ) {
            return [];
        }

        return self::broken_meta_finding( $raw );
    }

    /**
     * The finding for a `wp_postmeta` value whose serialization cannot be read.
     *
     * WARUM DAS SCHON INS INVENTAR GEHOERT. Ein Metawert mit kaputter
     * Serialisierung laesst sich nicht umschreiben: `maybe_unserialize()`
     * liefert `false`, und der Wert bleibt unangetastet stehen. Gemeldet wurde
     * das frueher NUR von `rewrite_meta_value()` — also erst in
     * `migrate_postmeta()`, mitten im schreibenden Lauf, NACHDEM
     * `migrate_posts()` seine Transaktion committed hat. Genau die halb
     * migrierte Installation, die dieser Plan verhindern soll.
     *
     * Diese Pruefung steht deshalb im VORHER-Inventar, auf dessen
     * Beanstandungen `run()` vor dem ersten Schreibzugriff abbricht. Sie ist
     * billig: keine Ersetzung, kein Umschreiben, nur die Frage, ob sich der
     * Wert ueberhaupt auspacken laesst.
     *
     * `maybe_unserialize()` liefert `false`, wenn `unserialize()` scheitert —
     * und `false` ist selbst ein gueltiger Wert. Unterschieden wird am
     * Quelltext: `b:0;` IST die Serialisierung von false, alles andere ist ein
     * kaputter Wert.
     *
     * DIE MELDUNG STEHT AN EINER STELLE. `rewrite_meta_value()` (Aufgabe 12)
     * ruft dieselbe Methode, statt den Wortlaut ein zweites Mal auszuschreiben;
     * sonst saehe derselbe Befund im Scan anders aus als im Lauf.
     *
     * @param string $raw Value exactly as it stands in the column.
     * @return array<int, string> One finding, or the empty list.
     */
    public static function broken_meta_finding( string $raw ): array {
        if ( ! \is_serialized( $raw ) ) {
            return [];
        }

        if ( 'b:0;' === trim( $raw ) || false !== \maybe_unserialize( $raw ) ) {
            return [];
        }

        return [
            sprintf(
                'Serialisierter Metawert nicht lesbar, nichts geschrieben: %s',
                substr( $raw, 0, 160 )
            ),
        ];
    }

    /**
     * Hands out the raw text of every carrier, source label included.
     *
     * WOFUER ES DAS GIBT. Bein 2 braucht zwei Erhebungen ueber den BESTAND —
     * die `type`-Werte der `button`-Bloecke (erwartete Abweichung 4) und die
     * Attributwerte, die die Wertpruefung des Nachbaus abweist (Abweichung 7).
     * Beide lesen Blockbegrenzer. In einer gerenderten Seitenaufnahme stehen
     * keine: `do_blocks()` setzt die Ausgabe aus `render_block()` je geparstem
     * Block zusammen und gibt die Begrenzer nicht wieder aus. Auf der Aufnahme
     * liefen beide Erhebungen deshalb immer leer — und ein leerer Nachweis
     * sieht aus wie ein erbrachter.
     *
     * Der Zugang steht HIER, weil `carriers()` die einzige Stelle im Repo ist,
     * an der die Traegermenge entsteht. Eine zweite Abfrage mit eigenem Muster
     * liesse die Differenz genau dort stehen, wo niemand hinsieht.
     *
     * Traeger, die sich nicht auspacken lassen, kommen NICHT heraus — sie
     * haben keinen lesbaren Text. Sie stehen in den Beanstandungen von
     * `snapshot()`, wo sie hingehoeren.
     *
     * @param bool $include_revisions Whether `post_type = revision` is scanned.
     * @return \Generator<int, array{source: string, id: string, text: string}>
     */
    public static function carrier_texts( bool $include_revisions ): \Generator {
        foreach ( self::carriers( $include_revisions ) as $carrier ) {
            if ( isset( $carrier['broken'] ) && is_array( $carrier['broken'] ) ) {
                continue;
            }

            yield [
                'source' => (string) $carrier['source'],
                'id'     => (string) $carrier['id'],
                'text'   => (string) $carrier['text'],
            ];
        }
    }

    /**
     * Takes stock of every source that carries `areoi/*` or `creabb/*` markup.
     *
     * @param bool $include_revisions Whether `post_type = revision` is scanned.
     * @return array{
     *     counts: array<string, array{areoi: array<string, int>, creabb: array<string, int>}>,
     *     rows: array<int, array<string, mixed>>,
     *     attributes: array<string, array<string, mixed>>,
     *     errors: array<int, string>
     * }
     */
    public static function snapshot( bool $include_revisions ): array {
        $counts     = [];
        $rows       = [];
        $attributes = [];
        $errors     = [];

        foreach ( self::carriers( $include_revisions ) as $carrier ) {
            $text = (string) $carrier['text'];

            // JE METAZEILE AUCH DIE SERIALISIERUNG. `Migrator::findings()` sieht
            // nur Blockbegrenzer; ein Metawert, dessen Serialisierung kaputt
            // ist, faellt darin nicht auf — das Blockmarkup darin ist ja in
            // Ordnung. Auffallen MUSS er hier: `run()` bricht auf den
            // Beanstandungen des Vorher-Inventars ab, bevor ein Byte
            // geschrieben ist. Erst in `migrate_postmeta()` gemeldet, stuende
            // `wp_posts` bereits migriert und committed daneben.
            //
            // DAS VORHER-INVENTAR IST NICHT DIE EINZIGE SPERRE VOR DEM
            // SCHREIBEN — und es kann es nicht sein. Was erst die
            // Umschreibfunktion hervorbringt (ein nicht anfassbares Objekt, ein
            // nicht schreibbarer Attribut-Container), sieht weder
            // `Migrator::findings()` noch `broken_meta_finding()`. Dafuer
            // faehrt `run()` im schreibenden Zweig zusaetzlich `simulate()`
            // (Aufgabe 12) und bricht auf dessen Beanstandungen genauso ab.
            // Diese Pruefung steht trotzdem HIER und nicht dort: Sie ist billig
            // — keine Ersetzung, nur die Frage, ob sich der Wert ueberhaupt
            // auspacken laesst —, und `scan` braucht sie ebenfalls.
            //
            // DER BEFUND GEHT DIREKT IN $errors, nicht durch `tally()`. Die
            // steigt fuer einen Traeger ohne jeden Begrenzer vorzeitig aus —
            // zu Recht, sie fuehrt Zahlen. `migrate_postmeta()` laedt eine
            // solche Zeile aber ueber dasselbe LIKE und bricht an ihr ab; sie
            // muss deshalb auch dann gemeldet werden. Die Beschriftung ist
            // dieselbe wie in `tally()` und in `simulate()`, damit
            // `array_unique()` im Trockenlauf denselben Befund nicht zweimal
            // zaehlt.
            if ( 'postmeta' === $carrier['kind'] ) {
                foreach ( self::broken_meta_finding( (string) $carrier['value'] ) as $message ) {
                    $errors[] = sprintf(
                        '%s #%s: %s',
                        (string) $carrier['source'],
                        (string) $carrier['id'],
                        $message
                    );
                }
            }

            // UND DIE KAPUTT SERIALISIERTE OPTION. `carriers()` reicht sie mit
            // ihrer eigenen Beanstandung heraus, weil `get_option()` dort
            // bereits `false` geliefert hat und die Frage nicht mehr
            // beantworten kann — ohne diesen Zweig faellt sie ohne ein Wort aus
            // dem Inventar.
            if ( isset( $carrier['broken'] ) && is_array( $carrier['broken'] ) ) {
                foreach ( $carrier['broken'] as $message ) {
                    $errors[] = sprintf(
                        '%s #%s: %s',
                        (string) $carrier['source'],
                        (string) $carrier['id'],
                        (string) $message
                    );
                }

                continue;
            }

            // KEIN UMSCHREIBEN FUERS INVENTAR. Gebraucht werden die
            // Beanstandungen und die Attributkarte — beides gibt es, ohne einen
            // einzigen Begrenzer neu zu bauen. Vorher fuhr `tally()` hier je
            // Traeger einen vollstaendigen `Migrator::rewrite()`, warf das
            // Ergebnis weg und liess danach die Attributkarte ein zweites Mal
            // ueber denselben Text laufen; `run()` erhebt das Inventar zweimal,
            // also war das viermal je Traeger.
            self::tally(
                $counts,
                $rows,
                $attributes,
                $errors,
                (string) $carrier['source'],
                (string) $carrier['id'],
                $text,
                [
                    'errors'     => Migrator::findings( $text ),
                    'attributes' => Migrator::block_attributes( $text ),
                ]
            );
        }

        ksort( $counts );

        return [
            'counts'     => $counts,
            'rows'       => $rows,
            'attributes' => $attributes,
            'errors'     => $errors,
        ];
    }

    /**
     * Adds one carrier to the inventory.
     *
     * ZWEI GETRENNTE ZWEIGE. Der alte und der neue Namensraum werden NIE in
     * denselben Schluessel addiert. Waeren sie es, veraenderte ein nicht
     * ersetzter Blockbegrenzer die Zahl ueberhaupt nicht — er wanderte nur vom
     * einen Summanden zum anderen —, und der Zaehlabgleich in `migrate run`
     * bestaende genau dann, wenn er scheitern muesste.
     *
     * BEANSTANDUNGEN werden hier eingesammelt, aber NICHT hier erzeugt. Der
     * Aufrufer reicht sie in `$probe` herein, zusammen mit der Attributkarte.
     * Frueher fuhr diese Methode dafuer selbst einen vollstaendigen
     * `Migrator::rewrite()` je Traeger, verwarf das Ergebnis und liess danach
     * die Attributkarte ein zweites Mal ueber denselben Text laufen — im
     * Trockenlauf war das der zweite vollstaendige Umschreiblauf je Traeger,
     * nachdem `rewrite_value()` den ersten schon gefahren hatte.
     *
     * SCHLIESSENDE BEGRENZER STEHEN IN EIGENEN SPALTEN. `count_blocks()` zaehlt
     * sie nicht mit, und das bleibt so — der Zaehlabgleich zaehlt Bloecke, nicht
     * Kommentare. `verify` braucht sie trotzdem: Ein Traeger, in dem nur noch
     * ein verwaister `<!-- /wp:areoi/… -->` steht, haette sonst ueberall die
     * Zahl null und liesse die Abnahme gruen melden.
     *
     * @param array<string, array{areoi: array<string, int>, creabb: array<string, int>}>        $counts     Counts, by reference.
     * @param array<int, array<string, mixed>>                                                   $rows       Rows, by reference.
     * @param array<string, array<string, mixed>>                                                $attributes Attribute map keyed `<source> #<id> @<n>:<block_id>`, by reference.
     * @param array<int, string>                                                                 $errors     Findings, by reference.
     * @param string                                                                             $source     Source label.
     * @param string                                                                             $id         Carrier id.
     * @param string                                                                             $content    Carrier content.
     * @param array{errors: array<int, string>, attributes: array<string, array<string, mixed>>} $probe      Findings and attribute map of $content, computed by the caller.
     */
    private static function tally( array &$counts, array &$rows, array &$attributes, array &$errors, string $source, string $id, string $content, array $probe ): void {
        $old       = Migrator::count_blocks( $content, Migrator::OLD_NAMESPACE );
        $new       = Migrator::count_blocks( $content, Migrator::NEW_NAMESPACE );
        $old_close = Migrator::count_closers( $content, Migrator::OLD_NAMESPACE );
        $new_close = Migrator::count_closers( $content, Migrator::NEW_NAMESPACE );

        // DIE VIER ZAEHLER LIEFERN NUR ZAHLEN und koennen einen Regex-Fehler
        // nicht selbst melden — sie legen ihn in den Behaelter von `Migrator`.
        // Hier steht der Aufrufer, der ihn einsammelt. Ohne diese Zeilen waere
        // ein gescheiterter Suchlauf von einem blockfreien Traeger nicht zu
        // unterscheiden: vier leere Zaehlungen, ein frueher Abbruch weiter
        // unten, kein Wort im Bericht.
        //
        // Abgeholt wird VOR dem fruehen Ausstieg, sonst bliebe der Befund im
        // Behaelter liegen und tauchte beim naechsten Traeger unter dessen
        // Kennung auf.
        foreach ( Migrator::take_pcre_findings() as $message ) {
            $errors[] = sprintf( '%s #%s: %s', $source, $id, $message );
        }

        // Auch ein Traeger, in dem NUR noch ein Schliesser steht, ist ein
        // Traeger. Fiele er hier heraus, kaeme er in keiner Zeile vor — und
        // `verify` haette nichts zu melden.
        if ( [] === $old && [] === $new && [] === $old_close && [] === $new_close ) {
            return;
        }

        if ( ! isset( $counts[ $source ] ) ) {
            $counts[ $source ] = [
                'areoi'  => [],
                'creabb' => [],
            ];
        }

        foreach ( $old as $name => $count ) {
            $counts[ $source ]['areoi'][ $name ] = ( $counts[ $source ]['areoi'][ $name ] ?? 0 ) + $count;
        }

        foreach ( $new as $name => $count ) {
            $counts[ $source ]['creabb'][ $name ] = ( $counts[ $source ]['creabb'][ $name ] ?? 0 ) + $count;
        }

        ksort( $counts[ $source ]['areoi'] );
        ksort( $counts[ $source ]['creabb'] );

        /*
         * DER ALTE NAME IM CONTAINER (E-102). Blockstudio liest
         * `blockstudio.name` zuerst; steht dort nach dem Lauf noch `areoi/*`,
         * rendert der Block nichts mehr, sobald der Legacy-Alias faellt. Kein
         * Begrenzerzaehler sieht ihn — er steht IN der Attribut-JSON, nicht am
         * Kommentar —, und `verify` gaebe die Freigabe fuer genau diesen
         * Zustand. Gezaehlt wird deshalb hier, und `verify` nimmt die Zahl mit.
         */
        $stale_names = preg_match_all( '/"name"\s*:\s*"' . preg_quote( Migrator::OLD_NAMESPACE, '/' ) . '/', $content );

        if ( false === $stale_names ) {
            $errors[] = sprintf(
                '%s #%s: Regex-Fehler bei der Suche nach dem alten Containernamen: %s',
                $source,
                $id,
                preg_last_error_msg()
            );

            $stale_names = 0;
        }

        $rows[] = [
            'source'       => $source,
            'id'           => $id,
            'areoi'        => array_sum( $old ),
            'creabb'       => array_sum( $new ),
            'areoi_close'  => array_sum( $old_close ),
            'creabb_close' => array_sum( $new_close ),
            'areoi_name'   => (int) $stale_names,
        ];

        foreach ( $probe['errors'] as $message ) {
            $errors[] = sprintf( '%s #%s: %s', $source, $id, (string) $message );
        }

        // DER SCHLUESSEL TRAEGT DIE FUNDSTELLE UND DEN TRAEGER. `block_id` ist
        // nicht eindeutig: Beim Duplizieren im Editor setzt das Alt-Plugin sie
        // nur fuer den obersten Block und die erste innerBlocks-Ebene zurueck
        // (assets/js/areoi.js, Zeilen 38-46). Zwei Bloecke mit derselben ID —
        // im selben Traeger oder in zweien — muessen zwei Eintraege bleiben,
        // sonst ueberschreibt der eine den anderen und der Wertevergleich
        // prueft nur noch einen von beiden.
        //
        // `Migrator::block_attributes()` liefert bereits `<Nummer>:<block_id>`
        // je Traeger; hier kommen Fundstelle und Traegerkennung davor. Erhoben
        // hat sie der Aufrufer — ein zweiter Suchlauf ueber denselben Text
        // brauchte niemand.
        foreach ( $probe['attributes'] as $entry => $values ) {
            $attributes[ sprintf( '%s #%s @%s', $source, $id, (string) $entry ) ] = $values;
        }
    }

    /**
     * Joins every string inside a value, for counting purposes only.
     *
     * @param mixed $value Option value.
     */
    private static function flatten_strings( mixed $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_array( $value ) || is_object( $value ) ) {
            $parts = [];

            foreach ( (array) $value as $item ) {
                $parts[] = self::flatten_strings( $item );
            }

            return implode( "\n", $parts );
        }

        return '';
    }
    /**
     * Decides whether the run may start, and where the backup goes.
     *
     * REGEL M4. Es gibt drei Wege in einen Lauf: mit `--backup=<pfad>`, mit dem
     * ausdruecklichen `--skip-backup` — und als `--dry-run`, der ueberhaupt
     * nichts schreibt. Der Normalfall ist der erste. Der zweite existiert fuer
     * Instanzen, deren Backup auf anderem Weg entsteht; er sagt in seiner
     * Meldung, was er bedeutet, und niemand kann ihn versehentlich treffen.
     *
     * @param array<string, mixed> $assoc_args Associative arguments of the run.
     * @return array{ok: bool, path: string, message: string}
     */
    public static function backup_decision( array $assoc_args ): array {
        if ( isset( $assoc_args['dry-run'] ) ) {
            return [
                'ok'      => true,
                'path'    => '',
                'message' => __( 'dry run — nothing is written, no backup is taken', 'crea-bootstrap-blocks' ),
            ];
        }

        if ( isset( $assoc_args['skip-backup'] ) ) {
            return [
                'ok'      => true,
                'path'    => '',
                'message' => __( '--skip-backup: running without a backup, there is no way back from here', 'crea-bootstrap-blocks' ),
            ];
        }

        $path = isset( $assoc_args['backup'] ) ? (string) $assoc_args['backup'] : '';

        if ( '' === $path ) {
            return [
                'ok'      => false,
                'path'    => '',
                'message' => __( 'There is no run without a way back. Pass --backup=<path> — or --skip-backup if the backup is taken elsewhere.', 'crea-bootstrap-blocks' ),
            ];
        }

        return [
            'ok'      => true,
            'path'    => $path,
            'message' => sprintf(
                /* translators: %s: backup path. */
                __( 'backup: %s', 'crea-bootstrap-blocks' ),
                $path
            ),
        ];
    }

    /**
     * Rewrites one `wp_postmeta.meta_value`, serialization included.
     *
     * REGEL M4 GILT AUCH FUER wp_postmeta. `05-migration.md` fuehrt diese
     * Fundstelle als die der „Drittplugins, die Blockmarkup in Meta ablegen" —
     * und ein Drittplugin, das ein Array ablegt, legt einen SERIALISIERTEN Wert
     * ab. Der Blockkommentar steht dann als Zeichenkette INNERHALB der
     * Serialisierung, und der Attribut-Container veraendert seit S-4 die
     * Bytelaenge jedes betroffenen Kommentars planmaessig. Wer den rohen
     * Spaltenwert umschreibt, laesst die `s:<n>:`-Praefixe auf den alten Zahlen
     * stehen: Der Wert ist danach nicht mehr deserialisierbar, und
     * `get_post_meta()` liefert den rohen String oder `false`.
     *
     * UND DER ZAEHLABGLEICH FAENGT ES NICHT. Die LIKE-Suche findet den
     * Blockkommentar im kaputten String weiterhin, die Zahlen stimmen, und die
     * Attributkarte wird vorher wie nachher aus derselben flachgelegten
     * Textform erhoben. Bein 1 meldete gruen auf einen zerstoerten Metawert.
     *
     * ZURUECK KOMMT DER PHP-WERT, NICHT DIE ZEICHENKETTE. Serialisiert wird
     * beim Schreiben, von der Meta-API — genauso, wie `migrate_options()` es
     * `update_option()` ueberlaesst.
     *
     * `maybe_unserialize()` liefert `false`, wenn `unserialize()` scheitert,
     * und `false` ist selbst ein gueltiger Wert. Unterschieden wird am
     * Quelltext: `b:0;` IST die Serialisierung von false, alles andere ist ein
     * kaputter Wert. Der wird beanstandet und bleibt stehen — und zwar mit dem
     * Wortlaut aus `broken_meta_finding()` (Aufgabe 11), damit derselbe Befund
     * im Scan, im Trockenlauf und im Lauf gleich heisst.
     *
     * @param string $raw Value exactly as it stands in the column.
     * @return array{value: mixed, changed: int, errors: array<int, string>, serialized: bool}
     */
    public static function rewrite_meta_value( string $raw ): array {
        $serialized = \is_serialized( $raw );
        $value      = $raw;

        if ( $serialized ) {
            $broken = self::broken_meta_finding( $raw );

            if ( [] !== $broken ) {
                return [
                    'value'      => $raw,
                    'changed'    => 0,
                    'errors'     => $broken,
                    'serialized' => true,
                ];
            }

            $value = \maybe_unserialize( $raw );
        }

        // Ab hier ist es ein PHP-Wert wie jeder andere, und die Rekursion in
        // Arrays und Objekte steht laengst da.
        $result = self::rewrite_value( $value );

        return [
            'value'      => $result['value'],
            'changed'    => $result['changed'],
            'errors'     => $result['errors'],
            'serialized' => $serialized,
        ];
    }

    /**
     * Builds the after-inventory in memory, without writing a single byte.
     *
     * DIESELBEN TRAEGER wie die drei Schreibmethoden — `carriers()` ist die
     * eine Stelle, an der die Liste entsteht — und JE TRAEGERART DIESELBE
     * UMSCHREIBFUNKTION, die auch die Schreibmethode nimmt. Was hier
     * herauskommt, ist genau das Inventar, das nach einem echten Lauf in der
     * Datenbank staende. Nur die Schreibaufrufe fehlen.
     *
     * OEFFENTLICH, WEIL `Migrate_Command::run()` sie aufruft — und trotzdem
     * kein Unterbefehl, weil diese Klasse nirgends per `add_command()`
     * registriert wird. Genau dafuer existiert sie (Regel C1).
     *
     * @param bool $include_revisions Whether revisions are simulated too.
     * @return array{
     *     counts: array<string, array{areoi: array<string, int>, creabb: array<string, int>}>,
     *     rows: array<int, array<string, mixed>>,
     *     attributes: array<string, array<string, mixed>>,
     *     errors: array<int, string>
     * }
     */
    public static function simulate( bool $include_revisions ): array {
        $counts     = [];
        $rows       = [];
        $attributes = [];
        $errors     = [];

        foreach ( self::carriers( $include_revisions ) as $carrier ) {
            // DIESELBE WAHL WIE DIE SCHREIBMETHODEN. `migrate_postmeta()` geht
            // ueber `rewrite_meta_value()`, `migrate_posts()` und
            // `migrate_options()` ueber `rewrite_value()`. Liefe der
            // Trockenlauf fuer Postmeta auf dem ROHEN Spaltenwert, faehre er
            // genau den Weg, den `rewrite_meta_value()` als den
            // zerstoererischen beschreibt — und die einzige Stelle mit
            // `is_serialized()` und `maybe_unserialize()`, also der einzige
            // Erzeuger der Beanstandung „Serialisierter Metawert nicht lesbar",
            // liefe im --dry-run NIE. Der Trockenlauf meldete Erfolg, und der
            // echte Lauf braeche erst in `migrate_postmeta()` ab, NACHDEM
            // `migrate_posts()` committed hat: die halb migrierte Installation.
            $result = 'postmeta' === $carrier['kind']
                ? self::rewrite_meta_value( (string) $carrier['value'] )
                : self::rewrite_value( $carrier['value'] );

            foreach ( $result['errors'] as $message ) {
                $errors[] = sprintf(
                    '%s #%s: %s',
                    (string) $carrier['source'],
                    (string) $carrier['id'],
                    (string) $message
                );
            }

            // Bei einem serialisierten Metawert ist `$result['value']` der
            // AUSGEPACKTE PHP-Wert — `rewrite_meta_value()` gibt ihn so heraus,
            // serialisiert wird erst beim Schreiben von der Meta-API. Zum
            // Zaehlen wird er hier mit `flatten_strings()` wieder flachgelegt.
            // Das ist dieselbe Menge Blockbegrenzer wie in der rohen Spalte,
            // die `snapshot()` fuer denselben Traeger sieht: Die
            // `s:<n>:`-Laengenpraefixe stehen ausserhalb der Begrenzer und
            // zaehlen nirgends mit. Zahlen und Attributkarte sind deshalb
            // dieselben, die nach dem echten Lauf in der Datenbank stehen.
            // GESCHRIEBEN wird von hier ohnehin nichts.
            $text = is_string( $result['value'] )
                ? $result['value']
                : self::flatten_strings( $result['value'] );

            // KEIN ZWEITER UMSCHREIBLAUF. Die Beanstandungen stehen schon in
            // $result — der Umschreiber hat sie oben erhoben, welcher der
            // beiden es je nach Traegerart auch war —,
            // und was hier noch fehlt, ist allein die Attributkarte des
            // umgeschriebenen Textes. Die kostet einen Suchlauf, nicht den
            // ganzen Umschreiber. Deshalb steht in `errors` hier die leere
            // Liste: Alles, was zu melden war, ist oben schon gemeldet, und
            // dasselbe zweimal einzusammeln machte aus jedem Befund zwei.
            self::tally(
                $counts,
                $rows,
                $attributes,
                $errors,
                (string) $carrier['source'],
                (string) $carrier['id'],
                $text,
                [
                    'errors'     => [],
                    'attributes' => Migrator::block_attributes( $text ),
                ]
            );
        }

        ksort( $counts );

        return [
            'counts'     => $counts,
            'rows'       => $rows,
            'attributes' => $attributes,
            'errors'     => $errors,
        ];
    }

    /**
     * Reconciles two inventories — the whole of leg 1, in one call.
     *
     * DREI PRUEFUNGEN, und jede faengt einen anderen Fehler:
     *
     *   1. JE FUNDSTELLE, nicht in Summe. `05-migration.md`, Schritt 6, und
     *      `06-verifikation.md`, Bein 1, verlangen woertlich „pro Typ und pro
     *      Fundstelle". Ein Block, der aus `posts:page` verschwindet und in
     *      `posts:wp_block` auftaucht, hebt sich in der Gesamtsumme auf.
     *   2. GETRENNTE ZWEIGE. Verglichen wird der areoi-Zweig VORHER gegen den
     *      creabb-Zweig NACHHER. Das Soll ist dabei areoi-vorher PLUS
     *      creabb-vorher: Nach Regel M3 ist der Lauf idempotent, bereits
     *      migrierte Bloecke stehen im Vorher-Stand schon im creabb-Zweig und
     *      bleiben dort.
     *   3. DIE HARTE BEDINGUNG. Nach dem Lauf darf in KEINER Fundstelle ein
     *      areoi-Begrenzer uebrig sein. Sie braucht keinen Vergleichswert und
     *      faellt deshalb auch dann nicht aus, wenn die Zaehlung selbst irrt.
     *
     * Dazu der Schluessel- und Wertevergleich aus Aufgabe 10 — je EINTRAG der
     * Attributkarte, nicht je `block_id`. Der Schluessel lautet
     * `<Fundstelle> #<Traeger> @<Nummer>:<block_id>`, weil `block_id` nicht
     * eindeutig ist (Herleitung in Aufgabe 9). Damit deckt der Vergleich ab,
     * was `05-migration.md`, Schritt 6, „je `block_id`" nennt, und zusaetzlich
     * die Faelle, in denen dieselbe ID mehrfach vorkommt.
     *
     * @param array{counts?: array<string, array{areoi: array<string, int>, creabb: array<string, int>}>, attributes?: array<string, array<string, mixed>>} $before Inventory before the run.
     * @param array{counts?: array<string, array{areoi: array<string, int>, creabb: array<string, int>}>, attributes?: array<string, array<string, mixed>>} $after  Inventory after the run.
     * @return array<int, string> Human readable findings; empty means "identical".
     */
    public static function reconcile_inventory( array $before, array $after ): array {
        $before_counts = isset( $before['counts'] ) && is_array( $before['counts'] ) ? $before['counts'] : [];
        $after_counts  = isset( $after['counts'] ) && is_array( $after['counts'] ) ? $after['counts'] : [];

        $sources = array_unique(
            array_merge( array_keys( $before_counts ), array_keys( $after_counts ) )
        );
        sort( $sources );

        $findings = [];

        foreach ( $sources as $source ) {
            $expected = self::merge_counts(
                is_array( $before_counts[ $source ]['areoi'] ?? null ) ? $before_counts[ $source ]['areoi'] : [],
                is_array( $before_counts[ $source ]['creabb'] ?? null ) ? $before_counts[ $source ]['creabb'] : []
            );

            $found = is_array( $after_counts[ $source ]['creabb'] ?? null ) ? $after_counts[ $source ]['creabb'] : [];

            foreach ( Migrator::reconcile_counts( $expected, $found ) as $finding ) {
                $findings[] = sprintf( '%s — %s', (string) $source, (string) $finding );
            }

            $left_over = is_array( $after_counts[ $source ]['areoi'] ?? null ) ? $after_counts[ $source ]['areoi'] : [];

            if ( [] === $left_over ) {
                continue;
            }

            $parts = [];

            foreach ( $left_over as $name => $count ) {
                $parts[] = sprintf( '%s: %d', (string) $name, (int) $count );
            }

            $findings[] = sprintf(
                /* translators: 1: source label, 2: comma separated list of block names and counts. */
                __( '%1$s — areoi/* blocks are left after the run: %2$s', 'crea-bootstrap-blocks' ),
                (string) $source,
                implode( ', ', $parts )
            );
        }

        return array_merge(
            $findings,
            Migrator::reconcile_attributes(
                isset( $before['attributes'] ) && is_array( $before['attributes'] ) ? $before['attributes'] : [],
                isset( $after['attributes'] ) && is_array( $after['attributes'] ) ? $after['attributes'] : []
            )
        );
    }

    /**
     * Adds two count maps into one.
     *
     * @param array<string, int> $left  First map.
     * @param array<string, int> $right Second map.
     * @return array<string, int>
     */
    private static function merge_counts( array $left, array $right ): array {
        $merged = [];

        foreach ( $left as $name => $count ) {
            $merged[ $name ] = (int) $count;
        }

        foreach ( $right as $name => $count ) {
            $merged[ $name ] = ( $merged[ $name ] ?? 0 ) + (int) $count;
        }

        ksort( $merged );

        return $merged;
    }

    /**
     * Folds one branch of a per-source inventory into one count per block type.
     *
     * NUR FUER DIE AUSGABEZEILE. Der Abgleich laeuft ueber
     * `reconcile_inventory()` je Fundstelle — eine Verschiebung zwischen zwei
     * Fundstellen hoebe sich in dieser Summe auf.
     *
     * Oeffentlich, weil `Migrate_Command::run()` die Zeile schreibt.
     *
     * @param array<string, array{areoi: array<string, int>, creabb: array<string, int>}> $counts Counts by source.
     * @param string                                                                      $branch Branch to fold: `areoi` or `creabb`.
     * @return array<string, int>
     */
    public static function flatten_counts( array $counts, string $branch ): array {
        $flat = [];

        foreach ( $counts as $branches ) {
            $blocks = is_array( $branches[ $branch ] ?? null ) ? $branches[ $branch ] : [];

            foreach ( $blocks as $name => $count ) {
                $flat[ $name ] = ( $flat[ $name ] ?? 0 ) + (int) $count;
            }
        }

        ksort( $flat );

        return $flat;
    }
    /**
     * Summarises the leftovers `verify` found.
     *
     * NULL TREFFER IST DER EINZIGE GRUENE ZUSTAND. Erst wenn dieser Befehl
     * sauber ist, duerfen auf der betreffenden Seite
     * CREA_BOOTSTRAP_BLOCKS_LEGACY_BLOCKS und spaeter …_LEGACY_CLASSES fallen
     * (05-migration.md).
     *
     * @param array<string, int> $hits              Remaining `areoi/*` occurrences, by source.
     * @param bool               $include_revisions Whether revisions were examined.
     * @return array{status: string, message: string}
     */
    public static function verify_summary( array $hits, bool $include_revisions = true ): array {
        $left = [];

        foreach ( $hits as $source => $count ) {
            if ( (int) $count > 0 ) {
                $left[] = sprintf( '%s: %d', (string) $source, (int) $count );
            }
        }

        if ( [] === $left ) {
            /*
             * DIE MELDUNG SAGT, WORUEBER SIE SPRICHT. Mit `--skip-revisions`
             * hat `verify` die Revisionen gar nicht angesehen; „in keiner
             * Fundstelle" waere dann eine Falschaussage — und auf genau diese
             * Meldung hin wird der Legacy-Alias abgeschaltet. Revisionen
             * tragen nach `05-migration.md` denselben Blockinhalt wie ihr
             * Elternbeitrag; ein `areoi/*`-Kommentar bleibt dort stehen und
             * kommt beim naechsten Zurueckrollen einer Revision zurueck.
             */
            if ( ! $include_revisions ) {
                return [
                    'status'  => 'ok',
                    'message' => __( 'No areoi/* block left — revisions were not examined (--skip-revisions).', 'crea-bootstrap-blocks' ),
                ];
            }

            return [
                'status'  => 'ok',
                'message' => __( 'No areoi/* block left in any source.', 'crea-bootstrap-blocks' ),
            ];
        }

        sort( $left );

        return [
            'status'  => 'error',
            'message' => sprintf(
                /* translators: %s: list of sources with leftover counts. */
                __( 'areoi/* blocks are left: %s. Do not switch off the legacy blocks yet.', 'crea-bootstrap-blocks' ),
                implode( ' | ', $left )
            ),
        ];
    }

    /**
     * Decides whether a dump can be restored.
     *
     * @param string $path     Value of `--from`.
     * @param bool   $exists   Result of `is_file()`.
     * @param bool   $readable Result of `is_readable()`.
     * @return array{ok: bool, message: string}
     */
    public static function rollback_decision( string $path, bool $exists, bool $readable ): array {
        if ( '' === $path ) {
            return [
                'ok'      => false,
                'message' => __( '--from=<path> is required: name the dump that is to be restored.', 'crea-bootstrap-blocks' ),
            ];
        }

        if ( ! $exists ) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: dump path. */
                    __( 'The dump %s does not exist.', 'crea-bootstrap-blocks' ),
                    $path
                ),
            ];
        }

        if ( ! $readable ) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: dump path. */
                    __( 'The dump %s is not readable.', 'crea-bootstrap-blocks' ),
                    $path
                ),
            ];
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                /* translators: %s: dump path. */
                __( 'restoring: %s', 'crea-bootstrap-blocks' ),
                $path
            ),
        ];
    }
}
