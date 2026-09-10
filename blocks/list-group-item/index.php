<?php
/**
 * Render template of the `creabb/list-group-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_list_group_item()`
 * (`blocks/list-group-item.php` des Alt-Plugins). Klassenreihenfolge woertlich:
 *
 *   list-group-item  <className>  <style, NUR wenn item_style leer>
 *   <item_style>  active  disabled  list-group-item-action
 *   <hide-Kaskade mit block>
 *
 * ZWEI HUELLEN: ein `<a>`, wenn `url` belegt ist, sonst ein `<div>`. Der Inhalt
 * ist in beiden Faellen `wp_kses_post( $attributes['text'] )` — NICHT das
 * Innenmarkup. Der Block hat keine Kindbloecke.
 *
 * `style` STEHT NICHT IM VERTRAG. Wie bei `list-group` liest das Original ein
 * Attribut, das nie registriert ist; der Wert ist immer leer. Der Aufruf bleibt
 * 1:1 stehen.
 *
 * ------------------------------------------------------------------------
 * DER `<div>`-ZWEIG ENTHAELT EINEN DEFEKT DES ORIGINALS, UND ER WIRD
 * MITGEBAUT. `blocks/list-group-item.php:37` lautet:
 *
 *     <div ' . areoi_return_id( $attributes ) . ' class="' .
 *         areoi_return_id( $attributes ) . ' ' . $class . '">
 *
 * `areoi_return_id()` liefert ein VOLLSTAENDIGES `id="…"`-Attribut. Es steht
 * hier ein zweites Mal INNERHALB des `class`-Attributs. Auf einer Seite mit
 * gesetztem Anker erzeugt das Markup wie
 *
 *     <div id="anker" class="id="anker" list-group-item …">
 *
 * also ein `class`-Attribut, das an seinem eigenen Anfuehrungszeichen endet.
 *
 * Das ist kein Tippfehler dieser Datei, sondern der Bestand. Vertragsebene 3
 * verlangt AEQUIVALENTES Markup, nicht das bessere (Ruling E-41). Wer es
 * „repariert", bricht den Zeichenvergleich und aendert das Aussehen jeder
 * Seite, deren Theme gegen diese Klassenkette stylt.
 *
 * Der Anker wird deshalb an dieser Stelle ROH ausgegeben, nicht durch
 * `esc_attr()` geschickt: Das Original escapt nur den Ankerwert innerhalb von
 * `areoi_return_id()`, nicht die zusammengesetzte Zeichenkette.
 * `tests/test-diff-list-group-item.php` hat dafuer einen eigenen Einzelfall.
 * ------------------------------------------------------------------------
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

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        'list-group-item',
        $cbb_a['className'] ?? '',
        ( ! empty( $cbb_a['style'] ) && empty( $cbb_a['item_style'] ) ) ? $cbb_a['style'] : '',
        $cbb_a['item_style'] ?? '',
        empty( $cbb_a['active'] ) ? '' : 'active',
        empty( $cbb_a['disabled'] ) ? '' : 'disabled',
        empty( $cbb_a['action'] ) ? '' : 'list-group-item-action',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

/*
 * ZWEI ZWEIGE, ZWEI REGELN — und das ist kein Versehen des Originals, sondern
 * gemessen: Der `<a>`-Zweig gibt `wp_kses_post( $attributes['text'] )` OHNE
 * Wache aus (Zeile 30), der `<div>`-Zweig mit `!empty()` (Zeile 38). Bei
 * `text = "0"` erscheint der Text deshalb im Anker, im div aber nicht — `"0"`
 * ist fuer `empty()` leer.
 *
 * Wo das Original `!empty()` prueft, prueft der Nachbau `!empty()` (Ruling
 * E-41). Vertragsebene 3 verlangt aequivalentes Markup, nicht das
 * widerspruchsfreiere. Die Differenzsuite hat genau diesen Fall gefunden.
 */
$cbb_text_anker = wp_kses_post( $cbb_a['text'] ?? '' );
$cbb_text_div   = empty( $cbb_a['text'] ) ? '' : wp_kses_post( $cbb_a['text'] );
$cbb_id         = crea_bootstrap_blocks_anchor_attr( $cbb_a );

if ( ! empty( $cbb_a['url'] ) ) :
    $cbb_title = empty( $cbb_a['url_title'] ) ? '' : ' title="' . esc_attr( (string) $cbb_a['url_title'] ) . '"';
    ?>
<a href="<?php echo esc_url( (string) $cbb_a['url'] ); ?>" rel="<?php echo esc_attr( (string) ( $cbb_a['rel'] ?? '' ) ); ?>" <?php echo $cbb_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> target="<?php echo esc_attr( (string) ( $cbb_a['linkTarget'] ?? '' ) ); ?>" id="block-<?php echo esc_attr( crea_bootstrap_blocks_block_id_value( $cbb_a['block_id'] ?? null ) ); ?>" class="<?php echo esc_attr( $cbb_class ); ?>">
	<?php echo $cbb_text_anker; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch wp_kses_post() gefiltert. ?>
</a>
<?php else : ?>
<div <?php echo $cbb_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo $cbb_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- DEFEKT DES ORIGINALS, siehe Kopfkommentar. Roh wie dort. ?> <?php echo esc_attr( $cbb_class ); ?>">
	<?php echo $cbb_text_div; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch wp_kses_post() gefiltert. ?>
</div>
<?php endif; ?>
