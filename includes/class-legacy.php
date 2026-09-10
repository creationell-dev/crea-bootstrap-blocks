<?php
/**
 * Legacy compatibility: the render shim and the alias block switch.
 *
 * ZWEI GETRENNTE AUFGABEN — und BEWUSST NICHT AM SELBEN SCHALTER:
 *
 *   1. Der RENDER-SHIM auf `render_block_data`. Blockstudio liest Feldwerte
 *      ausschliesslich aus dem WP-Attribut `blockstudio`, Unterschluessel
 *      `attributes`: `Block::transform()` (block.php:1805-1822) setzt zuerst
 *      alle registrierten Attribute auf ihre Defaults zurueck und merged dann
 *      `blockstudio.attributes` darueber. Flach gespeicherte Top-Level-Werte
 *      werden dabei IGNORIERT. Gemessen (S-4): `{"col_xs":"col-12",
 *      "num_field":40}` kam flach als `false` und `0` an, aus dem Container
 *      als `col-12` und `40`. Der Shim hebt die flachen Werte zur Renderzeit
 *      in den Container und macht nicht migrierten Bestandscontent damit
 *      wieder renderfaehig (S-6). Er laeuft fuer `creabb/*` IMMER und fuer
 *      `areoi/*` nur bei aktivem `legacy_blocks` — Begruendung bei
 *      is_in_scope().
 *   2. Das AUSBLENDEN der `areoi/*`-Aliasbloecke aus dem Inserter, plus
 *      blocks_enabled() als die eine Stelle, an der der Schalter gelesen wird.
 *      Die Aliasordner selbst entstehen erst im Blockplan; hier sitzt nur die
 *      Steuerung.
 *
 * DER SHIM WIRKT AUSDRUECKLICH NUR BEIM RENDERN. Im Editor zeigt ein flach
 * gespeicherter Block LEERE FELDER — der Editor liest ebenfalls ausschliesslich
 * den Container — und schreibt beim ersten Speichern den leeren Stand zurueck;
 * die flachen Werte sind dann endgueltig weg. Der Shim ist ein NETZ fuer
 * uebersehene Fundstellen, KEIN ERSATZ FUER DIE MIGRATION
 * (`wp creabb migrate run`).
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps unmigrated content rendering and the alias blocks out of the inserter.
 */
final class Legacy {

    /**
     * Namespace of the plugin's own blocks.
     */
    public const OWN_NAMESPACE = 'creabb/';

    /**
     * Namespace of the alias blocks.
     */
    public const ALIAS_NAMESPACE = 'areoi/';

    /**
     * Block namespaces this class is responsible for at all.
     *
     * @var array<int, string>
     */
    public const NAMESPACES = [ self::OWN_NAMESPACE, self::ALIAS_NAMESPACE ];

    /**
     * The one attribute key that carries the Blockstudio container.
     */
    public const CONTAINER = 'blockstudio';

    /**
     * Attribute keys that are never lifted.
     *
     * `lock` und `metadata` sind Core-Buchfuehrung. Der Editor und `WP_Block`
     * lesen sie oben im `attrs`-Objekt; sie sind keine Vertragsattribute und
     * haben im Blockstudio-Container nichts verloren.
     *
     * OEFFENTLICH UND DIE EINZIGE FASSUNG DIESER LISTE (Abschlussbefund W3):
     * Sie stand dreimal im Baum — hier, im Validator und im Generator der
     * Faehigkeiten. Kommt ein Schluessel des Kerns hinzu, entschiede der
     * Renderpfad sonst anders als der Validator anders als der Generator, und
     * der Validator meldete `missing_container` fuer einen gesunden Block.
     * Wer eine dritte Liste braucht, braucht in Wahrheit diese.
     *
     * @var array<int, string>
     */
    public const RESERVED = [ 'lock', 'metadata' ];

    /**
     * Whether init() has already run.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Registers both filters. Safe to call more than once.
     *
     * PRIORITAET 30 auf `blockstudio/blocks/meta`: Auf 10 sitzt die
     * Vertragsmechanik (`type`, `default`), auf 20 die Uebersetzung (`title`,
     * `description`). Dieser Callback fasst ausschliesslich
     * `supports['inserter']` an — die drei Schluesselmengen sind disjunkt, die
     * Reihenfolge ist ergebnisneutral.
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;

        add_filter( 'render_block_data', [ self::class, 'shim_block_data' ], 10, 1 );
        add_filter( 'blockstudio/blocks/meta', [ self::class, 'hide_alias_from_inserter' ], 30, 1 );
    }

    /**
     * Whether the legacy block path is active.
     *
     * Die EINE Stelle, an der `legacy_blocks` gelesen wird. Der Blockplan
     * fragt sie, bevor er das Alias-Verzeichnis an `Blockstudio\Build::init()`
     * uebergibt; der Shim fragt sie nur fuer den Namensraum `areoi/`
     * (is_in_scope()), nie fuer `creabb/`.
     */
    public static function blocks_enabled(): bool {
        return crea_bootstrap_blocks_setting_enabled( 'legacy_blocks', true );
    }

    /**
     * `render_block_data` callback: lifts flat attributes into the container.
     *
     * KEINE Schalterabfrage an dieser Stelle. Sie haengt vom Namensraum des
     * jeweiligen Blocks ab und faellt deshalb je Block in is_in_scope(), das
     * lift_tree() waehrend des Durchlaufs fragt. Eine Abfrage hier vorne haette
     * einen gemischten Baum — `creabb/*` neben `areoi/*` — nur als Ganzes
     * behandeln koennen.
     *
     * @param array<string, mixed> $block One parsed block.
     * @return array<string, mixed> The same block, with the container filled in.
     */
    public static function shim_block_data( array $block ): array {
        return self::lift_tree( $block );
    }

    /**
     * Lifts a whole parsed subtree.
     *
     * WARUM REKURSIV: `render_block_data` feuert in `render_block()`, und
     * `do_blocks()` ruft `render_block()` nur fuer die WURZELN des Blockbaums
     * auf. Verschachtelte Bloecke entstehen danach in `WP_Block_List`, ohne
     * dass der Filter noch einmal laeuft. Eine Column in einer Row in einem
     * Container bliebe also unversorgt — und genau so sieht der Bestand aus.
     * Der Filter bekommt den vollstaendigen Teilbaum unter `innerBlocks`
     * uebergeben und gibt ihn weiter an `new WP_Block( $parsed_block )`; dort
     * wird er deshalb hier selbst durchlaufen.
     *
     * Der Durchlauf findet UNABHAENGIG vom Namen der Wurzel statt: Ein
     * `core/group` darf `creabb/*`-Kinder haben.
     *
     * HIER faellt auch die Schalterentscheidung, und zwar je Block: is_in_scope()
     * beantwortet fuer diesen einen Blocknamen, ob der Shim ihn heben darf.
     *
     * @param array<string, mixed> $block One parsed block.
     * @return array<string, mixed>
     */
    public static function lift_tree( array $block ): array {
        if ( self::is_in_scope( $block['blockName'] ?? null ) ) {
            $block = self::lift_attributes( $block );
        }

        if ( ! isset( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
            return $block;
        }

        foreach ( $block['innerBlocks'] as $index => $inner ) {
            if ( is_array( $inner ) ) {
                $block['innerBlocks'][ $index ] = self::lift_tree( $inner );
            }
        }

        return $block;
    }

    /**
     * Lifts the flat attributes of ONE parsed block into the container.
     *
     * IDEMPOTENZ: Ein Block, dessen `attrs` ausser `blockstudio` und der
     * Core-Buchfuehrung nichts enthaelt, wird unveraendert zurueckgegeben —
     * das ist der migrierte Normalfall, und er bleibt byte-identisch.
     *
     * VORRANG: Liegen beide Formen vor, GEWINNT DER CONTAINER; die flachen
     * Werte werden darunter gemischt, nicht darueber. Die flachen Schluessel
     * bleiben stehen — `render_block_data` darf den geparsten Inhalt nicht
     * beschaedigen, und Blockstudio ignoriert sie ohnehin. Ein zweiter
     * Durchlauf mischt dieselbe Basis unter dasselbe Ergebnis und aendert
     * deshalb weder Werte noch Schluesselreihenfolge.
     *
     * @param array<string, mixed> $block One parsed block.
     * @return array<string, mixed>
     */
    public static function lift_attributes( array $block ): array {
        $name = $block['blockName'] ?? null;

        if ( ! is_string( $name ) || ! self::is_managed_name( $name ) ) {
            return $block;
        }

        $attrs = $block['attrs'] ?? null;

        if ( ! is_array( $attrs ) ) {
            return $block;
        }

        $flat = self::flat_attributes( $attrs );

        if ( [] === $flat ) {
            return $block;
        }

        $container = is_array( $attrs[ self::CONTAINER ] ?? null ) ? $attrs[ self::CONTAINER ] : [];
        $existing  = is_array( $container['attributes'] ?? null ) ? $container['attributes'] : [];

        /*
         * Bewusst kein array_merge(): Es nummeriert numerische Schluessel neu.
         * Ein Attribut namens "0" ist pathologisch, aber json_decode() liefert
         * dafuer einen int-Schluessel, und ein umbenanntes Attribut waere
         * stiller Datenverlust.
         */
        $merged = $flat;

        foreach ( $existing as $key => $value ) {
            $merged[ $key ] = $value;
        }

        $container['name']       = $name;
        $container['attributes'] = $merged;

        $block['attrs'][ self::CONTAINER ] = $container;

        return $block;
    }

    /**
     * The flat attribute keys of one block: everything that is neither the
     * container itself nor core bookkeeping.
     *
     * DIE EINE STELLE, an der „flach" definiert ist. Sie beantwortet dieselbe
     * Frage fuer alle drei Wege, die sie stellen: der Renderpfad
     * (lift_attributes() — was ist zu heben), der Generator
     * (`build-block-markup` — was ist nach dem Heben abzuraeumen) und der
     * Validator (`validate-block-markup` — was liegt faelschlich oben). Vor
     * dem Abschlussbefund W3 hatte jeder dieser drei seine eigene Liste.
     *
     * NICHT-STRINGE SCHLUESSEL BLEIBEN DRIN: `json_decode()` macht aus dem
     * Attributnamen "0" einen int. Ein solcher Schluessel ist kein Schluessel
     * des Kerns, also ist er flach — und muss weiter oben als unbekanntes
     * Attribut auffallen, statt hier stillschweigend zu verschwinden.
     *
     * @param array<array-key, mixed> $attrs Raw block attributes.
     * @return array<array-key, mixed> The flat subset, in unchanged order.
     */
    public static function flat_attributes( array $attrs ): array {
        return array_diff_key( $attrs, array_flip( array_merge( [ self::CONTAINER ], self::RESERVED ) ) );
    }

    /**
     * `blockstudio/blocks/meta` callback: keeps alias blocks out of the inserter.
     *
     * Die Aliasbloecke tragen `"supports": {"inserter": false}` bereits in
     * ihrer eigenen block.json (S-6a, dort gemessen: der Wert kommt unveraendert
     * in der Registry an). Dieser Filter ist der zweite Riegel — er greift fuer
     * JEDEN registrierten `areoi/*`-Block, unabhaengig davon, was in dessen
     * block.json steht. Ein Alias im Inserter wuerde neuen Content im ALTEN
     * Namensraum erzeugen, also genau das, was die Migration beseitigt.
     *
     * Mutiert in place und gibt dasselbe Objekt zurueck, wie die beiden
     * Callbacks auf Prioritaet 10 und 20.
     *
     * VERENGT WIRD MIT `instanceof`, nicht mit `is_object()` — dieselbe
     * Begruendung wie bei `Contract::filter_block_meta()`: Dies ist ein
     * GLOBALER Filter, der Parameter ist deshalb `mixed`. Mit `is_object()`
     * waere `$block->supports = …` ein Schreibzugriff auf ein undeklariertes
     * Feld eines beliebigen fremden Objekts. Ausserdem ist
     * `WP_Block_Type::$name` als `string` deklariert und damit nie null; ein
     * `isset()` darauf ist immer wahr und taeuscht eine Pruefung vor, die
     * keine ist (PHPStan Stufe 8, `isset.property`).
     *
     * @param mixed $block The registered block type.
     * @return mixed The same block.
     */
    public static function hide_alias_from_inserter( $block ) {
        if ( $block instanceof \WP_Block_Type ) {
            $name = is_string( $block->name ) ? $block->name : '';
        } elseif ( is_array( $block ) ) {
            $name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
        } else {
            return $block;
        }

        if ( ! str_starts_with( $name, self::ALIAS_NAMESPACE ) ) {
            return $block;
        }

        if ( $block instanceof \WP_Block_Type ) {
            $supports             = is_array( $block->supports ?? null ) ? $block->supports : [];
            $supports['inserter'] = false;
            $block->supports      = $supports;

            return $block;
        }

        $supports             = is_array( $block['supports'] ?? null ) ? $block['supports'] : [];
        $supports['inserter'] = false;
        $block['supports']    = $supports;

        return $block;
    }

    /**
     * Whether the shim currently applies to a block of this name.
     *
     * DIE BEIDEN NAMENSRAEUME WERDEN GETRENNT GESCHALTET:
     *
     *   `creabb/*` — IMMER, unabhaengig von jedem Schalter. Der Shim ist hier
     *   ein reines Sicherheitsnetz gegen bei der Migration uebersehene
     *   Fundstellen und kostet eine Namensraumpruefung und einen Array-Test je
     *   Block. Faellt er weg, rendert so eine Fundstelle mit Default-Werten,
     *   und der erste Redaktionszugriff schreibt den leeren Stand zurueck —
     *   Datenverlust. Wer nach abgeschlossener Migration die Aliasbloecke
     *   abschaltet, will genau dieses Netz NICHT mit abschalten; deshalb haengt
     *   es nicht mehr an `legacy_blocks`.
     *
     *   `areoi/*` — nur bei aktivem `legacy_blocks`. Ohne die
     *   Alias-Registrierung sind diese Bloecke gar nicht erst registriert; sie
     *   koennen nicht rendern, und ein gefuellter Container waere folgenlos.
     *
     * @param mixed $name Block name from the parsed block, may be null.
     */
    private static function is_in_scope( $name ): bool {
        if ( ! is_string( $name ) ) {
            return false;
        }

        if ( str_starts_with( $name, self::OWN_NAMESPACE ) ) {
            return true;
        }

        if ( str_starts_with( $name, self::ALIAS_NAMESPACE ) ) {
            return self::blocks_enabled();
        }

        return false;
    }

    /**
     * Whether a block name belongs to one of the two managed namespaces.
     *
     * Reine Namensraumzugehoerigkeit, OHNE Schalterabfrage — der Waechter von
     * lift_attributes(), das auch fuer sich allein aufgerufen werden darf.
     *
     * @param string $name Block name.
     */
    private static function is_managed_name( string $name ): bool {
        foreach ( self::NAMESPACES as $namespace ) {
            if ( str_starts_with( $name, $namespace ) ) {
                return true;
            }
        }

        return false;
    }
}

// Die Include-Liste in crea-bootstrap-blocks.php laedt diese Datei genau
// einmal; init() ist zusaetzlich gegen einen zweiten Aufruf gesichert.
Legacy::init();
