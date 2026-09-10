<?php
/**
 * Frontend template of the `creabb/media-grid` block.
 *
 * Uebersetzt `blocks/media-grid.php` des Alt-Plugins
 * (`areoi_render_block_media_grid()`). Die vier Verschachtelungsebenen
 * (container > row.h-100 > col > row.…-content-grid-columns) bleiben exakt
 * erhalten: Das mitgelieferte Block-CSS arbeitet mit Kindselektoren
 * (`.areoi-content-grid > div > .row > .col > .areoi-content-grid-columns`),
 * jede zusaetzliche oder fehlende Ebene bricht das Raster.
 *
 * FUENF BEWUSSTE ABWEICHUNGEN
 *
 * 1. `layout`, `container` und `columns` laufen durch eine Allowlist. Das
 *    Original schickt sie nur durch `esc_attr()` und schreibt sie dann in ein
 *    class-Attribut bzw. haengt sie an einen Klassennamen an. Auf gueltige
 *    Bestandswerte hat die Pruefung keine Wirkung.
 * 2. `align` laeuft durch eine Allowlist MIT DEM LEERSTRING ALS RUECKFALL. Das
 *    Original haengt den Wert ungeprueft an das Literal `align` an
 *    (media-grid.php:35). `supports.align` steht bei diesem Block — als
 *    einzigem der 14 — auf `true`; WordPress laesst damit genau fuenf Werte zu
 *    (wide, full, left, center, right), und alle fuenf gehen unveraendert
 *    durch. Ein unbekannter Wert erzeugt GAR KEINE Klasse. Kein Rueckfall auf
 *    `alignfull` wie bei container und strip: Dort steht `supports.align` auf
 *    false, hier waere eine erzwungene Vollbreite ein sichtbarer
 *    Layoutwechsel, und das Original gibt bei leerem `align` ebenfalls nichts
 *    aus.
 * 3. Der Rueckfall auf `areoi-card-medium` bleibt, obwohl der Default des
 *    Attributs `areoi-card-small` ist — das Original macht das genauso
 *    (media-grid.php:19), und die Klasse steuert die Mindesthoehe.
 * 4. Der Frueh-Ausstieg `if ( !$content ) return $content;` gilt nur im
 *    Frontend. Im Editor bliebe der Block sonst unerreichbar — es liesse sich
 *    nie ein erstes Bild einfuegen. Gepruefet wird `$inner_blocks`, nicht
 *    `$content`: Letzteres ist im Frontend immer leer, der Block stiege also
 *    IMMER aus (E-33).
 * 5. Die drei Utility-Klassen werden NICHT ausgegeben. Der Block deklariert
 *    sie, seine Renderfunktion liest sie aber nicht (media-grid.php:13-41).
 *
 * `$allow_pattern` IST hier gesetzt — `crea_bootstrap_blocks_background_markup(
 * $attributes, true )`, wie in strip. Das Original tut es in media-grid.php:4
 * mit derselben Zeile wie in strip.php:4; media-grid ist neben strip der
 * zweite und letzte der vierzehn Phase-1-Bloecke, der es tut.
 *
 * Die fruehere Fassung dieses Docblocks argumentierte dagegen: Das Flag
 * steuere allein die nicht nachgebaute Lightspeed-Integration, und das
 * Muster-Partial liefere ohne Filter ohnehin den Leerstring. Beides stimmt —
 * und beides ist kein Grund, das Flag wegzulassen. Es ist HEUTE folgenlos und
 * MORGEN nicht: Wer den Filter `crea_bootstrap_blocks_background_pattern`
 * setzt, bekommt das Muster in strip und nicht in media-grid, obwohl das
 * Original zwischen beiden nicht unterscheidet. Der Aufrufvertrag bildet ab,
 * was das Original tut, nicht was heute sichtbar wird.
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes Resolved block attributes.
 * @var string               $inner_blocks Rendered inner block markup.
 * @var array<string, mixed> $block      Block data provided by Blockstudio.
 * @var bool                 $isEditor   Whether the editor is rendering.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/_partials/patterns.php';
require_once dirname( __DIR__ ) . '/_partials/background.php';

// DAS INNENMARKUP STEHT IN `$inner_blocks`, NICHT IN `$content` (E-33).
// Hier waere der Fehler noch teurer als bei den uebrigen Templates: Der
// Frueh-Ausstieg darunter prueft genau diesen Wert. Mit `$content` — im
// Frontend IMMER der Leerstring — stiege der Block auf jeder echten Seite
// aus und gaebe GAR NICHTS aus, nicht bloss die Kindbloecke nicht.
$crea_content = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- $isEditor ist Blockstudios Vertrag, nicht unsere Wahl; die Renderumgebung stellt genau diesen Namen bereit (blockstudio/includes/classes/block.php, nachgebildet in tests/lib/creabb-render.php:264).
if ( empty( $isEditor ) && '' === trim( $crea_content ) ) {
    return;
}

$crea_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$crea_layout = crea_bootstrap_blocks_tag_name(
    is_string( $crea_a['layout'] ?? null ) ? $crea_a['layout'] : '',
    [ 'grid', 'masonry' ],
    'grid'
);

$crea_container = crea_bootstrap_blocks_tag_name(
    is_string( $crea_a['container'] ?? null ) ? $crea_a['container'] : '',
    [ 'container', 'container-sm', 'container-md', 'container-lg', 'container-xl', 'container-xxl', 'container-fluid' ],
    'container'
);

$crea_columns = crea_bootstrap_blocks_tag_name(
    is_string( $crea_a['columns'] ?? null ) ? $crea_a['columns'] : '',
    [ '1', '2', '3', '4', '5', '6' ],
    '3'
);

// `align` steht bei diesem Block NICHT im Vertrag: Der Wert kommt aus
// "supports": { "align": true } und liegt flach am Block —
// crea_bootstrap_blocks_native_attributes() hat ihn oben mit aufgeloest.
// Erlaubt sind die fuenf Werte, die WordPress bei align: true anbietet; ein
// unbekannter Wert erzeugt gar keine Klasse.
$crea_align_value = crea_bootstrap_blocks_tag_name(
    is_string( $crea_a['align'] ?? null ) ? $crea_a['align'] : '',
    [ 'wide', 'full', 'left', 'center', 'right' ],
    ''
);

$crea_align = '' === $crea_align_value ? '' : 'align' . $crea_align_value;

$crea_card_size = crea_bootstrap_blocks_dual_class_value(
    ! empty( $crea_a['card_size'] ) && is_string( $crea_a['card_size'] )
        ? $crea_a['card_size']
        : 'areoi-card-medium'
);

$crea_classes = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $crea_a['block_id'] ?? null ),
        crea_bootstrap_blocks_dual_classes( 'media-grid', 'content-grid', 'content-grid-' . $crea_layout ),
        'd-flex',
        $crea_card_size,

        empty( $crea_a['hide_xs'] ) ? ( $crea_a['vertical_align_xs'] ?? '' ) : '',
        empty( $crea_a['hide_sm'] ) ? ( $crea_a['vertical_align_sm'] ?? '' ) : '',
        empty( $crea_a['hide_md'] ) ? ( $crea_a['vertical_align_md'] ?? '' ) : '',
        empty( $crea_a['hide_lg'] ) ? ( $crea_a['vertical_align_lg'] ?? '' ) : '',
        empty( $crea_a['hide_xl'] ) ? ( $crea_a['vertical_align_xl'] ?? '' ) : '',
        empty( $crea_a['hide_xxl'] ) ? ( $crea_a['vertical_align_xxl'] ?? '' ) : '',

        empty( $crea_a['hide_xs'] ) ? ( $crea_a['horizontal_align_xs'] ?? '' ) : '',
        empty( $crea_a['hide_sm'] ) ? ( $crea_a['horizontal_align_sm'] ?? '' ) : '',
        empty( $crea_a['hide_md'] ) ? ( $crea_a['horizontal_align_md'] ?? '' ) : '',
        empty( $crea_a['hide_lg'] ) ? ( $crea_a['horizontal_align_lg'] ?? '' ) : '',
        empty( $crea_a['hide_xl'] ) ? ( $crea_a['horizontal_align_xl'] ?? '' ) : '',
        empty( $crea_a['hide_xxl'] ) ? ( $crea_a['horizontal_align_xxl'] ?? '' ) : '',

        $crea_align,
        $crea_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $crea_a, 'block' ),
        'position-relative',
    ]
);

$crea_columns_classes = crea_bootstrap_blocks_class_str(
    [
        'row',
        crea_bootstrap_blocks_dual_classes( 'content-grid-columns', 'content-grid-columns-' . $crea_columns ),
    ]
);

// Das zweite Argument ist NICHT wegzulassen: media-grid ist neben strip der
// zweite Block, der `$allow_pattern` auf true setzt (media-grid.php:4 des
// Originals, wortgleich mit strip.php:4). Ohne Filter bleibt das Muster leer —
// deshalb faellt ein fehlendes `true` in keiner Suite auf und muss hier stehen.
$crea_background = crea_bootstrap_blocks_background_markup( $crea_a, true );
$crea_prepend    = crea_bootstrap_blocks_prepend_content( $crea_a );
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $crea_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Der Helfer escapt den Ankerwert selbst. ?> class="<?php echo esc_attr( $crea_classes ); ?>">
	<?php echo $crea_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Das Partial escapt jeden eingesetzten Wert selbst. ?>
	<div class="<?php echo esc_attr( $crea_container ); ?>">
		<div class="row h-100">
			<div class="col">
				<?php echo $crea_prepend; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Der Helfer escapt jeden eingesetzten Wert selbst. ?>
				<div class="<?php echo esc_attr( $crea_columns_classes ); ?>">
					<?php echo crea_bootstrap_blocks_inner_blocks( $crea_content, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
				</div>
			</div>
		</div>
	</div>
</div>
