<?php
/**
 * Registers the read-only abilities of this plugin.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * Die Container-Regel aus S-4 laesst sich dokumentieren — dann muss ein Agent
 * sie umsetzen und kann sie falsch umsetzen, ohne dass es auffaellt. Oder man
 * kapselt sie: `build-block-markup` nimmt flache Attributwerte entgegen und
 * gibt korrektes Markup zurueck. Das ist der eigentliche Zweck dieser Datei;
 * die uebrigen fuenf Faehigkeiten liefern das Umfeld dazu.
 *
 * ALLE SECHS SIND AUSSCHLIESSLICH LESEND. Migration, Rollback und jeder
 * Schreibzugriff bleiben bei der WP-CLI mit einem Menschen davor.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registration of the ability category and the six abilities.
 */
final class Abilities {

    /**
     * Category slug shared by all six abilities.
     */
    public const CATEGORY = 'crea-bootstrap-blocks';

    /**
     * Whether init() has already run.
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Hooks registration in. Safe to call more than once.
     *
     * Registriert wird NUR, wenn die API da ist. Faellt sie weg, bleibt der
     * Rest des Plugins funktionsfaehig — deshalb steht die Wache hier und
     * nicht in den Callbacks.
     */
    public static function init(): void {
        if ( self::$initialized ) {
            return;
        }

        if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        self::$initialized = true;

        // Die Reihenfolge ist zwingend: Kategorien zuerst. Eine Faehigkeit mit
        // unbekannter Kategorie wird vom Kern abgewiesen.
        add_action( 'wp_abilities_api_categories_init', [ self::class, 'register_category' ], 10 );
        add_action( 'wp_abilities_api_init', [ self::class, 'register' ], 10 );
    }

    /**
     * Registers the category.
     */
    public static function register_category(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category(
            self::CATEGORY,
            [
                'label'       => __( 'CreaBootstrapBlocks', 'crea-bootstrap-blocks' ),
                'description' => __( 'Read-only insight into the blocks, their attribute contract and their markup.', 'crea-bootstrap-blocks' ),
            ]
        );
    }

    /**
     * Registers all six abilities.
     *
     * Der Rumpf waechst in den Aufgaben 7 bis 9 dieses Plans.
     */
    public static function register(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        self::ability(
            'list-blocks',
            [
                'label'               => __( 'List blocks', 'crea-bootstrap-blocks' ),
                'description'         => __( 'Lists every registered creabb/ block with its category, parent and attribute count.', 'crea-bootstrap-blocks' ),
                'execute_callback'    => [ self::class, 'run_list_blocks' ],
                'permission_callback' => [ self::class, 'may_read' ],
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [],
                    'additionalProperties' => false,
                ],
            ]
        );

        self::ability(
            'get-block-schema',
            [
                'label'               => __( 'Get block schema', 'crea-bootstrap-blocks' ),
                'description'         => __( 'Returns the complete attribute contract of one block: name, type, default.', 'crea-bootstrap-blocks' ),
                'execute_callback'    => [ self::class, 'run_get_block_schema' ],
                'permission_callback' => [ self::class, 'may_read' ],
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'name' => [
                            'type'        => 'string',
                            'description' => __( 'Full block name, for example creabb/container.', 'crea-bootstrap-blocks' ),
                        ],
                    ],
                    'required'             => [ 'name' ],
                    'additionalProperties' => false,
                ],
            ]
        );

        self::ability(
            'build-block-markup',
            [
                'label'               => __( 'Build block markup', 'crea-bootstrap-blocks' ),
                'description'         => __( 'Turns flat attribute values into valid block markup with the required blockstudio container.', 'crea-bootstrap-blocks' ),
                'execute_callback'    => [ self::class, 'run_build_block_markup' ],
                'permission_callback' => [ self::class, 'may_read' ],
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'name'       => [ 'type' => 'string' ],
                        'attributes' => [ 'type' => 'object' ],
                    ],
                    'required'             => [ 'name', 'attributes' ],
                    'additionalProperties' => false,
                ],
            ]
        );

        self::ability(
            'validate-block-markup',
            [
                'label'               => __( 'Validate block markup', 'crea-bootstrap-blocks' ),
                'description'         => __( 'Checks existing markup for a missing blockstudio container, attribute values stranded at the top level, unknown attributes, wrong types and leftover areoi/ blocks.', 'crea-bootstrap-blocks' ),
                'execute_callback'    => [ self::class, 'run_validate_block_markup' ],
                'permission_callback' => [ self::class, 'may_read' ],
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [ 'markup' => [ 'type' => 'string' ] ],
                    'required'             => [ 'markup' ],
                    'additionalProperties' => false,
                ],
            ]
        );

        self::ability(
            'get-docs',
            [
                'label'               => __( 'Get agent documentation', 'crea-bootstrap-blocks' ),
                'description'         => __( 'Returns the index or the full text of the bundled agent documentation.', 'crea-bootstrap-blocks' ),
                'execute_callback'    => [ self::class, 'run_get_docs' ],
                'permission_callback' => [ self::class, 'may_read' ],
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'file' => [
                            'type' => 'string',
                            'enum' => [ 'index', 'full' ],
                        ],
                    ],
                    'required'             => [ 'file' ],
                    'additionalProperties' => false,
                ],
            ]
        );

        self::ability(
            'diagnose',
            [
                'label'               => __( 'Diagnose', 'crea-bootstrap-blocks' ),
                'description'         => __( 'Reports the Blockstudio version, the state of the legacy switches and how many blocks and contracts are present.', 'crea-bootstrap-blocks' ),
                'execute_callback'    => [ self::class, 'run_diagnose' ],
                'permission_callback' => [ self::class, 'may_diagnose' ],
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [],
                    'additionalProperties' => false,
                ],
            ]
        );
    }

    /**
     * Returns every registered block of this plugin.
     *
     * @param mixed $input Ignored — the ability takes no arguments.
     * @return array{blocks: array<int, array<string, mixed>>}
     */
    public static function run_list_blocks( mixed $input ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- der Kern ruft jeden execute_callback mit dem validierten Input auf, unabhaengig davon, ob die Faehigkeit ihn braucht; list-blocks hat ein leeres Schema und ignoriert ihn deshalb bewusst.
        $blocks = [];

        foreach ( self::own_blocks() as $name => $type ) {
            $blocks[] = [
                'name'       => $name,
                'category'   => is_string( $type->category ?? null ) ? $type->category : '',
                'parent'     => is_array( $type->parent ?? null ) ? array_values( $type->parent ) : [],
                // Aus dem VERTRAG, nicht aus der Registry: Ein Attribut, das
                // die block.json nicht deklariert, existiert trotzdem.
                'attributes' => count( Contract::table( $name ) ),
            ];
        }

        usort( $blocks, static fn ( array $a, array $b ): int => strcmp( (string) $a['name'], (string) $b['name'] ) );

        return [ 'blocks' => $blocks ];
    }

    /**
     * Returns the attribute contract of one block.
     *
     * @param mixed $input Expects `['name' => 'creabb/…']`.
     * @return array<string, mixed>|\WP_Error
     */
    public static function run_get_block_schema( mixed $input ): array|\WP_Error {
        $name = is_array( $input ) && is_string( $input['name'] ?? null ) ? $input['name'] : '';

        if ( '' === $name || ! isset( self::own_blocks()[ $name ] ) ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_unknown_block',
                sprintf(
                    /* translators: %s: block name. */
                    __( 'No block of this plugin is registered under the name %s.', 'crea-bootstrap-blocks' ),
                    self::block_name_label( $name )
                ),
                [ 'status' => 404 ]
            );
        }

        $attributes = [];

        foreach ( Contract::table( $name ) as $attribut => $entry ) {
            $attributes[ $attribut ] = [
                'type'    => $entry['type'] ?? 'string',
                'default' => $entry['default'] ?? null,
            ];
        }

        return [
            'name'       => $name,
            'attributes' => $attributes,
        ];
    }

    /**
     * Builds valid block markup from flat attribute values.
     *
     * DIE CONTAINER-REGEL WIRD HIER NICHT UMGESETZT, sondern benutzt:
     * `Legacy::lift_attributes()` setzt sie bereits fuer den Renderpfad um.
     * Zwei Umsetzungen derselben Regel koennten auseinanderlaufen, ohne dass
     * es auffaellt — deshalb geht hier ein geparster Block durch dieselbe
     * Methode.
     *
     * ENTSCHEIDUNG ZU EINEM FEHLENDEN `serialize_block()` (Review-Fund
     * Aufgabe 7, Punkt 4): ein Fehler, kein leerer String. Ein Aufrufer, der
     * ein scheinbar erfolgreiches Ergebnis mit leerem `markup` bekommt, kann
     * diesen leeren String unbemerkt weiterreichen — derselbe stille
     * Fehlschlag, den die Repo-Konventionen fuer Attrappen verbieten, gilt
     * hier fuer das Ergebnis selbst. `serialize_block()` gehoert seit WordPress 5.3 zum
     * Kern; faellt sie dennoch weg, ist die Umgebung so kaputt, dass ein
     * Fehler ehrlicher ist als ein leeres Markup.
     *
     * @param mixed $input Expects `['name' => …, 'attributes' => [...]]`.
     * @return array{block: array<string, mixed>, markup: string}|\WP_Error
     */
    public static function run_build_block_markup( mixed $input ): array|\WP_Error {
        $name  = is_array( $input ) && is_string( $input['name'] ?? null ) ? $input['name'] : '';
        $werte = is_array( $input ) && is_array( $input['attributes'] ?? null ) ? $input['attributes'] : [];

        if ( '' === $name || ! isset( self::own_blocks()[ $name ] ) ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_unknown_block',
                sprintf(
                    /* translators: %s: block name. */
                    __( 'No block of this plugin is registered under the name %s.', 'crea-bootstrap-blocks' ),
                    self::block_name_label( $name )
                ),
                [ 'status' => 404 ]
            );
        }

        $vertrag = Contract::table( $name );
        $fehler  = self::contract_errors( $vertrag, $werte, $name );

        if ( null !== $fehler ) {
            return $fehler;
        }

        $block = Legacy::lift_attributes(
            [
                'blockName'    => $name,
                'attrs'        => $werte,
                'innerBlocks'  => [],

                /*
                 * `serialize_block()` (WordPress-Kern, wp-includes/blocks.php)
                 * greift UNGESCHUETZT auf `$block['innerContent']` zu — ohne
                 * diesen Schluessel warnt jeder Aufruf zweimal
                 * (Review-Fund, Fix-Runde 1). Ein geparster Block hat immer
                 * ein `innerContent`; ohne innere Bloecke ist es leer.
                 */
                'innerContent' => [],
            ]
        );

        /*
         * HIER WURDE BIS ZUM 2026-09-01 ABGERAEUMT — und das war falsch.
         *
         * Abschlussbefund W1 hatte den flachen Teil nach dem Heben entfernt,
         * mit der Begruendung, jedes Attribut stuende sonst doppelt und ein
         * Agent aendere dann womoeglich die flache Kopie, die beim Rendern
         * verworfen wird.
         *
         * Gemessen ist die Begruendung widerlegt. `anchor` steht in 45 von 47
         * Vertraegen, kommt also durch die Vertragspruefung und wird gehoben —
         * aber Blockstudio wirft es aus `$attributes`, weil es kein FELD ist.
         * Ohne die flache Kopie fehlt danach das `id`-Attribut im gerenderten
         * Markup; im Bestand tragen 599 Begrenzer ein `anchor`. Dasselbe gilt
         * fuer `className`.
         *
         * Und es ist genau die Form, die E-100 VERBIETET: Die Migration
         * kopiert, sie verschiebt nicht — die flache Ebene bleibt vollstaendig
         * stehen, der Container kommt dazu. Der Generator gab damit Markup
         * aus, das der Umschreiber so nie schreibt und der Renderpfad anders
         * behandelt.
         *
         * Die Sorge aus W1 bleibt berechtigt und steht jetzt dort, wo sie
         * hingehoert: als Hinweis NEBEN der Ausgabe, nicht als abweichende
         * Ausgabeform.
         */
        $markup = function_exists( 'serialize_block' ) ? serialize_block( $block ) : null;

        if ( null === $markup ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_serialize_unavailable',
                __( 'The WordPress core function serialize_block() is not available in this environment.', 'crea-bootstrap-blocks' ),
                [ 'status' => 500 ]
            );
        }

        return [
            'block'  => $block,
            'markup' => $markup,
            'note'   => __(
                'Attribute values appear twice on purpose: flat at the top level and inside the blockstudio container. That is the form the migration writes (it copies, it does not move). At render time the container wins — edit the container copy, not the flat one; the flat level only carries what WordPress itself reads from supports, such as anchor and className.',
                'crea-bootstrap-blocks'
            ),
        ];
    }

    /**
     * Checks existing block markup against the contract.
     *
     * BEFUNDE SIND KEIN FEHLER. Der Aufrufer will die Liste, nicht einen
     * Abbruch; `WP_Error` bleibt der unbrauchbaren EINGABE — und der
     * unbrauchbaren UMGEBUNG — vorbehalten.
     *
     * DIESELBE LINIE WIE BEI `serialize_block()` (Abschlussbefund G4): Beide
     * Kernfunktionen gibt es seit WordPress 5.x, und beide werden trotzdem
     * abgefragt. Der Grund ist nicht die Wahrscheinlichkeit, sondern die
     * Antwort im Fehlerfall: Ohne `parse_blocks()` liefe diese Faehigkeit in
     * einen Fatal, statt zu sagen, was fehlt — und die Entscheidung, welche
     * Kernfunktion eine Wache bekommt, waere von der Sache her willkuerlich.
     * Entweder beide oder keine; hier beide.
     *
     * @param mixed $input Expects `['markup' => '…']`.
     * @return array{valid: bool, findings: array<int, array<string, mixed>>}|\WP_Error
     */
    public static function run_validate_block_markup( mixed $input ): array|\WP_Error {
        $markup = is_array( $input ) && is_string( $input['markup'] ?? null ) ? $input['markup'] : '';

        if ( '' === trim( $markup ) ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_empty_markup',
                __( 'No markup was passed in.', 'crea-bootstrap-blocks' ),
                [ 'status' => 400 ]
            );
        }

        if ( ! function_exists( 'parse_blocks' ) ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_parse_unavailable',
                __( 'The WordPress core function parse_blocks() is not available in this environment.', 'crea-bootstrap-blocks' ),
                [ 'status' => 500 ]
            );
        }

        $findings = [];

        self::walk_for_findings( parse_blocks( $markup ), $findings );

        return [
            'valid'    => [] === $findings,
            'findings' => $findings,
        ];
    }

    /**
     * Returns one of the two bundled documentation files.
     *
     * ÜBER EINE ALLOWLIST, NICHT ÜBER EINE PFADBEREINIGUNG. Ein Dateiname aus
     * der Eingabe kommt hier nie in einen Pfad; `index` und `full` sind die
     * einzigen zulaessigen Werte, und die Zuordnung steht im Code. Damit gibt
     * es keinen Pfad, den jemand aus der Eingabe heraus verbiegen koennte.
     *
     * @param mixed $input Expects `['file' => 'index'|'full']`.
     * @return array{file: string, content: string}|\WP_Error
     */
    public static function run_get_docs( mixed $input ): array|\WP_Error {
        $erlaubt = [
            'index' => 'crea-bootstrap-blocks-llm.txt',
            'full'  => 'crea-bootstrap-blocks-llm-full.txt',
        ];

        $key = is_array( $input ) && is_string( $input['file'] ?? null ) ? $input['file'] : '';

        if ( ! isset( $erlaubt[ $key ] ) ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_unknown_doc',
                __( 'Only "index" and "full" are available.', 'crea-bootstrap-blocks' ),
                [ 'status' => 400 ]
            );
        }

        $pfad = CREA_BOOTSTRAP_BLOCKS_DIR . 'llm/' . $erlaubt[ $key ];

        if ( ! is_file( $pfad ) ) {
            return new \WP_Error(
                'crea_bootstrap_blocks_missing_doc',
                __( 'The documentation is not part of this installation.', 'crea-bootstrap-blocks' ),
                [ 'status' => 404 ]
            );
        }

        /*
         * `WordPress.WP.AlternativeFunctions` schlaegt hier an und schlaegt
         * `wp_remote_get()` vor. Wie in Contract::table(): Gelesen wird eine
         * Datei, die MIT DEM PLUGIN AUSGELIEFERT wird, kein entfernter URL.
         */
        return [
            'file'    => $erlaubt[ $key ],
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Begruendung im Block darueber.
            'content' => (string) file_get_contents( $pfad ),
        ];
    }

    /**
     * Reports the state of the installation.
     *
     * Nennt bewusst KEINE Fundstellen aus der Datenbank: Das waere eine Abfrage
     * ueber alle Beitraege und gehoert an die WP-CLI, nicht an eine Fähigkeit,
     * die jeder Agent im Sekundentakt aufrufen kann.
     *
     * DER THEME-OVERRIDE DAGEGEN SCHON (Abschlussbefund W4). Teil 9 der
     * Spezifikation verlangt ihn, er kostet zwei `is_dir()`, und laut dem
     * Docblock seiner eigenen Funktion ist ein liegengebliebener Ordner
     * `<theme>/all-bootstrap-blocks/` „der haeufigste Grund fuer Markup, das
     * sich nach der Migration nicht erklaeren laesst" — also genau die Frage,
     * mit der ein Agent hier ankommt.
     *
     * `null` STATT LEERER LISTE, wenn die Funktion fehlt: Sie wohnt in
     * `includes/admin-diagnose.php`. Die Datei steht unbedingt in der
     * Ladeliste, aber diese Faehigkeit laesst sich auch ohne sie aufrufen —
     * und `[]` hiesse „nachgesehen, nichts gefunden", was dann gelogen waere.
     *
     * @param mixed $input Ignored — the ability takes no arguments.
     * @return array<string, mixed>
     */
    public static function run_diagnose( mixed $input ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- wie run_list_blocks(): der Kern ruft jeden execute_callback mit dem validierten Input auf, auch bei leerem Schema.
        $bloecke = self::own_blocks();
        $mit     = 0;

        foreach ( array_keys( $bloecke ) as $name ) {
            if ( [] !== Contract::table( $name ) ) {
                ++$mit;
            }
        }

        return [
            'blockstudio_version' => defined( 'BLOCKSTUDIO_VERSION' ) ? (string) constant( 'BLOCKSTUDIO_VERSION' ) : null,
            'legacy_classes'      => crea_bootstrap_blocks_setting_enabled( 'legacy_classes', true ),
            'legacy_blocks'       => Legacy::blocks_enabled(),
            'blocks'              => count( $bloecke ),
            'contracts'           => $mit,
            'theme_overrides'     => function_exists( 'crea_bootstrap_blocks_diagnose_theme_overrides' )
                ? crea_bootstrap_blocks_diagnose_theme_overrides()
                : null,
        ];
    }

    /**
     * Collects findings across a parsed block tree, depth first.
     *
     * @param array<array-key, mixed>          $blocks   Parsed blocks.
     * @param array<int, array<string, mixed>> $findings Collected findings, by reference.
     */
    private static function walk_for_findings( array $blocks, array &$findings ): void {
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            $name = $block['blockName'] ?? null;

            if ( is_string( $name ) && str_starts_with( $name, Legacy::ALIAS_NAMESPACE ) ) {
                $findings[] = [
                    'code'    => 'legacy_namespace',
                    'block'   => $name,
                    'message' => sprintf(
                        /* translators: 1: legacy block name, 2: own namespace. */
                        __( '%1$s uses the old namespace. New markup belongs in %2$s.', 'crea-bootstrap-blocks' ),
                        $name,
                        Legacy::OWN_NAMESPACE
                    ),
                ];
            }

            if ( is_string( $name ) && str_starts_with( $name, Legacy::OWN_NAMESPACE ) ) {
                self::block_findings( $name, is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [], $findings );
            }

            if ( is_array( $block['innerBlocks'] ?? null ) ) {
                self::walk_for_findings( $block['innerBlocks'], $findings );
            }
        }
    }

    /**
     * Findings for one block of this plugin.
     *
     * Befundcodes: `missing_container`, `stray_flat_attribute`,
     * `unknown_attribute`, `wrong_type`.
     *
     * @param string                           $name     Block name.
     * @param array<array-key, mixed>          $attrs    Raw block attributes.
     * @param array<int, array<string, mixed>> $findings Collected findings, by reference.
     */
    private static function block_findings( string $name, array $attrs, array &$findings ): void {
        $container = is_array( $attrs[ Legacy::CONTAINER ] ?? null ) ? $attrs[ Legacy::CONTAINER ] : null;
        $vertrag   = Contract::table( $name );

        // Was „flach" heisst, steht an EINER Stelle — siehe Legacy::RESERVED.
        $flach = Legacy::flat_attributes( $attrs );

        if ( null === $container && [] !== $flach ) {
            $findings[] = [
                'code'    => 'missing_container',
                'block'   => $name,
                'message' => __( 'Attribute values sit at the top level. They are dropped at render time and replaced by defaults.', 'crea-bootstrap-blocks' ),
            ];
        }

        $werte              = is_array( $container['attributes'] ?? null ) ? $container['attributes'] : [];
        $vom_kern_container = self::registered_extras( $name );

        /*
         * Abschlussbefund W2 — UND SEINE KORREKTUR VOM 2026-09-01.
         *
         * W2 hatte recht darin, dass ein flacher Wert NEBEN einem Container
         * gelesen gehoert; bis dahin wurde `$flach` nur ausgewertet, wenn der
         * Container ganz fehlte. Falsch war die Schlussfolgerung: Es klagte
         * JEDEN flachen Schluessel an, ohne den Container zu befragen.
         *
         * Das ist per Konstruktion der von E-100 und harter Regel 2
         * VORGESCHRIEBENE Zustand. Die Migration kopiert, sie verschiebt
         * nicht — „gleiche Schluessel, gleiche Werte, gleiche Reihenfolge, ein
         * Container mehr". Wer die flache Ebene wegraeumt, verliert `className`
         * und `anchor` an beiden Stellen, weil Blockstudio `$block` aus der
         * oberen Ebene baut, BEVOR es den Container hineinmischt.
         *
         * Am echten Bestand gemessen: 30 674 von 30 674 Begrenzern (100 %)
         * loesten nach korrekter Migration mindestens einen Befund aus, auf
         * einer der gemessenen Installationen 8205 Anklagen bei 0 von 608 Traegern mit
         * `valid: true` — und ueber `parse_blocks()` gegengerechnet fehlte
         * oder abwich davon **kein einziger** Wert im Container. Die
         * Faehigkeit adelte damit den Vertragsbruch (nur Container →
         * `valid=true`) und klagte die Vertragstreue an.
         *
         * Verirrt ist ein flacher Schluessel deshalb nur, wenn er im Container
         * FEHLT oder dort ANDERS steht. Genau dann ist er unsichtbar und beim
         * ersten Speichern weg — der Fall, den W2 gemeint hat.
         */
        if ( null !== $container ) {
            foreach ( $flach as $attribut => $wert ) {
                $schluessel = (string) $attribut;

                if ( array_key_exists( $schluessel, $werte ) && $werte[ $schluessel ] === $wert ) {
                    continue;
                }

                /*
                 * UND WAS GAR NICHT IN DEN CONTAINER GEHOERT, kann dort auch
                 * nicht fehlen. `className` legt WordPress aus `supports` an,
                 * es steht in keinem der 47 Vertraege, und Blockstudio wirft
                 * jeden Nicht-Feld-Schluessel aus dem Container. Es gehoert
                 * OBEN — wer es dort anklagt, klagt den einzigen Ort an, an
                 * dem es wirkt. An der laufenden Instanz gemessen: 832 der
                 * 9037 Befunde waren genau das, ausnahmslos `className`.
                 */
                if ( ! isset( $vertrag[ $schluessel ] ) && in_array( $schluessel, $vom_kern_container, true ) ) {
                    continue;
                }

                $findings[] = [
                    'code'    => 'stray_flat_attribute',
                    'block'   => $name,
                    'message' => sprintf(
                        /* translators: 1: attribute name, 2: block name. */
                        __( 'The attribute %1$s of %2$s sits at the top level but is missing from the container or differs there. The container wins at render time, so this value is invisible in the editor and gone for good on the first save.', 'crea-bootstrap-blocks' ),
                        $schluessel,
                        $name
                    ),
                ];
            }
        }

        foreach ( $werte as $attribut => $wert ) {
            /*
             * Review-Fund Aufgabe 7, Punkt 1 (uebertragen auf diesen
             * Validator, Review-Fund Aufgabe 8, Befund 2): ein NICHT-stringer
             * Schluessel darf nicht in diesen Zweig geraten — der Vertrag
             * kennt ohnehin nur String-Schluessel, also ist ein solcher immer
             * unbekannt. Absichtlich NICHT `! is_string( $attribut ) ||
             * isset( $vertrag[ $attribut ] )` (Briefwortlaut): das wuerde den
             * nicht-stringen Fall in DIESEN Zweig statt in den
             * unknown_attribute-Befund unten schicken und ihn damit
             * stillschweigend durchrutschen lassen.
             */
            if ( is_string( $attribut ) && isset( $vertrag[ $attribut ] ) ) {
                /*
                 * Review-Fund Aufgabe 8, Befund 1 (Wichtig): die
                 * Spezifikation (09-spike-und-bauplan.md, Teil 9) verspricht
                 * "falsche Typen" als Befund; dieser Zweig kannte bisher nur
                 * missing_container und unknown_attribute — $wert wurde
                 * gebunden und nie gelesen. Die Typpruefung selbst stand
                 * danach WORTGLEICH hier und in contract_errors()
                 * (Abschlussbefund W3) und steht jetzt nur noch in
                 * type_matches().
                 */
                $typ = $vertrag[ $attribut ]['type'] ?? 'string';

                if ( ! self::type_matches( $typ, $wert ) ) {
                    $findings[] = [
                        'code'    => 'wrong_type',
                        'block'   => $name,
                        'message' => sprintf(
                            /* translators: 1: attribute name, 2: expected type. */
                            __( 'The attribute %1$s must be of type %2$s.', 'crea-bootstrap-blocks' ),
                            $attribut,
                            (string) $typ
                        ),
                    ];
                }

                continue;
            }

            /*
             * Auch hier gilt, was der Generator seit dem 2026-09-01 beachtet:
             * Was diese Installation registriert, ist nicht „unbekannt", auch
             * wenn kein Vertrag es kennt. `Legacy::lift_attributes()` traegt
             * jeden flachen Schluessel in den Container; `className` landet
             * damit auch dort, obwohl Blockstudio es von dort wieder
             * herauswirft. Ein Befund dagegen traefe 8635 von 30 674
             * Bestandsbloecken (28,2 %) — ohne dass irgendwo ein Wert fehlt.
             */
            if ( is_string( $attribut ) && in_array( (string) $attribut, $vom_kern_container, true ) ) {
                continue;
            }

            $findings[] = [
                'code'    => 'unknown_attribute',
                'block'   => $name,
                'message' => sprintf(
                    /* translators: %s: attribute name. */
                    __( 'The attribute %s is not part of this block\'s contract.', 'crea-bootstrap-blocks' ),
                    $attribut
                ),
            ];
        }
    }

    /**
     * Attribute keys this installation registers for a block.
     *
     * Der Container selbst ist ausgenommen: Ihn baut die Faehigkeit, er kommt
     * nicht als Eingabe herein.
     *
     * @param string $name Block name.
     * @return array<int, string>
     */
    private static function registered_extras( string $name ): array {
        if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
            return [];
        }

        $typ = \WP_Block_Type_Registry::get_instance()->get_all_registered()[ $name ] ?? null;

        $attribute = is_object( $typ ) && isset( $typ->attributes ) && is_array( $typ->attributes )
            ? array_keys( $typ->attributes )
            : [];

        return array_values(
            array_filter(
                array_map( 'strval', $attribute ),
                static fn ( string $key ): bool => Legacy::CONTAINER !== $key
            )
        );
    }

    /**
     * Checks flat attribute values against the contract.
     *
     * Die Meldung nennt das beanstandete Attribut beim NAMEN. Ein „ungueltige
     * Attribute" ohne Angabe zwingt den Aufrufer zum Suchen — und ein Agent
     * rate dann.
     *
     * @param array<string, array<string, mixed>> $vertrag Contract table.
     * @param array<array-key, mixed>             $werte   Flat attribute values.
     * @param string                              $name    Block name; empty skips the registry lookup.
     * @return \WP_Error|null Null when everything checks out.
     */
    private static function contract_errors( array $vertrag, array $werte, string $name = '' ): ?\WP_Error {
        $registriert = '' === $name ? [] : self::registered_extras( $name );

        foreach ( $werte as $attribut => $wert ) {
            /*
             * Die Buchfuehrung des Kerns steht nicht im Vertrag und wird nicht
             * geprueft. Welche Schluessel das sind, sagt Legacy::RESERVED —
             * dieselbe Liste, aus der der Renderpfad und der Validator lesen
             * (Abschlussbefund W3; hier stand vorher eine eigene Kopie).
             */
            if ( is_string( $attribut ) && in_array( $attribut, Legacy::RESERVED, true ) ) {
                continue;
            }

            /*
             * Review-Fund Aufgabe 7, Punkt 1: `json_decode()` macht aus einem
             * Schluessel "0" den int 0. Ein NICHT-stringer Schluessel darf
             * deshalb NICHT stillschweigend uebersprungen werden — der
             * Vertrag kennt ohnehin nur String-Schluessel, also ist ein
             * solches Attribut immer unbekannt. Ein stillschweigend
             * durchgereichtes Attribut ist schlimmer als ein abgewiesenes.
             */
            if ( ! is_string( $attribut ) || ! isset( $vertrag[ $attribut ] ) ) {
                /*
                 * NICHT IM VERTRAG, ABER VON DIESER INSTALLATION REGISTRIERT.
                 *
                 * WordPress legt aus `supports` eigene Attribute an — bei
                 * `creabb/container` gemessen: `className` und
                 * `blockVisibility` neben dem Container. `className` steht in
                 * KEINEM der 47 Vertraege und trifft trotzdem **8635 von
                 * 30 674** Bestandsbloecken (28,2 %). Es abzuweisen hiess, die
                 * Faehigkeit fuer beinahe jeden dritten echten Block mit
                 * HTTP 400 zu beenden — und zwar fuer einen Wert, den
                 * WordPress selbst liest.
                 *
                 * ABGELEITET, NICHT AUFGEZAEHLT: Gefragt wird die Registry
                 * dieser Installation, nicht eine Handliste. Eine Handliste
                 * waere schon beim naechsten `supports`-Schluessel falsch, und
                 * sie unterschiede sich zwischen Installationen mit anderen
                 * Filtern.
                 *
                 * DIE REIHENFOLGE IST BINDEND: Dieser Zweig ist erst
                 * vertretbar, seit die Faehigkeit BEIDE Ebenen ausgibt. Solange
                 * sie die flache Ebene abraeumte, waere ein durchgelassenes
                 * `className` ein Klassenverlust gewesen — es haette im
                 * Container gestanden, wo Blockstudio es herauswirft.
                 */
                if ( is_string( $attribut ) && in_array( $attribut, $registriert, true ) ) {
                    continue;
                }

                return new \WP_Error(
                    'crea_bootstrap_blocks_unknown_attribute',
                    sprintf(
                        /* translators: %s: attribute name. */
                        __( 'The attribute %s is not part of this block\'s contract.', 'crea-bootstrap-blocks' ),
                        $attribut
                    ),
                    [ 'status' => 400 ]
                );
            }

            $typ = $vertrag[ $attribut ]['type'] ?? 'string';

            if ( ! self::type_matches( $typ, $wert ) ) {
                return new \WP_Error(
                    'crea_bootstrap_blocks_wrong_type',
                    sprintf(
                        /* translators: 1: attribute name, 2: expected type. */
                        __( 'The attribute %1$s must be of type %2$s.', 'crea-bootstrap-blocks' ),
                        $attribut,
                        (string) $typ
                    ),
                    [ 'status' => 400 ]
                );
            }
        }

        return null;
    }

    /**
     * Whether one value satisfies the contract type of its attribute.
     *
     * DIE EINE FASSUNG DER TYPPRUEFUNG. Sie stand wortgleich in
     * block_findings() und in contract_errors() (Abschlussbefund W3) — zwei
     * Kopien, die zwei Faehigkeiten speisen, die einander widersprechen
     * koennen: `build-block-markup` weist ein Attribut ab, das
     * `validate-block-markup` durchwinkt. Keine Zusicherung band sie
     * aneinander; `tests/test-abilities-markup.php`, Teil 9 tut es jetzt.
     *
     * `default => is_string()` ist Absicht und kein Auffangnetz: Der Vertrag
     * kennt ausser den hier genannten Typen keinen, und alle Massangaben sind
     * laut harter Regel 2 Strings.
     *
     * @param mixed $typ  Declared contract type.
     * @param mixed $wert The value to check.
     */
    private static function type_matches( mixed $typ, mixed $wert ): bool {
        return match ( $typ ) {
            'boolean' => is_bool( $wert ),
            'number'  => is_int( $wert ) || is_float( $wert ),
            'object', 'array' => is_array( $wert ),
            default   => is_string( $wert ),
        };
    }

    /**
     * The block name for an error message, with a readable stand-in for none.
     *
     * Der Platzhalter war zweimal das deutsche Literal `(leer)` mitten in einer
     * englischen Quellzeichenkette (Abschlussbefund G6) — in jeder Sprache
     * deutsch, weil er nie durch die Uebersetzung lief. Jetzt laeuft er
     * hindurch, und zwar an genau einer Stelle.
     *
     * @param string $name Block name from the input, possibly empty.
     */
    private static function block_name_label( string $name ): string {
        /* translators: stands in for the block name when the caller passed none. */
        return '' === $name ? __( '(empty)', 'crea-bootstrap-blocks' ) : $name;
    }

    /**
     * The registered block types of this plugin's namespace.
     *
     * @return array<string, \WP_Block_Type>
     */
    private static function own_blocks(): array {
        if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
            return [];
        }

        $out = [];

        foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
            if ( is_string( $name ) && str_starts_with( $name, Legacy::OWN_NAMESPACE ) ) {
                $out[ $name ] = $type;
            }
        }

        return $out;
    }

    /**
     * Whether the current user may read block information.
     *
     * `edit_posts` ist die Schwelle: Wer keine Beitraege bearbeiten darf,
     * braucht auch keine Blockschemata.
     */
    public static function may_read(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Whether the current user may run the diagnosis.
     *
     * Strenger als der Rest: Die Diagnose nennt Plugin-Versionen, Fundstellen
     * und den Zustand der Alt-Optionen. Das ist Betriebswissen.
     */
    public static function may_diagnose(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Registers one ability with the shared meta every ability of this plugin needs.
     *
     * FUER DIE REGEL GESCHNITTEN, nicht fuer die heute bekannten sechs:
     * `show_in_rest` und die drei Annotationen sind im Kern optional, und wer
     * sie auslaesst, bekommt keine Fehlermeldung — sondern eine unsichtbare
     * bzw. per POST aufgerufene Faehigkeit. Hier kann sie niemand auslassen.
     *
     * @param lowercase-string     $slug Ability slug without the namespace.
     * @param array<string, mixed> $args Label, description, callbacks, schemas.
     */
    private static function ability( string $slug, array $args ): void {
        wp_register_ability(
            self::CATEGORY . '/' . $slug,
            array_merge(
                $args,
                [
                    'category' => self::CATEGORY,
                    'meta'     => [
                        'show_in_rest' => true,
                        'annotations'  => [
                            'readonly'    => true,
                            'destructive' => false,
                            'idempotent'  => true,
                        ],
                    ],
                ]
            )
        );
    }
}

Abilities::init();
