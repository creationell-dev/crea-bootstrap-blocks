<?php
/**
 * The data contract: attribute type and default of every block.
 *
 * DREI GETRENNTE QUELLEN, bewusst entkoppelt:
 *
 *   1. `blocks/_contracts/<block>.json` — der Datenvertrag. Attributname → Typ
 *      und Default, 1:1 aus der block.json des Originals. Diese Datei ist die
 *      Wahrheit ueber die gespeicherte Form und wird nie „aufgeraeumt".
 *   2. `blocks/<block>/block.json`, Schluessel `blockstudio.attributes` — die
 *      Editor-Oberflaeche. Frei gruppierbar, solange jedes Feld exakt auf einen
 *      Vertragsnamen schreibt.
 *   3. Diese Datei — sie haengt beides zusammen.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enforces the stored data contract of every block.
 */
final class Contract {

    /**
     * Overridden contract directory, or null for the default.
     *
     * @var string|null
     */
    private static ?string $dir = null;

    /**
     * Memoised contract tables: block slug => attribute table.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private static array $tables = [];

    /**
     * Cached `attributes` lists of the field libraries under `blocks/fields/`.
     *
     * @var array<string, array<int, mixed>>
     */
    private static array $field_libraries = [];

    /**
     * Whether init() has already run.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Overrides the contract directory.
     *
     * Nur fuer Tests und WP-CLI. Im Betrieb steht das Verzeichnis fest.
     *
     * @param string $dir Absolute path without a trailing slash.
     */
    public static function set_dir( string $dir ): void {
        self::$dir    = rtrim( $dir, '/' );
        self::$tables = [];
    }

    /**
     * The contract directory.
     */
    public static function dir(): string {
        if ( null !== self::$dir ) {
            return self::$dir;
        }

        return rtrim( CREA_BOOTSTRAP_BLOCKS_DIR, '/' ) . '/blocks/_contracts';
    }

    /**
     * The contract table of one block.
     *
     * `creabb/container` und `areoi/container` teilen sich eine Datei: Der
     * Aliasblock ist derselbe Block unter altem Namen und hat denselben
     * Datenvertrag.
     *
     * @param string $block_name Full block name, e.g. `creabb/container`.
     * @return array<string, array<string, mixed>> Attribute name => contract entry.
     */
    public static function table( string $block_name ): array {
        $slug = self::slug( $block_name );

        if ( '' === $slug ) {
            return [];
        }

        if ( isset( self::$tables[ $slug ] ) ) {
            return self::$tables[ $slug ];
        }

        $file = self::dir() . '/' . $slug . '.json';

        if ( ! is_readable( $file ) ) {
            self::$tables[ $slug ] = [];

            return [];
        }

        /*
         * `WordPress.WP.AlternativeFunctions` schlaegt hier an und schlaegt
         * `wp_remote_get()` vor. Das geht am Fall vorbei: Gelesen wird eine
         * Datei, die MIT DEM PLUGIN AUSGELIEFERT wird, kein entfernter URL. Ein
         * HTTP-Aufruf waere hier nicht sicherer, sondern langsamer und
         * fehleranfaelliger. Die Wache davor (`is_readable()`) und der
         * Plausibilitaetstest danach (`is_array()`) bleiben bestehen.
         */
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Begruendung im Block darueber.
        $decoded = json_decode( (string) file_get_contents( $file ), true );

        if ( ! is_array( $decoded ) ) {
            crea_bootstrap_blocks_log( 'Contract: ' . $file . ' is not valid JSON.' );
            self::$tables[ $slug ] = [];

            return [];
        }

        $table = [];

        foreach ( $decoded as $name => $entry ) {
            if ( is_string( $name ) && is_array( $entry ) ) {
                $table[ $name ] = $entry;
            }
        }

        self::$tables[ $slug ] = $table;

        return $table;
    }

    /**
     * Block slug of one of our own blocks, or '' for anything else.
     *
     * @param string $block_name Full block name.
     */
    private static function slug( string $block_name ): string {
        foreach ( [ 'creabb/', 'areoi/' ] as $prefix ) {
            if ( str_starts_with( $block_name, $prefix ) ) {
                return substr( $block_name, strlen( $prefix ) );
            }
        }

        return '';
    }

    /**
     * Registers both filters. Safe to call more than once.
     *
     * PRIORITAET 10 fuer beide. Auf 20 haengt die Uebersetzung
     * (`crea_bootstrap_blocks_translate_block_meta` und
     * `…_translate_block_attribute`). Beide Paare mutieren dasselbe Objekt bzw.
     * Array und geben es zurueck; die beruehrten Schluessel sind disjunkt
     * (`type`/`default` hier, `title`/`description`/`label`/`help` dort), sie
     * ueberschreiben einander also nicht.
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        self::$initialized = true;

        add_filter( 'blockstudio/blocks/attributes', [ self::class, 'filter_block_attribute' ], 10, 2 );
        add_filter( 'blockstudio/blocks/meta', [ self::class, 'filter_block_meta' ], 10, 1 );
    }

    /**
     * Clears the idempotency latch so init() registers again. TEST HELPER.
     *
     * Diese Datei ruft am Ende selbst `init()` auf — beim `require_once` einer
     * Suite stehen die Filter also schon und `$initialized` ist true. Eine Suite,
     * die ihre Hook-Aufzeichnung zuruecksetzt und die Registrierung beobachten
     * will, braucht deshalb diesen Griff. Im Betrieb wird er nie aufgerufen.
     */
    public static function reset_for_tests(): void {
        self::$initialized = false;
    }

    /**
     * Sets the contract default on one Blockstudio field definition.
     *
     * NUR `default`. `type` ist hier der Blockstudio-FELDTYP (`text`, `toggle`,
     * `creabb/select`) und nicht der Attributtyp — ein Ueberschreiben zerstoert
     * das Feld: Es verschwindet aus der Editor-Oberflaeche und bleibt als
     * attributloses Fragment zurueck (S-3, im Spike reproduziert). Den
     * Attributtyp setzt `filter_block_meta()`.
     *
     * @param array<string, mixed> $attribute One field definition.
     * @param array<string, mixed> $block     The block.json data of the owning block.
     * @return array<string, mixed>
     */
    public static function filter_block_attribute( array $attribute, array $block = [] ): array {
        $name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
        $id   = isset( $attribute['id'] ) && is_string( $attribute['id'] ) ? $attribute['id'] : '';

        if ( '' === $id ) {
            return $attribute;
        }

        $entry = self::table( $name )[ $id ] ?? null;

        if ( ! is_array( $entry ) || ! array_key_exists( 'default', $entry ) ) {
            return $attribute;
        }

        $attribute['default'] = $entry['default'];

        return $attribute;
    }

    /**
     * Enforces attribute type and default on the finished block type.
     *
     * DIES IST DER RICHTIGE HEBEL, nicht `register_block_type_args`: Der
     * Core-Filter feuert in `WP_Block_Type::set_props()`, also im Konstruktor,
     * und Blockstudio haengt die erzeugten Attribute erst DANACH direkt an
     * (build.php:2481-2484). Gemessen: Der Core-Filter sieht 9 Attribute, das
     * fertige Objekt hat 19.
     *
     * Die Feld-Metadaten (`blockstudio`, `field`, `options`, `id`) bleiben
     * stehen — ohne sie verschwindet das Feld aus dem Editor.
     *
     * VERENGT WIRD MIT `instanceof`, nicht mit `is_object()`. Der Plan sah
     * `is_object()` vor; damit ist `$block->attributes = …` ein Schreibzugriff
     * auf ein undeklariertes Feld eines beliebigen Objekts — PHPStan Stufe 8
     * beanstandet das zu Recht, und es waere auch inhaltlich falsch: Ein
     * fremdes Objekt bekaeme hier still ein Feld angehaengt, das es nicht
     * kennt. Dies ist ein GLOBALER Filter, der Parameter ist deshalb `mixed`
     * und die Verengung steht im Rumpf — dieselbe Begruendung wie bei
     * `crea_bootstrap_blocks_translate_block_meta()`.
     *
     * @param mixed $block The registered block type.
     * @return mixed The same block.
     */
    public static function filter_block_meta( $block ) {
        if ( $block instanceof \WP_Block_Type ) {
            $name       = is_string( $block->name ) ? $block->name : '';
            $attributes = is_array( $block->attributes ) ? $block->attributes : [];
        } elseif ( is_array( $block ) ) {
            $name       = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
            $attributes = is_array( $block['attributes'] ?? null ) ? $block['attributes'] : [];
        } else {
            return $block;
        }

        $table = self::table( $name );

        if ( [] === $table ) {
            return $block;
        }

        foreach ( $table as $attribute_name => $entry ) {
            $existing = is_array( $attributes[ $attribute_name ] ?? null )
                ? $attributes[ $attribute_name ]
                : [];

            if ( isset( $entry['type'] ) ) {
                $existing['type'] = $entry['type'];
            }

            /*
             * „Kein Default" ist ein eigener Zustand: 14 Attribute von
             * media-grid-image haben keinen, weil der Block von core/image
             * abstammt. Ein `default: null` waere dort eine andere Zusage.
             */
            if ( array_key_exists( 'default', $entry ) ) {
                $existing['default'] = $entry['default'];
            } else {
                unset( $existing['default'] );
            }

            $attributes[ $attribute_name ] = $existing;
        }

        if ( $block instanceof \WP_Block_Type ) {
            $block->attributes = $attributes;
        } else {
            $block['attributes'] = $attributes;
        }

        return $block;
    }

    /**
     * The Blockstudio field types that are locked for contract attributes.
     *
     * REGEL 1.4. Diese Typen normalisieren den Wert, bevor sie ihn ablegen:
     * `select`/`radio` speichern {"value":…,"label":…} statt des rohen
     * Optionswerts, `color`/`gradient` speichern {"value":…,"name":…,"slug":…}
     * statt des sechsschluessligen Altobjekts, `checkbox`/`token` eine Liste
     * solcher Paare, `unit` einen zusammengesetzten String statt zweier
     * Attribute (S-5). `files`, `link` und `icon` legen Objekte ab, `repeater`
     * erzeugt zusaetzlich ein Attribut vom Typ `array` mit verschachtelten
     * Kindern (S-1).
     *
     * Der Attributname stimmt in all diesen Faellen — nur die Wertform nicht.
     * Genau deshalb faellt der Fehler ohne diese Liste erst auf, wenn eine
     * Bestandsseite den Wert bereits ueberschrieben hat.
     *
     * @return array<int, string>
     */
    public static function locked_field_types(): array {
        return [
            'select',
            'radio',
            'color',
            'gradient',
            'checkbox',
            'token',
            'unit',
            'files',
            'link',
            'icon',
            'repeater',
        ];
    }

    /**
     * Maps a Blockstudio `attributes` list onto the attribute names it creates.
     *
     * NAMENSBILDUNG NACH S-1, gemessen an der laufenden Instanz:
     *
     *   - `custom/<name>` ist eine Referenz auf die eigene Feldbibliothek unter
     *     `blocks/fields/<name>/field.json`. Das Feld traegt SELBST KEINE `id`;
     *     seine Kinder bekommen ihre Namen ueber `idStructure`, in das die
     *     Kind-ID fuer `{id}` eingesetzt wird: Kind `padding_top` plus
     *     `idStructure: "{id}_xs"` → `padding_top_xs`.
     *   - `group` praefixt: Gruppe `padding` + Kind `top_xs` → `padding_top_xs`.
     *   - `tabs` praefixt NICHT: Tab `lg` + Kind `col_lg` → `col_lg`. Die
     *     Tab-ID geht nicht in den Attributnamen ein, `tabs` ist reine
     *     UI-Gruppierung.
     *   - `repeater` erzeugt GENAU EIN Attribut unter seiner eigenen ID; seine
     *     Kinder sind verschachtelte Werte und keine Vertragsattribute. Es wird
     *     deshalb nicht in ihn hinabgestiegen.
     *
     * Eine Gruppe darf ihre ID unter `id` oder unter `key` tragen; beides kommt
     * in den Blockstudio-Beispielen vor.
     *
     * DER `custom/`-ZWEIG IST NICHT OPTIONAL. Ohne ihn faellt jedes Feld ohne
     * eigene `id` durch die Wache `'' === $id` — und die 47 `block.json` dieses
     * Plugins komponieren ihre Abstaende, Hoehen und Sichtbarkeitsschalter
     * ausschliesslich so. Gemessen: `"type": "group"` steht in keiner einzigen
     * `block.json`, `custom/creabb-spacing` 72-mal in zwoelf. Ohne den Zweig
     * sieht diese Methode 937 der 1867 Vertragsattribute — die uebrigen 792
     * meldete `wp creabb doctor` fuer Regel 1.4 dauerhaft gruen, weil sie
     * strukturell gar nicht bei ihm ankommen.
     *
     * @param array<int, mixed> $fields       Blockstudio field definitions.
     * @param string            $prefix       Prefix accumulated from enclosing groups.
     * @param string            $id_structure Active `idStructure` pattern.
     * @param string|null       $root         Plugin root; defaults to the plugin directory.
     * @return array<int, array{id: string, type: string}>
     */
    public static function flatten_fields(
        array $fields,
        string $prefix = '',
        string $id_structure = '{id}',
        ?string $root = null
    ): array {
        if ( null === $root ) {
            $root = defined( 'CREA_BOOTSTRAP_BLOCKS_DIR' )
                ? rtrim( (string) constant( 'CREA_BOOTSTRAP_BLOCKS_DIR' ), '/' )
                : '';
        }

        $flat = [];

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $type = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '';

            if ( str_starts_with( $type, 'custom/' ) ) {
                $child_structure = isset( $field['idStructure'] ) && is_string( $field['idStructure'] )
                    ? $field['idStructure']
                    : '{id}';

                foreach (
                    self::flatten_fields(
                        self::field_library( $root, substr( $type, strlen( 'custom/' ) ) ),
                        $prefix,
                        $child_structure,
                        $root
                    ) as $child
                ) {
                    $flat[] = $child;
                }

                continue;
            }

            if ( 'tabs' === $type ) {
                $tabs = isset( $field['tabs'] ) && is_array( $field['tabs'] ) ? $field['tabs'] : [];

                foreach ( $tabs as $tab ) {
                    if ( ! is_array( $tab ) || ! isset( $tab['attributes'] ) || ! is_array( $tab['attributes'] ) ) {
                        continue;
                    }

                    foreach ( self::flatten_fields( $tab['attributes'], $prefix, $id_structure, $root ) as $child ) {
                        $flat[] = $child;
                    }
                }

                continue;
            }

            $id = '';

            if ( isset( $field['id'] ) && is_string( $field['id'] ) ) {
                $id = $field['id'];
            } elseif ( isset( $field['key'] ) && is_string( $field['key'] ) ) {
                $id = $field['key'];
            }

            /*
             * Der group-Zweig steht VOR der Leer-ID-Wache. Eine Gruppe ohne id ist rein
             * darstellend: Blockstudio setzt dort den leeren Praefix und wendet die
             * idStructure auf die KINDER an (build.php, rewrite_attribute_ids). Stuende
             * die Wache davor, faellt jede solche Gruppe samt Kindern heraus, und jede
             * Blocksuite meldet einen Vertragsbruch, den es nicht gibt.
             */
            if ( 'group' === $type ) {
                $children = isset( $field['attributes'] ) && is_array( $field['attributes'] )
                    ? $field['attributes']
                    : [];

                $kind_prefix    = '' === $id ? $prefix : $prefix . str_replace( '{id}', $id, $id_structure ) . '_';
                $kind_structure = '' === $id ? $id_structure : '{id}';

                foreach ( self::flatten_fields( $children, $kind_prefix, $kind_structure, $root ) as $child ) {
                    $flat[] = $child;
                }

                continue;
            }

            if ( '' === $id ) {
                continue;
            }

            $id = str_replace( '{id}', $id, $id_structure );

            $flat[] = [
                'id'   => $prefix . $id,
                'type' => $type,
            ];
        }

        return $flat;
    }

    /**
     * The `attributes` list of one field library under `blocks/fields/`.
     *
     * Gelesen wird eine Datei, die MIT DEM PLUGIN AUSGELIEFERT wird. Die
     * Ergebnisse werden gemerkt: `wp creabb doctor` laeuft ueber 47
     * `block.json`, und zwoelf davon verweisen je sechsmal auf dieselbe
     * Bibliothek.
     *
     * @param string $root Plugin root directory without a trailing slash.
     * @param string $name Library name, e.g. `creabb-spacing`.
     * @return array<int, mixed>
     */
    private static function field_library( string $root, string $name ): array {
        if ( '' === $root || '' === $name ) {
            return [];
        }

        $key = $root . '|' . $name;

        if ( isset( self::$field_libraries[ $key ] ) ) {
            return self::$field_libraries[ $key ];
        }

        $file = $root . '/blocks/fields/' . $name . '/field.json';

        if ( ! is_readable( $file ) ) {
            self::$field_libraries[ $key ] = [];

            return [];
        }

        /*
         * `WordPress.WP.AlternativeFunctions` schlaegt hier an und schlaegt
         * `wp_remote_get()` vor. Das geht am Fall vorbei: Gelesen wird eine
         * `field.json`, die MIT DEM PLUGIN AUSGELIEFERT wird, kein entfernter
         * URL. Die Wache davor und der Plausibilitaetstest danach bleiben.
         */
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Begruendung im Block darueber.
        $decoded = json_decode( (string) file_get_contents( $file ), true );

        $attributes = is_array( $decoded ) && isset( $decoded['attributes'] ) && is_array( $decoded['attributes'] )
            ? $decoded['attributes']
            : [];

        self::$field_libraries[ $key ] = $attributes;

        return $attributes;
    }

    /**
     * Reports every field definition that breaks contract rule 1.4.
     *
     * Rein und ohne WordPress benutzbar: Sie liest nur die Vertragsdatei ueber
     * `table()` und die uebergebene Felddefinition. `wp creabb doctor` ruft sie
     * je `block.json` auf und bricht bei einem Treffer ab — ein solcher Block
     * darf nicht auf Bestandsdaten losgelassen werden.
     *
     * ZWEI ARTEN VON VERSTOSS, im Feld `reason` unterschieden:
     *
     *   `locked`  — der Feldtyp steht auf der Sperrliste aus Regel 1.4.
     *   `numeric` — `number` oder `range` an einem Attribut, das im Original
     *               kein `{"type":"number"}` ist. Alle Massangaben sind dort
     *               Strings ("64", "0", ""), 13 614 belegte Werte ausnahmslos;
     *               ein Zahlenfeld machte daraus Zahlen und aenderte die
     *               Wertform (Regel 1.5).
     *
     * @param string            $block_name Full block name, e.g. `creabb/container`.
     * @param array<int, mixed> $fields     Blockstudio field definitions of that block.
     * @return array<int, array{block: string, attribute: string, field_type: string, reason: string}>
     */
    public static function field_type_violations( string $block_name, array $fields ): array {
        $table = self::table( $block_name );

        if ( [] === $table ) {
            return [];
        }

        $locked     = self::locked_field_types();
        $violations = [];

        foreach ( self::flatten_fields( $fields ) as $field ) {
            $id   = $field['id'];
            $type = $field['type'];

            if ( '' === $id || ! array_key_exists( $id, $table ) ) {
                continue;
            }

            if ( in_array( $type, $locked, true ) ) {
                $violations[] = [
                    'block'      => $block_name,
                    'attribute'  => $id,
                    'field_type' => $type,
                    'reason'     => 'locked',
                ];

                continue;
            }

            if ( in_array( $type, [ 'number', 'range' ], true ) ) {
                $contract_type = isset( $table[ $id ]['type'] ) && is_string( $table[ $id ]['type'] )
                    ? $table[ $id ]['type']
                    : '';

                if ( 'number' !== $contract_type && 'integer' !== $contract_type ) {
                    $violations[] = [
                        'block'      => $block_name,
                        'attribute'  => $id,
                        'field_type' => $type,
                        'reason'     => 'numeric',
                    ];
                }
            }
        }

        return $violations;
    }
}

Contract::init();
