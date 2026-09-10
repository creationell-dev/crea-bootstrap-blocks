<?php
/**
 * Block registration and the Blockstudio filters of CreaBootstrapBlocks.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * --- Uebersetzung der Blockmetadaten -------------------------------------
 *
 * Blockstudio registriert Bloecke ueber `new WP_Block_Type()` plus
 * `register_block_type()` statt ueber `register_block_type_from_metadata()`
 * (Spezifikation S-7). WordPress' automatische block.json-Uebersetzung laeuft
 * damit nie an — der Schluessel `textdomain` in der block.json ist wirkungslos.
 * Titel und Beschreibung muessen deshalb hier uebersetzt werden.
 *
 * Nachgeschlagen wird mit `translate()`, nicht mit `__()`: Der Text kommt aus
 * der block.json und ist zur Laufzeit eine Variable. `__( $var, … )` wuerde
 * dasselbe tun, aber `wp i18n make-pot` wuerde daran haengenbleiben und nichts
 * extrahieren. Die Literale stehen deshalb in `includes/i18n-strings.php`.
 *
 * PRIORITAET 20. Derselbe Filter traegt auf Prioritaet 10 die Vertragsmechanik
 * (Attributtyp und Default aus `blocks/_contracts/<block>.json`). Beide
 * Callbacks sind eigenstaendig, mutieren dasselbe Objekt und geben es zurueck —
 * keiner ersetzt oder klont es, deshalb ueberschreiben sie einander nicht.
 *
 * Der Filter ist GLOBAL: Er feuert auch fuer Blockstudio-Bloecke fremder
 * Plugins und des Themes. Angefasst wird deshalb nur, was in unseren beiden
 * Namensraeumen liegt.
 */

/**
 * Translates block title and description of our own blocks.
 *
 * Der Parameter ist `mixed`, nicht `WP_Block_Type|array`, und das ist keine
 * Nachlaessigkeit: Dies ist ein GLOBALER Filter. Was ankommt, entscheidet der
 * Aufrufer, nicht dieser Code — genau deshalb gibt es den letzten Zweig, der
 * unveraendert zurueckgibt. Eine enge Signatur zu deklarieren und im Rumpf
 * gegen Werte ausserhalb dieser Signatur zu verteidigen, waere ein Widerspruch;
 * verengt wird deshalb hier, mit `instanceof` und `is_array()`.
 *
 * @param mixed $block The registered block type.
 * @return mixed The same block, translated.
 */
function crea_bootstrap_blocks_translate_block_meta( $block ) {
    if ( $block instanceof WP_Block_Type ) {
        $name = is_string( $block->name ) ? $block->name : '';
    } elseif ( is_array( $block ) ) {
        $name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
    } else {
        return $block;
    }

    if ( ! crea_bootstrap_blocks_is_own_block( $name ) ) {
        return $block;
    }

    foreach ( [ 'title', 'description' ] as $key ) {
        $value = $block instanceof WP_Block_Type ? $block->$key : ( $block[ $key ] ?? '' );

        if ( ! is_string( $value ) || '' === $value ) {
            continue;
        }

        /*
         * WPCS beanstandet hier zwei Dinge, und beide gehen am Entwurf vorbei:
         * `LowLevelTranslationFunction` haelt `translate()` fuer zu tief, und
         * `NonSingularStringLiteralText` verlangt ein String-Literal als Text.
         * Ein Literal KANN hier nicht stehen — der Text kommt zur Laufzeit aus
         * der block.json. Genau dafuer ist `translate()` die richtige Funktion,
         * und genau deshalb liegen die Literale in
         * `includes/i18n-strings.php`, wo `make-pot` sie findet. Ein
         * `__( $value, … )` waere dieselbe Laufzeitwirkung, wuerde aber die
         * Extraktion vortaeuschen.
         */
        // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText -- Begruendung im Block darueber.
        $translated = translate( $value, 'crea-bootstrap-blocks' );

        if ( $block instanceof WP_Block_Type ) {
            $block->$key = $translated;
        } else {
            $block[ $key ] = $translated;
        }
    }

    return $block;
}

add_filter( 'blockstudio/blocks/meta', 'crea_bootstrap_blocks_translate_block_meta', 20, 1 );

/*
 * --- Uebersetzung der Feld-Beschriftungen ---------------------------------
 *
 * `blockstudio/blocks/attributes` bekommt je Aufruf EINE Felddefinition — nicht
 * das erzeugte WP-Attribut. Uebersetzt werden deshalb nur die Stellen, die im
 * Editor sichtbar sind: label, help, description, die Labels der
 * Options-Eintraege — und, seit 2026-09-07, `value` bei `type === 'message'`.
 *
 * NICHT angefasst wird `options[*]['value']`. Das ist der Wert, der im
 * Blockkommentar landet — bei `vertical_align_xs` zum Beispiel der Literalwert
 * "Default", der nach Vertragsregel 1.3 ein Datenwert ist. Wuerde er uebersetzt,
 * waere das stiller Datenverlust auf jeder Bestandsseite.
 *
 * `message.value` IST DER GEGENTEILIGE FALL, deshalb kein Widerspruch dazu:
 * `message` ist der einzige Feldtyp ohne eigenes Attribut — Blockstudio traegt
 * ihn gar nicht erst in `blockstudio.attributes` ein und rendert `value`
 * ausschliesslich als Anzeigetext (siehe der `message`-Zweig unten). Es gibt
 * nichts, was gespeichert werden koennte; die Unterscheidung faellt am
 * FELDTYP, nicht am Schluesselnamen `value`.
 *
 * Ebenfalls nicht angefasst: `type` (das ist der Blockstudio-Feldtyp, ein
 * Ueberschreiben zerstoert das Feld, siehe Spezifikation S-3) und `default`
 * (den setzt die Vertragsmechanik auf Prioritaet 10).
 *
 * REKURSION IN `tabs` UND `group` — KORRIGIERT AM 2026-09-07.
 *
 * Bis dahin stand hier „REKURSION GENAU IN `tabs`, UND NUR DORT": Die eigene
 * Rekursion solle `group` bewusst nicht anfassen, weil
 * `Build::filter_attributes()` (build.php:483) selbst in die Kinder von
 * `group` und `repeater` absteigt und den Filter dort erneut aufruft — eine
 * eigene Rekursion dorthin schickte dieselben Strings angeblich nur ein
 * zweites Mal durch `translate()`.
 *
 * Das war nur die halbe Messung. So absteigend erreicht Blockstudio nur eine
 * Gruppe auf OBERSTER Ebene. INNERHALB eines Reiters steigt es in `group` gar
 * nicht erst ein — genau wie in `tabs` selbst. Unser Filter hielt es bis dahin
 * exakt andersherum: er stieg in `tabs` ab, aber nicht in `group`. Eine Gruppe
 * INNERHALB eines Reiters bekam dadurch ihre Kinder nie uebersetzt, und ihr
 * eigener Titel (`title`) ueberhaupt nicht — beides lautlos, bei gruenem Lauf.
 *
 * Die Rekursion steigt deshalb jetzt IMMER in `group` ab, unabhaengig von der
 * Ebene — sie kennt den Unterschied zwischen oberster Ebene und Reiter gar
 * nicht. Fuer eine Gruppe auf oberster Ebene bleibt die alte Sorge trotzdem
 * folgenlos: `Build::filter_attributes()` rekursiert dort in dasselbe
 * Kinder-Array, das unser Filter per Referenz schon gefuellt hat — der zweite
 * Aufruf bekommt also bereits die deutsche Zeichenkette zu sehen, findet
 * dafuer keinen `msgid` im Katalog und gibt sie unveraendert zurueck.
 *
 * In `tabs` steigt Blockstudio nach wie vor NICHT ab, und dieser Zweig war
 * deshalb bis zum 2026-09-02 komplett unuebersetzt: 1320 der 1729
 * Felddefinitionen liegen dort. Aufgefallen ist es erst, als die Tabs im
 * Editor ueberhaupt sichtbar wurden — davor verdeckte ein zweiter Fehler den
 * ersten.
 *
 * Die Luecke hatte die perfekte Tarnung: Die Kataloge sind VOLLSTAENDIG, jedes
 * Literal steht in `includes/i18n-strings.php`, und
 * `tests/test-i18n-strings-literals.php` sammelt die Tab-Titel ausdruecklich
 * rekursiv ein und verlangt sie im Katalog. Uebersetzt wurde trotzdem nichts —
 * gefehlt hat nicht der Text, sondern seine Zustellung.
 *
 * WAS DIE REKURSION NICHT ANFASST, und warum das der wichtigere Satz ist:
 * `options[*]['value']`. INNERHALB der Tabs heissen 120 Optionswerte
 * „Default" — der Literalwert, den `vertical_align_*` und Geschwister nach
 * Vertragsregel 1.3 im Blockkommentar ablegen. Wuerde er uebersetzt, stuende
 * auf jeder deutschen Instanz „Standard" in den Bestandsdaten, und die
 * Renderhelfer, die auf `'Default' === $value` pruefen, liefen ins Leere. Das
 * waere stiller Datenverlust auf Produktivseiten. Uebersetzt wird deshalb
 * ausschliesslich, was der Editor ANZEIGT.
 */

/**
 * Translates the editor-facing labels of one Blockstudio field definition.
 *
 * @param array<string, mixed> $attribute One field definition.
 * @param array<string, mixed> $block     The block.json data of the owning block.
 * @return array<string, mixed> The field definition with translated labels.
 */
function crea_bootstrap_blocks_translate_block_attribute( array $attribute, array $block = [] ): array {
    $name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

    if ( ! crea_bootstrap_blocks_is_own_block( $name ) ) {
        return $attribute;
    }

    return crea_bootstrap_blocks_translate_field( $attribute );
}

/**
 * Translates one field definition and, for `tabs`, everything inside it.
 *
 * Getrennt vom Filter, damit die Rekursion eine reine Funktion ist: Der Filter
 * entscheidet ueber den Namensraum, diese Funktion ueber den Text. Ohne die
 * Trennung liesse sich die Rekursion nur ueber den Filter fahren und damit nur
 * zusammen mit einem vollstaendigen Blockobjekt (E-135).
 *
 * @param array<string, mixed> $attribute One field definition.
 * @return array<string, mixed> The field definition with translated labels.
 */
function crea_bootstrap_blocks_translate_field( array $attribute ): array {
    foreach ( [ 'label', 'help', 'description' ] as $key ) {
        $value = $attribute[ $key ] ?? null;

        if ( is_string( $value ) && '' !== $value ) {
            // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText -- Der Text kommt zur Laufzeit aus der block.json; die Literale liegen in includes/i18n-strings.php. Begruendung ausfuehrlich beim Meta-Filter oben.
            $attribute[ $key ] = translate( $value, 'crea-bootstrap-blocks' );
        }
    }

    /*
     * `message` ist der einzige Anzeigetyp ohne Attribut: Blockstudio
     * ueberspringt ihn im Attributbau (kein Eintrag in
     * `blockstudio.attributes`) und rendert `value` ausschliesslich als Text
     * im Editor. Der wird deshalb uebersetzt — im Unterschied zu
     * `options[*]['value']` weiter unten, das ein GESPEICHERTER Vertragswert
     * ist und nach E-179 nie uebersetzt werden darf. Die Unterscheidung ist
     * der FELDTYP, nicht der Schluesselname `value`.
     *
     * Der Zweig steht bewusst hier, VOR dem `group`-Zweig: Der uebersetzt
     * seine Kinder per erneutem Aufruf dieser Funktion und kehrt danach
     * ueber ein eigenes `return $attribute;` zurueck. Ein message-Feld
     * INNERHALB einer Gruppe durchlaeuft also denselben Funktionskopf noch
     * einmal — dieser Zweig muss deshalb vor jedem fruehen Rueckgabepunkt
     * liegen, sonst wuerde er fuer ein solches Kind nie erreicht.
     */
    if ( 'message' === ( $attribute['type'] ?? '' ) ) {
        $value = $attribute['value'] ?? null;

        if ( is_string( $value ) && '' !== $value ) {
            // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText -- Wie oben.
            $attribute['value'] = translate( $value, 'crea-bootstrap-blocks' );
        }
    }

    if ( isset( $attribute['options'] ) && is_array( $attribute['options'] ) ) {
        foreach ( $attribute['options'] as $index => $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }

            $label = $option['label'] ?? null;

            if ( is_string( $label ) && '' !== $label ) {
                // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText -- Wie oben. Uebersetzt wird ausschliesslich das LABEL; options[*]['value'] bleibt der gespeicherte Vertragswert.
                $attribute['options'][ $index ]['label'] = translate( $label, 'crea-bootstrap-blocks' );
            }
        }
    }

    /*
     * GRUPPEN. Blockstudio ruft den Filter fuer die Kinder einer Gruppe auf der
     * obersten Ebene selbst auf, aber nicht fuer eine Gruppe INNERHALB eines
     * Reiters — dort steigt es gar nicht erst ein. Wir steigen deshalb selbst ab.
     * Ein doppelter translate()-Aufruf auf denselben String ist folgenlos: Die
     * zweite Uebersetzung findet den bereits uebersetzten Text nicht im Katalog
     * und gibt ihn unveraendert zurueck.
     */
    if ( 'group' === ( $attribute['type'] ?? '' ) ) {
        $title = $attribute['title'] ?? null;

        if ( is_string( $title ) && '' !== $title ) {
            // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText -- Wie oben; das Literal steht in includes/i18n-strings.php.
            $attribute['title'] = translate( $title, 'crea-bootstrap-blocks' );
        }

        if ( is_array( $attribute['attributes'] ?? null ) ) {
            foreach ( $attribute['attributes'] as $kind_index => $kind ) {
                if ( is_array( $kind ) ) {
                    $attribute['attributes'][ $kind_index ] = crea_bootstrap_blocks_translate_field( $kind );
                }
            }
        }

        return $attribute;
    }

    if ( 'tabs' !== ( $attribute['type'] ?? '' ) || ! is_array( $attribute['tabs'] ?? null ) ) {
        return $attribute;
    }

    foreach ( $attribute['tabs'] as $index => $tab ) {
        if ( ! is_array( $tab ) ) {
            continue;
        }

        $title = $tab['title'] ?? null;

        if ( is_string( $title ) && '' !== $title ) {
            // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction, WordPress.WP.I18n.NonSingularStringLiteralText -- Wie oben. Der Tab-Titel steht als Literal in includes/i18n-strings.php.
            $attribute['tabs'][ $index ]['title'] = translate( $title, 'crea-bootstrap-blocks' );
        }

        if ( ! is_array( $tab['attributes'] ?? null ) ) {
            continue;
        }

        foreach ( $tab['attributes'] as $kind_index => $kind ) {
            if ( is_array( $kind ) ) {
                $attribute['tabs'][ $index ]['attributes'][ $kind_index ] = crea_bootstrap_blocks_translate_field( $kind );
            }
        }
    }

    return $attribute;
}

add_filter( 'blockstudio/blocks/attributes', 'crea_bootstrap_blocks_translate_block_attribute', 20, 2 );

/*
 * --- Kategorien und Blockregistrierung ------------------------------------
 *
 * Blockstudio uebernimmt Discovery, Registrierung und das Asset-Handling der
 * Bloecke. Es gibt deshalb kein eigenes `register_block_type()` und kein
 * eigenes Enqueue je Block — `AREOI_Styles::traverse_block_styles()` entfaellt
 * ersatzlos.
 *
 * PRIORITAET 10 auf `init`: Blockstudios eigene Discovery haengt auf
 * PHP_INT_MAX - 1, die WP-Registrierung auf PHP_INT_MAX (register.php:43).
 * `Build::init()` muss davor laufen, die eigenen Feldtypen (Prioritaet 5) noch
 * davor — sonst kennt die Discovery sie beim Bauen der Attribute nicht.
 */

/**
 * Absolute path of the block directory, without a trailing slash.
 */
function crea_bootstrap_blocks_blocks_dir(): string {
    return rtrim( CREA_BOOTSTRAP_BLOCKS_DIR, '/' ) . '/blocks';
}

/**
 * Absolute path of the legacy alias directory, without a trailing slash.
 *
 * Das Verzeichnis liegt AUSSERHALB von `blocks/`. `Blockstudio\Build::init()`
 * durchsucht das uebergebene Verzeichnis rekursiv; laege der Alias unter
 * `blocks/_alias/`, wuerde er zusammen mit den echten Bloecken registriert
 * und liesse sich nicht mehr abschalten. Erzeugt wird der Inhalt von
 * `bin/build-aliases.php`.
 */
function crea_bootstrap_blocks_legacy_blocks_dir(): string {
    return rtrim( CREA_BOOTSTRAP_BLOCKS_DIR, '/' ) . '/blocks-legacy';
}

/**
 * Adds the plugin's three block categories.
 *
 * Vorangestellt, nicht angehaengt: Die Bloecke dieses Plugins sind auf den
 * betroffenen Seiten das Layoutwerkzeug, nicht eine Beigabe. Ein zweiter
 * Durchlauf legt nichts erneut an — der Filter kann mehrfach feuern.
 *
 * @param array<int, array<string, mixed>> $categories Registered categories.
 * @return array<int, array<string, mixed>>
 */
function crea_bootstrap_blocks_block_categories( array $categories ): array {
    $existing = [];
    foreach ( $categories as $category ) {
        if ( isset( $category['slug'] ) ) {
            $existing[] = (string) $category['slug'];
        }
    }

    $own = [];

    foreach (
        [
            'creabb-layout'     => __( 'Bootstrap Layout', 'crea-bootstrap-blocks' ),
            'creabb-components' => __( 'Bootstrap Components', 'crea-bootstrap-blocks' ),
            'creabb-strips'     => __( 'Bootstrap Strips', 'crea-bootstrap-blocks' ),
        ] as $slug => $title
    ) {
        if ( in_array( $slug, $existing, true ) ) {
            continue;
        }

        $own[] = [
            'slug'  => $slug,
            'title' => $title,
            'icon'  => null,
        ];
    }

    return array_merge( $own, $categories );
}

add_filter( 'block_categories_all', 'crea_bootstrap_blocks_block_categories', 10, 1 );

/**
 * Hands the block directory over to Blockstudio.
 *
 * Ohne Blockstudio passiert NICHTS ausser einem Logeintrag: kein Fatal, keine
 * Selbstdeaktivierung. Die Admin-Notice des Umgebungschecks in der Hauptdatei
 * sagt dem Betreiber bereits, was fehlt; das Backend bleibt bedienbar.
 */
/**
 * Hands the block directories over to Blockstudio.
 *
 * Ohne Blockstudio passiert NICHTS ausser einem Logeintrag: kein Fatal, keine
 * Selbstdeaktivierung. Die Admin-Notice des Umgebungschecks in der Hauptdatei
 * sagt dem Betreiber bereits, was fehlt; das Backend bleibt bedienbar.
 *
 * ZWEI VERZEICHNISSE, EIN SCHALTER:
 *
 *   `blocks/`        — die eigenen `creabb/*`-Bloecke. Haengt an KEINEM
 *                      Schalter. Wer `legacy_blocks` abschaltet, will die
 *                      Aliasbloecke los, nicht das Plugin.
 *   `blocks-legacy/` — die `areoi/*`-Aliasbloecke. Nur bei aktivem
 *                      `legacy_blocks`. Gelesen wird der Schalter
 *                      ausschliesslich ueber `Legacy::blocks_enabled()` —
 *                      dieselbe Stelle, die auch der Render-Shim fragt.
 *
 * Beide Verzeichnisse teilen sich die Feldbibliothek: `blockstudio/fields/paths`
 * ist global und wird je Discovery-Lauf ausgewertet.
 *
 * Fehlt `blocks-legacy/` — der Zustand vor dem ersten Lauf von
 * `bin/build-aliases.php` —, bleibt es bei einem Logeintrag.
 */
function crea_bootstrap_blocks_register_blocks(): void {
    if ( ! class_exists( '\Blockstudio\Build' ) ) {
        crea_bootstrap_blocks_log(
            'register_blocks skipped: Blockstudio (\Blockstudio\Build) is not available.'
        );

        return;
    }

    \Blockstudio\Build::init(
        [
            'dir' => crea_bootstrap_blocks_blocks_dir(),
        ]
    );

    if ( ! \Creationell\BootstrapBlocks\Legacy::blocks_enabled() ) {
        return;
    }

    $legacy_dir = crea_bootstrap_blocks_legacy_blocks_dir();

    if ( ! is_dir( $legacy_dir ) ) {
        crea_bootstrap_blocks_log(
            'register_blocks: legacy alias directory is missing (' . $legacy_dir . '). Run bin/build-aliases.php.'
        );

        return;
    }

    \Blockstudio\Build::init(
        [
            'dir' => $legacy_dir,
        ]
    );
}

add_action( 'init', 'crea_bootstrap_blocks_register_blocks', 10 );
