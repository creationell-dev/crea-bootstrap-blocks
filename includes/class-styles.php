<?php
/**
 * The generated `.block-<uuid>` stylesheet.
 *
 * Neufassung von `AREOI_Styles::add_block_styles()`. Selektoren,
 * `@media`-Gruppierung, Breakpoints und das Weglassen leerer Media-Bloecke
 * bleiben unveraendert (Vertragsregel 4). Vier Korrekturen:
 *
 *   - Eingesammelt wird nur, was auf der Seite vorkommt.
 *   - Das Ergebnis wird pro Anfrage memoisiert.
 *   - WERTE WERDEN VALIDIERT, bevor sie ins CSS gehen.
 *   - `0` bleibt ein gueltiger Wert (`!== null && !== ''`, nicht `empty()`).
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds the generated stylesheet of the current page.
 */
final class Styles {

    /**
     * Breakpoint minimum widths of Bootstrap 5.3, keyed by suffix.
     *
     * Ersetzt die Alt-Optionen `areoi-layout-grid-grid-breakpoint-<bp>`.
     *
     * @var array<string, int>
     */
    private const DEFAULT_BREAKPOINTS = [
        'xs'  => 0,
        'sm'  => 576,
        'md'  => 768,
        'lg'  => 992,
        'xl'  => 1200,
        'xxl' => 1400,
    ];

    /**
     * Allowed CSS length units.
     *
     * @var array<int, string>
     */
    private const DEFAULT_UNITS = [ 'px', '%', 'vh', 'vw', 'rem', 'em' ];

    /**
     * Memoised block tree of the current request.
     *
     * @var array<array-key, mixed>|null
     */
    private static ?array $blocks = null;

    /**
     * Memoised stylesheet of the current request.
     *
     * @var string|null
     */
    private static ?string $stylesheet = null;

    /**
     * Validates a numeric attribute value.
     *
     * Gueltig ist ausschliesslich eine Zahl OHNE Einheit — so liegen die Werte
     * im Bestand vor: alle 13 614 belegten `padding_*`- und `margin_*`-Werte
     * sind Strings wie "64", "0" oder "", nie Zahlen mit Einheit.
     *
     * `false` wird abgewiesen: Es ist der Registry-Default eines
     * Blockstudio-Feldes, kein vom Benutzer gesetzter Wert. `0` dagegen kommt
     * durch — das ist Vertragsregel 1.5.
     *
     * @param mixed $value Raw attribute value.
     * @return string|null Canonical number, or null when the value is not usable.
     */
    public static function css_number( mixed $value ): ?string {
        if ( is_bool( $value ) || null === $value ) {
            return null;
        }

        if ( is_int( $value ) ) {
            return (string) $value;
        }

        if ( is_float( $value ) ) {
            return rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );
        }

        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = trim( $value );

        if ( '' === $value ) {
            return null;
        }

        // Kein Exponent, kein fuehrendes Plus, kein Dezimalkomma, keine Einheit.
        if ( 1 !== preg_match( '/^-?(?:\d+|\d*\.\d+)$/', $value ) ) {
            return null;
        }

        return $value;
    }

    /**
     * The allowed CSS length units, filterable.
     *
     * @return array<int, string>
     */
    private static function allowed_units(): array {
        $filtered = apply_filters( 'crea_bootstrap_blocks_allowed_units', self::DEFAULT_UNITS );

        if ( ! is_array( $filtered ) ) {
            return self::DEFAULT_UNITS;
        }

        $clean = [];

        foreach ( $filtered as $unit ) {
            if ( is_string( $unit ) && 1 === preg_match( '/^[a-z%]{1,5}$/', $unit ) ) {
                $clean[] = $unit;
            }
        }

        return [] === $clean ? self::DEFAULT_UNITS : $clean;
    }

    /**
     * Validates a CSS length unit against the allowlist.
     *
     * @param mixed $value Raw attribute value.
     * @return string|null Lowercased unit, or null when not allowed.
     */
    public static function css_unit( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = strtolower( trim( $value ) );

        return in_array( $value, self::allowed_units(), true ) ? $value : null;
    }

    /**
     * The character set a `block_id` must consist of.
     *
     * Buchstaben, Ziffern, Unterstrich, Bindestrich — mehr nicht. Genau dieser
     * Vorrat kann einen CSS-Selektor nicht verlassen.
     */
    private const BLOCK_ID_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * Validates a `block_id` attribute against a selector-safe character set.
     *
     * WOFUER DIE PRUEFUNG DA IST. Der Wert landet unmaskiert als Selektor
     * `.block-<wert> {` im erzeugten Stylesheet (siehe rules() weiter unten).
     * Das Original schreibt ihn dort ungeprueft (class.areoi.styles.php:335) —
     * ein `block_id` wie `x{display:none}body` erzeugt damit beliebige Regeln,
     * und ein blosses `a,body` braucht dafuer nicht einmal eine Klammer:
     * `.block-a,body { display:none }` faerbt die ganze Seite.
     *
     * WARUM EIN ZEICHENSATZ UND NICHT DIE UUID-FORM. Bis zum 2026-08-31 verlangte
     * diese Methode die UUID-Form. Das war zu eng: Am echten Bestand gemessen —
     * drei Produktivdatenbanken, 30 674 Belegungen — sind 7124 davon (23,2 %)
     * KEINE UUID, sondern redaktionelle Kennungen wie `team-grid` oder `hn-1`.
     * Auf einer der drei Installationen sind es 6710 von 6741, und das aktive
     * Theme dort stylt direkt gegen fuenf dieser Klassen. Die Sperre kostete
     * damit genau das, was der Kompatibilitaetsvertrag schuetzen soll
     * (Vertragsregel 3), ohne auf dem Klassenweg ueberhaupt etwas zu gewinnen:
     * Dort escapt schon das Original.
     *
     * Der Zeichenvorrat ALLER 1436 verschiedenen Nicht-UUID-Werte des Bestands
     * ist `a-z`, `0-9` und `-`. Der Zeichensatz hier laesst jeden davon durch
     * und weist trotzdem jeden Wert ab, der aus dem Selektor ausbrechen kann.
     *
     * WAS BEWUSST DRAUSSEN BLEIBT: der Punkt (macht einen zusammengesetzten
     * Selektor), das Komma (siehe oben), Leerraum innen (Nachfahrenselektor),
     * Doppelpunkt, eckige Klammern, Schraegstriche und der Backslash. Ein Wert
     * mit einem dieser Zeichen kommt im Bestand nicht vor; kaeme er auf einer
     * anderen Installation vor, verloere er hier seine Klasse — das ist die
     * bewusst in Kauf genommene Restgroesse dieser Entscheidung.
     *
     * Die Schreibweise bleibt erhalten (kein strtolower): Der Selektor muss zu
     * der Klasse passen, die crea_bootstrap_blocks_block_id_class() ausgibt.
     *
     * @param mixed $value Raw attribute value.
     * @return string|null The value, or null when it is not selector-safe.
     */
    public static function block_id( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = trim( $value );

        if ( 1 !== preg_match( self::BLOCK_ID_PATTERN, $value ) ) {
            return null;
        }

        return $value;
    }

    /**
     * The unit used for generated padding and margin values.
     *
     * Ersetzt die Alt-Option `areoi-dashboard-global-display-units`. Sie steht in
     * allen drei geprueften Projekten auf `px` und ist deshalb hartkodiert, mit
     * dem Filter als Ausweg (04-kompatibilitaets-vertrag.md).
     */
    public static function spacing_unit(): string {
        $unit = apply_filters( 'crea_bootstrap_blocks_spacing_unit', 'px' );

        return self::css_unit( $unit ) ?? 'px';
    }

    /**
     * Breakpoint minimum widths, keyed by suffix.
     *
     * Ersetzt die Alt-Optionen `areoi-layout-grid-grid-breakpoint-<bp>`. Nur die
     * sechs bekannten Schluessel ueberleben den Filter — ein siebter Breakpoint
     * erzeugte einen @media-Block, dem im Markup nichts entspricht.
     *
     * @return array<string, int>
     */
    public static function breakpoints(): array {
        $filtered = apply_filters( 'crea_bootstrap_blocks_breakpoints', self::DEFAULT_BREAKPOINTS );

        if ( ! is_array( $filtered ) ) {
            return self::DEFAULT_BREAKPOINTS;
        }

        $out = [];
        foreach ( self::DEFAULT_BREAKPOINTS as $key => $default_value ) {
            $value = $filtered[ $key ] ?? null;

            if ( is_int( $value ) && $value >= 0 ) {
                $out[ $key ] = $value;
                continue;
            }

            if ( is_string( $value ) && 1 === preg_match( '/^\d+$/', trim( $value ) ) ) {
                $out[ $key ] = (int) trim( $value );
                continue;
            }

            $out[ $key ] = $default_value;
        }

        return $out;
    }
    /**
     * Extracts the attribute set of a parsed block, in both storage forms.
     *
     * Der CSS-Generator liest den ROHEN Blockkommentar aus `parse_blocks()` —
     * nicht die von Blockstudio aufgeloesten Attribute. Er sieht deshalb genau
     * das, was in `post_content` steht, und muss beide Formen kennen:
     *
     *   verschachtelt (migriert, S-4):
     *     attrs.blockstudio.attributes = { block_id: "…", padding_top_xs: "64" }
     *   flach (nicht migrierter Bestand, areoi/*-Aliasbloecke, uebersehene
     *   Fundstellen):
     *     attrs = { block_id: "…", padding_top_xs: "64" }
     *
     * Bei Namenskollision gewinnt der Container — genauso wie Blockstudios
     * `Block::transform()` (block.php:1805-1822) es beim Rendern tut. Waere es
     * andersherum, zeigte das generierte CSS einen anderen Stand als das Markup.
     *
     * Der Schluessel `blockstudio` selbst wird entfernt: Er ist der Container,
     * kein Vertragsattribut.
     *
     * @param array<string, mixed> $block One block as returned by parse_blocks().
     * @return array<string, mixed> Flat attribute set.
     */
    public static function block_attributes( array $block ): array {
        $attributes = $block['attrs'] ?? null;

        if ( ! is_array( $attributes ) ) {
            return [];
        }

        $container = $attributes['blockstudio'] ?? null;
        unset( $attributes['blockstudio'] );

        if ( ! is_array( $container ) ) {
            return $attributes;
        }

        $nested = $container['attributes'] ?? null;

        if ( ! is_array( $nested ) ) {
            return $attributes;
        }

        /*
         * DER CONTAINER ZUERST, DIE FLACHEN RESTE DAHINTER.
         *
         * Der Plan druckte hier `array_merge( $attributes, $nested )` — die
         * flachen Werte zuerst — und seine eigene Suite erwartete die
         * umgekehrte Reihenfolge. Inhaltlich ist der Container der eigentliche
         * Attributsatz; was flach danebensteht, ist Rest einer
         * unvollstaendigen Migration. `array_diff_key()` haelt dabei die
         * Namenskollision beim Container: Ein blosses
         * `array_merge( $nested, $attributes )` liesse den ALTEN flachen Wert
         * gewinnen und waere ein Vertragsbruch.
         *
         * Auf das erzeugte CSS wirkt die Reihenfolge nicht — `declarations()`
         * laeuft ueber eine feste Eigenschaftsliste und schlaegt jeden
         * Attributnamen einzeln nach. Sie ist hier Formsache und als solche
         * zugesichert, nicht Teil des Vertrags aus Regel 4.
         */
        return array_merge( $nested, array_diff_key( $attributes, $nested ) );
    }
    /**
     * Builds the declarations of one block for one breakpoint.
     *
     * Erzeugt werden genau die Eigenschaften des Originals:
     * `height` aus `height_dimension_<bp>` plus `height_unit_<bp>` (Fallback
     * `px`), sowie `padding-top|right|bottom|left` und
     * `margin-top|right|bottom|left` mit der uebergebenen Einheit.
     *
     * ZWEI VERSCHIEDENE LEERPRUEFUNGEN, beide 1:1 aus dem Original:
     *
     * - Abstaende pruefen auf `!== null && !== ''`. Der Wert `0` ist gueltig:
     *   `padding_top_xs = "0"` erzeugt `padding-top: 0px`, `""` erzeugt keine
     *   Regel (Vertragsregel 1.5). Ein `empty()` machte aus beidem dasselbe und
     *   loeschte im Bestand jede bewusst auf null gesetzte Kante.
     * - Die HOEHE prueft mit `empty()` (class.areoi.styles.php:320). Eine Hoehe
     *   von "0" erzeugt deshalb keine Regel. Das ist eine Eigenheit, keine
     *   Nachlaessigkeit — sie bleibt erhalten, damit die Regelmenge auf
     *   Bestandsseiten identisch bleibt.
     *
     * Jeder Wert laeuft vor der Ausgabe durch css_number() bzw. css_unit(). Das
     * Original schreibt sie ungeprueft in den Stylesheet-String.
     *
     * @param array<string, mixed> $attributes Flat attribute set.
     * @param string               $suffix     Breakpoint suffix including the underscore (`_xs`).
     * @param string               $unit       Unit for padding and margin values.
     * @return string CSS declarations, or an empty string.
     */
    public static function declarations( array $attributes, string $suffix, string $unit ): string {
        $out = '';

        $height = $attributes[ 'height_dimension' . $suffix ] ?? null;

        if ( ! empty( $height ) ) {
            $number = self::css_number( $height );

            if ( null === $number ) {
                self::log( 'height_dimension' . $suffix, $height );
            } else {
                $height_unit = self::css_unit( $attributes[ 'height_unit' . $suffix ] ?? null ) ?? 'px';
                $out        .= 'height: ' . $number . $height_unit . ';';
            }
        }

        foreach ( [ 'padding', 'margin' ] as $box ) {
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                $key   = $box . '_' . $side . $suffix;
                $value = $attributes[ $key ] ?? null;

                // Bewusst NICHT empty(): "0" muss durchkommen.
                if ( null === $value || '' === $value || false === $value ) {
                    continue;
                }

                $number = self::css_number( $value );

                if ( null === $number ) {
                    self::log( $key, $value );
                    continue;
                }

                $out .= $box . '-' . $side . ': ' . $number . $unit . ';';
            }
        }

        return $out;
    }

    /**
     * The CSS grid declarations of one block, one breakpoint.
     *
     * NACHGEMESSEN AM ORIGINAL, `class.areoi.styles.php:340-388`. Der Zweig
     * lief dort hinter `$is_cssgrid` und erzeugt ZWEI verschiedene Formen:
     *
     *   `row`    → Selektor `.block-<id>.grid`, Deklarationen `--bs-columns`,
     *              `--bs-gap`, `row-gap`, `--bs-rows`.
     *   `column` → Selektor `.block-<id>` — der EINFACHE —, Deklaration
     *              `grid-row`. Sie steht damit als zweite Regel neben der
     *              Abstandsregel desselben Blocks im selben `@media`-Block.
     *
     * `--bs-columns` kommt aus den ENDSTAENDIGEN Ziffern von `row_cols_<bp>`;
     * gespeichert ist dort `row-cols-3`, nicht `3`. Das Original zieht sie mit
     * `#(\d+)$#` heraus, und diese Form ist Vertragsbestandteil.
     *
     * Jede Wache ist `!empty()` — wie im Original, an jeder Stelle einzeln
     * (E-41). Die WERTE dagegen laufen durch dieselbe Pruefung wie die
     * Abstaende: Das ist die gebuchte erwartete Abweichung 7, und ohne sie
     * ginge ein `}` im Wert als CSS-Ausbruch durch.
     *
     * @param string               $slug       Block slug without namespace.
     * @param array<string, mixed> $attributes Flat attribute values.
     * @param string               $suffix     Breakpoint suffix including the underscore.
     * @return string Declarations, empty when the block contributes none.
     */
    public static function grid_declarations( string $slug, array $attributes, string $suffix ): string {
        if ( 'column' === $slug ) {
            $value = $attributes[ 'grid_row' . $suffix ] ?? null;

            if ( empty( $value ) ) {
                return '';
            }

            $number = self::css_number( $value );

            if ( null === $number ) {
                self::log( 'grid_row' . $suffix, $value );

                return '';
            }

            return 'grid-row: ' . $number . ';';
        }

        if ( 'row' !== $slug ) {
            return '';
        }

        $out  = '';
        $cols = $attributes[ 'row_cols' . $suffix ] ?? null;

        if ( ! empty( $cols ) && is_scalar( $cols ) ) {
            $matches = [];

            if ( 1 === preg_match( '#(\d+)$#', (string) $cols, $matches ) ) {
                $out .= '--bs-columns: ' . $matches[1] . ';';
            } else {
                self::log( 'row_cols' . $suffix, $cols );
            }
        }

        foreach (
            [
                'grid_gap'     => '--bs-gap',
                'grid_row_gap' => 'row-gap',
            ] as $family => $property
        ) {
            $dimension = $attributes[ $family . '_dimension' . $suffix ] ?? null;

            if ( empty( $dimension ) ) {
                continue;
            }

            $number = self::css_number( $dimension );

            if ( null === $number ) {
                self::log( $family . '_dimension' . $suffix, $dimension );

                continue;
            }

            $unit = self::css_unit( $attributes[ $family . '_unit' . $suffix ] ?? null ) ?? 'px';

            $out .= $property . ': ' . $number . $unit . ';';
        }

        $rows = $attributes[ 'grid_rows' . $suffix ] ?? null;

        if ( ! empty( $rows ) ) {
            $number = self::css_number( $rows );

            if ( null === $number ) {
                self::log( 'grid_rows' . $suffix, $rows );
            } else {
                $out .= '--bs-rows: ' . $number . ';';
            }
        }

        return $out;
    }

    /**
     * The selector suffix a block's grid rule carries.
     *
     * `row` schreibt unter `.block-<id>.grid`, `column` unter den einfachen
     * Selektor — nachgemessen am Original, nicht angenommen.
     *
     * @param string $slug Block slug without namespace.
     */
    public static function grid_selector_suffix( string $slug ): string {
        return 'row' === $slug ? '.grid' : '';
    }

    /**
     * The block slug of one parsed block, or the empty string.
     *
     * Nur die beiden eigenen Namensraeume zaehlen: Ein fremdes `xyz/row` ist
     * kein Block dieses Plugins und bekommt keine Grid-Regel.
     *
     * @param array<array-key, mixed> $block Parsed block.
     */
    public static function block_slug( array $block ): string {
        $name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';

        foreach ( [ 'creabb/', 'areoi/' ] as $prefix ) {
            if ( str_starts_with( $name, $prefix ) ) {
                return substr( $name, strlen( $prefix ) );
            }
        }

        return '';
    }

    /**
     * Builds the `.block-<uuid>` rules for a block tree, one breakpoint.
     *
     * Laeuft rekursiv ueber `innerBlocks` — der Elternblock steht vor seinen
     * Kindern, wie im Original. Eine Regel entsteht nur, wenn es Deklarationen
     * UND ein gueltiges `block_id` gibt.
     *
     * @param array<array-key, mixed> $blocks    Parsed blocks.
     * @param string                  $suffix    Breakpoint suffix including the underscore.
     * @param string                  $unit      Unit for padding and margin values.
     * @param bool|null               $grid_mode CSS grid mode; null asks `crea_bootstrap_blocks_css_grid_enabled()`.
     * @return string Concatenated CSS rules.
     */
    public static function rules( array $blocks, string $suffix, string $unit, ?bool $grid_mode = null ): string {
        /*
         * DER GRID-SCHALTER KOMMT ALS PARAMETER, nicht als versteckter Aufruf.
         *
         * Diese Klasse bleibt ohne `helpers.php` benutzbar — dieselbe
         * Ueberlegung wie beim Logger unten. Ein blosser
         * `function_exists()`-Aufruf haette den Modus stillschweigend
         * abgeschaltet, sobald die Helfer fehlen; so ist er explizit und von
         * einer Suite in beide Richtungen fahrbar.
         */
        if ( null === $grid_mode ) {
            $grid_mode = function_exists( 'crea_bootstrap_blocks_css_grid_enabled' )
                && crea_bootstrap_blocks_css_grid_enabled();
        }

        $css = '';

        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            $attributes   = self::block_attributes( $block );
            $declarations = self::declarations( $attributes, $suffix, $unit );

            /*
             * DIE GRID-REGEL KOMMT NACH DER ABSTANDSREGEL — wie im Original,
             * wo beide in derselben Schleife an `$styles` gehaengt werden
             * (`class.areoi.styles.php:333` und `:340`). Bei `column` tragen
             * beide denselben Selektor; die Reihenfolge entscheidet dann in
             * der Kaskade, und `css_rules()` haengt sie seit #31 in genau
             * dieser Reihenfolge aneinander.
             */
            $grid = $grid_mode
                ? self::grid_declarations( self::block_slug( $block ), $attributes, $suffix )
                : '';

            if ( '' !== $declarations || '' !== $grid ) {
                $block_id = self::block_id( $attributes['block_id'] ?? null );

                if ( null === $block_id ) {
                    self::log( 'block_id', $attributes['block_id'] ?? null );
                } else {
                    if ( '' !== $declarations ) {
                        $css .= '.block-' . $block_id . ' {' . $declarations . '}';
                    }

                    if ( '' !== $grid ) {
                        $css .= '.block-' . $block_id
                            . self::grid_selector_suffix( self::block_slug( $block ) )
                            . ' {' . $grid . '}';
                    }
                }
            }

            $inner = $block['innerBlocks'] ?? null;

            if ( is_array( $inner ) && [] !== $inner ) {
                $css .= self::rules( $inner, $suffix, $unit, $grid_mode );
            }
        }

        return $css;
    }

    /**
     * Reports a rejected attribute value.
     *
     * Der Logger lebt in `includes/helpers.php` und schreibt nur, wenn
     * CREA_BOOTSTRAP_BLOCKS_DEBUG gesetzt ist. Der function_exists()-Test haelt
     * diese Klasse davon unabhaengig — sie laeuft auch, wenn nur sie selbst
     * geladen ist.
     *
     * @param string $key   Attribute name.
     * @param mixed  $value Rejected value.
     */
    private static function log( string $key, mixed $value ): void {
        if ( ! function_exists( 'crea_bootstrap_blocks_log' ) ) {
            return;
        }

        crea_bootstrap_blocks_log(
            sprintf(
                'Styles: Attributwert verworfen — %s = %s',
                $key,
                is_scalar( $value ) ? (string) $value : gettype( $value )
            )
        );
    }

    /**
     * Drops the per-request memoisation.
     *
     * Nur fuer Tests und WP-CLI gedacht — im Frontend gibt es pro Anfrage genau
     * eine Seite und damit genau einen Blockbaum.
     */
    public static function reset_cache(): void {
        self::$blocks     = null;
        self::$stylesheet = null;
    }

    /**
     * Collects every block that actually appears on the current page.
     *
     * Vier Quellen (Vertragsregel 3.2):
     *   1. der aktuelle Post,
     *   2. dessen `core/block`-Referenzen (rekursiv, zyklussicher),
     *   3. das aktuelle FSE-Template samt `core/template-part`,
     *   4. Widget-Bloecke aus `widget_block` entlang `sidebars_widgets`.
     *
     * Das `get_posts(['post_type'=>'wp_block','numberposts'=>-1])` des Originals
     * (class.areoi.styles.php:245-254) entfaellt: Es laedt ALLE Reusable Blocks
     * der Installation auf JEDEM Seitenaufruf und erzeugt Regeln fuer Bloecke,
     * die auf der Seite gar nicht vorkommen. Diese Regeln sind wirkungslos; die
     * Verifikation muss die Differenz als erwartet einstufen
     * (06-verifikation.md).
     *
     * `get_the_ID()` ist bewusst die einzige Quelle fuer den Post — ein
     * Rueckfall auf `get_queried_object_id()` lieferte auf Archivseiten eine
     * Term-ID und damit den Inhalt eines wildfremden Posts.
     *
     * @return array<array-key, mixed> Parsed block tree.
     */
    public static function collect_blocks(): array {
        if ( null !== self::$blocks ) {
            return self::$blocks;
        }

        $blocks  = [];
        $post_id = (int) get_the_ID();

        if ( $post_id > 0 ) {
            $blocks = parse_blocks( (string) get_the_content( null, false, $post_id ) );
        }

        $blocks = array_merge( $blocks, self::template_blocks(), self::widget_blocks() );

        $seen         = [];
        self::$blocks = self::expand_reusable( $blocks, $seen );

        return self::$blocks;
    }

    /**
     * Builds the complete generated stylesheet for the current page.
     *
     * Ein `@media ( min-width: <bp>px )`-Block je Breakpoint, in aufsteigender
     * Reihenfolge. Ein Block, der keine Regel enthielte, wird WEGGELASSEN — das
     * Original loest das mit einem `continue` (class.areoi.styles.php:300-302),
     * hier mit derselben Wirkung.
     *
     * Die Schreibweise der Media-Query ist uebernommen, samt der Leerzeichen
     * innerhalb der Klammern. Sie ist fuer Browser bedeutungslos, macht aber
     * einen Diff gegen die Ausgabe des Originals lesbar.
     *
     * Kein Minifier: `areoi_minify_css()` hat eine defekte HEX-Regel und liefert
     * beim PCRE-Backtrack-Limit stillschweigend null — dann verschwindet das
     * gesamte Inline-CSS. Bei dieser Menge lohnt keiner.
     *
     * @return string The stylesheet, or an empty string when there is nothing to emit.
     */
    public static function stylesheet(): string {
        if ( null !== self::$stylesheet ) {
            return self::$stylesheet;
        }

        $blocks = self::collect_blocks();
        $unit   = self::spacing_unit();
        $css    = '';

        foreach ( self::breakpoints() as $key => $min_width ) {
            $rules = self::rules( $blocks, '_' . $key, $unit );

            if ( '' === $rules ) {
                continue;
            }

            $css .= '@media ( min-width: ' . $min_width . 'px ) {' . $rules . '}';
        }

        self::$stylesheet = $css;

        return $css;
    }

    /**
     * Resolves `core/block` references into the tree.
     *
     * Der referenzierte Inhalt wird als `innerBlocks` des `core/block`-Eintrags
     * eingehaengt, damit rules() ihn beim normalen Rekursionsdurchlauf mitnimmt.
     * `$seen` verhindert die Endlosschleife bei einem Selbst- oder Ringbezug —
     * moeglich, seit Reusable Blocks im Editor ineinander gesetzt werden koennen.
     *
     * @param array<array-key, mixed> $blocks Parsed blocks.
     * @param array<int, bool>        $seen   Already resolved wp_block post IDs.
     * @return array<array-key, mixed>
     */
    private static function expand_reusable( array $blocks, array &$seen ): array {
        foreach ( $blocks as $key => $block ) {
            if ( ! is_array( $block ) ) {
                unset( $blocks[ $key ] );
                continue;
            }

            $ref = $block['attrs']['ref'] ?? null;

            if ( 'core/block' === ( $block['blockName'] ?? null ) && is_numeric( $ref ) ) {
                $ref = (int) $ref;

                if ( $ref > 0 && ! isset( $seen[ $ref ] ) ) {
                    $seen[ $ref ] = true;
                    $post         = get_post( $ref );
                    $content      = $post instanceof \WP_Post ? $post->post_content : '';

                    if ( '' !== $content ) {
                        $blocks[ $key ]['innerBlocks'] = self::expand_reusable( parse_blocks( $content ), $seen );
                        continue;
                    }
                }

                $blocks[ $key ]['innerBlocks'] = [];
                continue;
            }

            $inner = $block['innerBlocks'] ?? null;

            if ( is_array( $inner ) && [] !== $inner ) {
                $blocks[ $key ]['innerBlocks'] = self::expand_reusable( $inner, $seen );
            }
        }

        return array_values( $blocks );
    }

    /**
     * Parses the current FSE template, template parts included.
     *
     * `$_wp_current_template_content` wird von `locate_block_template()` auf dem
     * Filter `template_include` gesetzt — also VOR `wp_enqueue_scripts`, auf dem
     * dieser Generator laeuft.
     *
     * @return array<array-key, mixed>
     */
    private static function template_blocks(): array {
        $content = $GLOBALS['_wp_current_template_content'] ?? null;

        if ( ! is_string( $content ) || '' === $content ) {
            return [];
        }

        $seen = [];

        return self::expand_template_parts( parse_blocks( $content ), $seen );
    }

    /**
     * Loads `core/template-part` content into the tree.
     *
     * Nachgeladen wird nur, wenn der Part noch keine `innerBlocks` hat — sonst
     * stuende der Inhalt doppelt im Baum. `$seen` deckelt den Fall, dass zwei
     * Templates denselben Part einbinden.
     *
     * @param array<array-key, mixed> $blocks Parsed blocks.
     * @param array<string, bool>     $seen  Already loaded template part slugs.
     * @return array<array-key, mixed>
     */
    private static function expand_template_parts( array $blocks, array &$seen ): array {
        foreach ( $blocks as $key => $block ) {
            if ( ! is_array( $block ) ) {
                unset( $blocks[ $key ] );
                continue;
            }

            $slug  = $block['attrs']['slug'] ?? null;
            $inner = $block['innerBlocks'] ?? null;

            if ( 'core/template-part' === ( $block['blockName'] ?? null )
                && is_string( $slug ) && '' !== $slug
                && ( ! is_array( $inner ) || [] === $inner )
                && ! isset( $seen[ $slug ] )
            ) {
                $seen[ $slug ] = true;
                $part          = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
                $content       = $part instanceof \WP_Block_Template ? $part->content : '';

                if ( '' !== $content ) {
                    $blocks[ $key ]['innerBlocks'] = self::expand_template_parts( parse_blocks( $content ), $seen );
                    continue;
                }
            }

            if ( is_array( $inner ) && [] !== $inner ) {
                $blocks[ $key ]['innerBlocks'] = self::expand_template_parts( $inner, $seen );
            }
        }

        return array_values( $blocks );
    }

    /**
     * Collects block widgets that are actually assigned to a sidebar.
     *
     * `sidebars_widgets` fuehrt Widget-IDs der Form `block-<n>`; der Inhalt liegt
     * unter demselben `<n>` in der Option `widget_block`. Der Schluessel
     * `array_version` ist Buchhaltung von WordPress und keine Sidebar.
     *
     * @return array<array-key, mixed>
     */
    private static function widget_blocks(): array {
        $widget_block = get_option( 'widget_block', [] );
        $sidebars     = get_option( 'sidebars_widgets', [] );

        if ( ! is_array( $widget_block ) || ! is_array( $sidebars ) ) {
            return [];
        }

        unset( $sidebars['array_version'] );

        $blocks = [];

        foreach ( $sidebars as $widgets ) {
            if ( ! is_array( $widgets ) ) {
                continue;
            }

            foreach ( $widgets as $widget_id ) {
                if ( ! is_string( $widget_id ) || ! str_starts_with( $widget_id, 'block-' ) ) {
                    continue;
                }

                $entry   = $widget_block[ substr( $widget_id, 6 ) ] ?? null;
                $content = is_array( $entry ) ? ( $entry['content'] ?? null ) : null;

                if ( ! is_string( $content ) || '' === $content ) {
                    continue;
                }

                $blocks = array_merge( $blocks, parse_blocks( $content ) );
            }
        }

        return $blocks;
    }
}
