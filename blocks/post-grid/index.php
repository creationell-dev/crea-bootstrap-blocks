<?php
/**
 * Render template of the `creabb/post-grid` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_post_grid()` (`blocks/post-grid.php`
 * des Alt-Plugins). Mit 176 Vertragsattributen der groesste Block des ganzen
 * Inventars — und mit `breadcrumb` einer von nur zwei DATENABHAENGIGEN.
 *
 * Aussere Klassenreihenfolge woertlich, identisch mit `content-grid`:
 *
 *   block-<uuid>  areoi-content-grid  areoi-content-grid-<layout>  d-flex
 *   <size ODER areoi-medium>  <sechs vertical_align_*>
 *   <sechs horizontal_align_*>  align<align>  <className>
 *   <hide-Kaskade mit block>  position-relative
 *
 * FUENF EIGENHEITEN, alle im Original nachgemessen:
 *
 * 1. `layout` FAELLT AUF `gird` ZURUECK — mit demselben Tippfehler wie bei
 *    `content-grid` (`post-grid.php:12`). Mitgebaut, Vertragsebene 3.
 *
 * 2. `$include_media` IST NIE FALSCH. Das Original schreibt
 *    `!empty( … ) ? 'has-image' : true` — beide Zweige sind wahr. Die Weiche
 *    `if ( $include_media )` greift deshalb IMMER; was der Schalter wirklich
 *    steuert, ist nur die Klasse `has-image`. Ein Nachbau, der hier `false`
 *    einsetzt, laesst das Medium ganz weg.
 *
 * 3. `title_element` IST EINE WEISSLISTE MIT RUECKFALL auf `h3` — anders als
 *    bei `accordion-item` OHNE `p`.
 *
 * 4. `areoi-has-url` STEHT FEST, nicht bedingt. Anders als bei
 *    `content-grid-item`, wo es an `url` haengt.
 *
 * 5. DIE AUSRICHTUNGSKLASSEN DES KARTENKOERPERS SIND NICHT AN `hide_<bp>`
 *    GEKOPPELT — anders als die der aeusseren Kette und anders als bei
 *    `content-grid-item`. Das Original prueft dort nur `!empty()`.
 *
 * DER HINTERGRUND WIRD ZWEIMAL GEBAUT: einmal fuer den Block, einmal je Eintrag
 * bei `media_layout == 'background'`. Der zweite Aufruf laeuft mit
 * `$allow_pattern = false` und mit UMGESCHRIEBENEN Attributen — das Titelbild
 * wird zu `background_image`, `item_background_color` zu `background_color`,
 * `item_overlay` zu `background_overlay`.
 *
 * DAS INNENMARKUP WIRD NICHT AUSGEGEBEN — der Block hat keine Kindbloecke; sein
 * Inhalt kommt aus der Abfrage.
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var array<string, mixed> $block      Block data provided by Blockstudio.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/_partials/patterns.php';
require_once dirname( __DIR__ ) . '/_partials/background.php';

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_post_type  = empty( $cbb_a['post_type'] ) ? 'post' : esc_attr( (string) $cbb_a['post_type'] );
$cbb_display    = empty( $cbb_a['display_posts'] ) ? 'selected' : esc_attr( (string) $cbb_a['display_posts'] );
$cbb_per_page   = empty( $cbb_a['posts_per_page'] ) ? '8' : esc_attr( (string) $cbb_a['posts_per_page'] );
$cbb_orderby    = empty( $cbb_a['orderby'] ) ? 'title' : esc_attr( (string) $cbb_a['orderby'] );
$cbb_order      = empty( $cbb_a['order'] ) ? 'asc' : esc_attr( (string) $cbb_a['order'] );
$cbb_post_ids   = empty( $cbb_a['post_ids'] ) ? [] : (array) $cbb_a['post_ids'];
$cbb_layout     = empty( $cbb_a['layout'] ) ? 'gird' : esc_attr( (string) $cbb_a['layout'] );
$cbb_style      = empty( $cbb_a['style'] ) ? 'card' : esc_attr( (string) $cbb_a['style'] );
$cbb_container  = empty( $cbb_a['container'] ) ? 'container' : esc_attr( (string) $cbb_a['container'] );
$cbb_columns    = empty( $cbb_a['columns'] ) ? '3' : esc_attr( (string) $cbb_a['columns'] );
$cbb_title_el   = empty( $cbb_a['title_element'] ) ? 'h1' : esc_attr( (string) $cbb_a['title_element'] );
$cbb_text_color = empty( $cbb_a['text_color'] ) ? '' : esc_attr( (string) $cbb_a['text_color'] );
$cbb_media_flag = empty( $cbb_a['include_media'] ) ? true : 'has-image';
$cbb_media_lay  = empty( $cbb_a['media_layout'] ) ? 'inline' : esc_attr( (string) $cbb_a['media_layout'] );
$cbb_pagination = empty( $cbb_a['include_pagination'] ) ? false : esc_attr( (string) $cbb_a['include_pagination'] );
$cbb_pag_color  = empty( $cbb_a['pagination_color'] ) ? 'btn-primary' : esc_attr( (string) $cbb_a['pagination_color'] );
$cbb_show_all   = in_array( 'all', $cbb_post_ids, true );

if ( ! in_array( $cbb_title_el, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
    $cbb_title_el = 'h3';
}

$cbb_background = crea_bootstrap_blocks_background_markup( $cbb_a, true );

$cbb_parts = [
    crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
    'areoi-content-grid',
    'areoi-content-grid-' . $cbb_layout,
    'd-flex',
    empty( $cbb_a['size'] ) ? 'areoi-medium' : $cbb_a['size'],
];

foreach ( [ 'vertical_align', 'horizontal_align' ] as $cbb_axis ) {
    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
        $cbb_parts[] = ( empty( $cbb_a[ 'hide_' . $cbb_bp ] ) && ! empty( $cbb_a[ $cbb_axis . '_' . $cbb_bp ] ) )
            ? (string) $cbb_a[ $cbb_axis . '_' . $cbb_bp ]
            : '';
    }
}

$cbb_parts[] = empty( $cbb_a['align'] ) ? '' : 'align' . $cbb_a['align'];
$cbb_parts[] = $cbb_a['className'] ?? '';
$cbb_parts[] = crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' );

$cbb_class   = crea_bootstrap_blocks_class_str( $cbb_parts );
$cbb_prepend = crea_bootstrap_blocks_prepend_content( $cbb_a );

$cbb_parent_in     = [];
$cbb_parent_not_in = [];
$cbb_in            = $cbb_post_ids;

if ( 'child-pages' === $cbb_post_type || 'children' === $cbb_display ) {
    $cbb_parent_in = $cbb_post_ids;
    $cbb_in        = [];
}

if ( $cbb_show_all ) {
    $cbb_in        = [];
    $cbb_parent_in = [ '0' ];
}

if ( $cbb_show_all && 'children' === $cbb_display ) {
    $cbb_in            = [];
    $cbb_parent_in     = [];
    $cbb_parent_not_in = [ '0' ];
}

$cbb_paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );

$cbb_query = new WP_Query(
    [
        'posts_per_page'      => $cbb_per_page,
        'post_type'           => $cbb_post_type,
        'post_parent__in'     => $cbb_parent_in,
        'post_parent__not_in' => $cbb_parent_not_in,
        'post__in'            => $cbb_in,
        'orderby'             => $cbb_orderby,
        'order'               => $cbb_order,
        'paged'               => $cbb_paged,
        'ignore_sticky_posts' => 1,
    ]
);

$cbb_body = '';

if ( $cbb_query->have_posts() ) {
    $cbb_body .= '<div class="row areoi-content-grid-columns areoi-content-grid-columns-' . $cbb_columns . '">';

    while ( $cbb_query->have_posts() ) {
        $cbb_query->the_post();

        $cbb_url     = empty( $cbb_a['include_permalink'] ) ? '' : '<a class="areoi-full-link" href="' . get_the_permalink() . '"></a>';
        $cbb_title   = empty( $cbb_a['include_title'] ) ? '' : '<' . $cbb_title_el . ' class="' . esc_attr( $cbb_text_color ) . '">' . get_the_title() . '</' . $cbb_title_el . '>';
        $cbb_excerpt = empty( $cbb_a['include_excerpt'] ) ? '' : '<p class="' . esc_attr( $cbb_text_color ) . '">' . get_the_excerpt() . '</p>';

        $cbb_content = '<div>' . $cbb_title . $cbb_excerpt . '</div>';

        $cbb_media = '';

        if ( $cbb_media_flag ) {
            if ( 'inline' === $cbb_media_lay && get_the_post_thumbnail() ) {
                $cbb_media .= '<div class="card-img-top  position-relative">'
                    . '<div class="areoi-background">'
                    . get_the_post_thumbnail()
                    . '</div></div>';
            } elseif ( 'background' === $cbb_media_lay ) {
                $cbb_image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );

                $cbb_item                               = $cbb_a;
                $cbb_item['background_display']         = true;
                $cbb_item['background_image']           = [ 'url' => empty( $cbb_image[0] ) ? null : $cbb_image[0] ];
                $cbb_item['background_color']           = $cbb_a['item_background_color'] ?? null;
                $cbb_item['background_display_overlay'] = esc_attr( (string) ( $cbb_a['item_display_overlay'] ?? '' ) );
                $cbb_item['background_overlay']         = $cbb_a['item_overlay'] ?? null;

                $cbb_media .= crea_bootstrap_blocks_background_markup( $cbb_item );
            }
        }

        $cbb_card_parts = [ 'card-body', 'd-flex', 'position-relative' ];
        foreach ( [ 'item_vertical_align', 'item_horizontal_align', 'item_text_align' ] as $cbb_axis ) {
            foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $cbb_bp ) {
                $cbb_card_parts[] = empty( $cbb_a[ $cbb_axis . '_' . $cbb_bp ] ) ? '' : (string) $cbb_a[ $cbb_axis . '_' . $cbb_bp ];
            }
        }
        $cbb_card_parts[] = $cbb_text_color;
        $cbb_card_class   = crea_bootstrap_blocks_class_str( $cbb_card_parts );

        $cbb_item_class = crea_bootstrap_blocks_class_str(
            [
                'areoi-content-grid-item',
                is_string( $cbb_media_flag ) ? $cbb_media_flag : '',
                empty( $cbb_a['card_size'] ) ? 'areoi-card-small' : esc_attr( (string) $cbb_a['card_size'] ),
            ]
        );

        $cbb_inner_card = '<div class="' . $cbb_card_class . '">' . $cbb_content . '</div>' . $cbb_url;

        if ( 'full' === $cbb_style ) {
            $cbb_body .= '<div class="' . $cbb_item_class . ' p-0">'
                . '<div class="d-flex flex-column h-100 overflow-hidden position-relative areoi-has-url">'
                . $cbb_media . $cbb_inner_card . '</div></div>';
        } elseif ( 'flush' === $cbb_style ) {
            $cbb_body .= '<div class="' . $cbb_item_class . '">'
                . '<div class="d-flex flex-column h-100 overflow-hidden position-relative areoi-has-url">'
                . $cbb_media . $cbb_inner_card . '</div></div>';
        } else {
            $cbb_body .= '<div class="' . $cbb_item_class . '">'
                . '<div class="card h-100 overflow-hidden position-relative areoi-has-url">'
                . $cbb_media . $cbb_inner_card . '</div></div>';
        }
    }

    $cbb_body .= '</div>';

    if ( $cbb_pagination ) {
        $cbb_big   = 999999999;
        $cbb_pages = paginate_links(
            [
                'base'    => str_replace( (string) $cbb_big, '%#%', esc_url( get_pagenum_link( $cbb_big ) ) ),
                'format'  => '?paged=%#%',
                'current' => max( 1, (int) $cbb_paged ),
                'total'   => $cbb_query->max_num_pages,
            ]
        );
        $cbb_pages = str_replace( 'page-numbers', 'page-numbers btn ' . $cbb_pag_color, (string) $cbb_pages );
        $cbb_pages = str_replace( 'current', 'current active', $cbb_pages );

        $cbb_body .= '<div class="text-center p-2"><div class="btn-group">' . $cbb_pages . '</div></div>';
    }

    wp_reset_postdata();
}
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?> position-relative">
<?php echo $cbb_background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<div class="<?php echo esc_attr( $cbb_container ); ?>">
<div class="row h-100">
<div class="col">
<?php echo $cbb_prepend; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fertiges, intern escaptes Markup. ?>
<?php echo $cbb_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Werte einzeln escapt. ?>
</div>
</div>
</div>
</div>
