<?php
/**
 * Render template of the `creabb/accordion-item` block.
 *
 * 1:1-Uebersetzung von `areoi_render_block_accordion_item()`
 * (`blocks/accordion-item.php` des Alt-Plugins). Aussere Klassenreihenfolge:
 *
 *   block-<uuid>  accordion-item  <className>  <hide-Kaskade mit block>
 *
 * VIER BESONDERHEITEN, alle im Original nachgemessen:
 *
 * 1. `heading` IST EINE WEISSLISTE MIT RUECKFALL. Erlaubt sind `h1` bis `h6`
 *    und `p`; JEDER andere Wert — der leere eingeschlossen — wird zu `h3`.
 *    Die Auswahlliste des Originals fuehrt „Default" mit dem WERT `h1`; ein
 *    nie gesetztes `heading` faellt dagegen auf `h3`.
 *
 * 2. ZWEI ABGELEITETE IDs: `block-<block_id>-header` und
 *    `block-<block_id>-collapse`. Sie verbinden Ueberschrift, Knopf und Koerper
 *    ueber `aria-controls` und `aria-labelledby`.
 *
 * 3. `parent_id` STEUERT `data-bs-parent`. Ist `always_open` LEER, wird
 *    `data-bs-parent=".block-<parent_id>"` gesetzt — mit fuehrendem PUNKT, also
 *    einem Klassenselektor. Ist `always_open` gesetzt, entfaellt das Attribut.
 *
 * 4. `title` LAEUFT DURCH `wp_kses_post()`, nicht durch `esc_html()`. Sein
 *    Vertragsdefault ist der BOOLEAN `true` bei `"type": "string"`, 1:1 aus
 *    dem Original; `wp_kses_post( true )` ergibt die Zeichenkette `1`. Ein
 *    frisch eingefuegtes Element traegt deshalb eine `1` als Ueberschrift,
 *    solange niemand einen Titel eintippt. IM FRONTEND BLEIBT DAS SO — im
 *    Editor wird ein Titel, der kein String ist, geleert. Die Begruendung
 *    steht ueber der Zuweisung von `$cbb_title`; sie braucht mehr Raum, als
 *    diese Liste hat.
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

$cbb_a = crea_bootstrap_blocks_native_attributes( is_array( $attributes ?? null ) ? $attributes : [], $block ?? [] );

$cbb_class = crea_bootstrap_blocks_class_str(
    [
        crea_bootstrap_blocks_block_id_class( $cbb_a['block_id'] ?? null ),
        'accordion-item',
        $cbb_a['className'] ?? '',
        crea_bootstrap_blocks_display_class_str( $cbb_a, 'block' ),
    ]
);

$cbb_button_class = crea_bootstrap_blocks_class_str(
    [
        'accordion-button',
        empty( $cbb_a['open'] ) ? 'collapsed' : '',
    ]
);

$cbb_body_class = crea_bootstrap_blocks_class_str(
    [
        'accordion-collapse',
        'collapse',
        empty( $cbb_a['open'] ) ? '' : 'show',
    ]
);

$cbb_heading = esc_attr( (string) ( $cbb_a['heading'] ?? '' ) );

if ( ! in_array( $cbb_heading, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ], true ) ) {
    $cbb_heading = 'h3';
}

/*
 * BEIDE KENNUNGEN DURCH DASSELBE TOR WIE DIE KLASSE. Bis zum 2026-08-31 stand
 * hier der Rohwert, nur durch esc_attr() geschickt: `block_id` in drei
 * abgeleiteten `id` und in `data-bs-target`/`aria-controls`, `parent_id` in
 * `data-bs-parent`. Die Huelle desselben Blocks verlor ihre Klasse dagegen an
 * der Pruefung — Kopf, Knopf und Koerper verwiesen also auf Kennungen, zu denen
 * es keine Klasse gab, und `data-bs-parent` zeigte auf eine Elternklasse, die
 * das Akkordeon nie ausgab.
 */
$cbb_block_id = esc_attr( crea_bootstrap_blocks_block_id_value( $cbb_a['block_id'] ?? null ) );

$cbb_parent = empty( $cbb_a['always_open'] )
    ? 'data-bs-parent=".block-' . esc_attr( crea_bootstrap_blocks_block_id_value( $cbb_a['parent_id'] ?? null ) ) . '"'
    : '';

/*
 * DIE `1` IM KNOPF IST VERTRAGSTREU — UND WEICHT TROTZDEM, ABER NUR IM EDITOR.
 *
 * DAS ORIGINAL TUT DASSELBE, am 2026-09-08 nachgemessen und nicht vermutet:
 * `areoi/accordion-item` ist KEIN statischer Block. `blocks/index.php:58-88`
 * des Alt-Plugins registriert jeden Blockordner, zu dem eine gleichnamige
 * `.php` existiert, mit `'render_callback' => 'areoi_render_block_' . …`; fuer
 * `accordion-item` existiert sie, und `areoi_render_block_accordion_item()`
 * schreibt `wp_kses_post( $attributes['title'] )` ohne jede Wache. Beide
 * Seiten geben zeichengleich `>1</button>` aus. Die `1` ist ein Defekt DES
 * ORIGINALS, den der Nachbau vertragstreu wiedergibt.
 *
 * DESHALB BLEIBT DAS FRONTEND UNVERAENDERT. Vertragsebene 3 verlangt
 * aequivalentes Markup, nicht das bessere (dieselbe Linie wie E-41 und E-148).
 * Ein Riegel auf beiden Wegen macht 93 der 99 Vergleiche des Differenzlaufs
 * rot und laesst sich ueber die gebuchten Abweichungen NICHT decken: Deren
 * Gegenprobe setzt das gebuchte Attribut auf seinen Vertragsdefault zurueck —
 * und der ist hier genau der abweichende Wert. Ausfuehrlich steht das ueber
 * Abschnitt 4 von `tests/test-block-accordion-item.php`.
 *
 * IM EDITOR MISST NICHTS GEGEN DAS ORIGINAL. Dort kostet das Leeren null
 * Vergleiche und nimmt dem Redakteur eine Ziffer weg, die er sonst fuer
 * eingegebenen Inhalt haelt. Entscheidung des Auftraggebers vom 2026-09-08.
 *
 * DER SCHALTER GEHT DURCH DIESELBE WACHE WIE DIE KINDBLOECKE, UND DAS IST KEIN
 * SCHMUCK. Blockstudios `$isEditor` kommt aus `$_GET['blockstudioMode']`, ohne
 * Nonce und ohne Capability (block.php:1926) — `! empty( $isEditor )` allein
 * gaebe den Zweig jedem anonymen Besucher frei; gemessen wurde das schon
 * einmal, mit sieben literalen Tags im oeffentlichen Quelltext.
 * `crea_bootstrap_blocks_is_editor_render()` haengt `current_user_can(
 * 'edit_posts' )` davor. HIER waere die Folge harmlos: Ein Besucher saehe eine
 * leere Ueberschrift statt einer `1`, und nur bei einem nie gefuellten Titel.
 * Sie ist es aber nur HEUTE und nur fuer DIESES eine Feld. Wer an derselben
 * Verzweigung das naechste Mal mehr aufhaengt, erbte sonst einen
 * ungeschuetzten Zweig samt der Begruendung, warum das schon einmal in Ordnung
 * war — deshalb steht die Wache jetzt da und nicht spaeter.
 *
 * `! is_string()` STATT `true === …`: Ein Titel, den jemand eintippt, ist immer
 * eine Zeichenkette — auch der wieder geleerte (`''`) und der rein numerische.
 * Was kein String ist, kann kein Titel sein, sondern nur der durchgereichte
 * Default. Die Wache ist damit fuer die Regel geschnitten, nicht fuer den
 * einen heute bekannten Wert.
 */
$cbb_title = $cbb_a['title'] ?? '';

if ( crea_bootstrap_blocks_is_editor_render( ! empty( $isEditor ) ) && ! is_string( $cbb_title ) ) {
    $cbb_title = '';
}

$cbb_inner = is_string( $inner_blocks ?? null ) ? $inner_blocks : '';
?>
<div <?php echo crea_bootstrap_blocks_anchor_attr( $cbb_a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?> class="<?php echo esc_attr( $cbb_class ); ?>">
<<?php echo $cbb_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gegen eine Weissliste geprueft. ?> class="accordion-header" id="block-<?php echo $cbb_block_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch esc_attr() gefiltert. ?>-header">
<button class="<?php echo esc_attr( $cbb_button_class ); ?>" type="button" data-bs-toggle="collapse" data-bs-target="#block-<?php echo $cbb_block_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch esc_attr() gefiltert. ?>-collapse" aria-expanded="<?php echo empty( $cbb_a['open'] ) ? 'false' : 'true'; ?>" aria-controls="block-<?php echo $cbb_block_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch esc_attr() gefiltert. ?>-collapse">
<?php echo wp_kses_post( $cbb_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch wp_kses_post() gefiltert. ?>
</button>
</<?php echo $cbb_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gegen eine Weissliste geprueft. ?>>
<div id="block-<?php echo $cbb_block_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch esc_attr() gefiltert. ?>-collapse" class="<?php echo esc_attr( $cbb_body_class ); ?>" aria-labelledby="block-<?php echo $cbb_block_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits durch esc_attr() gefiltert. ?>-header" <?php echo $cbb_parent; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- baut sein Attribut samt esc_attr() selbst. ?>>
<div class="accordion-body">
<?php echo crea_bootstrap_blocks_inner_blocks( $cbb_inner, ! empty( $isEditor ), is_string( $block['name'] ?? null ) ? $block['name'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerenderte Kindbloecke, im Editor Blockstudios <InnerBlocks />. ?>
</div>
</div>
</div>
