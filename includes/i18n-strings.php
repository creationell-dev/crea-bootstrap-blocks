<?php
/**
 * Extraction-only catalogue of translatable block strings.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * Blockstudio registriert Bloecke ueber `new WP_Block_Type()` plus
 * `register_block_type()` statt ueber `register_block_type_from_metadata()`.
 * WordPress' automatische block.json-Uebersetzung laeuft damit nie an: Der
 * Schluessel `textdomain` in der block.json ist WIRKUNGSLOS, Blocktitel und
 * -beschreibung gehen roh in den Editor. Die Feld-Labels unter
 * `blockstudio.attributes` sieht `wp i18n make-pot` ohnehin nicht an — es
 * kennt nur die Kernschluessel einer block.json.
 *
 * Damit die Strings ueberhaupt in die `.pot` gelangen, stehen sie hier ein
 * zweites Mal, als reine String-Literale in `__()`-Aufrufen. Zur Laufzeit
 * schlagen die beiden Filter in `includes/class-blocks.php`
 * (`blockstudio/blocks/meta` und `blockstudio/blocks/attributes`) sie mit
 * `translate( $text, 'crea-bootstrap-blocks' )` nach.
 *
 * DIESE DATEI WIRD NIE AUSGEFUEHRT. Sie steht nicht in der Ladeliste der
 * Hauptdatei, und der Inhalt liegt zusaetzlich in einer Funktion, die nirgends
 * aufgerufen wird — ein versehentliches `require` bleibt damit folgenlos.
 * `tests/test-i18n-strings-literals.php` erzwingt beides.
 *
 * REGELN FUER AENDERUNGEN
 *
 * - Nur `__( '<Literal>', 'crea-bootstrap-blocks' );`. Keine Variablen, keine
 *   Verkettung, keine Konstante als Domain, keine ausgebende Form.
 * - Jedes Literal genau einmal.
 * - Neue Bloecke tragen ihre Titel, Beschreibungen, Feld-Labels, Hilfetexte
 *   und Options-Labels hier nach, sobald ihre block.json entsteht.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Never called. Exists so that `wp i18n make-pot` can extract the strings.
 */
function crea_bootstrap_blocks_i18n_strings(): void {

    // --- Blockkategorien --------------------------------------------------
    __( 'Bootstrap Layout', 'crea-bootstrap-blocks' );
    __( 'Bootstrap Components', 'crea-bootstrap-blocks' );
    __( 'Bootstrap Strips', 'crea-bootstrap-blocks' );

    // --- Blocktitel (Phase 1, 14 Bloecke) ---------------------------------
    __( 'Strip', 'crea-bootstrap-blocks' );
    __( 'Container', 'crea-bootstrap-blocks' );
    __( 'Row', 'crea-bootstrap-blocks' );
    __( 'Column', 'crea-bootstrap-blocks' );
    __( 'Div', 'crea-bootstrap-blocks' );
    __( 'Button', 'crea-bootstrap-blocks' );
    __( 'Button Group', 'crea-bootstrap-blocks' );
    __( 'Card', 'crea-bootstrap-blocks' );
    __( 'Card Body', 'crea-bootstrap-blocks' );
    __( 'Card Header', 'crea-bootstrap-blocks' );
    __( 'Card Footer', 'crea-bootstrap-blocks' );
    __( 'Card Group', 'crea-bootstrap-blocks' );
    __( 'Media Grid', 'crea-bootstrap-blocks' );
    __( 'Image', 'crea-bootstrap-blocks' );

    // --- Blocktitel (Phase 2) ---------------------------------------------
    __( 'Column Break', 'crea-bootstrap-blocks' );

    // --- Blockbeschreibungen ----------------------------------------------
    __( 'Breaking columns to a new line in flexbox requires a small hack: add an element with width: 100% wherever you want to wrap your columns to a new line. Normally this is accomplished with multiple .rows, but not every implementation method can account for this.', 'crea-bootstrap-blocks' );
    __( 'Full width strip allowing background colors, images and videos.', 'crea-bootstrap-blocks' );
    __( 'Containers are a fundamental building block of Bootstrap that contain, pad, and align your content within a given device or viewport.', 'crea-bootstrap-blocks' );
    __( 'Rows are wrappers for columns. Each column has horizontal padding (called a gutter) for controlling the space between them. This padding is then counteracted on the rows.', 'crea-bootstrap-blocks' );
    __( 'Modify columns with a handful of options for alignment, ordering, and offsetting thanks to the flexbox grid system.', 'crea-bootstrap-blocks' );
    __( 'Creates a div element with the ability to add whatever classes you want. Useful for custom layouts built from Bootstrap utility and helper classes.', 'crea-bootstrap-blocks' );
    __( 'Custom button styles for actions in forms, dialogs, and more, with support for multiple sizes and states.', 'crea-bootstrap-blocks' );
    __( 'Group a series of buttons together on a single line or stack them in a vertical column.', 'crea-bootstrap-blocks' );
    __( 'A flexible and extensible content container with multiple variants and options.', 'crea-bootstrap-blocks' );
    __( 'The building block of a card. Use it whenever you need a padded section within a card.', 'crea-bootstrap-blocks' );
    __( 'Add an optional header within a card.', 'crea-bootstrap-blocks' );
    __( 'Add an optional footer within a card.', 'crea-bootstrap-blocks' );
    __( 'Render cards as a single, attached element with equal width and height columns. Card groups start off stacked and become attached at the small breakpoint.', 'crea-bootstrap-blocks' );
    __( 'Display multiple media items in a rich gallery.', 'crea-bootstrap-blocks' );
    __( 'Insert an image to make a visual statement.', 'crea-bootstrap-blocks' );

    /*
     * --- Breakpoint-Tabs ---------------------------------------------------
     *
     * SEIT DEM 2026-09-08 TRAGEN DIE REITER DIE BOOTSTRAP-INFIXE. Ausgeschrieben
     * passten von sechs Reitern nur vier in die 280 Pixel der Seitenleiste — XL
     * und XXL waren nicht erreichbar. Der Entwurf hatte die Kurzform von Anfang
     * an vorgesehen ("XS SM MD LG XL XXL — sechs kurze Knoepfe"), der Pilot hat
     * sie nicht umgesetzt, und im echten Editor ist es sofort aufgefallen.
     *
     * Die Infixe statt S/M/L, weil die Feldbeschriftungen daneben `col-sm-*`,
     * `col-md-*` und `col-lg-*` lauten: Wer den Reiter liest, sieht die Klasse,
     * die er setzt. Das ausgeschriebene Wort steht weiterhin in der
     * `message`-Zeile unmittelbar unter der Reiterleiste, es geht also nichts
     * verloren.
     */
    __( 'XS', 'crea-bootstrap-blocks' );
    __( 'SM', 'crea-bootstrap-blocks' );
    __( 'MD', 'crea-bootstrap-blocks' );
    __( 'LG', 'crea-bootstrap-blocks' );
    __( 'XL', 'crea-bootstrap-blocks' );
    __( 'XXL', 'crea-bootstrap-blocks' );

    /*
     * DIE AUSGESCHRIEBENEN NAMEN BLEIBEN — sie sind kein Rest. In sechzehn
     * Bloecken sind sie Optionslabels einer Groessenauswahl (Button, Modal,
     * Spinner, Media-Grid und andere); wer sie mit den Reitertiteln
     * herausnaehme, entfernte sie auch dort.
     */
    __( 'Extra small', 'crea-bootstrap-blocks' );
    __( 'Small', 'crea-bootstrap-blocks' );
    __( 'Medium', 'crea-bootstrap-blocks' );
    __( 'Large', 'crea-bootstrap-blocks' );
    __( 'Extra large', 'crea-bootstrap-blocks' );
    __( 'Extra extra large', 'crea-bootstrap-blocks' );

    // --- Blockuebergreifende Feld-Labels ----------------------------------
    __( 'Block ID', 'crea-bootstrap-blocks' );
    __( 'Anchor', 'crea-bootstrap-blocks' );
    __( 'Preview', 'crea-bootstrap-blocks' );
    __( 'Alignment', 'crea-bootstrap-blocks' );
    __( 'Container width', 'crea-bootstrap-blocks' );
    __( 'Background utilities', 'crea-bootstrap-blocks' );
    __( 'Text utilities', 'crea-bootstrap-blocks' );
    __( 'Border utilities', 'crea-bootstrap-blocks' );
    __( 'Show background', 'crea-bootstrap-blocks' );
    __( 'Background color', 'crea-bootstrap-blocks' );
    __( 'Background image', 'crea-bootstrap-blocks' );
    __( 'Background video', 'crea-bootstrap-blocks' );
    __( 'Show overlay', 'crea-bootstrap-blocks' );
    __( 'Overlay color', 'crea-bootstrap-blocks' );
    __( 'Pattern color', 'crea-bootstrap-blocks' );
    __( 'Horizontal background alignment', 'crea-bootstrap-blocks' );

    /*
     * --- Hilfetexte, Editor-Seitenleiste "Jedes Feld bekommt Hilfetext"
     * (Aufgabe 13b, 2026-09-07) — bisher nur auf `column`, gilt aber
     * blockuebergreifend, weil Feld und Label selbst blockuebergreifend sind.
     */
    __( 'Turns on the background layer below — its color, overlay and alignment fields only take effect while this is on.', 'crea-bootstrap-blocks' );
    __( 'Turns on the overlay layer, drawn above the background color. The overlay color below only takes effect while this is on.', 'crea-bootstrap-blocks' );
    __( 'Drawn as a layer above the background color. Like the background color, only the rgb values are used for rendering.', 'crea-bootstrap-blocks' );
    __( 'Aligns the background layer within its row when the background column width is narrower than the full row. Has no visible effect at full width.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap background-color utility class to this block. If the background layer is switched on, it is drawn on top and hides this utility.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to this block, inherited by its content unless a nested block sets its own color.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap border-color utility class to this block. Bootstrap\'s border utilities only show a border when a border width is also set, which this field does not add.', 'crea-bootstrap-blocks' );
    __( 'Title attribute of the link created by the URL above. Shown as a tooltip by most browsers.', 'crea-bootstrap-blocks' );
    __( 'Controls whether the link created by the URL above opens in the same tab or a new one.', 'crea-bootstrap-blocks' );
    __( 'Value of the rel attribute on the link created by the URL above, for example nofollow or noopener.', 'crea-bootstrap-blocks' );

    // --- Feld-Labels je Breakpoint ----------------------------------------

    /*
     * 'Height' BLEIBT, obwohl `creabb-spacing` das Label am 2026-09-08 auf
     * 'Height value' geschaerft hat: Die sechs `height_dimension_<bp>` von
     * `blocks/media-grid/block.json` fuehren weiterhin das nackte 'Height', und
     * der Gruppentitel der Bibliothek heisst ebenfalls so. Wer den Eintrag mit
     * dem Label wegwirft, laesst beide unuebersetzt — bei gruenem Lauf, weil
     * `test-i18n-catalogs.php` nur Vollstaendigkeit der geernteten Strings
     * fordert und ein NICHT geernteter String dort gar nicht auftaucht.
     */
    __( 'Height', 'crea-bootstrap-blocks' );
    __( 'Height value', 'crea-bootstrap-blocks' );
    __( 'Height unit', 'crea-bootstrap-blocks' );
    __( 'Padding top', 'crea-bootstrap-blocks' );
    __( 'Padding right', 'crea-bootstrap-blocks' );
    __( 'Padding bottom', 'crea-bootstrap-blocks' );
    __( 'Padding left', 'crea-bootstrap-blocks' );
    __( 'Margin top', 'crea-bootstrap-blocks' );
    __( 'Margin right', 'crea-bootstrap-blocks' );
    __( 'Margin bottom', 'crea-bootstrap-blocks' );
    __( 'Margin left', 'crea-bootstrap-blocks' );
    __( 'Hide at this breakpoint', 'crea-bootstrap-blocks' );
    __( 'Hide background at this breakpoint', 'crea-bootstrap-blocks' );
    __( 'Background columns', 'crea-bootstrap-blocks' );
    __( 'Column width', 'crea-bootstrap-blocks' );
    __( 'Offset', 'crea-bootstrap-blocks' );
    __( 'Order', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment', 'crea-bootstrap-blocks' );
    __( 'Grid row', 'crea-bootstrap-blocks' );

    // --- Feld-Labels von column, row, button, media-grid-image ------------
    __( 'Link URL', 'crea-bootstrap-blocks' );
    __( 'Link title', 'crea-bootstrap-blocks' );
    __( 'Title attribute', 'crea-bootstrap-blocks' );
    __( 'Link target', 'crea-bootstrap-blocks' );
    __( 'Link relationship', 'crea-bootstrap-blocks' );
    __( 'Flex row', 'crea-bootstrap-blocks' );
    __( 'Button text', 'crea-bootstrap-blocks' );
    __( 'Button style', 'crea-bootstrap-blocks' );
    __( 'Button size', 'crea-bootstrap-blocks' );
    __( 'Image URL', 'crea-bootstrap-blocks' );
    __( 'Attachment ID', 'crea-bootstrap-blocks' );
    __( 'Image size', 'crea-bootstrap-blocks' );
    __( 'Link destination', 'crea-bootstrap-blocks' );
    __( 'Parent block ID', 'crea-bootstrap-blocks' );

    // --- Hilfetexte -------------------------------------------------------
    __( 'Unique identifier of this block. It links the block to its generated inline CSS and must not be changed.', 'crea-bootstrap-blocks' );
    __( 'Numeric value without a unit. The unit is set below.', 'crea-bootstrap-blocks' );
    __( 'Number without a unit. The unit is a plugin setting, pixels by default. Leave empty for no spacing; enter 0 to force a zero value.', 'crea-bootstrap-blocks' );
    __( 'Number without a unit. The unit is a plugin setting, pixels by default.', 'crea-bootstrap-blocks' );
    __( 'Hiding a breakpoint also removes the column, offset, order and alignment classes for that breakpoint.', 'crea-bootstrap-blocks' );
    __( 'Only the rgb values are used for rendering. The remaining keys are kept for compatibility with the previous plugin.', 'crea-bootstrap-blocks' );
    __( 'Applies from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap utility classes, separated by spaces.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the id attribute of the outer element.', 'crea-bootstrap-blocks' );

    /*
     * --- Options-Labels ---------------------------------------------------
     *
     * Achtung: Uebersetzt wird ausschliesslich das LABEL. Der gespeicherte
     * Wert bleibt unveraendert — insbesondere der Literalwert "Default", der
     * nach Vertragsregel 1.3 ein Datenwert ist, im Content steht und beim
     * Rendern als "keine Klasse" behandelt wird.
     */
    /* translators: Label of the option whose stored value is the literal string "Default". */
    __( 'Default', 'crea-bootstrap-blocks' );
    __( 'None', 'crea-bootstrap-blocks' );
    __( 'Auto', 'crea-bootstrap-blocks' );
    __( 'Start', 'crea-bootstrap-blocks' );
    __( 'Center', 'crea-bootstrap-blocks' );
    __( 'End', 'crea-bootstrap-blocks' );
    __( 'Top', 'crea-bootstrap-blocks' );
    __( 'Middle', 'crea-bootstrap-blocks' );
    __( 'Bottom', 'crea-bootstrap-blocks' );
    __( 'Pixels', 'crea-bootstrap-blocks' );
    __( 'Percent', 'crea-bootstrap-blocks' );
    __( 'Viewport height', 'crea-bootstrap-blocks' );
    __( 'Rem', 'crea-bootstrap-blocks' );

    // --- Feld-Labels: creabb-spacing ---------------------------------------
    __( 'Em', 'crea-bootstrap-blocks' );

    // --- Gruppentitel (Editor-Seitenleiste) ---------------------------------
    __( 'Padding', 'crea-bootstrap-blocks' );
    __( 'Margin', 'crea-bootstrap-blocks' );
    __( 'Visibility', 'crea-bootstrap-blocks' );
    __( 'Background at this breakpoint', 'crea-bootstrap-blocks' );
    __( 'CSS grid', 'crea-bootstrap-blocks' );
    __( 'Utility classes', 'crea-bootstrap-blocks' );
    __( 'Technical', 'crea-bootstrap-blocks' );

    /*
     * --- Name der Feldbibliothek (field.json, oberste Ebene) --------------
     *
     * Seit Aufgabe 7 (2026-09-07) traegt field.json dieselbe Schluesselmenge
     * wie block.json (tests/test-i18n-strings-literals.php). Das erfasst auch
     * den eigenen Namen der geteilten Bibliothek, nicht nur die Titel ihrer
     * Gruppen — obwohl kein Filter ihn je an den Editor ausliefert
     * (`Field_Registry` reicht nur `attributes` weiter). E-35 kennt diese
     * Unterscheidung nicht: Jedes Literal aus einer block.json/field.json
     * gehoert hierher.
     */
    __( 'Spacing', 'crea-bootstrap-blocks' );
    __( 'Height and margin', 'crea-bootstrap-blocks' );

    // --- Options-Labels: container ------------------------------------------
    __( 'Fixed', 'crea-bootstrap-blocks' );
    __( 'Fluid', 'crea-bootstrap-blocks' );
    __( 'Wide', 'crea-bootstrap-blocks' );
    __( 'Full', 'crea-bootstrap-blocks' );
    __( 'Primary', 'crea-bootstrap-blocks' );
    __( 'Secondary', 'crea-bootstrap-blocks' );
    __( 'Success', 'crea-bootstrap-blocks' );
    __( 'Warning', 'crea-bootstrap-blocks' );
    __( 'Danger', 'crea-bootstrap-blocks' );
    __( 'Info', 'crea-bootstrap-blocks' );
    __( 'Dark', 'crea-bootstrap-blocks' );
    __( 'Light', 'crea-bootstrap-blocks' );
    __( 'Body', 'crea-bootstrap-blocks' );
    __( 'White', 'crea-bootstrap-blocks' );
    __( 'Left', 'crea-bootstrap-blocks' );
    __( 'Right', 'crea-bootstrap-blocks' );

    // --- Feld- und Options-Labels: row --------------------------------------
    __( 'Force flexbox', 'crea-bootstrap-blocks' );
    __( 'Keeps this row on the Bootstrap flexbox grid even when the CSS grid mode is switched on. Passed to the columns inside.', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment', 'crea-bootstrap-blocks' );
    __( 'Around', 'crea-bootstrap-blocks' );
    __( 'Between', 'crea-bootstrap-blocks' );
    __( 'Evenly', 'crea-bootstrap-blocks' );
    __( 'Columns per row', 'crea-bootstrap-blocks' );
    __( 'Grid column gap', 'crea-bootstrap-blocks' );
    __( 'Grid column gap unit', 'crea-bootstrap-blocks' );
    __( 'Grid row gap', 'crea-bootstrap-blocks' );
    __( 'Grid row gap unit', 'crea-bootstrap-blocks' );
    __( 'Grid rows', 'crea-bootstrap-blocks' );
    __( 'Only used in CSS grid mode.', 'crea-bootstrap-blocks' );

    // --- Feld- und Options-Labels: column ------------------------------------
    __( 'New tab', 'crea-bootstrap-blocks' );
    __( 'Columns', 'crea-bootstrap-blocks' );
    __( 'Turns the whole column into a link. The anchor is rendered behind the content and covers the column.', 'crea-bootstrap-blocks' );

    /*
     * --- Editor-Seitenleiste: column (Aufgabe 13, 2026-09-07) -------------
     *
     * Die vier Bootstrap-Kernfelder tragen ihre Klasse im Label, je
     * Breakpoint verschieden ausser bei `xs` (kein Infix in Bootstrap 5).
     * Die vier Hilfetexte darunter sind absichtlich EIN Katalogeintrag statt
     * sechs — dieselbe Formulierung gilt an jedem Breakpoint. Ebenso die
     * `message`-Zeile der Gruppe "CSS grid" (deutsch: "CSS-Grid", konsistent
     * mit der bestehenden Uebersetzung von "Only used in CSS grid mode.").
     */
    __( 'Column width · col-*', 'crea-bootstrap-blocks' );
    __( 'Column width · col-sm-*', 'crea-bootstrap-blocks' );
    __( 'Column width · col-md-*', 'crea-bootstrap-blocks' );
    __( 'Column width · col-lg-*', 'crea-bootstrap-blocks' );
    __( 'Column width · col-xl-*', 'crea-bootstrap-blocks' );
    __( 'Column width · col-xxl-*', 'crea-bootstrap-blocks' );
    __( 'Offset · offset-*', 'crea-bootstrap-blocks' );
    __( 'Offset · offset-sm-*', 'crea-bootstrap-blocks' );
    __( 'Offset · offset-md-*', 'crea-bootstrap-blocks' );
    __( 'Offset · offset-lg-*', 'crea-bootstrap-blocks' );
    __( 'Offset · offset-xl-*', 'crea-bootstrap-blocks' );
    __( 'Offset · offset-xxl-*', 'crea-bootstrap-blocks' );
    __( 'Order · order-*', 'crea-bootstrap-blocks' );
    __( 'Order · order-sm-*', 'crea-bootstrap-blocks' );
    __( 'Order · order-md-*', 'crea-bootstrap-blocks' );
    __( 'Order · order-lg-*', 'crea-bootstrap-blocks' );
    __( 'Order · order-xl-*', 'crea-bootstrap-blocks' );
    __( 'Order · order-xxl-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-self-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-self-sm-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-self-md-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-self-lg-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-self-xl-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-self-xxl-*', 'crea-bootstrap-blocks' );
    __( 'Extra small · no Bootstrap infix (default size)', 'crea-bootstrap-blocks' );
    __( 'Small · Bootstrap infix sm', 'crea-bootstrap-blocks' );
    __( 'Medium · Bootstrap infix md', 'crea-bootstrap-blocks' );
    __( 'Large · Bootstrap infix lg', 'crea-bootstrap-blocks' );
    __( 'Extra large · Bootstrap infix xl', 'crea-bootstrap-blocks' );
    __( 'Extra extra large · Bootstrap infix xxl', 'crea-bootstrap-blocks' );
    __( 'Number of the twelve grid columns this block occupies from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Empty grid columns to the left of this block.', 'crea-bootstrap-blocks' );
    __( 'Visual position within the row, independent of the source order.', 'crea-bootstrap-blocks' );
    __( 'Alignment of this block within the row.', 'crea-bootstrap-blocks' );
    __( 'Hides the background layer of this block at this breakpoint and restores it at the next visible one.', 'crea-bootstrap-blocks' );
    __( 'Width of the background layer\'s grid column, independent of the block\'s own column width.', 'crea-bootstrap-blocks' );
    __( 'Number for the CSS grid-row property, for example 2.', 'crea-bootstrap-blocks' );
    __( 'Only takes effect when CSS grid is enabled in the plugin settings.', 'crea-bootstrap-blocks' );

    // --- Feld- und Options-Labels: button -----------------------------------
    __( 'Tag name', 'crea-bootstrap-blocks' );
    __( 'Only these four tag names are rendered; anything else falls back to a.', 'crea-bootstrap-blocks' );
    __( 'Link', 'crea-bootstrap-blocks' );
    __( 'Span', 'crea-bootstrap-blocks' );
    __( 'Outline primary', 'crea-bootstrap-blocks' );
    __( 'Outline secondary', 'crea-bootstrap-blocks' );
    __( 'Outline dark', 'crea-bootstrap-blocks' );
    __( 'Outline light', 'crea-bootstrap-blocks' );
    __( 'Text wrapping', 'crea-bootstrap-blocks' );
    __( 'No wrapping', 'crea-bootstrap-blocks' );
    __( 'Rendered through wp_kses_post(); inline markup is allowed, scripts are not.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the title attribute.', 'crea-bootstrap-blocks' );
    __( 'Kept for compatibility. The rendered title attribute comes from the link title above.', 'crea-bootstrap-blocks' );
    __( 'Link opens a modal', 'crea-bootstrap-blocks' );
    __( 'Only rendered when the filter crea_bootstrap_blocks_button_modal returns true.', 'crea-bootstrap-blocks' );
    __( 'Show icon', 'crea-bootstrap-blocks' );
    __( 'Icon class', 'crea-bootstrap-blocks' );
    __( 'A Bootstrap Icons class such as bi-activity. Bootstrap Icons must be enabled under Appearance, CreaBootstrapBlocks, Assets — or come from the theme.', 'crea-bootstrap-blocks' );
    __( 'Icon size', 'crea-bootstrap-blocks' );
    __( 'Numeric value without a unit; pixels are added on output.', 'crea-bootstrap-blocks' );
    __( 'Icon position', 'crea-bootstrap-blocks' );
    __( 'Before the text', 'crea-bootstrap-blocks' );
    __( 'After the text', 'crea-bootstrap-blocks' );
    __( 'Show popover', 'crea-bootstrap-blocks' );
    __( 'Popover title', 'crea-bootstrap-blocks' );
    __( 'Popover content', 'crea-bootstrap-blocks' );
    __( 'Popover direction', 'crea-bootstrap-blocks' );
    __( 'Popover trigger', 'crea-bootstrap-blocks' );
    __( 'Click', 'crea-bootstrap-blocks' );
    __( 'Hover', 'crea-bootstrap-blocks' );
    __( 'Show tooltip', 'crea-bootstrap-blocks' );
    __( 'Tooltip content', 'crea-bootstrap-blocks' );
    __( 'Tooltip direction', 'crea-bootstrap-blocks' );
    __( 'Show dropdown', 'crea-bootstrap-blocks' );
    __( 'Dropdown auto close', 'crea-bootstrap-blocks' );
    __( 'Always', 'crea-bootstrap-blocks' );
    __( 'Inside', 'crea-bootstrap-blocks' );
    __( 'Outside', 'crea-bootstrap-blocks' );
    __( 'Never', 'crea-bootstrap-blocks' );
    __( 'Dropdown style', 'crea-bootstrap-blocks' );
    __( 'Dropdown direction', 'crea-bootstrap-blocks' );
    __( 'Down', 'crea-bootstrap-blocks' );
    __( 'Up', 'crea-bootstrap-blocks' );
    __( 'Dropdown alignment', 'crea-bootstrap-blocks' );
    __( 'Show badge', 'crea-bootstrap-blocks' );
    __( 'Badge content', 'crea-bootstrap-blocks' );
    __( 'Badge style', 'crea-bootstrap-blocks' );
    __( 'Pill', 'crea-bootstrap-blocks' );
    __( 'Badge background', 'crea-bootstrap-blocks' );
    __( 'Badge text color', 'crea-bootstrap-blocks' );
    __( 'Badge classes', 'crea-bootstrap-blocks' );
    __( 'Full width at this breakpoint', 'crea-bootstrap-blocks' );

    // --- button-group -------------------------------------------------------
    __( 'Group style', 'crea-bootstrap-blocks' );
    __( 'Group size', 'crea-bootstrap-blocks' );
    __( 'Horizontal', 'crea-bootstrap-blocks' );
    __( 'Vertical', 'crea-bootstrap-blocks' );

    // --- Feld-Labels: strip -------------------------------------------------
    __( 'Exclude divider', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. Only the Lightspeed theme evaluates it; this plugin stores the value and renders nothing from it.', 'crea-bootstrap-blocks' );
    __( 'Exclude pattern', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. A pattern is only rendered when the filter crea_bootstrap_blocks_background_pattern supplies one.', 'crea-bootstrap-blocks' );
    __( 'Change pattern color', 'crea-bootstrap-blocks' );
    __( 'Exclude transition', 'crea-bootstrap-blocks' );
    __( 'Exclude parallax', 'crea-bootstrap-blocks' );

    // --- Feld- und Options-Labels: media-grid ------------------------------
    __( 'Layout', 'crea-bootstrap-blocks' );
    __( 'Grid', 'crea-bootstrap-blocks' );
    __( 'Masonry', 'crea-bootstrap-blocks' );
    __( 'Item style', 'crea-bootstrap-blocks' );
    __( 'Flush', 'crea-bootstrap-blocks' );
    __( 'Item size', 'crea-bootstrap-blocks' );
    __( 'Card size', 'crea-bootstrap-blocks' );
    __( 'Media size', 'crea-bootstrap-blocks' );
    __( 'Media fit', 'crea-bootstrap-blocks' );
    __( 'Media height', 'crea-bootstrap-blocks' );
    __( 'Media width', 'crea-bootstrap-blocks' );
    __( 'Media alignment', 'crea-bootstrap-blocks' );
    __( 'Cover', 'crea-bootstrap-blocks' );
    __( 'Contain', 'crea-bootstrap-blocks' );
    __( 'Set dimensions', 'crea-bootstrap-blocks' );
    __( 'Link to', 'crea-bootstrap-blocks' );
    __( 'Media file', 'crea-bootstrap-blocks' );
    __( 'Attachment page', 'crea-bootstrap-blocks' );
    __( 'Thumbnail', 'crea-bootstrap-blocks' );
    __( 'Full size', 'crea-bootstrap-blocks' );
    __( 'Crop images', 'crea-bootstrap-blocks' );
    __( 'Fixed height', 'crea-bootstrap-blocks' );
    __( 'Allow resizing', 'crea-bootstrap-blocks' );
    __( 'Show heading', 'crea-bootstrap-blocks' );
    __( 'Heading level', 'crea-bootstrap-blocks' );
    __( 'Heading', 'crea-bootstrap-blocks' );
    __( 'Heading color', 'crea-bootstrap-blocks' );
    __( 'Show intro', 'crea-bootstrap-blocks' );
    __( 'Intro', 'crea-bootstrap-blocks' );
    __( 'Intro color', 'crea-bootstrap-blocks' );
    __( 'Heading columns', 'crea-bootstrap-blocks' );
    __( 'Heading horizontal alignment', 'crea-bootstrap-blocks' );
    __( 'Heading text alignment', 'crea-bootstrap-blocks' );
    __( 'H1', 'crea-bootstrap-blocks' );
    __( 'H2', 'crea-bootstrap-blocks' );
    __( 'H3', 'crea-bootstrap-blocks' );
    __( 'H4', 'crea-bootstrap-blocks' );
    __( 'H5', 'crea-bootstrap-blocks' );
    __( 'H6', 'crea-bootstrap-blocks' );
    __( 'Applied by the editor to every image in this grid.', 'crea-bootstrap-blocks' );
    __( 'Passed on to the images: Full removes their padding.', 'crea-bootstrap-blocks' );
    __( 'Stored for compatibility. This block does not render utility classes — neither does the previous plugin.', 'crea-bootstrap-blocks' );

    // --- Feld-Labels: media-grid-image -------------------------------------
    __( 'Alt text', 'crea-bootstrap-blocks' );
    __( 'Image width', 'crea-bootstrap-blocks' );
    __( 'Image height', 'crea-bootstrap-blocks' );
    __( 'Block ID of the surrounding media grid. It supplies the item style and the link target.', 'crea-bootstrap-blocks' );
    __( 'The image is rendered from this attachment, not from the URL below.', 'crea-bootstrap-blocks' );
    __( 'Leave empty for decorative images.', 'crea-bootstrap-blocks' );
    __( 'The rendered link uses the target of the surrounding media grid.', 'crea-bootstrap-blocks' );
    __( 'Only used with Set dimensions. Numeric value without a unit; pixels are added on output.', 'crea-bootstrap-blocks' );

    // --- Phase 2, Gruppe H: Titel, Beschreibungen, Labels, Hilfetexte -----
    __( 'Alert', 'crea-bootstrap-blocks' );
    __( 'Provide contextual feedback messages for typical user actions with the handful of available and flexible alert messages.', 'crea-bootstrap-blocks' );
    __( 'Style', 'crea-bootstrap-blocks' );
    __( 'Alerts are available for any length of text, as well as an optional close button. For proper styling, use one of the eight required contextual classes (e.g., .alert-success).', 'crea-bootstrap-blocks' );
    __( 'Display Close Button', 'crea-bootstrap-blocks' );
    __( 'Add a close button and the .alert-dismissible class, which adds extra padding to the right of the alert and positions the close button.', 'crea-bootstrap-blocks' );
    __( 'Collapse', 'crea-bootstrap-blocks' );
    __( 'Toggle the visibility of content across your project with a few classes and our JavaScript plugins.', 'crea-bootstrap-blocks' );
    __( 'Open', 'crea-bootstrap-blocks' );
    __( 'Set the collapsible content visible as a default.', 'crea-bootstrap-blocks' );
    __( 'Spinner', 'crea-bootstrap-blocks' );
    __( 'Indicate the loading state of a component or page with Bootstrap spinners, built entirely with HTML, CSS, and no JavaScript.', 'crea-bootstrap-blocks' );
    __( 'The border spinner uses currentColor for its border-color, meaning you can customize the color with text color utilities. You can use any of our text color utilities on the standard spinner.', 'crea-bootstrap-blocks' );
    __( 'Border', 'crea-bootstrap-blocks' );
    __( 'Grow', 'crea-bootstrap-blocks' );
    __( 'Color', 'crea-bootstrap-blocks' );
    __( 'Size', 'crea-bootstrap-blocks' );
    __( 'Add .spinner-border-sm and .spinner-grow-sm to make a smaller spinner that can quickly be used within other components.', 'crea-bootstrap-blocks' );
    __( 'Icon', 'crea-bootstrap-blocks' );
    __( 'Display a Bootstrap icon in any of the available theme colours and sizes.', 'crea-bootstrap-blocks' );
    __( 'Extra Small', 'crea-bootstrap-blocks' );
    __( 'Extra Large', 'crea-bootstrap-blocks' );
    __( 'Extra Extra Large', 'crea-bootstrap-blocks' );
    __( 'Horizontal Align', 'crea-bootstrap-blocks' );
    __( 'List Group', 'crea-bootstrap-blocks' );
    __( 'List groups are a flexible and powerful component for displaying a series of content.', 'crea-bootstrap-blocks' );
    __( 'Add .list-group-flush to remove some borders and rounded corners to render list group items edge-to-edge in a parent container (e.g., cards).', 'crea-bootstrap-blocks' );
    __( 'Add .list-group-horizontal to change the layout of list group items from vertical to horizontal across all breakpoints.', 'crea-bootstrap-blocks' );
    __( 'List Group Item', 'crea-bootstrap-blocks' );
    __( 'A single item within a list group.', 'crea-bootstrap-blocks' );
    __( 'Identifier of the surrounding block. It is set automatically and must not be changed.', 'crea-bootstrap-blocks' );
    __( 'Active', 'crea-bootstrap-blocks' );
    __( 'Add .active to a .list-group-item to indicate the current active selection.', 'crea-bootstrap-blocks' );
    __( 'Disabled', 'crea-bootstrap-blocks' );
    __( 'Add .disabled to a .list-group-item to make it appear disabled. Note that some elements with .disabled will also require custom JavaScript to fully disable their click events (e.g., links).', 'crea-bootstrap-blocks' );
    __( 'Action', 'crea-bootstrap-blocks' );
    __( 'Contextual classes also work with .list-group-item-action. Note the addition of the hover styles.', 'crea-bootstrap-blocks' );
    __( 'Contextual classes', 'crea-bootstrap-blocks' );
    __( 'Use contextual classes to style list items with a stateful background and color.', 'crea-bootstrap-blocks' );
    __( 'Text', 'crea-bootstrap-blocks' );
    __( 'URL', 'crea-bootstrap-blocks' );
    __( 'Title', 'crea-bootstrap-blocks' );
    __( 'Open the link in a new tab.', 'crea-bootstrap-blocks' );
    __( 'Dropdown Item', 'crea-bootstrap-blocks' );
    __( 'A single entry within a dropdown menu.', 'crea-bootstrap-blocks' );
    __( 'Type', 'crea-bootstrap-blocks' );
    __( 'Choose how you would like the item to be displayed.', 'crea-bootstrap-blocks' );
    __( 'Header', 'crea-bootstrap-blocks' );
    __( 'Divider', 'crea-bootstrap-blocks' );
    __( 'Add .active to items in the dropdown to style them as active.', 'crea-bootstrap-blocks' );
    __( 'Add .disabled to items in the dropdown to style them as disabled.', 'crea-bootstrap-blocks' );

    // --- Phase 2, Gruppe I -------------------------------------------------
    __( 'Accordion', 'crea-bootstrap-blocks' );
    __( 'Build vertically collapsing accordions in combination with our Collapse JavaScript plugin.', 'crea-bootstrap-blocks' );
    __( 'Add .accordion-flush to remove the default background-color, some borders, and some rounded corners to render accordions edge-to-edge with their parent container.', 'crea-bootstrap-blocks' );
    __( 'Accordion Item', 'crea-bootstrap-blocks' );
    __( 'A single collapsible section within an accordion.', 'crea-bootstrap-blocks' );
    __( 'Specify the element type to apply to the accordion header.', 'crea-bootstrap-blocks' );
    __( 'p', 'crea-bootstrap-blocks' );
    __( 'The accordion uses collapse internally to make it collapsible. To render an accordion that’s expanded, add the .open class on the .accordion.', 'crea-bootstrap-blocks' );
    __( 'Always Open', 'crea-bootstrap-blocks' );
    __( 'Omit the data-bs-parent attribute on each .accordion-collapse to make accordion items stay open when another item is opened.', 'crea-bootstrap-blocks' );
    __( 'Tabs', 'crea-bootstrap-blocks' );
    __( 'Wrap navigation and its tab panes so they can be shown and hidden together.', 'crea-bootstrap-blocks' );
    __( 'Nav and Tab', 'crea-bootstrap-blocks' );
    __( 'Navigation available in Bootstrap share general markup and styles, from the base .nav class to the active and disabled states.', 'crea-bootstrap-blocks' );
    __( 'Pills', 'crea-bootstrap-blocks' );
    __( 'Fill & Justify', 'crea-bootstrap-blocks' );
    __( 'Force your .nav’s contents to extend the full available width one of two modifier classes. To proportionately fill all available space with your .nav-items, use .nav-fill. Notice that all horizontal space is occupied, but not every nav item has the same width.', 'crea-bootstrap-blocks' );
    __( 'Fill', 'crea-bootstrap-blocks' );
    __( 'Justify', 'crea-bootstrap-blocks' );
    __( 'Change the horizontal alignment of your nav with flexbox utilities. By default, navs are left-aligned, but you can easily change them to center or right aligned.', 'crea-bootstrap-blocks' );
    __( 'Vertical Align', 'crea-bootstrap-blocks' );
    __( 'Stack your navigation by changing the flex item direction with the .flex-column utility. Need to stack them on some viewports but not others? Use the responsive versions (e.g., .flex-sm-column).', 'crea-bootstrap-blocks' );
    __( 'Nav and Tab Item', 'crea-bootstrap-blocks' );
    __( 'A single link within a nav or tab list.', 'crea-bootstrap-blocks' );
    __( 'Add .active to a .nav-item to indicate the current active selection.', 'crea-bootstrap-blocks' );
    __( 'Add .disabled to a .nav-item to make it appear disabled. Note that some elements with .disabled will also require custom JavaScript to fully disable their click events (e.g., links).', 'crea-bootstrap-blocks' );

    // --- Phase 2, Gruppe J -------------------------------------------------
    __( 'Modal', 'crea-bootstrap-blocks' );
    __( 'Use Bootstrap’s JavaScript modal plugin to add dialogs to your site for lightboxes, user notifications, or completely custom content.', 'crea-bootstrap-blocks' );
    __( 'Backdrop', 'crea-bootstrap-blocks' );
    __( 'When backdrop is set to static, the modal will not close when clicking outside of it.', 'crea-bootstrap-blocks' );
    __( 'Static', 'crea-bootstrap-blocks' );
    __( 'Scrollable Dialog', 'crea-bootstrap-blocks' );
    __( 'When modals become too long for the user’s viewport or device, they scroll independent of the page itself.', 'crea-bootstrap-blocks' );
    __( 'Scrollable', 'crea-bootstrap-blocks' );
    __( 'Vertically Centered Dialog', 'crea-bootstrap-blocks' );
    __( 'Add .modal-dialog-centered to .modal-dialog to vertically center the modal.', 'crea-bootstrap-blocks' );
    __( 'Centered', 'crea-bootstrap-blocks' );
    __( 'Dialog Size', 'crea-bootstrap-blocks' );
    __( 'Modals have optional sizes, available via modifier classes to be placed on a .modal-dialog. These sizes kick in at certain breakpoints to avoid horizontal scrollbars on narrower viewports.', 'crea-bootstrap-blocks' );
    __( 'Fullscreen', 'crea-bootstrap-blocks' );
    __( 'Modal Header', 'crea-bootstrap-blocks' );
    __( 'The header of a modal dialog.', 'crea-bootstrap-blocks' );
    __( 'Add a close button to the modal header.', 'crea-bootstrap-blocks' );
    __( 'Modal Body', 'crea-bootstrap-blocks' );
    __( 'The body of a modal dialog.', 'crea-bootstrap-blocks' );
    __( 'Modal Footer', 'crea-bootstrap-blocks' );
    __( 'The footer of a modal dialog.', 'crea-bootstrap-blocks' );
    __( 'Offcanvas', 'crea-bootstrap-blocks' );
    __( 'Build hidden sidebars into your project for navigation, shopping carts, and more.', 'crea-bootstrap-blocks' );
    __( 'Choose whether a backdrop is shown behind the offcanvas panel.', 'crea-bootstrap-blocks' );
    __( 'Include Backdrop', 'crea-bootstrap-blocks' );
    __( 'No Backdrop', 'crea-bootstrap-blocks' );
    __( 'Choose whether the page behind the offcanvas panel can be scrolled.', 'crea-bootstrap-blocks' );
    __( 'Disable Scrolling', 'crea-bootstrap-blocks' );
    __( 'Allow Scrolling', 'crea-bootstrap-blocks' );
    __( 'Placement', 'crea-bootstrap-blocks' );
    __( 'Choose the edge the offcanvas panel slides in from.', 'crea-bootstrap-blocks' );
    __( 'Offcanvas Header', 'crea-bootstrap-blocks' );
    __( 'The header of an offcanvas panel.', 'crea-bootstrap-blocks' );
    __( 'Offcanvas Body', 'crea-bootstrap-blocks' );
    __( 'The body of an offcanvas panel.', 'crea-bootstrap-blocks' );
    __( 'Toast', 'crea-bootstrap-blocks' );
    __( 'Push notifications to your visitors with a toast, a lightweight and easily customizable alert message.', 'crea-bootstrap-blocks' );
    __( 'Choose the corner the toast is shown in.', 'crea-bootstrap-blocks' );
    __( 'Top Left', 'crea-bootstrap-blocks' );
    __( 'Top Right', 'crea-bootstrap-blocks' );
    __( 'Bottom Right', 'crea-bootstrap-blocks' );
    __( 'Bottom Left', 'crea-bootstrap-blocks' );
    __( 'Toast Header', 'crea-bootstrap-blocks' );
    __( 'The header of a toast notification.', 'crea-bootstrap-blocks' );
    __( 'Toast Body', 'crea-bootstrap-blocks' );
    __( 'The body of a toast notification.', 'crea-bootstrap-blocks' );

    // --- Phase 2, Gruppe K -------------------------------------------------
    __( 'Carousel', 'crea-bootstrap-blocks' );
    __( 'A slideshow component for cycling through elements — images or slides of text — like a carousel.', 'crea-bootstrap-blocks' );
    __( 'Display Controls', 'crea-bootstrap-blocks' );
    __( 'Show the previous and next buttons.', 'crea-bootstrap-blocks' );
    __( 'Display Indicators', 'crea-bootstrap-blocks' );
    __( 'Show the slide indicators below the carousel.', 'crea-bootstrap-blocks' );
    __( 'Touch Enabled', 'crea-bootstrap-blocks' );
    __( 'Allow swiping left and right on touch devices.', 'crea-bootstrap-blocks' );
    __( 'Pause', 'crea-bootstrap-blocks' );
    __( 'Choose whether the carousel pauses when the pointer hovers over it.', 'crea-bootstrap-blocks' );
    __( 'False', 'crea-bootstrap-blocks' );
    __( 'Auto Scroll', 'crea-bootstrap-blocks' );
    __( 'Cycle through the slides automatically.', 'crea-bootstrap-blocks' );
    __( 'Auto Scroll Interval', 'crea-bootstrap-blocks' );
    __( 'Time in milliseconds between two slides.', 'crea-bootstrap-blocks' );
    __( 'Choose the colour scheme of the carousel controls and indicators.', 'crea-bootstrap-blocks' );
    __( 'Transition', 'crea-bootstrap-blocks' );
    __( 'Choose how one slide is replaced by the next.', 'crea-bootstrap-blocks' );
    __( 'Crossfade', 'crea-bootstrap-blocks' );
    __( 'Carousel Item', 'crea-bootstrap-blocks' );
    __( 'A single slide within a carousel.', 'crea-bootstrap-blocks' );
    __( 'Interval', 'crea-bootstrap-blocks' );
    __( 'Time in milliseconds this slide is shown before the next one.', 'crea-bootstrap-blocks' );
    __( 'Progress', 'crea-bootstrap-blocks' );
    __( 'Document the progress of a task or an action with a progress bar.', 'crea-bootstrap-blocks' );
    __( 'Width', 'crea-bootstrap-blocks' );
    __( 'Width of the bar in percent.', 'crea-bootstrap-blocks' );
    __( 'Include Label', 'crea-bootstrap-blocks' );
    __( 'Show the width value as text inside the bar.', 'crea-bootstrap-blocks' );
    __( 'Include Stripes', 'crea-bootstrap-blocks' );
    __( 'Add a striped pattern to the bar.', 'crea-bootstrap-blocks' );
    __( 'Include Animation', 'crea-bootstrap-blocks' );
    __( 'Animate the striped pattern.', 'crea-bootstrap-blocks' );
    __( 'Background', 'crea-bootstrap-blocks' );
    __( 'Choose the colour of the bar.', 'crea-bootstrap-blocks' );

    // --- Phase 2, Gruppe L -------------------------------------------------
    __( 'Banner', 'crea-bootstrap-blocks' );
    __( 'A banner area holding one or more banner items.', 'crea-bootstrap-blocks' );
    __( 'Banner Item', 'crea-bootstrap-blocks' );
    __( 'A single banner within a banner area.', 'crea-bootstrap-blocks' );
    __( 'Content with Media', 'crea-bootstrap-blocks' );
    __( 'A content section with an image or video beside it.', 'crea-bootstrap-blocks' );
    __( 'Choose which side the content sits on.', 'crea-bootstrap-blocks' );
    __( 'Content Grid', 'crea-bootstrap-blocks' );
    __( 'A grid of content items with an optional heading and intro.', 'crea-bootstrap-blocks' );
    __( 'Content Grid Item', 'crea-bootstrap-blocks' );
    __( 'A single item within a content grid.', 'crea-bootstrap-blocks' );

    // --- Phase 2, Gruppe M -------------------------------------------------
    __( 'Breadcrumb', 'crea-bootstrap-blocks' );
    __( 'Indicate the current page\'s location within a navigational hierarchy that automatically adds separators via CSS.', 'crea-bootstrap-blocks' );
    __( 'Dividers are automatically added in CSS through ::before and content. They can be changed by modifying a local CSS custom property --bs-breadcrumb-divider, or through the $breadcrumb-divider Sass variable — and $breadcrumb-divider-flipped for its RTL counterpart, if needed. We default to our Sass variable, which is set as a fallback to the custom property. This way, you get a global divider that you can override without recompiling CSS at any time.', 'crea-bootstrap-blocks' );
    __( 'Use Front Page Title', 'crea-bootstrap-blocks' );
    __( 'If checked the title of the front page will be used in place of Home.', 'crea-bootstrap-blocks' );
    __( 'Post Grid', 'crea-bootstrap-blocks' );
    __( 'A grid of posts or pages, queried automatically, with an optional heading, intro and pagination.', 'crea-bootstrap-blocks' );
    __( 'Post Type', 'crea-bootstrap-blocks' );
    __( 'Choose which post type the grid pulls from.', 'crea-bootstrap-blocks' );
    __( 'Posts', 'crea-bootstrap-blocks' );
    __( 'Pages', 'crea-bootstrap-blocks' );
    __( 'Child pages', 'crea-bootstrap-blocks' );
    __( 'Display Posts', 'crea-bootstrap-blocks' );
    __( 'Choose whether selected entries or their children are shown.', 'crea-bootstrap-blocks' );
    __( 'Selected', 'crea-bootstrap-blocks' );
    __( 'Children', 'crea-bootstrap-blocks' );
    __( 'Posts per Page', 'crea-bootstrap-blocks' );
    __( 'How many entries one page of the grid shows.', 'crea-bootstrap-blocks' );
    __( 'Order by', 'crea-bootstrap-blocks' );
    __( 'Choose the field the entries are sorted by.', 'crea-bootstrap-blocks' );
    __( 'Date', 'crea-bootstrap-blocks' );
    __( 'Menu order', 'crea-bootstrap-blocks' );
    __( 'Choose the sort direction.', 'crea-bootstrap-blocks' );
    __( 'Ascending', 'crea-bootstrap-blocks' );
    __( 'Descending', 'crea-bootstrap-blocks' );
    __( 'Include Pagination', 'crea-bootstrap-blocks' );
    __( 'Show page links below the grid.', 'crea-bootstrap-blocks' );
    __( 'Pagination Colour', 'crea-bootstrap-blocks' );
    __( 'Choose the button colour of the page links.', 'crea-bootstrap-blocks' );
    __( 'Card Size', 'crea-bootstrap-blocks' );
    __( 'Choose the size of the entry cards.', 'crea-bootstrap-blocks' );
    __( 'Include Media', 'crea-bootstrap-blocks' );
    __( 'Show the featured image of each entry.', 'crea-bootstrap-blocks' );
    __( 'Media Layout', 'crea-bootstrap-blocks' );
    __( 'Choose whether the image sits inline or as a background.', 'crea-bootstrap-blocks' );
    __( 'Inline', 'crea-bootstrap-blocks' );
    __( 'Include Title', 'crea-bootstrap-blocks' );
    __( 'Show the title of each entry.', 'crea-bootstrap-blocks' );
    __( 'Title Element', 'crea-bootstrap-blocks' );
    __( 'Choose the heading level of the entry title.', 'crea-bootstrap-blocks' );
    __( 'Include Excerpt', 'crea-bootstrap-blocks' );
    __( 'Show the excerpt of each entry.', 'crea-bootstrap-blocks' );
    __( 'Include Permalink', 'crea-bootstrap-blocks' );
    __( 'Make the whole entry a link to the post.', 'crea-bootstrap-blocks' );
    __( 'Item background colour', 'crea-bootstrap-blocks' );
    __( 'Display Item Overlay', 'crea-bootstrap-blocks' );
    __( 'Lay a colour over the background image of each entry.', 'crea-bootstrap-blocks' );
    __( 'Item overlay colour', 'crea-bootstrap-blocks' );
    __( 'Item text alignment', 'crea-bootstrap-blocks' );

    /*
     * --- Editor-UI, Aufgabe 15 (Phase 1: die zwoelf grossen Bloecke) -------
     *
     * Gruppentitel und Hilfetexte, die beim Umbau auf die neue Seitenleiste
     * entstanden sind. Sie stehen hier, weil  PHP liest und keine
     * block.json; `tests/test-i18n-strings-literals.php` haelt beide Seiten
     * gegeneinander und meldet jedes Literal, das nur in der block.json steht.
     */

    // --- banner-item -------------------------------------------------------
    __( 'Width of the grid column that carries the background layer.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. The render path of this block does not read the utility fields, so the value is stored and no class is written.', 'crea-bootstrap-blocks' );
    __( 'Turns the whole block into a link. The anchor is rendered behind the content and covers the block.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. This block has no link title field, so the link overlay renders no title attribute.', 'crea-bootstrap-blocks' );
    __( 'Theme integration', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. It replaces the automatic pattern color with the color below. This plugin renders no pattern itself.', 'crea-bootstrap-blocks' );
    __( 'Color of the background pattern while the override above is on. The value is passed to the filter crea_bootstrap_blocks_background_pattern, the only source of a pattern in this plugin.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. Only the Lightspeed theme evaluates it; there it stops this block from using transitions.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. Only the Lightspeed theme evaluates it; there it stops this block from using parallax.', 'crea-bootstrap-blocks' );
    __( 'Block ID of the surrounding banner. Its layout setting decides which of the two wrappers this item renders.', 'crea-bootstrap-blocks' );

    // --- button ------------------------------------------------------------
    __( 'Display at this breakpoint', 'crea-bootstrap-blocks' );
    __( 'Makes the button a full-width block at this breakpoint. It returns to inline-block at the next breakpoint unless that one is switched on as well.', 'crea-bootstrap-blocks' );
    __( 'Adds one of Bootstrap\'s predefined button classes, for example .btn-primary or .btn-outline-primary.', 'crea-bootstrap-blocks' );
    __( 'Adds .btn-lg or .btn-sm for a larger or smaller button.', 'crea-bootstrap-blocks' );
    __( 'Adds .text-nowrap so the button text is kept on one line.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the href attribute of the button.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the target attribute. New tab writes _blank.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the rel attribute, for example nofollow or noopener.', 'crea-bootstrap-blocks' );
    __( 'Adds Bootstrap\'s modal data attributes with the link URL as the target. Only rendered when a URL is set and the filter crea_bootstrap_blocks_button_modal returns true.', 'crea-bootstrap-blocks' );
    __( 'Shows the icon configured below. The icon is only rendered when an icon class and an icon size are set as well.', 'crea-bootstrap-blocks' );
    __( 'Places the icon before or after the button text.', 'crea-bootstrap-blocks' );
    __( 'Badge', 'crea-bootstrap-blocks' );
    __( 'Adds a Bootstrap badge inside the button, after the text and the icon.', 'crea-bootstrap-blocks' );
    __( 'Content of the badge, usually a number. Rendered through wp_kses_post(); inline markup is allowed, scripts are not.', 'crea-bootstrap-blocks' );
    __( 'Adds .rounded-pill to give the badge a larger border radius.', 'crea-bootstrap-blocks' );
    __( 'Adds a Bootstrap background-color utility class to the badge.', 'crea-bootstrap-blocks' );
    __( 'Adds a Bootstrap text-color utility class to the badge.', 'crea-bootstrap-blocks' );
    __( 'Further classes for the badge, added after the three fields above — for example Bootstrap\'s position utilities.', 'crea-bootstrap-blocks' );
    __( 'Popover', 'crea-bootstrap-blocks' );
    __( 'Wraps the button in a popover container. Bootstrap\'s JavaScript is required for the popover to open.', 'crea-bootstrap-blocks' );
    __( 'Heading of the popover, rendered as the title attribute of the popover container.', 'crea-bootstrap-blocks' );
    __( 'Body text of the popover.', 'crea-bootstrap-blocks' );
    __( 'Side of the button on which the popover opens. Directions are mirrored in right-to-left languages.', 'crea-bootstrap-blocks' );
    __( 'Event that opens the popover. Default is a click; the popover opens on focus in either case.', 'crea-bootstrap-blocks' );
    __( 'Tooltip', 'crea-bootstrap-blocks' );
    __( 'Adds Bootstrap\'s tooltip attributes to the button. Bootstrap\'s JavaScript is required for the tooltip to appear.', 'crea-bootstrap-blocks' );
    __( 'Text shown in the tooltip. It is rendered as the button\'s title attribute.', 'crea-bootstrap-blocks' );
    __( 'Side of the button on which the tooltip appears. Directions are mirrored in right-to-left languages.', 'crea-bootstrap-blocks' );
    __( 'Dropdown', 'crea-bootstrap-blocks' );
    __( 'Turns the button into a dropdown toggle. The child blocks of this button are only rendered inside the dropdown menu — with the dropdown switched off they do not appear at all.', 'crea-bootstrap-blocks' );
    __( 'Adds .dropdown-menu-dark for a dark dropdown menu.', 'crea-bootstrap-blocks' );
    __( 'Controls whether a click inside the menu, outside it, both or neither closes the open dropdown.', 'crea-bootstrap-blocks' );
    __( 'Direction in which the dropdown menu opens. Directions are mirrored in right-to-left languages.', 'crea-bootstrap-blocks' );
    __( 'Adds .dropdown-menu-end to align the menu with the end of the button.', 'crea-bootstrap-blocks' );

    // --- container ---------------------------------------------------------
    __( 'Number of grid columns the background layer spans, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap container class. Fixed sets a maximum width at each breakpoint, Fluid is full width at every breakpoint, and a breakpoint value stays full width until that breakpoint is reached.', 'crea-bootstrap-blocks' );
    __( 'Adds the WordPress width class to the container, for example alignfull.', 'crea-bootstrap-blocks' );

    // --- content-grid-item -------------------------------------------------
    __( 'Aligns the item content from top to bottom inside the card body, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Aligns the item content from left to right inside the card body, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Width of the background layer\'s grid column at this breakpoint.', 'crea-bootstrap-blocks' );
    __( 'Item media', 'crea-bootstrap-blocks' );
    __( 'How the media fills its container: Cover, Contain, or the dimensions set below.', 'crea-bootstrap-blocks' );
    __( 'Maximum height of the media in pixels. Only used with Media fit set to Set dimensions; an empty value falls back to 50.', 'crea-bootstrap-blocks' );
    __( 'Maximum width of the media in pixels. Only used with Media fit set to Set dimensions; an empty value falls back to 100.', 'crea-bootstrap-blocks' );
    __( 'Alignment of the media inside its container. An empty value falls back to Center.', 'crea-bootstrap-blocks' );
    __( 'Stored for compatibility with the previous plugin. This block does not render utility classes.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap background-color utility class.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap text-color utility class.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap border-color utility class.', 'crea-bootstrap-blocks' );
    __( 'Turns the whole grid item into a link. The anchor is rendered behind the content and covers the item.', 'crea-bootstrap-blocks' );
    __( 'Block ID of the surrounding content grid. It supplies the item style.', 'crea-bootstrap-blocks' );
    __( 'Stored copy of the item style. The rendering takes this value from the surrounding content grid instead, through the parent block ID above.', 'crea-bootstrap-blocks' );

    // --- content-grid ------------------------------------------------------
    __( 'Aligns the grid content from top to bottom, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Number of the twelve grid columns the heading and intro occupy, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Aligns the heading column within its row, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Aligns the heading and intro text within their column, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Layout of the items inside this grid.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap container the grid is wrapped in — a fixed width, one of the breakpoint widths, or fluid across the full width.', 'crea-bootstrap-blocks' );
    __( 'Number of item columns in the grid.', 'crea-bootstrap-blocks' );
    __( 'Items', 'crea-bootstrap-blocks' );
    __( 'Shell of each grid item: Card, Full or Flush. Every item reads the value from this block through its parent block ID.', 'crea-bootstrap-blocks' );
    __( 'Preset size of the media area in the grid items. Rendered as a size class on the grid wrapper.', 'crea-bootstrap-blocks' );
    __( 'Stored for compatibility with the previous plugin. Each grid item renders from its own media fields.', 'crea-bootstrap-blocks' );
    __( 'Maximum height of the media in pixels.', 'crea-bootstrap-blocks' );
    __( 'Maximum width of the media in pixels.', 'crea-bootstrap-blocks' );
    __( 'Alignment of the media inside its container.', 'crea-bootstrap-blocks' );
    __( 'Heading and intro', 'crea-bootstrap-blocks' );
    __( 'Turns on the area above the grid. The heading is rendered only while this is on and the heading text below is filled in.', 'crea-bootstrap-blocks' );
    __( 'Heading text shown above the grid. Basic inline HTML is allowed.', 'crea-bootstrap-blocks' );
    __( 'HTML heading level used for the heading, H1 to H6. An empty value falls back to H2.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap text-color utility class applied to the heading.', 'crea-bootstrap-blocks' );
    __( 'Intro text shown below the heading. Basic inline HTML is allowed.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap text-color utility class applied to the intro.', 'crea-bootstrap-blocks' );
    __( 'Lets the pattern color below override the automatic contrast color of the pattern.', 'crea-bootstrap-blocks' );
    __( 'Color of the background pattern. Only the hex value is used.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. Only its Lightspeed theme integration evaluates the value; this plugin renders nothing from it.', 'crea-bootstrap-blocks' );

    // --- content-with-media ------------------------------------------------
    __( 'Settings', 'crea-bootstrap-blocks' );
    __( 'Layout of the media beside the content. The render path reacts to the value full-width only: it moves the media into a full-width background layer from the LG breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. Neither render path reads it in this block; the value is stored only.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. This block has no parent block, and neither render path reads the value.', 'crea-bootstrap-blocks' );

    // --- div ---------------------------------------------------------------
    __( 'Turns the whole div into a link. The anchor is rendered behind the content and covers the block.', 'crea-bootstrap-blocks' );

    // --- media-grid --------------------------------------------------------
    __( 'Aligns the content of this block from top to bottom, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Aligns the content of this block from left to right, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Unit of the height value above.', 'crea-bootstrap-blocks' );
    __( 'Heading and intro at this breakpoint', 'crea-bootstrap-blocks' );
    __( 'Positions the heading and intro within their row, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Width of the background layer\'s grid column from this breakpoint upwards. Without a value the layer fills the whole row.', 'crea-bootstrap-blocks' );
    __( 'Hides this block at this breakpoint and restores it at the next visible one. Hiding also drops this breakpoint\'s alignment and heading classes.', 'crea-bootstrap-blocks' );
    __( 'Grid keeps every item the same width. Masonry lets individual items span two columns.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap container that wraps the grid. Fluid spans the full viewport width.', 'crea-bootstrap-blocks' );
    __( 'How many columns the grid shows on wide screens. Narrower viewports step down on their own, down to a single column below 576 pixels.', 'crea-bootstrap-blocks' );
    __( 'Grid items', 'crea-bootstrap-blocks' );
    __( 'Stored for compatibility. This block does not render the value — neither does the previous plugin.', 'crea-bootstrap-blocks' );
    __( 'Minimum height of every image tile: extra small 20vh, small 40vh, medium 60vh, large 80vh, extra large 100vh. Empty falls back to medium.', 'crea-bootstrap-blocks' );
    __( 'Media', 'crea-bootstrap-blocks' );
    __( 'How the media fills its container. The rendering reads each image\'s own Media fit field, not this one.', 'crea-bootstrap-blocks' );
    __( 'Maximum media height in pixels, used when Media fit is Set dimensions. The rendering reads each image\'s own Media height field, not this one.', 'crea-bootstrap-blocks' );
    __( 'Maximum media width in pixels, used when Media fit is Set dimensions. The rendering reads each image\'s own Media width field, not this one.', 'crea-bootstrap-blocks' );
    __( 'How the media sits inside its container. The rendering reads each image\'s own Media alignment field, not this one.', 'crea-bootstrap-blocks' );
    __( 'Stored for compatibility. This block does not render the value, and the previous plugin has no editor field for it either.', 'crea-bootstrap-blocks' );
    __( 'Images', 'crea-bootstrap-blocks' );
    __( 'Registered image size the pictures are loaded at. The rendering reads each image\'s own Image size field, not this one.', 'crea-bootstrap-blocks' );
    __( 'Where a click on a picture leads. The rendering reads each image\'s own Link destination field, not this one.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the target attribute of the link on every image in this grid. New tab writes target=_blank.', 'crea-bootstrap-blocks' );
    __( 'Cropped thumbnails in the previous plugin\'s editor. Kept as stored data; neither plugin renders it.', 'crea-bootstrap-blocks' );
    __( 'Fixed image height in the previous plugin\'s editor. Kept as stored data; neither plugin renders it.', 'crea-bootstrap-blocks' );
    __( 'Drag handles on the pictures in the previous plugin\'s editor. Kept as stored data; neither plugin renders it.', 'crea-bootstrap-blocks' );
    __( 'Shows a heading above the grid. The heading text below is only rendered while this is on.', 'crea-bootstrap-blocks' );
    __( 'HTML heading level of the heading above the grid.', 'crea-bootstrap-blocks' );
    __( 'Text of the heading above the grid. Basic inline HTML is allowed.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to the heading.', 'crea-bootstrap-blocks' );
    __( 'Turns on the block above the grid. The intro text is rendered as soon as it is filled and either this switch or Show heading is on — a quirk of the previous plugin, kept on purpose.', 'crea-bootstrap-blocks' );
    __( 'Text of the intro above the grid. Basic inline HTML is allowed.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to the intro.', 'crea-bootstrap-blocks' );
    __( 'Background pattern', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin. This plugin hands the value to the pattern filter and renders nothing from it itself.', 'crea-bootstrap-blocks' );
    __( 'Color the previous plugin used for the background pattern. Handed to the pattern filter unchanged; this plugin renders nothing from it.', 'crea-bootstrap-blocks' );

    // --- nav-and-tab -------------------------------------------------------
    __( 'Hides this block at this breakpoint and restores it at the next visible one. While it is hidden, the alignment classes of this breakpoint are dropped as well.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap nav style, added as a class: nav-tabs draws the items as tabs, nav-pills as pills.', 'crea-bootstrap-blocks' );

    // --- post-grid ---------------------------------------------------------
    __( 'Aligns the heading and intro column within its row, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Items at this breakpoint', 'crea-bootstrap-blocks' );
    __( 'Aligns the content in the card body of each entry from top to bottom, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Aligns the content in the card body of each entry from left to right, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Aligns the text in the card body of each entry, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Number of the twelve grid columns the background layer occupies, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Pagination', 'crea-bootstrap-blocks' );
    __( 'Arrangement of the entries. Masonry lets single entries span two grid columns instead of one.', 'crea-bootstrap-blocks' );
    __( 'Bootstrap container class that limits the width of the grid inside the block.', 'crea-bootstrap-blocks' );
    __( 'Number of columns the grid grows to on wide screens. Below 576 px it always shows a single column.', 'crea-bootstrap-blocks' );
    __( 'Shows a heading above the grid. It is only rendered once the heading text below is filled in.', 'crea-bootstrap-blocks' );
    __( 'Text of the heading above the grid.', 'crea-bootstrap-blocks' );
    __( 'Heading level the text above the grid is rendered as.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to the heading above the grid.', 'crea-bootstrap-blocks' );
    __( 'Shows an intro above the grid. Note: while the heading switch is on, a filled-in intro is rendered even with this switch off.', 'crea-bootstrap-blocks' );
    __( 'Text of the intro above the grid.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to the intro above the grid.', 'crea-bootstrap-blocks' );
    __( 'Content', 'crea-bootstrap-blocks' );
    __( 'Item appearance', 'crea-bootstrap-blocks' );
    __( 'Height of the inline media of each entry: Small is 20vh, Medium is 30vh. Large carries no rule of its own and keeps the default of 40vh.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to the title, excerpt and card body of each entry.', 'crea-bootstrap-blocks' );
    __( 'Drawn as a layer above the background image of each entry while the overlay switch above is on. Only the rgb values are used for rendering.', 'crea-bootstrap-blocks' );
    __( 'Kept from the previous plugin, where it makes the background pattern use the color below. A pattern is only rendered when the filter crea_bootstrap_blocks_background_pattern supplies one.', 'crea-bootstrap-blocks' );
    __( 'Color of the background pattern, used while the switch above is on. A pattern is only rendered when the filter crea_bootstrap_blocks_background_pattern supplies one.', 'crea-bootstrap-blocks' );
    __( 'Theme options', 'crea-bootstrap-blocks' );

    // --- row ---------------------------------------------------------------
    __( 'Vertical alignment · align-items-*', 'crea-bootstrap-blocks' );
    __( 'Aligns the columns within the row from top to bottom, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment · justify-content-*', 'crea-bootstrap-blocks' );
    __( 'Aligns the columns within the row from left to right, from this breakpoint upwards.', 'crea-bootstrap-blocks' );
    __( 'Columns per row · row-cols-*', 'crea-bootstrap-blocks' );
    __( 'Number of columns the row lays its children out in, from this breakpoint upwards. In CSS grid mode the number is written to the --bs-columns custom property instead.', 'crea-bootstrap-blocks' );
    __( 'Only takes effect when CSS grid is enabled in the plugin settings and Force flexbox is off.', 'crea-bootstrap-blocks' );
    __( 'Number of rows in the grid, for example 3. It is written to the --bs-rows custom property.', 'crea-bootstrap-blocks' );
    __( 'Numeric value without a unit, written to the --bs-gap custom property. The unit is set below.', 'crea-bootstrap-blocks' );
    __( 'Unit of the value above.', 'crea-bootstrap-blocks' );
    __( 'Numeric value without a unit, written as the row-gap property. The unit is set below.', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-items-sm-*', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment · justify-content-sm-*', 'crea-bootstrap-blocks' );
    __( 'Columns per row · row-cols-sm-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-items-md-*', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment · justify-content-md-*', 'crea-bootstrap-blocks' );
    __( 'Columns per row · row-cols-md-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-items-lg-*', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment · justify-content-lg-*', 'crea-bootstrap-blocks' );
    __( 'Columns per row · row-cols-lg-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-items-xl-*', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment · justify-content-xl-*', 'crea-bootstrap-blocks' );
    __( 'Columns per row · row-cols-xl-*', 'crea-bootstrap-blocks' );
    __( 'Vertical alignment · align-items-xxl-*', 'crea-bootstrap-blocks' );
    __( 'Horizontal alignment · justify-content-xxl-*', 'crea-bootstrap-blocks' );
    __( 'Columns per row · row-cols-xxl-*', 'crea-bootstrap-blocks' );

    // --- strip -------------------------------------------------------------
    __( 'Adds the align class of this value to the block, for example alignfull. The value is written into the class list unchanged.', 'crea-bootstrap-blocks' );
    __( 'Turns on the area above the grid. Once that area is on, the intro paragraph is rendered as soon as the intro text below is filled in, with or without this switch — as in the previous plugin.', 'crea-bootstrap-blocks' );

    /*
     * --- Editor-UI, Aufgabe 15 (Phase 2: die 34 uebrigen Bloecke) ----------
     */

    // --- accordion-item ----------------------------------------------------
    __( 'Hides this block at this breakpoint and restores it at the next visible one.', 'crea-bootstrap-blocks' );
    __( 'Text on the accordion button that opens and closes this item. It is rendered inside the heading element set below and may contain inline markup.', 'crea-bootstrap-blocks' );

    // --- banner ------------------------------------------------------------
    __( 'Arranges the banner items inside this block. Three arrangements are built: grid places the items side by side, stacked puts them below each other, carousel turns them into slides. Any other value renders the wrapper without its items.', 'crea-bootstrap-blocks' );
    __( 'Preset height of the banner: Small is 50vh and Medium is 75vh. Large carries no rule of its own and keeps the default of 100vh. In the grid layout all three double below the sm breakpoint.', 'crea-bootstrap-blocks' );

    // --- button-group ------------------------------------------------------
    __( 'Lays the buttons out in a row (.btn-group) or stacks them vertically (.btn-group-vertical).', 'crea-bootstrap-blocks' );
    __( 'Sizes every button of the group at once through .btn-group-sm or .btn-group-lg, instead of setting a size on each button.', 'crea-bootstrap-blocks' );

    // --- card-body ---------------------------------------------------------
    __( 'Applies a Bootstrap background-color utility class to this block.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to this block.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap border-color utility class to this block.', 'crea-bootstrap-blocks' );

    // --- carousel-item -----------------------------------------------------
    __( 'Per-slide delay in milliseconds (data-bs-interval). The value is stored on the block, but neither this plugin nor the original writes it into the markup.', 'crea-bootstrap-blocks' );

    // --- carousel ----------------------------------------------------------
    __( 'Navigation', 'crea-bootstrap-blocks' );
    __( 'Autoplay', 'crea-bootstrap-blocks' );
    __( 'Appearance', 'crea-bootstrap-blocks' );

    // --- dropdown-item -----------------------------------------------------
    __( 'Content of the item. Rendered through wp_kses_post(); inline markup is allowed, scripts are not.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the href attribute. Only the Link type renders an anchor; the other types render a div.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the title attribute of the anchor.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the rel attribute of the anchor, for example nofollow or noopener.', 'crea-bootstrap-blocks' );

    // --- icon --------------------------------------------------------------
    __( 'Stored alignment for this breakpoint. This block renders no element of its own; it outputs its inner content unchanged.', 'crea-bootstrap-blocks' );
    __( 'Stored hide flag for this breakpoint. This block renders no element of its own; it outputs its inner content unchanged.', 'crea-bootstrap-blocks' );
    __( 'Stored colour of the icon. Kept from the previous plugin, where this block drew the icon itself; here it outputs its inner content unchanged.', 'crea-bootstrap-blocks' );
    __( 'Stored size of the icon. Kept from the previous plugin, where this block drew the icon itself; here it outputs its inner content unchanged.', 'crea-bootstrap-blocks' );
    __( 'Stored name of the Bootstrap icon. Kept from the previous plugin, where this block drew the icon itself; here it outputs its inner content unchanged.', 'crea-bootstrap-blocks' );

    // --- list-group-item ---------------------------------------------------
    __( 'Rendered as the href attribute. With a URL the item is rendered as a link, without one as a div.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the title attribute of the link.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the rel attribute of the link, for example nofollow or noopener.', 'crea-bootstrap-blocks' );

    // --- media-grid-image --------------------------------------------------
    __( 'Source URL of the picked image. The rendering resolves the file from the attachment ID above and does not read this value.', 'crea-bootstrap-blocks' );
    __( 'Width stored by the editor\'s image size control. The rendering takes the width from the attachment file instead.', 'crea-bootstrap-blocks' );
    __( 'Height stored by the editor\'s image size control. The rendering takes the height from the attachment file instead.', 'crea-bootstrap-blocks' );
    __( 'Registered image size the file is loaded at. An empty value falls back to the full size.', 'crea-bootstrap-blocks' );
    __( 'Where a click on the image leads: nowhere, the image file, or the attachment page.', 'crea-bootstrap-blocks' );

    // --- nav-and-tab-item --------------------------------------------------
    __( 'Content of the link. Rendered through wp_kses_post(); inline markup is allowed, scripts are not.', 'crea-bootstrap-blocks' );
    __( 'Rendered as the href attribute. It is only added when a value is set.', 'crea-bootstrap-blocks' );

    // --- toast -------------------------------------------------------------
    __( 'Applies a Bootstrap background-color utility class to the toast.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap text-color utility class to the toast.', 'crea-bootstrap-blocks' );
    __( 'Applies a Bootstrap border-color utility class to the toast.', 'crea-bootstrap-blocks' );

    /*
     * --- Hinweiszeilen der fuenf Leinwandfelder --------------------------
     *
     * Fuenf Felder waren im Alt-Plugin RichText-Knoten in der LEINWAND, in die
     * man direkt hineintippte. Blockstudio kann das ohne eigenen React-Build
     * nicht; sie liegen deshalb nur in der Seitenleiste. Der Auftraggeber hat
     * genau daran vorbeigegriffen — die Zeile sagt jetzt, wo der Text hingehoert.
     */
    __( 'Set the title here — you cannot type it directly into the block.', 'crea-bootstrap-blocks' );
    __( 'Set the text here — you cannot type it directly into the block.', 'crea-bootstrap-blocks' );
}
