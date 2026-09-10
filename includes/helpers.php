<?php
/**
 * Shared helpers: logging, defaults and the settings read path.
 *
 * Diese Datei steht als ERSTE in der Ladeliste. Jedes weitere Modul benutzt
 * mindestens den Logger, die meisten auch den Leseweg auf die Einstellungen.
 *
 * Der LESEWEG liegt bewusst hier und nicht in `class-settings.php`: Dort wohnt
 * die Registrierung (Option, Sections, Fields, Sanitizer), und die braucht das
 * Admin-Umfeld. Der Leseweg dagegen laeuft im Frontend bei jedem Seitenaufruf.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Writes a diagnostic line to the PHP error log.
 *
 * Schweigt vollstaendig, solange CREA_BOOTSTRAP_BLOCKS_DEBUG nicht gesetzt ist.
 *
 * @param string $message Message without a prefix.
 */
function crea_bootstrap_blocks_log( string $message ): void {
    if ( ! defined( 'CREA_BOOTSTRAP_BLOCKS_DEBUG' ) || ! CREA_BOOTSTRAP_BLOCKS_DEBUG ) {
        return;
    }

    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( 'CreaBootstrapBlocks: ' . $message );
}

/**
 * The five switches with their default values.
 *
 * Die drei Bootstrap-Schalter sind AUS: Ein Plugin-Update darf auf keiner
 * Bestandsseite ploetzlich ein zweites Bootstrap in den Kopf haengen. Die
 * beiden Legacy-Schalter sind AN: Themes stylen heute gegen `.areoi-element`,
 * und die Bestandsseiten sind noch nicht migriert.
 *
 * @return array<string, bool>
 */
function crea_bootstrap_blocks_default_settings(): array {
    return [
        'bootstrap_css'     => false,
        'bootstrap_js'      => false,
        'bootstrap_icons'   => false,
        'legacy_classes'    => true,
        'legacy_blocks'     => true,

        /*
         * Die Inserter-Vorschau. VOREINGESTELLT AN, wie die beiden
         * Legacy-Schalter: Sie ist eine Bedienhilfe im Editor und beruehrt
         * weder Bestand noch Frontend. Wer sie nicht will, schaltet sie hier
         * oder ueber CREA_BOOTSTRAP_BLOCKS_INSERTER_PREVIEWS ab.
         */
        'inserter_previews' => true,
    ];
}

/**
 * The stored settings, merged over the defaults.
 *
 * @return array<string, mixed>
 */
function crea_bootstrap_blocks_get_settings(): array {
    $stored = get_option( 'crea_bootstrap_blocks_settings', [] );

    if ( ! is_array( $stored ) ) {
        return crea_bootstrap_blocks_default_settings();
    }

    return array_merge( crea_bootstrap_blocks_default_settings(), $stored );
}

/**
 * Reads one setting.
 *
 * @param string $key           Settings key.
 * @param mixed  $default_value Value for an unknown key.
 * @return mixed
 */
function crea_bootstrap_blocks_get_setting( string $key, mixed $default_value = null ): mixed {
    $settings = crea_bootstrap_blocks_get_settings();

    return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default_value;
}

/**
 * Reads one boolean switch, constant first.
 *
 * RANGFOLGE: Die Konstante `CREA_BOOTSTRAP_BLOCKS_<KEY>` schlaegt die Option,
 * die Option schlaegt den Standardwert. Damit laesst sich eine Seite per
 * wp-config.php festnageln, unabhaengig davon, was im Backend steht — genau
 * dafuer sind die Konstanten da, und genau deshalb definiert das Plugin sie
 * nirgends selbst.
 *
 * @param string $key           Settings key, e.g. `bootstrap_css`.
 * @param bool   $default_value Value when neither constant nor option says anything.
 */
function crea_bootstrap_blocks_setting_enabled( string $key, bool $default_value = false ): bool {
    $constant = 'CREA_BOOTSTRAP_BLOCKS_' . strtoupper( $key );

    if ( defined( $constant ) ) {
        return (bool) constant( $constant );
    }

    $settings = crea_bootstrap_blocks_get_settings();

    if ( array_key_exists( $key, $settings ) ) {
        return (bool) $settings[ $key ];
    }

    return $default_value;
}

/*
 * ------------------------------------------------------------------------
 * Renderhelfer
 *
 * 1:1-Uebersetzungen von `helpers.php:16-125` des Originals. Anders als dort
 * liefern sie ROHE Strings: Das Original escapt in der Helferfunktion UND beim
 * Aufrufer, ein Klassenname mit `&` kaeme dadurch als `&amp;amp;` im DOM an.
 * Escapt wird hier genau einmal — am Ausgabepunkt im Blocktemplate.
 * ------------------------------------------------------------------------
 */

/**
 * Joins class names into one raw, space-separated class string.
 *
 * Uebersetzt `areoi_get_class_name_str()`. Uebersprungen werden leere Werte
 * und der Literalwert `Default` — Letzterer ist im Bestand ein gespeicherter
 * Attributwert und darf nie als CSS-Klasse im Markup landen. Nur die exakte
 * Schreibweise faellt weg; `default` bleibt eine gueltige Klasse.
 *
 * @param array<int|string, mixed> $classes Class candidates.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_class_str( array $classes ): string {
    $parts = [];

    foreach ( $classes as $class ) {
        // Arrays, Objekte, null und Boolesche Werte sind keine Klassennamen.
        if ( ! is_string( $class ) && ! is_int( $class ) && ! is_float( $class ) ) {
            continue;
        }

        $class = (string) $class;

        // "0" faellt weg, weil das Original mit `!$class` prueft.
        if ( '' === $class || '0' === $class || 'Default' === $class ) {
            continue;
        }

        $parts[] = $class;
    }

    return trim( implode( ' ', $parts ) );
}

/**
 * The shared `hide_<bp>` cascade behind the two display helpers.
 *
 * Die Kaskade laeuft ueber xs, sm, md, lg, xl, xxl. Ist ein Breakpoint
 * versteckt, kommt `d-[<bp>-]none`; der ERSTE nicht versteckte Breakpoint NACH
 * einem versteckten holt das Element mit `d-[<bp>-]<display>` zurueck. Beim
 * Breakpoint `xs` entfaellt das Infix — `d-none`, nicht `d-xs-none`.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $prefix     Attribute prefix, `hide_` or `background_hide_`.
 * @param string               $display    Display value to restore with, e.g. `block`.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_hide_cascade( array $attributes, string $prefix, string $display ): string {
    // Der Wert kommt in jedem Blocktemplate als Literal ("block", "flex").
    // Der Rueckfall schuetzt trotzdem: die Klasse landet ungeprueft im Markup.
    if ( 1 !== preg_match( '/^[a-z][a-z-]*$/', $display ) ) {
        $display = 'block';
    }

    $classes      = [];
    $prev_display = false;

    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $device ) {
        $infix  = 'xs' === $device ? '' : $device . '-';
        $hidden = ! empty( $attributes[ $prefix . $device ] );

        if ( $hidden ) {
            $classes[]    = 'd-' . $infix . 'none';
            $prev_display = true;
            continue;
        }

        if ( $prev_display ) {
            $classes[]    = 'd-' . $infix . $display;
            $prev_display = false;
        }
    }

    return implode( ' ', $classes );
}

/**
 * The `hide_<bp>` cascade of a block.
 *
 * Uebersetzt `areoi_get_display_class_str()`.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $display    Display value to restore with.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_display_class_str( array $attributes, string $display ): string {
    return crea_bootstrap_blocks_hide_cascade( $attributes, 'hide_', $display );
}

/**
 * The `background_hide_<bp>` cascade of a block background.
 *
 * Uebersetzt `areoi_get_background_display_class_str()`. Gleiche Logik, anderes
 * Attributpraefix — im Original zwei wortgleiche Funktionen.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $display    Display value to restore with.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_background_display_class_str( array $attributes, string $display ): string {
    return crea_bootstrap_blocks_hide_cascade( $attributes, 'background_hide_', $display );
}

/**
 * The `block_<bp>` cascade of a block.
 *
 * Uebersetzt `areoi_get_display_block_class_str()`. Der Helfer sieht der
 * hide_-Kaskade aehnlich, hat aber die umgekehrte Bedeutung und teilt sich
 * deshalb keinen Rumpf mit ihr: Ein GESETZTES `block_<bp>` erzwingt an diesem
 * Breakpoint `d-[<bp>-]block`, und der ERSTE nicht gesetzte Breakpoint DANACH
 * stellt mit `d-[<bp>-]<display>` zurueck. Beim Breakpoint `xs` entfaellt das
 * Infix. `blocks/button.php` ruft die Funktion dreimal auf (Zeilen 18, 97, 103),
 * jedes Mal mit dem Anzeigewert `inline-block`; die Attribute `block_xs` bis
 * `block_xxl` stehen in `blocks/button/block.json`.
 *
 * Abweichung: Das Original liest `$attributes['block_' . $device]` ungeprueft
 * und erzeugt eine PHP-Notice, sobald eines der sechs Attribute fehlt. Hier
 * wird geprueft. Auf gueltige Daten hat das keine Wirkung — ein fehlendes
 * Attribut gilt auch im Original als nicht gesetzt.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param string               $display    Display value to restore with.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_block_display_class_str( array $attributes, string $display ): string {
    // Gleicher Rueckfall wie in crea_bootstrap_blocks_hide_cascade():
    // die Klasse landet ungeprueft im Markup.
    if ( 1 !== preg_match( '/^[a-z][a-z-]*$/', $display ) ) {
        $display = 'block';
    }

    $classes      = [];
    $prev_display = false;

    foreach ( [ 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' ] as $device ) {
        $infix = 'xs' === $device ? '' : $device . '-';

        if ( ! empty( $attributes[ 'block_' . $device ] ) ) {
            $classes[]    = 'd-' . $infix . 'block';
            $prev_display = true;
            continue;
        }

        if ( $prev_display ) {
            $classes[]    = 'd-' . $infix . $display;
            $prev_display = false;
        }
    }

    return implode( ' ', $classes );
}

/**
 * The three utility classes of a block.
 *
 * Uebersetzt `areoi_get_utilities_classes()`. Reihenfolge wie im Original:
 * bg, text, border. `Default` faellt ueber crea_bootstrap_blocks_class_str()
 * weg. Anders als das Original ohne fuehrende und doppelte Leerzeichen.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_utilities_classes( array $attributes ): string {
    return crea_bootstrap_blocks_class_str(
        [
            $attributes['utilities_bg'] ?? '',
            $attributes['utilities_text'] ?? '',
            $attributes['utilities_border'] ?? '',
        ]
    );
}

/**
 * A `block_id` reduced to the part that may leave the plugin.
 *
 * DIE EINE STELLE, AN DER ENTSCHIEDEN WIRD, WAS DURCHKOMMT. Jeder Weg, auf dem
 * ein `block_id` oder ein `parent_id` ins Dokument gelangt, geht hierdurch —
 * die Klasse, der Selektor des erzeugten Stylesheets, `data-bs-target` des
 * Karussells, die abgeleiteten `id` von `accordion-item` und `list-group-item`
 * und `data-bs-parent`.
 *
 * Bis zum 2026-08-31 war das NICHT so: Nur die Klasse ging durch die Pruefung,
 * die drei Bloecke schrieben den Rohwert weiter aus. Fuer `block_id=team-slider`
 * zeigte `data-bs-target` deshalb auf `.block-team-slider`, waehrend die Huelle
 * genau diese Klasse nicht ausgab — Pfeile und Indikatoren fassten ins Leere.
 *
 * @param mixed $block_id Raw `block_id` or `parent_id` attribute value.
 * @return string The safe value, or an empty string when it is unusable.
 */
function crea_bootstrap_blocks_block_id_value( mixed $block_id ): string {
    return \Creationell\BootstrapBlocks\Styles::block_id( $block_id ) ?? '';
}

/**
 * The `block-<kennung>` class an element carries for its generated CSS.
 *
 * Uebersetzt `areoi_format_block_id()`. Die Pruefung wird NICHT wiederholt,
 * sondern an Styles::block_id() delegiert: Nur so ist zugesichert, dass der
 * erzeugte Selektor `.block-<kennung>` und diese Klasse dieselbe Zeichenkette
 * benutzen (Vertragsregel 4). Ein Wert, der aus dem Selektor ausbrechen koennte,
 * liefert gar keine Klasse, statt wie im Original `block-x{display:none}body`
 * ins Markup zu schreiben.
 *
 * @param mixed $block_id Raw `block_id` attribute value.
 * @return string Raw class name, or an empty string when the value is unusable.
 */
function crea_bootstrap_blocks_block_id_class( mixed $block_id ): string {
    $safe = crea_bootstrap_blocks_block_id_value( $block_id );

    return '' === $safe ? '' : 'block-' . $safe;
}

/**
 * The `id` attribute built from the `anchor` attribute.
 *
 * Uebersetzt `areoi_return_id()`. Einziger Helfer, der selbst escapt: Er baut
 * ein fertiges Attribut samt Anfuehrungszeichen, sein Wert kann am
 * Ausgabepunkt nicht mehr nachtraeglich escapt werden.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string `id="…"` or an empty string.
 */
function crea_bootstrap_blocks_anchor_attr( array $attributes ): string {
    $anchor = $attributes['anchor'] ?? null;

    /*
     * ZWEI PRUEFUNGEN, BEIDE AM ORIGINAL GEMESSEN.
     *
     * `empty()` bildet `helpers.php:80-83` des Originals ab und ist
     * ergebnisrelevant: Der Anker "0" erzeugt dort KEIN id-Attribut. Dieser
     * Fall ist erreichbar — "0" ist ein zulaessiger String.
     *
     * `is_string()` stand hier bis zum Abschluss von Block A als
     * `is_scalar()`, mit der Begruendung, ein numerischer Anker erzeuge im
     * Original sehr wohl ein id-Attribut. Fuer den HELFER stimmt das:
     * `areoi_return_id( [ 'anchor' => 5 ] )` liefert gemessen `id="5"`. Fuer
     * den BLOCK stimmt es nicht. `WP_Block_Type::prepare_attributes_for_render()`
     * prueft `anchor` gegen sein Schema `{"type":"string","default":false}`,
     * `rest_validate_string_value_from_schema()` weist jeden Nicht-String ab,
     * der Wert wird verworfen und faellt auf den Default `false` zurueck. Am
     * laufenden Kern (WordPress 7.0.4) nachgemessen: von 'oben', '0', 5, 0,
     * 1.5, true, false und einem Array erreichen genau die beiden Strings die
     * Renderfunktion, alles andere kommt als `false` an. Ein numerischer
     * Anker erreicht die Renderfunktion des Originals also nie, und ein
     * `id="5"` gibt es dort nicht.
     *
     * `is_string()` ist damit die Pruefung, die das Original abbildet — und
     * zugleich die, die `crea_bootstrap_blocks_native_attributes()` weiter
     * unten ohnehin erzwingt. Vorher widersprachen sich die beiden Stellen:
     * Dieselbe Eingabe lieferte hier `id="5"` und dort ''.
     */
    if ( empty( $anchor ) || ! is_string( $anchor ) ) {
        return '';
    }

    return 'id="' . esc_attr( $anchor ) . '"';
}

/**
 * An `rgba()` color value in the exact punctuation of the original.
 *
 * Uebersetzt `areoi_get_rgba_str()`: Leerzeichen nach den ersten beiden
 * Kommas, KEINES vor dem Alphawert — `rgba(13, 110, 253,0.15)`. Themes und
 * der DOM-Diff sehen diesen String unveraendert.
 *
 * Abweichung: Die Werte werden geprueft. Der String landet in einem
 * `style`-Attribut; das Original schreibt ihn ungeprueft. Kanaele werden auf
 * 0–255 geklemmt, der Alphawert auf 0–1, nicht-numerische Werte fallen auf 0
 * bzw. 1. `%.4F` formatiert ohne Locale-Einfluss, die Nullen am Ende fallen
 * weg — 1 bleibt "1", 0.15 bleibt "0.15".
 *
 * @param array<string, mixed> $rgb Color with the keys `r`, `g`, `b` and `a`.
 * @return string Raw CSS color value, not escaped.
 */
function crea_bootstrap_blocks_rgba_str( array $rgb ): string {
    $channel = static function ( mixed $value ): int {
        if ( ! is_numeric( $value ) ) {
            return 0;
        }

        return max( 0, min( 255, (int) $value ) );
    };

    $alpha = is_numeric( $rgb['a'] ?? null ) ? (float) $rgb['a'] : 1.0;
    $alpha = max( 0.0, min( 1.0, $alpha ) );
    $alpha = rtrim( rtrim( sprintf( '%.4F', $alpha ), '0' ), '.' );

    if ( '' === $alpha ) {
        $alpha = '0';
    }

    return 'rgba('
        . $channel( $rgb['r'] ?? null ) . ', '
        . $channel( $rgb['g'] ?? null ) . ', '
        . $channel( $rgb['b'] ?? null ) . ','
        . $alpha . ')';
}

/**
 * The base classes every block element carries.
 *
 * `creabb-element` ist der neue Klassensatz, `areoi-element` der alte. Themes
 * der Bestandsseiten stylen direkt gegen `.areoi-element` — deshalb geben die
 * Bloecke uebergangsweise beide aus, bis der Schalter `legacy_classes` auf
 * der jeweiligen Seite abgeschaltet wird (Vertragsregel 3).
 *
 * Fachlich ist das `crea_bootstrap_blocks_class_pair( 'element' )`, und genau
 * dorthin delegiert die Funktion (Ruling E-12). Den `legacy_classes`-Zweig ein
 * zweites Mal auszuschreiben hiesse, dieselbe Bedingung und dieselbe
 * Paarreihenfolge an zwei Stellen derselben Datei zu pflegen. Name, Signatur
 * und Verhalten bleiben — sie ist seit Plan 1 in Gebrauch.
 *
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_element_classes(): string {
    return crea_bootstrap_blocks_class_pair( 'element' );
}

/**
 * Resolves a tag name from an attribute against an allowlist.
 *
 * Das Original baut den Tagnamen direkt aus `$attributes['type']` und schuetzt
 * ihn nur mit `esc_attr()` (blocks/button.php:66 und :121). `esc_attr()` haelt
 * hier nichts auf: Der Wert steht HINTER der oeffnenden spitzen Klammer, ein
 * `type` von `div onclick=alert(1)` erzeugt also ein Ereignisattribut, und ein
 * `type` von `script` erzeugt ein Skriptelement. Deshalb die Allowlist. Der
 * Rueckfallwert gehoert per Aufrufvertrag selbst auf die Liste.
 *
 * @param string             $tag      Tag name from the attribute.
 * @param array<int, string> $allowed  Allowed tag names, lowercase.
 * @param string             $fallback Tag name used when `$tag` is not allowed.
 */
function crea_bootstrap_blocks_tag_name( string $tag, array $allowed, string $fallback ): string {
    $tag = strtolower( trim( $tag ) );

    return in_array( $tag, $allowed, true ) ? $tag : $fallback;
}

/**
 * The double class set of one suffix.
 *
 * Vertragsregel 2.2: Waehrend der Uebergangsphase gibt jeder Block seine
 * Klassen doppelt aus — `creabb-*` fuer die Zukunft, `areoi-*` fuer die Themes
 * der Bestandsseiten. Die Reihenfolge ist verbindlich: erst die eigene, dann
 * die alte Klasse.
 *
 * Der Differenzlauf aus Block B streicht die creabb-Klassen PRAEFIXWEISE aus
 * unserer Ausgabe. Beide Klassen eines Paars stehen nebeneinander — eine
 * vertauschte Paarreihenfolge sieht er deshalb NICHT. Die einzige Stelle, an
 * der sie falsifizierbar ist, ist `tests/test-class-pairs.php`.
 *
 * @param string $suffix Class suffix without prefix, e.g. `background__color`.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_class_pair( string $suffix ): string {
    $suffix = trim( $suffix );

    if ( '' === $suffix ) {
        return '';
    }

    $classes = [ 'creabb-' . $suffix ];

    if ( crea_bootstrap_blocks_setting_enabled( 'legacy_classes', true ) ) {
        $classes[] = 'areoi-' . $suffix;
    }

    return implode( ' ', $classes );
}

/**
 * The double class sets of several suffixes, pair by pair.
 *
 * @param string ...$suffixes Class suffixes without prefix.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_dual_classes( string ...$suffixes ): string {
    $parts = [];

    foreach ( $suffixes as $suffix ) {
        $pair = crea_bootstrap_blocks_class_pair( $suffix );

        if ( '' !== $pair ) {
            $parts[] = $pair;
        }
    }

    return implode( ' ', $parts );
}

/**
 * Rewrites a stored `areoi-*` class value into the double class set.
 *
 * Einige Attribute speichern eine fertige Klasse statt eines Suffixes —
 * `media-grid.card_size` etwa haelt im Bestand `areoi-card-medium`. Nach
 * Vertragsregel 1.1 bleibt der gespeicherte Wert unangetastet; ausgegeben wird
 * er als Paar. Ein Wert, der weder mit `areoi-` noch mit `creabb-` BEGINNT,
 * ist keine unserer Klassen und kommt unveraendert zurueck — `js-areoi-card`
 * bleibt also stehen.
 *
 * @param string $stored Stored class value.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_dual_class_value( string $stored ): string {
    $stored = trim( $stored );

    foreach ( [ 'areoi-', 'creabb-' ] as $prefix ) {
        if ( str_starts_with( $stored, $prefix ) ) {
            return crea_bootstrap_blocks_class_pair( substr( $stored, strlen( $prefix ) ) );
        }
    }

    return $stored;
}

/**
 * Resolves the native block attributes `anchor`, `className` and `align`.
 *
 * Alle drei kommen nicht aus `blockstudio.attributes`: `anchor` stammt aus
 * `supports.anchor` und wird von Blockstudio als Top-Level-Attribut angehaengt
 * (build.php:2448-2468), `align` aus `supports.align`, `className` ist ein
 * Kernattribut von WordPress. Je nach Speicherstand liegen sie im
 * Attributsatz, unter `$block['attributes']` oder direkt am Blockobjekt. Diese
 * Funktion loest alle drei Stellen auf und liefert einen Attributsatz, in dem
 * `crea_bootstrap_blocks_anchor_attr()` und die Klassenhelfer sie an ihrer
 * erwarteten Stelle finden.
 *
 * Der Attributsatz gewinnt: Was dort steht, wurde gespeichert.
 *
 * Ein `anchor` von `false` — der fehlerhafte Default des Originals in 12 der
 * 14 Bloecke — wird zum Leerstring. Er ist kein Anker, und
 * `crea_bootstrap_blocks_anchor_attr()` erzeugt daraus ohnehin kein Attribut.
 *
 * `align` FOLGT EINER STRENGEREN REGEL als die beiden anderen. Es gibt zwei
 * Lagen:
 *
 *   - `media-grid` deklariert `align` NICHT im Vertrag. Der Wert kommt allein
 *     aus `"supports": { "align": true }`, liegt flach am Block und wird von
 *     der Renderfunktion gelesen (media-grid.php:35). Nach S-4 setzt
 *     `Block::transform()` alle registrierten Attribute auf ihre Defaults
 *     zurueck und merged nur `blockstudio.attributes` darueber — ohne diese
 *     Aufloesung ginge der Wert verloren.
 *   - `container` (Default `""`) und `strip` (Default `"full"`) fuehren
 *     `align` als Vertragsattribut und haben `supports.align: false`. Ein dort
 *     LEER gespeicherter Wert ist eine Aussage, kein Loch.
 *
 * Deshalb wird `align` nur dann in den Blockquellen gesucht, wenn der
 * Schluessel im Attributsatz ganz FEHLT. Bei `anchor` und `className` bleibt es
 * beim ersten nicht leeren String, weil deren Vertragsdefault `false` bzw. gar
 * kein Wert ist und der echte Wert regelmaessig erst am Blockobjekt steht.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @param mixed                $block      Blockstudio's `$block` variable.
 * @return array<string, mixed> The attributes with `anchor`, `className` and `align` resolved.
 */
function crea_bootstrap_blocks_native_attributes( array $attributes, mixed $block = [] ): array {
    $sources = [];

    if ( is_array( $block ) ) {
        if ( isset( $block['attributes'] ) && is_array( $block['attributes'] ) ) {
            $sources[] = $block['attributes'];
        }

        $sources[] = $block;
    }

    foreach ( [ 'anchor', 'className', 'align' ] as $name ) {
        $value = $attributes[ $name ] ?? null;

        // Ein im Attributsatz vorhandenes `align` wurde gespeichert — auch als
        // Leerstring. Nur wenn der Schluessel fehlt, darf die flache Ablage
        // einspringen.
        $search = 'align' !== $name || ! array_key_exists( 'align', $attributes );

        if ( $search ) {
            foreach ( $sources as $source ) {
                if ( is_string( $value ) && '' !== $value ) {
                    break;
                }

                $candidate = $source[ $name ] ?? null;

                if ( is_string( $candidate ) && '' !== $candidate ) {
                    $value = $candidate;
                }
            }
        }

        /*
         * Jeder Nicht-String wird zum Leerstring. Das ist nicht Bequemlichkeit,
         * sondern die Regel des Kerns: `prepare_attributes_for_render()` weist
         * fuer alle drei Attribute jeden Nicht-String ab und setzt den Default
         * (gemessen an WordPress 7.0.4). `crea_bootstrap_blocks_anchor_attr()`
         * prueft aus demselben Grund auf `is_string` — beide Stellen sagen
         * dasselbe, und keine von beiden erfindet ein `id="5"`, das es im
         * Original nicht gibt.
         */
        $attributes[ $name ] = is_string( $value ) ? $value : '';
    }

    return $attributes;
}

/**
 * Whether the CSS grid mode is active.
 *
 * DIE ALT-OPTION WIRD GELESEN, seit dem 2026-09-01 (E-146). Vorher war der
 * Wert hartkodiert `false`, mit der Begruendung, er stehe in allen drei
 * geprueften Projekten auf seinem Default — was stimmt, aber nicht traegt:
 * `wp creabb doctor` blockierte jede Installation mit gesetzter Option hart
 * (Exit 1), und die einzige Abhilfe, die es nannte, war der Filter unten.
 *
 * Genau dorthin durfte niemand geschickt werden. Mit gesetztem Filter steht
 * Grid-Markup auf BEIDEN Seiten des Abnahmevergleichs, die Markuppruefung wird
 * wieder gruen — und die CSS-Haelfte weicht ab, ohne dass es auffiel, weil
 * `css_rules()` den zusammengesetzten Selektor `.block-<id>.grid` nicht las.
 * Der Bearbeiter waere seiner eigenen Empfehlung in einen Zustand gefolgt, den
 * kein Abnahmebein prueft.
 *
 * REIHENFOLGE: Erst die Option, dann der Filter. So kann eine Installation den
 * gespeicherten Zustand uebersteuern, ohne ihn zu verlieren.
 *
 * Der Modus aendert drei Dinge: `row` gibt `grid` statt `row` aus und laesst
 * alle Ausrichtungsklassen weg, `column` schreibt `col-` zu `g-col-` und
 * `offset-` zu `g-start-` um — und der Stylesheet-Generator erzeugt die
 * Grid-Regeln (`Styles::grid_declarations()`).
 */
function crea_bootstrap_blocks_css_grid_enabled(): bool {
    $option = function_exists( 'get_option' )
        ? get_option( 'areoi-customize-options-enable-cssgrid', '' )
        : '';

    // Das Alt-Plugin speichert eine Checkbox als "1" oder als Leerstring. Ein
    // kaputt serialisierter Wert kommt als `false` zurueck (E-113) und gilt
    // damit als „aus" — das ist die schaerfere Richtung.
    $enabled = is_scalar( $option ) && '' !== (string) $option && '0' !== (string) $option;

    /**
     * Filters whether the CSS grid mode is active.
     *
     * @param bool $enabled Value of the legacy option `areoi-customize-options-enable-cssgrid`.
     */
    return (bool) apply_filters( 'crea_bootstrap_blocks_css_grid', $enabled );
}

/**
 * Rewrites Bootstrap grid classes for the CSS grid mode.
 *
 * Woertlich aus `column.php:48-49`: eine reine Zeichenkettenersetzung ueber den
 * fertigen Klassenstring. Sie trifft auch `col-lg-4` und `offset-lg-1`, weil
 * das Suchmuster nur den Praefix nennt.
 *
 * @param string $class_str Raw class string.
 * @return string Raw class string, not escaped.
 */
function crea_bootstrap_blocks_grid_class_str( string $class_str ): string {
    return str_replace( [ 'col-', 'offset-' ], [ 'g-col-', 'g-start-' ], $class_str );
}

/**
 * The attributes of the block with a given `block_id`.
 *
 * Uebersetzt `areoi_get_parent_block()` (helpers.php:361-371). `media-grid-image`
 * liest daraus die Item-Darstellung und das Linkziel seines Rasters.
 *
 * ZWEI BEWUSSTE ABWEICHUNGEN:
 *
 *   1. Beide Ablageformen. Das Original kennt nur die flache Form; nach der
 *      Migration liegt der Wert im Blockstudio-Container. Liegt beides vor,
 *      gewinnt der Container — er ist die Form, die der Editor schreibt.
 *      Nach S-4 und E-100 unverzichtbar.
 *   2. Memoisiert je parent_id. Ein Raster mit zwoelf Bildern parst den Post im
 *      Original zwoelfmal. Aendert am Ergebnis nichts.
 *
 * ES WAREN EINMAL DREI. Die dritte hiess „rekursiv ueber innerBlocks" und ist
 * am 2026-09-01 mit E-148 gefallen — die Suche geht wie im Original NUR ueber
 * die oberste Ebene.
 *
 * DIE BESCHRAENKUNG IST ABSICHT, kein Versehen. Das Original
 * (`areoi_get_parent_block()`, helpers.php:361-371) durchsucht ausschliesslich
 * die oberste Ebene; liegt der Elternblock tiefer, findet es ihn nicht und der
 * Kindblock faellt auf seinen Vorgabewert zurueck (`grid` bzw. `card`). Wer das
 * repariert, macht den Nachbau BESSER als das Original — und genau das verbietet
 * Vertragsebene 3 (E-41, E-77): Eine migrierte Seite mit verschachteltem Raster
 * saehe danach anders aus als vorher.
 *
 * GEMESSEN, BEVOR ENTSCHIEDEN WURDE. Mit verschachteltem Elternblock
 * divergierten `banner-item` in 268 von 268 Faellen, `content-grid-item` in
 * 280 von 280 und `media-grid-image` in 34 von 84; mit flachem Elternblock in
 * keinem einzigen. Die Faelle stehen als Umgebung `[… VERSCHACHTELT]` in
 * `cbb_matrix_environments()` und halten fest, dass die Rekursion nicht
 * zurueckkommt.
 *
 * Und die Begruendung, mit der der Plan sie einst vorschrieb — „das ist keine
 * Verhaltensaenderung im Bestand (dort steht media-grid auf oberster Ebene)" —
 * ist messbar falsch: Ueber alle drei Dumps liegt KEINES der sieben Raster auf
 * oberster Ebene, 0 von 7.
 *
 * @param mixed $parent_id Block ID of the parent block.
 * @param bool  $reset     Clears the memo; for the test suites only.
 * @return array<string, mixed> The parent's attributes, or an empty array.
 */
function crea_bootstrap_blocks_parent_block_attributes( mixed $parent_id, bool $reset = false ): array {
    static $cache = [];

    if ( $reset ) {
        $cache = [];
    }

    if ( ! is_string( $parent_id ) || '' === $parent_id ) {
        return [];
    }

    if ( isset( $cache[ $parent_id ] ) ) {
        return $cache[ $parent_id ];
    }

    $post = get_post();

    // KEIN isset() auf post_content: WP_Post deklariert die Property als
    // string, sie ist also nie undefiniert — PHPStan meldet die Pruefung als
    // tot. Die is_string()-Pruefung bleibt, weil `treatPhpDocTypesAsCertain`
    // in phpstan.neon.dist auf false steht und ein Post aus einem Filter
    // theoretisch etwas anderes tragen kann.
    if ( ! is_object( $post ) || ! is_string( $post->post_content ) ) {
        $cache[ $parent_id ] = [];

        return [];
    }

    $found = crea_bootstrap_blocks_find_block_attributes( parse_blocks( $post->post_content ), $parent_id );

    $cache[ $parent_id ] = $found;

    return $found;
}

/**
 * Walks a parsed block tree and returns the attributes of one block.
 *
 * Der Schluesseltyp ist ABSICHTLICH `array-key` und nicht `int`: `parse_blocks()`
 * ist im WordPress-Stub als `array<int|string, …>` typisiert, und diese
 * Funktion braucht die Einschraenkung nicht — sie laeuft mit `foreach` ueber
 * die Werte und prueft jeden einzeln mit `is_array()`. Ein engerer Typ waere
 * eine Behauptung ueber den Aufrufer, die hier niemand einloest.
 *
 * @param array<array-key, mixed> $blocks    Parsed blocks.
 * @param string                  $parent_id Block ID to look for.
 * @return array<string, mixed> The block's attributes, or an empty array.
 */
function crea_bootstrap_blocks_find_block_attributes( array $blocks, string $parent_id ): array {
    foreach ( $blocks as $block ) {
        if ( ! is_array( $block ) ) {
            continue;
        }

        $attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
        $inner = is_array( $attrs['blockstudio']['attributes'] ?? null )
            ? $attrs['blockstudio']['attributes']
            : [];

        $resolved = array_merge( $attrs, $inner );
        unset( $resolved['blockstudio'] );

        if ( isset( $resolved['block_id'] ) && $resolved['block_id'] === $parent_id ) {
            return $resolved;
        }

        /*
         * HIER STAND EINMAL EIN ABSTIEG IN `innerBlocks` — gefallen mit E-148
         * am 2026-09-01.
         *
         * Das Original steigt nicht ab (`areoi_get_parent_block()`,
         * helpers.php:361-371). Wer es hier tut, findet einen Elternblock, den
         * das Original nicht findet, und rendert einen anderen Zweig: Aus dem
         * Rueckfall `grid`/`card` wird der echte Elternwert. Der Nachbau waere
         * damit BESSER — und genau das verbietet Vertragsebene 3.
         *
         * Die Beschraenkung ist von `cbb_matrix_environments()` bewacht: Die
         * Umgebungen `[… VERSCHACHTELT]` fahren genau diesen Baum durch beide
         * Seiten und muessen ohne Abweichung durchlaufen.
         */
    }

    return [];
}

/**
 * The heading and intro block that precedes a grid.
 *
 * Uebersetzt `areoi_get_prepend_content()` (helpers.php:374-434). Zwei
 * Eigenheiten des Originals werden BEWUSST nicht repariert:
 *
 *   1. Die Einleitung haengt NICHT an `prepend_display_intro`. Das Original
 *      prueft dort zweimal `!empty( $attributes['prepend_intro'] )` — offenbar
 *      ein Tippfehler, aber ein ergebnisbestimmender: Eine Reparatur liesse
 *      Einleitungen verschwinden, die heute auf Bestandsseiten stehen.
 *   2. Der Rahmen entsteht nur, wenn mindestens einer der beiden
 *      Anzeigeschalter an ist. Im Bestand stehen beide auf false, es entsteht
 *      also gar kein Markup.
 *
 * Abweichung: `prepend_heading_level` wird mit `?? 'h2'` gelesen. Das Original
 * greift ungeprueft zu und erzeugt eine Notice, sobald das Attribut fehlt; die
 * Allowlist danach ist dieselbe.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string Escaped markup, ready for output.
 */
function crea_bootstrap_blocks_prepend_content( array $attributes ): string {
    if ( empty( $attributes['prepend_display_heading'] ) && empty( $attributes['prepend_display_intro'] ) ) {
        return '';
    }

    $row_class = crea_bootstrap_blocks_class_str(
        [
            'row',
            'position-relative',
            empty( $attributes['hide_xs'] ) ? ( $attributes['prepend_horizontal_align_xs'] ?? '' ) : '',
            empty( $attributes['hide_sm'] ) ? ( $attributes['prepend_horizontal_align_sm'] ?? '' ) : '',
            empty( $attributes['hide_md'] ) ? ( $attributes['prepend_horizontal_align_md'] ?? '' ) : '',
            empty( $attributes['hide_lg'] ) ? ( $attributes['prepend_horizontal_align_lg'] ?? '' ) : '',
            empty( $attributes['hide_xl'] ) ? ( $attributes['prepend_horizontal_align_xl'] ?? '' ) : '',
            empty( $attributes['hide_xxl'] ) ? ( $attributes['prepend_horizontal_align_xxl'] ?? '' ) : '',
        ]
    );

    $col_class = crea_bootstrap_blocks_class_str(
        [
            'col',
            empty( $attributes['hide_xs'] ) ? ( $attributes['prepend_col_xs'] ?? '' ) : '',
            empty( $attributes['hide_sm'] ) ? ( $attributes['prepend_col_sm'] ?? '' ) : '',
            empty( $attributes['hide_md'] ) ? ( $attributes['prepend_col_md'] ?? '' ) : '',
            empty( $attributes['hide_lg'] ) ? ( $attributes['prepend_col_lg'] ?? '' ) : '',
            empty( $attributes['hide_xl'] ) ? ( $attributes['prepend_col_xl'] ?? '' ) : '',
            empty( $attributes['hide_xxl'] ) ? ( $attributes['prepend_col_xxl'] ?? '' ) : '',
            empty( $attributes['hide_xs'] ) ? ( $attributes['prepend_text_align_xs'] ?? '' ) : '',
            empty( $attributes['hide_sm'] ) ? ( $attributes['prepend_text_align_sm'] ?? '' ) : '',
            empty( $attributes['hide_md'] ) ? ( $attributes['prepend_text_align_md'] ?? '' ) : '',
            empty( $attributes['hide_lg'] ) ? ( $attributes['prepend_text_align_lg'] ?? '' ) : '',
            empty( $attributes['hide_xl'] ) ? ( $attributes['prepend_text_align_xl'] ?? '' ) : '',
            empty( $attributes['hide_xxl'] ) ? ( $attributes['prepend_text_align_xxl'] ?? '' ) : '',
        ]
    );

    $heading_level = $attributes['prepend_heading_level'] ?? 'h2';

    if ( ! is_string( $heading_level ) || ! in_array( $heading_level, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
        $heading_level = 'h2';
    }

    $heading_color = is_string( $attributes['prepend_heading_color'] ?? null ) ? $attributes['prepend_heading_color'] : '';
    $intro_color   = is_string( $attributes['prepend_intro_color'] ?? null ) ? $attributes['prepend_intro_color'] : '';

    $heading = '';

    if ( ! empty( $attributes['prepend_display_heading'] ) && ! empty( $attributes['prepend_heading'] ) ) {
        $heading = '<' . $heading_level . ' class="' . esc_attr( $heading_color ) . '">'
            . wp_kses_post( (string) $attributes['prepend_heading'] )
            . '</' . $heading_level . '>';
    }

    $intro = '';

    // Ohne Pruefung auf prepend_display_intro — siehe Docblock, Eigenheit 1.
    if ( ! empty( $attributes['prepend_intro'] ) ) {
        $intro = '<p class="' . esc_attr( $intro_color ) . '">'
            . wp_kses_post( (string) $attributes['prepend_intro'] )
            . '</p>';
    }

    return '<div class="' . esc_attr( $row_class ) . '">'
        . '<div class="' . esc_attr( $col_class ) . '">'
        . $heading
        . $intro
        . '</div></div>';
}

/**
 * Builds the breadcrumb trail of the current post.
 *
 * 1:1-Uebersetzung von `areoi_generate_breadcrumbs()` und
 * `areoi_generate_breadcrumbs_parent()` (`helpers.php:127` und `:161` des
 * Alt-Plugins). Er gehoert hierher und nicht ins Template: Das Template baut
 * Markup, dieser Helfer liest den Bestand.
 *
 * DIE REIHENFOLGE ENTSTEHT IN ZWEI SCHRITTEN, und das ist der Grund fuer das
 * `array_reverse()` in der Mitte: Die Elternkette wird von unten nach oben
 * gesammelt, die Startseite kommt zuletzt dazu — und erst die Umkehrung bringt
 * beides in Leserichtung. Der aktuelle Beitrag wird DANACH angehaengt.
 *
 * DIE STARTSEITE ERSCHEINT NUR, WENN DER BEITRAG EINEN ELTERNTEIL HAT. Das ist
 * Bestand, kein Versehen: `if ( $post->post_parent )` umschliesst im Original
 * beides, die Elternkette UND den Startseiteneintrag.
 *
 * IST DER BEITRAG SELBST DIE STARTSEITE, wird nichts angehaengt; stattdessen
 * wird der erste Eintrag aktiv gesetzt. Ohne Elternteil ist die Liste an dieser
 * Stelle leer — der Zugriff auf `[0]` erzeugt dann im Original eine Meldung und
 * einen Eintrag aus dem Nichts. Der Nachbau tut dasselbe, damit das Markup
 * gleich bleibt.
 *
 * DER RUECKGABETYP IST ABSICHTLICH LOCKER. Der `else`-Zweig unten schreibt in
 * `[0]` NUR den Schluessel `active` — steht der Beitrag ohne Elternteil auf der
 * Startseite, ist die Liste an dieser Stelle leer und es entsteht ein Eintrag
 * aus dem Nichts, dem `permalink` und `label` fehlen. Das ist Bestand
 * (`helpers.php:154` des Alt-Plugins); das Template prueft deshalb jeden
 * Schluessel einzeln ab. Eine engere Signatur waere eine Zusage, die dieser
 * Code nicht haelt.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return list<array<string, mixed>>
 */
function crea_bootstrap_blocks_breadcrumbs( array $attributes ): array {
    $post = get_post();

    if ( ! is_object( $post ) ) {
        return [];
    }

    $breadcrumbs = [];

    if ( ! empty( $post->post_parent ) ) {
        $breadcrumbs = crea_bootstrap_blocks_breadcrumbs_parent( $breadcrumbs, (int) $post->post_parent );

        $title         = __( 'Home', 'crea-bootstrap-blocks' );
        $front_page_id = (int) get_option( 'page_on_front' );

        if ( $front_page_id > 0 && ! empty( $attributes['is_front_page'] ) ) {
            $title = get_the_title( $front_page_id );
        }

        $breadcrumbs[] = [
            'permalink' => home_url(),
            'label'     => (string) $title,
            'active'    => false,
        ];
    }

    $breadcrumbs = array_reverse( $breadcrumbs );

    if ( get_permalink( $post->ID ) !== home_url() ) {
        $breadcrumbs[] = [
            'permalink' => (string) get_the_permalink( $post->ID ),
            'label'     => get_the_title( $post->ID ),
            'active'    => true,
        ];
    } else {
        $breadcrumbs[0]['active'] = true;
    }

    return $breadcrumbs;
}

/**
 * Walks up the parent chain, newest first.
 *
 * @param list<array{permalink: string, label: string, active: bool}> $breadcrumbs Collected so far.
 * @param int                                                         $parent_id   Parent post ID.
 * @return list<array{permalink: string, label: string, active: bool}>
 */
function crea_bootstrap_blocks_breadcrumbs_parent( array $breadcrumbs, int $parent_id ): array {
    $parent = get_post( $parent_id );

    if ( ! is_object( $parent ) ) {
        return $breadcrumbs;
    }

    if ( get_permalink( $parent->ID ) !== home_url() ) {
        $breadcrumbs[] = [
            'permalink' => (string) get_the_permalink( $parent->ID ),
            'label'     => get_the_title( $parent->ID ),
            'active'    => false,
        ];
    }

    if ( ! empty( $parent->post_parent ) ) {
        return crea_bootstrap_blocks_breadcrumbs_parent( $breadcrumbs, (int) $parent->post_parent );
    }

    return $breadcrumbs;
}

/**
 * Whether a block name belongs to this plugin.
 *
 * DIE ENTSCHEIDUNG STEHT HIER, NICHT IN DEN FILTERN. Vier globale
 * Blockstudio-Filter treffen sie — Metadaten, Feldbeschriftungen, Vertrag und
 * der InnerBlocks-Wrapper —, und alle vier feuern auch fuer Bloecke fremder
 * Plugins und des Themes. Solange die Antwort viermal einzeln ausformuliert
 * war, konnte keine Suite sie fahren: Sie steckte in Callbacks, die ohne
 * WordPress nicht ladbar sind. Als reine Funktion ist sie messbar — und der
 * Renderrahmen der Differenzsuiten stellt damit dieselbe Frage wie der
 * Betrieb, statt sie nachzubauen (E-135).
 *
 * Beide Namensraeume, nicht nur `creabb/`: Die 47 Aliasbloecke unter
 * `blocks-legacy/` rendern mit demselben Template und brauchen dieselbe
 * Behandlung.
 *
 * @param string $name Block name, e.g. `creabb/container`.
 * @return bool True when the block is one of ours.
 */
function crea_bootstrap_blocks_is_own_block( string $name ): bool {
    return str_starts_with( $name, 'creabb/' ) || str_starts_with( $name, 'areoi/' );
}

/**
 * The inner block markup a template should print — tag in the editor, markup on the frontend.
 *
 * WARUM ZWEI WEGE, UND NICHT EINER
 *
 * Im EDITOR mountet Gutenberg die Kindbloecke nur, wenn im gerenderten Markup
 * Blockstudios Komponente `<InnerBlocks />` steht. Ohne sie liegen sie zwar im
 * Datenmodell, haben aber keinen DOM-Knoten im Canvas: kein Einstellungs-Panel,
 * keine Werkzeugleiste, keine Einfuegestelle. Am 2026-09-02 an der laufenden
 * Instanz gemessen — 13 creabb-Bloecke im Datenmodell von Beitrag 118, drei mit
 * DOM-Knoten, genau die drei auf oberster Ebene.
 *
 * AUF DEM FRONTEND DARF DER TAG NICHT ERSCHEINEN, und das ist keine
 * Geschmacksfrage. `Block::replace_components()` steigt dort unveraendert aus,
 * solange die Ausgabe KEINES der vier Merkmale `<InnerBlocks`, `<RichText`,
 * `<MediaPlaceholder` und `useBlockProps` traegt (block.php:528-535). Steht der
 * Tag darin, laeuft die Ausgabe weiter — und dann prueft
 * `content_has_component_cleanup_attribute()` (block.php:650, Muster :754-767)
 * das FERTIGE Markup samt aller Kindbloecke gegen siebzehn Attributnamen,
 * darunter das blosse `tag`. Das Muster lautet
 * `/\s(?:…|tag|…)(?:\s*=\s*|(?=\s|\/?>))/i` — es trifft damit das deutsche Wort
 * „Tag" mit nachfolgendem Leerzeichen. Schlaegt es an, geht die gesamte Ausgabe
 * durch einen `DOMDocument`-Umlauf, der Umlaute zu Entities macht, `<div>` aus
 * `<p>` herauszieht und `viewBox` kleinschreibt.
 *
 * Gemessen im echten Bestand einer Installation: 23 Zeilen mit `wp:areoi/`
 * enthalten „ Tag " — darunter Seiten aus dem Pilotsatz. Der Tag im
 * Frontendmarkup waere also kein theoretisches Risiko, sondern eine Aenderung
 * am Markup echter Kundenseiten.
 *
 * Der Frontendweg bleibt damit Zeichen fuer Zeichen der bisherige, und die 47
 * Differenzsuiten pruefen ihn unveraendert weiter — ihr Rahmen WEIGERT sich,
 * eine Ausgabe mit `<InnerBlocks` anzunehmen, und ist damit die Wache dagegen,
 * dass der Tag je auf dem Frontendpfad landet.
 *
 * `$isEditor` stellt Blockstudio jedem PHP-Template bereit (block.php:2186);
 * gesetzt wird es fuer den Renderlauf, den der Editor selbst anfordert.
 *
 * DIE SCHRANKEN HAENGEN AM TAG, NICHT AN DER block.json. Blockstudios
 * InnerBlocks-Komponente liest `allowedBlocks` und `template` als
 * Tag-Attribute und reicht sie an `useInnerBlocksProps()` durch
 * (inner-blocks.tsx:29-90). Beide werden mit `JSON.parse()` gelesen, und ein
 * Fehlschlag faellt dort STILL auf `[]` zurueck (get-attributes.ts:14-31) —
 * bei `allowedBlocks` hiesse das „gar nichts einfuegbar". Deshalb wird hier
 * mit `wp_json_encode()` erzeugt und mit `esc_attr()` eingesetzt, und deshalb
 * misst `tests/test-editor-inner-blocks.php` das Ergebnis zurueck durch
 * Entity-Dekodierung und `json_decode()`.
 *
 * @param string $inner_blocks The rendered child markup, as Blockstudio handed it in.
 * @param bool   $is_editor    The template's `$isEditor`.
 * @param string $block_name   The block's name, e.g. `creabb/row` — from `$block['name']`.
 * @return string The tag in the editor, the markup otherwise.
 */
function crea_bootstrap_blocks_inner_blocks( string $inner_blocks, bool $is_editor, string $block_name = '' ): string {
    if ( ! crea_bootstrap_blocks_is_editor_render( $is_editor ) ) {
        return $inner_blocks;
    }

    $schranken = crea_bootstrap_blocks_inner_blocks_attrs( $block_name );

    return '' === $schranken ? '<InnerBlocks />' : '<InnerBlocks ' . $schranken . ' />';
}

/**
 * Whether this really is a render for the block editor.
 *
 * `$isEditor` ALLEIN GENUEGT NICHT, UND DAS IST DER TEURE TEIL DIESER DATEI.
 *
 * Blockstudio leitet den Schalter aus einem QUERY-PARAMETER ab —
 * `$is_editor = isset( $_GET['blockstudioMode'] ) && 'editor' === $_GET['blockstudioMode'];`
 * (block.php:1926-1928), ohne Nonce und ohne Capability. Dieselbe Datei gatet
 * ihren Devtools-Zweig zwoelfhundert Zeilen weiter ausdruecklich mit
 * `current_user_can( 'edit_posts' )`.
 *
 * Ohne eine eigene Wache haette damit JEDER anonyme Besucher den Editorzweig
 * unserer Templates ausloesen koennen. An der laufenden Instanz gemessen, ohne
 * Cookie: `?blockstudioMode=editor` lieferte HTTP 200 mit sieben literalen
 * `<InnerBlocks`-Tags im oeffentlichen Quelltext und 16 kB weniger Inhalt —
 * die Kindbloecke fehlten auf der Seite.
 *
 * Kein Abnahmebein haette das gesehen: Weder Bein 2 noch Bein 3 haengt je
 * einen Query-String an eine Adresse. Es ist derselbe Fehlertyp wie E-122 und
 * E-153 — ein gruener Lauf ueber einer Seite, die er so nie abgerufen hat.
 *
 * Die Wache ist dieselbe wie Blockstudios eigene: `edit_posts`. Wer im Editor
 * arbeitet, hat sie; ein Besucher nicht. Sie ist bewusst NICHT enger gefasst
 * (etwa auf `REST_REQUEST`): Blockstudio rendert die Editorvorschau ueber
 * mehrere Wege, und eine Wache, die einen davon verfehlt, macht den Editor
 * kaputt statt sicher.
 *
 * OHNE WordPress gilt der Schalter unveraendert — der Renderrahmen der
 * Testsuiten laedt keine Capability-Maschinerie, und dort ist die Frage
 * gegenstandslos: Es gibt keinen Besucher, den man schuetzen muesste.
 *
 * @param bool $is_editor The template's `$isEditor`.
 * @return bool True when the tag may be printed.
 */
function crea_bootstrap_blocks_is_editor_render( bool $is_editor ): bool {
    if ( ! $is_editor ) {
        return false;
    }

    if ( ! function_exists( 'current_user_can' ) ) {
        return true;
    }

    return (bool) current_user_can( 'edit_posts' );
}

/**
 * The `allowedBlocks` and `template` attributes for one block, ready for the tag.
 *
 * DIE DATEN KOMMEN AUS DEM ORIGINAL, nicht aus einer Meinung:
 * `includes/inner-blocks.json` wird von `bin/extract-inner-blocks.php` aus den
 * `ALLOWED_BLOCKS`- und `BLOCKS_TEMPLATE`-Konstanten des Alt-Plugins erzeugt.
 * Dort steht nur, was das Original auch BENUTZT — elf Bloecke deklarieren eine
 * der Konstanten und übergeben sie nie, `container` zum Beispiel eine leere
 * Liste. Wer die Deklaration liest statt der Verwendung, sperrt ausgerechnet
 * den Layoutblock fuer jeden Inhalt.
 *
 * BEIDE NAMENSRAEUME BEI `allowedBlocks`, NUR DER EIGENE BEIM `template`.
 *
 * `areoi/row` bekommt `["creabb/column","areoi/column",…]`: Seine bestehenden
 * Kinder heissen `areoi/*`, und was neu eingefuegt wird, soll `creabb/*` sein.
 * Die `areoi/*`-Bloecke tragen `inserter: false` und werden ohnehin nicht
 * angeboten — die zusaetzlichen Eintraege kosten nichts und halten den
 * Uebergangszustand bedienbar.
 *
 * BEIM `template` GEHT DAS NICHT, und das ist am `parent` der Kinder gemessen:
 * `creabb/banner-item` fuehrt `parent: ["creabb/banner"]`, der Aliasblock
 * dagegen `["creabb/banner","areoi/banner"]`. Ein kanonisches Kind laesst sich
 * also gar nicht in einen `areoi/*`-Elternblock saeen — Gutenberg lehnt es ab,
 * und der Block bliebe leer. Die Vorlage eines Aliasblocks fuehrt deshalb
 * seinen EIGENEN Namensraum. Die erste Fassung dieser Funktion (E-188) sah es
 * anders und war falsch; elf Bloecke waren betroffen.
 *
 * @param string $block_name The block's name.
 * @return string The attribute string, or '' when the block does not restrict.
 */
function crea_bootstrap_blocks_inner_blocks_attrs( string $block_name ): string {
    static $tabelle = null;

    if ( null === $tabelle ) {
        $datei = __DIR__ . '/inner-blocks.json';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Gelesen wird eine mitgelieferte Datei des Plugins, kein entfernter URL.
        $roh = is_file( $datei ) ? json_decode( (string) file_get_contents( $datei ), true ) : null;

        /*
         * FEHLT ODER KAPUTT HEISST „KEINE SCHRANKE", NICHT „NICHTS ERLAUBT".
         * Die Datei ist erzeugt und eingecheckt; faehrt jemand ohne sie, sollen
         * die Bloecke sich verhalten wie vor dem 2026-09-02 — offen. Ein
         * leeres `allowedBlocks` waere die gefaehrlichere Vorgabe: Es sperrte
         * jeden Container zu, und zwar still.
         */
        $tabelle = is_array( $roh ) ? $roh : [];
    }

    if ( ! str_contains( $block_name, '/' ) ) {
        return '';
    }

    [ $namensraum, $slug ] = explode( '/', $block_name, 2 );

    if ( ! isset( $tabelle[ $slug ] ) || ! is_array( $tabelle[ $slug ] ) ) {
        return '';
    }

    $eintrag = $tabelle[ $slug ];
    $teile   = [];

    if ( isset( $eintrag['allowedBlocks'] ) && is_array( $eintrag['allowedBlocks'] ) ) {
        $erlaubt = $eintrag['allowedBlocks'];

        if ( 'areoi' === $namensraum ) {
            foreach ( $eintrag['allowedBlocks'] as $name ) {
                if ( is_string( $name ) && str_starts_with( $name, 'creabb/' ) ) {
                    $erlaubt[] = 'areoi/' . substr( $name, 7 );
                }
            }
        }

        $teile[] = 'allowedBlocks="' . esc_attr( (string) wp_json_encode( array_values( $erlaubt ) ) ) . '"';
    }

    /*
     * DIE ALIASBLOECKE SAEEN NICHTS (2026-09-03, vom Auftraggeber). Sie sind
     * per Definition die, die ALTEN Inhalt tragen: Ein `areoi/*`-Block steht
     * nur dort, wo das Alt-Plugin ihn geschrieben hat. Ihn beim Oeffnen einer
     * alten Seite mit einer Grundstruktur zu fuellen, aendert Kundendaten,
     * ohne dass jemand etwas eingegeben hat — gemessen am 2026-09-03: ein
     * leerer Block wuchs allein durch Oeffnen und Speichern von 1 auf bis zu
     * 8 Blockbegrenzer.
     *
     * `allowedBlocks` BLEIBT und wird oben weiter ausgegeben: Das ist eine
     * Schranke, keine Saat. Sie kostet keine Daten und haelt den
     * Uebergangszustand bedienbar.
     *
     * NEU EINGEFUEGTE `creabb/*`-BLOECKE BEHALTEN IHRE VORLAGE — dort ist sie
     * eine Bequemlichkeit und kein Eingriff, weil der Block in dem Moment
     * entsteht und noch niemandem gehoert.
     */
    if ( 'creabb' === $namensraum && isset( $eintrag['template'] ) && is_array( $eintrag['template'] ) ) {
        $teile[] = 'template="' . esc_attr(
            (string) wp_json_encode(
                crea_bootstrap_blocks_template_objects(
                    crea_bootstrap_blocks_template_ohne_fuelltext( $eintrag['template'] )
                )
            )
        ) . '"';
    }

    return implode( ' ', $teile );
}

/**
 * Removes the seeded filler text from a template.
 *
 * DIE ENTSCHEIDUNG DAHINTER (2026-09-03, vom Auftraggeber). Am 2026-09-03 ist
 * gemessen worden, dass Gutenberg die Vorlage auch in einen BEREITS
 * GESPEICHERTEN leeren Block saet: Ein `creabb/tabs` wuchs beim blossen
 * Oeffnen und Speichern von 1 auf 8 Blockbegrenzer, und im Bestand standen
 * danach „Tab 1 Content" und „Tab 2 Content" — englischer Fuelltext auf einer
 * deutschen Kundenseite, den niemand geschrieben hat.
 *
 * Das Alt-Plugin tat dasselbe, der Nachbau war insofern treu. Die Entscheidung
 * lautet trotzdem: Der TEXT geht weg, die STRUKTUR bleibt. Von den 17 Vorlagen
 * betrifft das genau zwei — `banner` und `tabs`.
 *
 * ENTFERNT WIRD NUR `content`, NICHT `text`. Alle vier Fuelltexte des Originals
 * stehen unter `content`: dreimal „Enter Heading" an `core/heading`, einmal
 * „Enter description" und zweimal „Tab N Content" an `core/paragraph`. Ein
 * leeres `core/heading` zeigt Gutenbergs eigenen Platzhalter — genau das ist
 * gewollt.
 *
 * Die beiden Reiterbeschriftungen „Tab 1" und „Tab 2" stehen dagegen unter
 * `text` an `creabb/nav-and-tab-item` und BLEIBEN. Sie sind die Beschriftung
 * eines Strukturelements, nicht sein Inhalt; ohne sie waere der Reiter
 * unbeschriftet und im Editor kaum anklickbar. Einen Bedienmangel zu
 * verhindern ist nicht dasselbe wie Fuelltext zu dulden.
 *
 * DIE FORM DER VORLAGE BLEIBT. Der Attributslot wird nicht entfernt, wenn er
 * durch das Streichen leer wird — `crea_bootstrap_blocks_template_objects()`
 * unterscheidet „Slot da, aber leer" von „kein Slot", und das Original tut es
 * auch (E-148).
 *
 * @param array<int, mixed> $template Template entries.
 * @return array<int, mixed> The same entries without seeded content.
 */
function crea_bootstrap_blocks_template_ohne_fuelltext( array $template ): array {
    $out = [];

    foreach ( $template as $eintrag ) {
        if ( ! is_array( $eintrag ) ) {
            $out[] = $eintrag;
            continue;
        }

        $neu = $eintrag;

        if ( isset( $neu[1] ) && is_array( $neu[1] ) ) {
            unset( $neu[1]['content'] );
        }

        if ( isset( $neu[2] ) && is_array( $neu[2] ) ) {
            $neu[2] = crea_bootstrap_blocks_template_ohne_fuelltext( $neu[2] );
        }

        $out[] = $neu;
    }

    return $out;
}


/**
 * Forces the attribute slot of every template entry to be a JSON object.
 *
 * EIN LEERES PHP-ARRAY WIRD ZU `[]`, NICHT ZU `{}`. Ein Templateeintrag lautet
 * `[ name, attribute, kinder ]`; die Attribute sind ein Objekt, und fast alle
 * Eintraege des Originals haben dort `{}`. Ohne diesen Umweg stuende im Tag
 * `["creabb/column",[]]` — JavaScript wuerde daraus ein Array statt eines
 * Objekts machen. Es faellt heute nicht auf, weil `getDefaultsFromTemplate()`
 * beides spreizen kann; es ist trotzdem die falsche Form, und die naechste
 * Fassung von Gutenberg muss sie nicht dulden.
 *
 * @param array<int, mixed> $template Template entries.
 * @return array<int, mixed> The same entries with object-shaped attributes.
 */
function crea_bootstrap_blocks_template_objects( array $template ): array {
    $out = [];

    foreach ( $template as $eintrag ) {
        if ( ! is_array( $eintrag ) || ! isset( $eintrag[0] ) ) {
            $out[] = $eintrag;
            continue;
        }

        /*
         * DIE FORM DES ORIGINALS BLEIBT. Drei Eintraege von `content-grid`
         * lauten dort `[ 'areoi/content-grid-item' ]` — ohne Attributslot.
         * Ein `{}` dazuzuerfinden waere semantisch dasselbe und trotzdem
         * falsch: Der Nachbau soll die Form des Originals tragen, nicht die
         * vollstaendigere (E-148). Umgewandelt wird nur, was schon da ist.
         */
        $neu = [ $eintrag[0] ];

        if ( array_key_exists( 1, $eintrag ) ) {
            $neu[] = (object) ( is_array( $eintrag[1] ) ? $eintrag[1] : [] );
        }

        if ( isset( $eintrag[2] ) && is_array( $eintrag[2] ) ) {
            $neu[] = crea_bootstrap_blocks_template_objects( $eintrag[2] );
        }

        $out[] = $neu;
    }

    return $out;
}
