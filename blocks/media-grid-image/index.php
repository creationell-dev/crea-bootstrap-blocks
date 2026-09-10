<?php
/**
 * Frontend template of the `creabb/media-grid-image` block.
 *
 * Uebersetzt `blocks/media-grid-image.php` des Alt-Plugins
 * (`areoi_render_block_media_grid_image()`).
 *
 * ZWEI TOTE BERECHNUNGEN DES ORIGINALS ENTFALLEN. Es baut dort `$card_class`
 * (media-grid-image.php:12-32) und `$class` (:34-41) und gibt beide NIE aus —
 * das erzeugte `figure` traegt eine fest verdrahtete Klassenliste. Beide
 * lesen ausserdem `vertical_align_*`, `horizontal_align_*`, `hide_*` und
 * `className`, und von diesen ist in der block.json des Blocks kein einziges
 * deklariert. Ebenso tot ist `$layout` (:8): zugewiesen, nie benutzt.
 *
 * VIER BEWUSSTE ABWEICHUNGEN
 *
 * 1. Geprueftes Anhangsergebnis. Das Original greift direkt auf `$image[0]`
 *    zu; ist der Anhang geloescht, liefert `wp_get_attachment_image_src()`
 *    `false` und PHP 8 bricht mit einem Fatal ab. Hier entsteht in dem Fall
 *    kein Markup.
 * 2. `alt` wird ausgegeben. Das Original setzt `$image_alt = ''` und ignoriert
 *    das gespeicherte Attribut. Im gesamten Bestand ist `alt` nicht belegt —
 *    dort bleibt es also bei `alt=""` und damit bei byte-gleichem Markup; fuer
 *    neue Inhalte ist die Ausgabe die richtige.
 * 3. Allowlisten fuer `media_fit`, `media_align` und die beiden Maximalmasse.
 *    Die ersten beiden landen als Klassen im Markup, die beiden anderen in
 *    einem `style`-Attribut; das Original schickt alle vier nur durch
 *    `esc_attr()`.
 * 4. `wp-block-areoi-media-grid-image` wird weiterhin ausgegeben, solange der
 *    Schalter `legacy_classes` an ist. Das Original hat diesen Klassennamen
 *    fest im String; er entsteht bei uns nicht mehr von selbst, weil der Block
 *    `creabb/media-grid-image` heisst.
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes Raw block attributes.
 * @var array<string, mixed> $block      Block data, carries the native attributes.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Wie in den uebrigen dreizehn Templates: anchor, className und align liegen je
// nach Ablagestelle flach am Block statt im Attributsatz (S-4). Der Helfer legt
// beide Formen uebereinander, wobei der Attributsatz gewinnt.
$crea_a = crea_bootstrap_blocks_native_attributes(
    is_array( $attributes ?? null ) ? $attributes : [],
    is_array( $block ?? null ) ? $block : []
);

$crea_attachment_id = $crea_a['id'] ?? 0;

if ( ! is_numeric( $crea_attachment_id ) || (int) $crea_attachment_id <= 0 ) {
    return;
}

$crea_attachment_id = (int) $crea_attachment_id;

// `! empty()` UND NICHT `'' !== …` — die Falle des Originals wird
// nachgebildet, nicht repariert (E-41). `media-grid-image.php:49` schreibt
// `!empty( $attributes['sizeSlug'] ) ? … : 'full'`, und in PHP ist
// `empty( "0" )` WAHR: Ein sizeSlug von "0" faellt dort auf `full` zurueck.
// Gemessen an der Belegung `null-string:"0"` der Matrix, unter BEIDEN
// Elternrastern. Vertragsebene 3 verlangt aequivalentes Markup.
$crea_size = is_string( $crea_a['sizeSlug'] ?? null ) && ! empty( $crea_a['sizeSlug'] )
    ? $crea_a['sizeSlug']
    : 'full';

$crea_image = wp_get_attachment_image_src( $crea_attachment_id, $crea_size );

if ( ! is_array( $crea_image ) || ! isset( $crea_image[0] ) ) {
    return;
}

$crea_parent = crea_bootstrap_blocks_parent_block_attributes( $crea_a['parent_id'] ?? null );

// `! empty()` wie das Original (media-grid-image.php:9), aus demselben
// Grund wie bei `sizeSlug` daruber: ein `style` von "0" faellt dort auf
// `flush` zurueck (E-41).
$crea_style = is_string( $crea_parent['style'] ?? null ) && ! empty( $crea_parent['style'] )
    ? $crea_parent['style']
    : 'flush';

$crea_link_target = ! empty( $crea_parent['linkTarget'] ) ? ' target="_blank"' : '';

$crea_image_url = (string) $crea_image[0];
$crea_link_url  = '';

if ( 'media' === ( $crea_a['linkDestination'] ?? null ) ) {
    $crea_link_url = $crea_image_url;
}

if ( 'attachment' === ( $crea_a['linkDestination'] ?? null ) ) {
    $crea_link_url = get_attachment_link( $crea_attachment_id );
}

$crea_fit = crea_bootstrap_blocks_tag_name(
    is_string( $crea_a['media_fit'] ?? null ) ? $crea_a['media_fit'] : '',
    [ 'cover', 'contain', 'set' ],
    'cover'
);

$crea_align = crea_bootstrap_blocks_tag_name(
    is_string( $crea_a['media_align'] ?? null ) ? $crea_a['media_align'] : '',
    [ 'start', 'center', 'end' ],
    'center'
);

$crea_inline_style = '';

if ( 'set' === $crea_fit ) {
    $crea_max_height = \Creationell\BootstrapBlocks\Styles::css_number(
        ! empty( $crea_a['media_height'] ) ? $crea_a['media_height'] : '50'
    );
    $crea_max_width  = \Creationell\BootstrapBlocks\Styles::css_number(
        ! empty( $crea_a['media_width'] ) ? $crea_a['media_width'] : '100'
    );

    if ( null !== $crea_max_height ) {
        $crea_inline_style .= 'max-height: ' . $crea_max_height . 'px;';
    }

    if ( null !== $crea_max_width ) {
        $crea_inline_style .= 'max-width: ' . $crea_max_width . 'px;';
    }
}

$crea_figure_classes = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_dual_classes( 'media-grid-image' ) === ''
            ? ''
            : ( crea_bootstrap_blocks_setting_enabled( 'legacy_classes', true )
                ? 'wp-block-creabb-media-grid-image wp-block-areoi-media-grid-image'
                : 'wp-block-creabb-media-grid-image' ),
        crea_bootstrap_blocks_dual_classes( 'content-grid-item' ),
        'full' === $crea_style ? 'p-0' : '',
    ]
);

$crea_media_classes = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_dual_classes( 'media' ),
        // `! empty()` wie das Original (media-grid-image.php:69), E-41.
        ! empty( $crea_link_url ) ? crea_bootstrap_blocks_dual_classes( 'has-url' ) : '',
    ]
);

$crea_container_classes = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_dual_classes( 'media-container' ),
        $crea_fit,
        $crea_align,
    ]
);
?>
<figure class="<?php echo esc_attr( $crea_figure_classes ); ?>">
	<?php if ( ! empty( $crea_link_url ) ) : // E-41: wie das Original, media-grid-image.php:69 und :78. ?>
	<a href="<?php echo esc_url( $crea_link_url ); ?>"<?php echo $crea_link_target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal, keine Attributwerte. ?> class="<?php echo esc_attr( $crea_media_classes ); ?>">
	<?php else : ?>
	<div class="<?php echo esc_attr( $crea_media_classes ); ?>">
	<?php endif; ?>
		<div class="<?php echo esc_attr( $crea_container_classes ); ?>">
			<img src="<?php echo esc_url( $crea_image_url ); ?>" alt="<?php echo esc_attr( is_string( $crea_a['alt'] ?? null ) ? $crea_a['alt'] : '' ); ?>" width="<?php echo esc_attr( (string) ( $crea_image[1] ?? '' ) ); ?>" height="<?php echo esc_attr( (string) ( $crea_image[2] ?? '' ) ); ?>" style="<?php echo esc_attr( $crea_inline_style ); ?>" />
		</div>
	<?php if ( ! empty( $crea_link_url ) ) : // E-41: wie das Original, media-grid-image.php:69 und :78. ?>
	</a>
	<?php else : ?>
	</div>
	<?php endif; ?>
</figure>
