<?php
/**
 * Link overlay partial — the full-area link of `column` and `div`.
 *
 * 1:1-Uebersetzung von `blocks/column.php:54-72` des Alt-Plugins. Der Anker
 * steht im erzeugten Markup HINTER dem Inhalt und deckt den Block per CSS
 * vollflaechig ab.
 *
 * ATTRIBUTREIHENFOLGE IST VERBINDLICH: class, href, title, rel, target.
 *
 * DIE empty()-SEMANTIK DES ORIGINALS BLEIBT. Eine URL "0" erzeugt keinen
 * Anker, ein url_title von "0" kein title-Attribut. Das ist kein Versehen,
 * sondern die gemessene Ausgabe; Vertragsregel 2.1 verlangt aequivalentes
 * Markup.
 *
 * AUSGEGEBEN WIRD `creabb-full-link` PLUS `areoi-full-link`, NICHT
 * `areoi-has-url`: Diese Klasse entsteht im Original an ganz anderer Stelle —
 * am UMSCHLIESSENDEN Element von `content-grid-item` und `post-grid`, an der
 * Medienverlinkung von `media-grid-image`, in der Klassenliste des
 * `button`-Blocks und durchgehend in der Lightspeed-Integration. An keinem der
 * sechs `areoi-full-link`-Anker des Originals steht sie.
 *
 * DAS ORIGINAL BAUT DEN ANKER MEHRZEILIG, DIESES PARTIAL EINZEILIG
 * (Ruling E-25). `column.php:56-58` setzt ihn aus dem String
 * "\r\n\t\t\t<a class=\"areoi-full-link\"\r\n\t\t" zusammen und haengt
 * " href=…" daran — gemessen an der Rohausgabe: ein CRLF plus drei Tabs VOR
 * dem `<a`, ein zweites CRLF plus zwei Tabs INNERHALB des Tags, zwischen dem
 * class-Attribut und `href`. Wir geben eine Zeile aus.
 *
 * HTML-aequivalent, Vertragsregel 2.3 ist gewahrt — aber JEDER Byte-Vergleich
 * muss normalisieren, und zwar auch INNERHALB von Tags, sonst melden alle
 * sechs `areoi-full-link`-Anker des Originals eine Differenz. Der
 * Differenzrahmen aus Block B tut das nachweislich
 * (`cbb_markup_collapse()`); der DOM-Diff aus Phase 8 muss es ebenfalls tun.
 * Wer die Normalisierung dort weglaesst, bekommt sechs Falschmeldungen und
 * keinen echten Befund.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'crea_bootstrap_blocks_link_overlay' ) ) {
    /**
     * The full-area link of one block.
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @return string Ready-to-print HTML. Not escaped again by the caller.
     */
    function crea_bootstrap_blocks_link_overlay( array $attributes ): string {
        $url = $attributes['url'] ?? null;

        if ( empty( $url ) || ! is_string( $url ) ) {
            return '';
        }

        $markup = '<a class="' . esc_attr( crea_bootstrap_blocks_class_pair( 'full-link' ) ) . '"'
            . ' href="' . esc_url( $url ) . '"';

        /*
         * DIE REIHENFOLGE DIESER DREI EINTRAEGE IST DIE AUSGABEREIHENFOLGE im
         * Anker und damit Vertrag (`column.php:56-71`). Sie steht mehrzeilig,
         * weil `WordPress.Arrays.ArrayDeclarationSpacing` die einzeilige Form
         * eines Arrays mit expliziten Schluesseln zurueckweist.
         */
        $optional = [
            'title'  => 'url_title',
            'rel'    => 'rel',
            'target' => 'linkTarget',
        ];

        foreach ( $optional as $attribute => $name ) {
            $value = $attributes[ $name ] ?? null;

            if ( empty( $value ) || ! is_string( $value ) ) {
                continue;
            }

            $markup .= ' ' . $attribute . '="' . esc_attr( $value ) . '"';
        }

        return $markup . '></a>';
    }
}
