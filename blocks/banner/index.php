<?php
/**
 * Render template of the `creabb/banner` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_banner()` (`blocks/banner.php` des
 * Alt-Plugins) samt der drei Template-Teile unter
 * `blocks/banner/template-parts/`. Klassenreihenfolge woertlich:
 *
 *   block-<uuid>  areoi-banner-<layout>  <banner-grid-has-follows>
 *   <size>  align<align>  <className>  <hide-Kaskade mit block>
 *
 * FRUEH-AUSSTIEG: `if ( !$content ) return $content;` — ohne Innenmarkup gibt
 * der Block NICHTS aus, nicht einmal seine Huelle. Die Pruefung laeuft auf der
 * Innenmarkup-Variablen; ein Template, das sie anders benennt als die Pruefung,
 * gibt auf einer echten Seite gar nichts aus und bleibt in der Blocksuite gruen
 * (Ruling E-46, bei `media-grid` genau so passiert).
 *
 * DER BLOCK ZERLEGT SEIN INNENMARKUP. Er laedt es in ein `DOMDocument`, sucht
 * per XPath nach `//div[contains(@class, "banner-item")]`, klont jeden Treffer
 * in ein eigenes Dokument und setzt die Stuecke ueber einen Template-Teil neu
 * zusammen — `grid`, `stacked` oder `carousel`, gewaehlt ueber `layout`.
 *
 * `utf8_decode()` IST DIE EINZIGE ECHTE PHP-8.2-DEPRECATION DER PHASE 2, und
 * sie ist ungeschuetzt: `banner.php:9` ruft
 * `$dom->loadHTML( utf8_decode( $content ) )` bedingungslos auf. Uebersetzt ist
 * hier der VERHALTENSGLEICHE Ersatz — `utf8_decode()` wandelt UTF-8 nach
 * ISO-8859-1 und ersetzt alles Nichtdarstellbare durch `?`; `loadHTML()` liest
 * die Bytes danach als ISO-8859-1. `mb_convert_encoding( …, 'ISO-8859-1',
 * 'UTF-8' )` leistet dasselbe ohne Deprecation. Der Zeichenvergleich in
 * `tests/test-diff-banner.php` haelt beides gegeneinander.
 *
 * DER KARUSSELL-TEMPLATE-TEIL TRAEGT EINEN DEFEKT DES ORIGINALS: Er schreibt
 * `id="<?php echo areoi_return_id( $attributes ) ?>-carousel"`, und
 * `areoi_return_id()` liefert ein VOLLSTAENDIGES `id="…"`-Attribut. Bei
 * gesetztem Anker entsteht daraus `id="id="anker"-carousel"`. Wie bei
 * `list-group-item` wird das mitgebaut (Vertragsebene 3, Ruling E-41).
 *
 * DAS INNENMARKUP STEHT IN `$inner_blocks`, NICHT IN `$content` (Ruling E-33).
 *
 * @package Creationell\BootstrapBlocks
 *
 * @var array<string, mixed> $attributes   Block attributes.
 * @var string               $inner_blocks Inner blocks output.
 * @var array<string, mixed> $block        Block data provided by Blockstudio.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';

/*
 * DER FRUEH-AUSSTIEG GILT NUR AUF DEM FRONTEND.
 *
 * Das Original steigt aus, solange kein Kindblock da ist, und der
 * Nachbau tut es deshalb auch — auf dem Frontend Zeichen fuer Zeichen
 * gleich. Im EDITOR waere derselbe Ausstieg eine Falle: Ein frisch
 * eingefuegter Block hat noch keine Kinder, gaebe also nichts aus,
 * erzeugte kein <InnerBlocks />-Feld — und liesse sich nie befuellen.
 * `$isEditor` stellt Blockstudio dem Template dafuer bereit.
 */
if ( empty( $isEditor ) && ( '' === $cbb_inner || '0' === $cbb_inner ) ) {
    echo $cbb_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Frueh-Ausstieg des Originals, gibt den Wert unveraendert zurueck.
    return;
}

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_previous = libxml_use_internal_errors( true );

$cbb_dom           = new DOMDocument();
$cbb_dom->encoding = 'utf-8';
$cbb_dom->loadHTML( (string) mb_convert_encoding( $cbb_inner, 'ISO-8859-1', 'UTF-8' ) );

$cbb_xpath = new DOMXPath( $cbb_dom );
$cbb_items = $cbb_xpath->query( '//div[contains(@class, "banner-item")]' );

libxml_use_internal_errors( $cbb_previous );

$cbb_layout    = empty( $cbb_a['layout'] ) ? 'grid' : esc_attr( (string) $cbb_a['layout'] );
$cbb_container = empty( $cbb_a['container'] ) ? 'container' : esc_attr( (string) $cbb_a['container'] );
$cbb_count     = false !== $cbb_items ? $cbb_items->count() : 0;
$cbb_follows   = $cbb_count > 3 ? 'banner-grid-has-follows' : '';

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'areoi-banner-' . $cbb_layout,
        $cbb_follows,
        $cbb_a['size'] ?? '',
        empty( $cbb_a['align'] ) ? '' : 'align' . $cbb_a['align'],
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_contents = [];
if ( false !== $cbb_items ) {
    foreach ( $cbb_items as $cbb_item ) {
        $cbb_new = new DOMDocument();
        $cbb_new->appendChild( $cbb_new->importNode( $cbb_item->cloneNode( true ), true ) );
        $cbb_contents[] = (string) $cbb_new->saveHTML();
    }
}

/*
 * OHNE ZAEHLBEDINGUNG, UND DAS IST GEMESSEN — dieselbe Falle wie bei
 * `carousel`. Das Original schreibt `if ( !empty( $items ) )`; `$items` ist ein
 * DOMNodeList-OBJEKT, und `empty()` liefert fuer Objekte immer `false`. Der
 * Template-Teil laeuft deshalb IMMER, auch mit leerer Stueckliste — und
 * erzeugt dann seine Huelle ohne Inhalt. Ein Nachbau mit `[] !== $cbb_contents`
 * liesse die Huelle weg und weicht in jedem Fall ohne Bannerelemente ab.
 */
$cbb_body = '';
if ( false !== $cbb_items ) {
    if ( 'carousel' === $cbb_layout ) {
        $cbb_id    = crea_bootstrap_blocks_anchor_attr( $cbb_a );
        $cbb_body  = '<div id="' . $cbb_id . '-carousel" class="carousel slide" data-bs-ride="carousel">';
        $cbb_body .= '<div class="carousel-indicators">';
        foreach ( $cbb_contents as $cbb_key => $cbb_part ) {
            $cbb_body .= '<button type="button" data-bs-target="#' . $cbb_id . '-carousel" data-bs-slide-to="' . $cbb_key . '" class="' . ( 0 === $cbb_key ? 'active' : '' ) . '" aria-label="Slide ' . ( $cbb_key + 1 ) . '"></button>';
        }
        $cbb_body .= '</div><div class="carousel-inner">';
        foreach ( $cbb_contents as $cbb_key => $cbb_part ) {
            $cbb_body .= '<div class="carousel-item ' . ( 0 === $cbb_key ? 'active' : '' ) . '">' . $cbb_part . '</div>';
        }
        $cbb_body .= '</div></div>';
    } elseif ( 'stacked' === $cbb_layout ) {
        $cbb_body = implode( '', $cbb_contents );
    } elseif ( 'grid' === $cbb_layout ) {
        $cbb_body = '<div class="container-fluid"><div class="row h-100">';
        foreach ( $cbb_contents as $cbb_part ) {
            $cbb_body .= '<div class="col position-relative">' . $cbb_part . '</div>';
        }
        $cbb_body .= '</div></div>';
    }
}
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>"><?php
/*
 * IM EDITOR DER TAG, IM FRONTEND DIE UMFORMUNG.
 *
 * Dieser Block baut sein Innenmarkup neu auf: Er schneidet die
 * `banner-item`-Elemente aus, verteilt sie je nach Layout auf Karussell,
 * Stapel oder Raster und setzt die Huelle darum. Der Tag `<InnerBlocks />`
 * wird im Betrieb dagegen durch das UNVERAENDERTE Innenmarkup ersetzt — er
 * kann die Umformung nicht ersetzen, und deshalb steht er hier nicht immer.
 *
 * Im EDITOR ist er trotzdem noetig: Ohne ihn mountet Gutenberg die
 * Bannerelemente nicht, der Block hat keine Einfuegestelle und laesst sich
 * nicht befuellen. Dort soll auch nicht umgeformt werden — der Redakteur
 * bearbeitet die Elemente einzeln. Dieselbe Aufteilung wie bei `carousel`.
 */
echo crea_bootstrap_blocks_inner_blocks( $cbb_body, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aus geklonten, bereits gerenderten Kindbloecken zusammengesetzt; im Editor Blockstudios <InnerBlocks />.
?></div>
