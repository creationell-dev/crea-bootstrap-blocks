<?php
/**
 * Background partial — the `creabb-background` construct of the layout blocks.
 *
 * 1:1-Uebersetzung von `blocks/_partials/background.php` des Alt-Plugins.
 * SECHS der 14 Phase-1-Bloecke binden es ein: container, row, column, div,
 * strip und media-grid (`blocks/{container,row,column,div,strip,media-grid}.php`
 * des Originals). Bei `row` ist die Einbindung folgenlos — `row/block.json`
 * fuehrt kein einziges `background_*`-Attribut, das Partial liefert dort also
 * immer den Leerstring.
 *
 * NEUN BEWUSSTE ABWEICHUNGEN, die Liste ist abschliessend:
 *
 *   1. Funktion statt include-und-return. Ohne WordPress testbar, kein
 *      Scope-Durchgriff auf Variablen des Aufrufers.
 *   2. Doppelter Klassensatz (Vertragsregel 2.2): `creabb-background` UND
 *      `areoi-background`, solange `legacy_classes` an ist. Dasselbe fuer die
 *      drei Elementklassen der Schichten.
 *   3. Einfaches Escaping. Die Helfer liefern rohe Strings; escapt wird genau
 *      einmal, hier an der Ausgabestelle.
 *   4. Geprueftes Lesen der Attribute. Das Original liest VIER
 *      Unterschluessel ungeprueft — `background_color['rgb']`,
 *      `background_image['url']`, `background_video['url']` und
 *      `background_overlay['rgb']` — und schreibt bei fehlendem Schluessel
 *      `rgba(, , ,)` bzw. `url()` ins style-Attribut; ein leerer Wert erzeugt
 *      dort dieselbe leere Schicht. Es reicht ausserdem
 *      `$attributes['background_utility']` ungeprueft an `esc_attr()`. Hier
 *      wird jeder dieser Zugriffe vorher geprueft: Ist der Attributwert gar
 *      kein Array — eine Zeichenkette, eine Zahl, ein Objekt — oder fehlt der
 *      Unterschluessel, entsteht KEINE Schicht.
 *
 *      WAS EIN LEERER UNTERSCHLUESSEL BEWIRKT, IST JE SCHICHT VERSCHIEDEN.
 *      Nachgemessen, nicht abgeleitet — dies ist die Datei, die als
 *      Abweichungsregister dient, und eine fruehere Fassung dieses Absatzes
 *      hat es falsch behauptet („fehlt der Unterschluessel, ODER IST ER LEER,
 *      entsteht KEINE Schicht"):
 *
 *        - Bild und Video verlangen einen nicht leeren STRING. `url => ''`
 *          erzeugt keine Schicht. Das Original schriebe dafuer
 *          `background-image:url()` bzw. ein `<source src="" />`.
 *        - Farbe und Overlay verlangen nur, dass `rgb` ein ARRAY ist — und
 *          ein leeres Array IST eines. `background_color => [ 'rgb' => [] ]`
 *          erzeugt hier also sehr wohl eine Schicht, naemlich
 *          `<div class="creabb-background__color …" style="background:
 *          rgba(0, 0, 0,1)">`: die Ersatzwerte von
 *          `crea_bootstrap_blocks_rgba_str()` fuer nicht-numerische Kanaele
 *          (0) und Alpha (1). Das Original erzeugt fuer dieselbe Eingabe
 *          ebenfalls eine Schicht, mit `rgba(, , ,)` und vier
 *          PHP-Warnungen. BEIDE erzeugen eine Schicht; die Abweichung liegt
 *          in ihrem INHALT, nicht in ihrem Vorhandensein.
 *
 *      Die Wache `is_array( $container )` ist dabei nicht doppelt
 *      gemoppelt: `$x['rgb'] ?? null` ist nur fuer SKALARE diagnosefrei; bei
 *      einem gewoehnlichen Objekt wirft derselbe Ausdruck
 *      `Error: Cannot use object of type stdClass as array`, bei einem
 *      `ArrayAccess`-Wert liefert er ein Array und die Schicht entstuende. Auf
 *      gueltige Daten ohne Wirkung.
 *   5. Kein `areoi_is_lightspeed()`-Zweig — das Muster-Partial liefert von
 *      sich aus den Leerstring.
 *   6. Normalisierter Whitespace. Der DOM-Diff aus 06-verifikation.md
 *      normalisiert vor dem Vergleich; Elementstruktur und Klassenreihenfolge
 *      sind unveraendert. Das Original setzt jede der vier Schichten mit
 *      Zeilenumbruch und Tabulatoren zusammen und laesst zwischen ihnen
 *      Leerzeilen stehen (`:30-56`); hier stehen sie ohne Trennzeichen
 *      hintereinander.
 *   7. `! empty()` statt `isset()` + `trim()` bei `background_utility`. Die
 *      einzige Abweichung vom AUFTRAGSTEXT statt vom Original — sie stellt die
 *      Uebereinstimmung mit dem Original gerade HER: Der Wert `"0"` gilt hier
 *      wie dort als NICHT gesetzt, ein reiner Leerzeichenwert hier wie dort
 *      als gesetzt. Sichtbar ist der Unterschied nur an der Farbschicht, die
 *      der Wert unterdrueckt; am Wrapper wirft `class_str()` beide Faelle
 *      ohnehin weg. Siehe unten, zweite Eigenheit.
 *   8. Der Literalwert `Default` als `background_utility`. Das Original
 *      verkettet den Wert ROH in die Wrapperklasse
 *      (`'areoi-background ' . $background_utility`); `Default` ueberlebte
 *      dort als Klassenname. Hier laeuft er durch
 *      `crea_bootstrap_blocks_class_str()` und faellt weg (Vertragsregel 1.3).
 *      Praktisch unerreichbar: Kein Phase-1-Block deklariert das Attribut, und
 *      die Auswahlliste des Originals bietet nur `''` und `bg-*` an. Die
 *      Farbunterdrueckung ist davon NICHT betroffen — sie liest `$utility`,
 *      nicht die fertige Klassenkette.
 *   9. Geprueftes `rgba()`. `crea_bootstrap_blocks_rgba_str()` (Plan 1,
 *      Aufgabe 32a) klemmt die Kanaele auf 0-255 und den Alphawert auf 0-1 und
 *      formatiert ihn mit `%.4F`; das Original verkettet die Rohwerte
 *      (`areoi_get_rgba_str()`, Original `helpers.php:122-125`). Ererbt, aber
 *      HIER zum ersten Mal im Markup
 *      sichtbar — Farb- und Overlayschicht sind die einzige Stelle, an der der
 *      String in den DOM gelangt. Fuer jedes Farbobjekt, das der Editor
 *      erzeugt, ergebnisgleich; auseinander laufen beide Fassungen bei VIER
 *      Eingaben: Kanalwerte ausserhalb 0-255 (geklemmt), nicht-numerische
 *      Werte (0 bzw. Alpha 1), mehr als vier Nachkommastellen im Alphawert
 *      (gerundet) — und, leicht zu uebersehen, NICHT GANZZAHLIGE Kanalwerte
 *      INNERHALB des gueltigen Bereichs: die `(int)`-Wandlung in unserem
 *      `crea_bootstrap_blocks_rgba_str()` macht aus `r: 12.7` eine `12`, das
 *      Original schreibt `12.7`.
 *
 * ZWEI EIGENHEITEN DES ORIGINALS, DIE ERHALTEN BLEIBEN:
 *
 *   - Das Muster wird IMMER angehaengt, auch wenn `background_display` aus ist
 *     (`return $background . $background_pattern;`).
 *   - `background_utility` wird gelesen, obwohl KEINER der 14 Phase-1-Bloecke
 *     das Attribut deklariert. Der Zweig bleibt stehen, weil er im Original
 *     die Farbausgabe unterdrueckt; belegt werden kann er in Phase 1 nicht.
 *     Seine Leerheitspruefung ist deshalb wortgleich die des Originals
 *     (`! empty()`, ohne `trim()`): Nur so gilt `"0"` hier wie dort als NICHT
 *     gesetzt und ein reiner Leerzeichenwert hier wie dort als gesetzt — das
 *     ist Abweichung 7 oben, und sichtbar wird sie ausschliesslich an der
 *     Farbschicht.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'crea_bootstrap_blocks_background_markup' ) ) {
    /**
     * The background construct of one block.
     *
     * @param array<string, mixed> $attributes    Block attributes.
     * @param bool                 $allow_pattern Whether this block allows a background pattern.
     * @return string Ready-to-print HTML. Not escaped again by the caller.
     */
    function crea_bootstrap_blocks_background_markup( array $attributes, bool $allow_pattern = false ): string {
        $pattern = crea_bootstrap_blocks_background_pattern_markup( $attributes, $allow_pattern );

        if ( empty( $attributes['background_display'] ) ) {
            return $pattern;
        }

        // `background_utility` deklariert kein Phase-1-Block; der Zweig bleibt
        // dennoch stehen, weil er im Original die Farbausgabe unterdrueckt.
        $utility = '';

        if ( ! empty( $attributes['background_utility'] ) && is_string( $attributes['background_utility'] ) ) {
            $utility = $attributes['background_utility'];
        }

        $wrapper_class = crea_bootstrap_blocks_class_str(
            [
                crea_bootstrap_blocks_class_pair( 'background' ),
                $utility,
                crea_bootstrap_blocks_background_display_class_str( $attributes, 'block' ),
            ]
        );

        $row_class = crea_bootstrap_blocks_class_str(
            [
                'row',
                $attributes['background_horizontal_align'] ?? '',
            ]
        );

        $col_class = crea_bootstrap_blocks_class_str(
            [
                'col',
                $attributes['background_col_xs'] ?? '',
                $attributes['background_col_sm'] ?? '',
                $attributes['background_col_md'] ?? '',
                $attributes['background_col_lg'] ?? '',
                $attributes['background_col_xl'] ?? '',
                $attributes['background_col_xxl'] ?? '',
            ]
        );

        $markup = '<div class="' . esc_attr( $wrapper_class ) . '">'
            . '<div class="container-fluid" style="padding: 0;">'
            . '<div class="' . esc_attr( $row_class ) . '">'
            . '<div class="' . esc_attr( $col_class ) . '">'
            . crea_bootstrap_blocks_background_layers( $attributes, $utility )
            . '</div></div></div></div>';

        return $markup . $pattern;
    }
}

if ( ! function_exists( 'crea_bootstrap_blocks_background_layers' ) ) {
    /**
     * The four content layers inside the background column.
     *
     * Reihenfolge und Bedingungen woertlich aus
     * `blocks/_partials/background.php:30-56` des Originals:
     *
     *   Farbe    !empty( background_color ) && !$background_utility   :30
     *   Bild     !empty( background_image )                           :38
     *   Video    !empty( background_video )                           :44
     *   Overlay  !empty( background_display_overlay )
     *            && !empty( background_overlay )                      :50
     *
     * Die Farbe verschwindet, sobald eine Utility-Klasse gesetzt ist. Das ist
     * keine Nachlaessigkeit des Originals, sondern der Grund, warum es das
     * Attribut gibt: Die Klasse soll die Farbe ueberschreiben, nicht neben ihr
     * stehen.
     *
     * Gerendert wird ausschliesslich aus dem `rgb`-Schluessel des Farbobjekts
     * (Vertragsregel 1.6). Fehlt er — wie beim Default von `pattern_color` —
     * entsteht keine Schicht, statt `rgba(, , ,)` ins style-Attribut zu
     * schreiben.
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @param string               $utility    Resolved background utility class, `''` when unset.
     * @return string Ready-to-print HTML.
     */
    function crea_bootstrap_blocks_background_layers( array $attributes, string $utility ): string {
        $layers = '';

        $color = $attributes['background_color'] ?? null;

        if ( '' === $utility && is_array( $color ) && is_array( $color['rgb'] ?? null ) ) {
            $layers .= '<div class="'
                . esc_attr( crea_bootstrap_blocks_class_pair( 'background__color' ) )
                . '" style="background: '
                . esc_attr( crea_bootstrap_blocks_rgba_str( $color['rgb'] ) )
                . '"></div>';
        }

        $image = $attributes['background_image'] ?? null;

        if ( is_array( $image ) && is_string( $image['url'] ?? null ) && '' !== $image['url'] ) {
            $layers .= '<div class="'
                . esc_attr( crea_bootstrap_blocks_class_pair( 'background__image' ) )
                . '" style="background-image:url('
                . esc_url( $image['url'] )
                . ')"></div>';
        }

        $video = $attributes['background_video'] ?? null;

        if ( is_array( $video ) && is_string( $video['url'] ?? null ) && '' !== $video['url'] ) {
            $layers .= '<video autoplay loop playsinline muted><source src="'
                . esc_url( $video['url'] )
                . '" /></video>';
        }

        $overlay = $attributes['background_overlay'] ?? null;

        if ( ! empty( $attributes['background_display_overlay'] )
            && is_array( $overlay )
            && is_array( $overlay['rgb'] ?? null )
        ) {
            $layers .= '<div class="'
                . esc_attr( crea_bootstrap_blocks_class_pair( 'background__overlay' ) )
                . '" style="background: '
                . esc_attr( crea_bootstrap_blocks_rgba_str( $overlay['rgb'] ) )
                . '"></div>';
        }

        return $layers;
    }
}
