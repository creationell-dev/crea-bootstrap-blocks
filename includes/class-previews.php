<?php
/**
 * Inserter-Vorschau: ein SVG-Schema statt des gerenderten Blocks.
 *
 * Das abgeloeste Alt-Plugin zeigt im Inserter fuer 47 seiner 48 Bloecke ein
 * Bild statt des Blocks. Sein Weg dorthin ist eine eigene React-Editorkomponente
 * (`blocks/_components/DisplayPreview.js`), die `attributes.preview` liest. Den
 * Weg gibt es hier nicht: Die Bloecke sind Blockstudio-Bloecke, `block.json`
 * plus PHP-Template, ohne JS-Build.
 *
 * GEMESSEN am 2026-09-04 gegen die laufende Instanz, Station fuer Station:
 *
 * 1. `example` aus der `block.json` erreicht die Registry unveraendert
 *    (`WP_Block_Type::$example`), und ueber `/wp/v2/block-types/<name>` mit
 *    Kontext `edit` auch den Editor — Status 200, Feld vorhanden. Blockstudio
 *    verwirft es NICHT.
 * 2. Ohne `example` zeigt der Inserter gar keine Vorschau, sondern die Zeile
 *    "No preview available." Der Kern prueft nur den Wahrheitswert.
 * 3. Ist `example` gesetzt, baut der Kern daraus einen echten Block und rendert
 *    ihn ueber die JS-`edit`-Komponente. Fuer unsere Bloecke ist das
 *    Blockstudios eigene; sie holt das gerenderte PHP-Template per REST.
 * 4. In diesem Renderlauf steht Blockstudios `$is_preview` auf `true`, und der
 *    Filter `blockstudio/blocks/render` bekommt ihn als viertes Argument
 *    mitgeliefert — er muss ihn nicht selbst aus der Adresse lesen.
 *
 * Daraus folgt der Bauweg: `example` an EINER Stelle nachtragen (statt in 47
 * `block.json`, die dann vier Generatoren nachziehen muessten), und die Ausgabe
 * an EINER Stelle ersetzen (statt in 47 Templates).
 *
 * DER GET-PARAMETER IST KEINE BERECHTIGUNG. `$is_preview` leitet Blockstudio aus
 * `$_GET['blockstudioMode']` ab, ohne Nonce und ohne Capability. Ein Filter, der
 * allein daran haengt, laesst jeden anonymen Besucher das echte Markup einer
 * Seite gegen ein Schema-SVG tauschen, indem er `?blockstudioMode=preview`
 * anhaengt. Der Riegel in may_preview() ist deshalb nicht Kosmetik, sondern die
 * Bedingung dafuer, dass dieser Filter ueberhaupt existieren darf.
 *
 * @package CreaBootstrapBlocks
 */

declare(strict_types=1);

namespace Creationell\BootstrapBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the inserter preview and swaps the markup while it is drawn.
 */
final class Previews {

	/**
	 * Canonical block namespace. Alias blocks never get a preview: they carry
	 * `"inserter": false` and are kept out a second time by Legacy.
	 */
	public const BLOCK_NAMESPACE = 'creabb/';

	/**
	 * Where build-block-previews.php writes, relative to the plugin root.
	 */
	public const IMAGE_DIR = 'assets/img/block-previews/';

	/**
	 * Capability a viewer needs before the swap happens at all.
	 *
	 * `edit_posts` is what the block editor itself requires; anyone who can see
	 * an inserter has it, and nobody else does.
	 */
	public const CAPABILITY = 'edit_posts';

	/**
	 * Whether init() has already run.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Wie viele Beispielbloecke je Blockname noch auf ihren Render warten.
	 *
	 * DER VORSCHAUMODUS ALLEIN REICHT NICHT — das war der Fehler vom 2026-09-04.
	 * Blockstudios `$is_preview` bedeutet nicht „Inserter-Vorschau eines
	 * Beispielblocks", sondern „irgendeine Gutenberg-BlockPreview": Sein
	 * Editorcode setzt den Modus, sobald der Blockknoten in einem Element mit der
	 * Klasse `block-editor-block-preview__content-iframe` liegt, und die haengt
	 * der Kern an JEDE Vorschau-iframe — auch an die Musterkacheln, den
	 * Blockwechsler und die Vorlagenvorschau. Ein gespeichertes Muster mit einem
	 * `creabb/*`-Block erschien dort als graues Schema statt als Inhalt; auf
	 * einer Bestandsinstallation betraf das fuenf `wp_block`-Beitraege.
	 *
	 * Das Original unterscheidet sauber: Seine Komponente tauscht nur bei
	 * `attributes.preview` (`blocks/_components/DisplayPreview.js`) — der
	 * Beispielblock des Inserters traegt es, ein Musterblock nie. Wo der Nachbau
	 * einfacher ist als das Original, ist er falsch.
	 *
	 * NACHBAUEN LAESST SICH DAS NUR UEBER `render_block_data`. Am Renderfilter
	 * selbst ist `preview` bereits fort: Blockstudio wirft jeden Schluessel weg,
	 * der kein deklariertes Feld ist, und `preview` ist keines. Gemessen an der
	 * laufenden Instanz — dort tragen die Attribute am Filter 89 Schluessel, und
	 * `preview` ist in KEINEM der beiden Faelle darunter. Im `render_block_data`
	 * dagegen steht es flach und unveraendert.
	 *
	 * @var array<string, int>
	 */
	private static array $beispiel_offen = [];

	/**
	 * Registers both filters. Safe to call more than once.
	 *
	 * PRIORITAET 40 auf `blockstudio/blocks/meta`: 10 traegt die
	 * Vertragsmechanik (`type`, `default`), 20 die Uebersetzung (`title`,
	 * `description`), 30 haelt die Aliasbloecke aus dem Inserter. Dieser
	 * Callback fasst ausschliesslich `example` an — die vier Schluesselmengen
	 * sind disjunkt, die Reihenfolge ist ergebnisneutral. 40 steht trotzdem
	 * hinten, damit ein spaeterer Blick auf die Kette sie in Bauabschnitten
	 * liest.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		add_filter( 'blockstudio/blocks/meta', [ self::class, 'add_example' ], 40, 1 );
		add_filter( 'blockstudio/blocks/render', [ self::class, 'swap_in_preview' ], 10, 4 );

		/*
		 * PRIORITAET 20 auf `render_block_data`: Auf 10 sitzt der Legacy-Shim, der
		 * flache Attribute in den Container HEBT. Er kopiert (E-100), laesst die
		 * flache Ebene also stehen — `preview` ist danach unveraendert da.
		 * Gemessen: bei Prioritaet 99, also weit hinter dem Shim, traegt der
		 * Beispielblock `attrs=[preview, blockstudio]`, ein Bestandsblock
		 * `attrs=[block_id, blockstudio]`.
		 */
		add_filter( 'render_block_data', [ self::class, 'merke_beispielblock' ], 20, 1 );
	}

	/**
	 * Maps a block name to the slug its preview file is named after.
	 *
	 * Gemessen ueber alle 47: der Blockname ist der Namensraum plus dem
	 * Verzeichnisnamen unter `blocks/`, und der Generator benennt seine Dateien
	 * nach ebendiesem Verzeichnisnamen. Die beiden Mengen decken sich exakt.
	 * Fuer einen fremden Namensraum gibt es keinen Slug — Leerstring, nicht
	 * etwa der ungepruefte Rest hinter dem Schraegstrich.
	 *
	 * @param string $name Registered block name.
	 * @return string The slug, or an empty string when the name is out of scope.
	 */
	public static function slug_for( string $name ): string {
		if ( ! str_starts_with( $name, self::BLOCK_NAMESPACE ) ) {
			return '';
		}

		$slug = substr( $name, strlen( self::BLOCK_NAMESPACE ) );

		// Der Slug wird zu einem Dateipfad. Alles, was nicht dem Zeichensatz der
		// erzeugten Dateinamen entspricht, faellt heraus — ein Punkt genuegt fuer
		// einen Ausbruch aus dem Verzeichnis, es braucht keinen Schraegstrich.
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			return '';
		}

		return $slug;
	}

	/**
	 * Absolute path of a block's preview file, or '' when there is none.
	 *
	 * @param string $name Registered block name.
	 * @return string Absolute path, or an empty string.
	 */
	public static function image_path( string $name ): string {
		$slug = self::slug_for( $name );

		if ( '' === $slug ) {
			return '';
		}

		$path = CREA_BOOTSTRAP_BLOCKS_DIR . self::IMAGE_DIR . $slug . '.svg';

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Public URL of a block's preview file, or '' when there is none.
	 *
	 * @param string $name Registered block name.
	 * @return string URL, or an empty string.
	 */
	public static function image_url( string $name ): string {
		if ( '' === self::image_path( $name ) ) {
			return '';
		}

		return CREA_BOOTSTRAP_BLOCKS_URL . self::IMAGE_DIR . self::slug_for( $name ) . '.svg';
	}

	/**
	 * Whether the inserter preview is switched on at all.
	 *
	 * DER RUECKWEG. Jeder andere ausgelieferte Verhaltenszweig dieses Plugins hat
	 * einen — die drei Asset-Schalter, `legacy_classes`, `legacy_blocks`. Dieser
	 * hatte bis zum 2026-09-04 keinen, und das fiel erst einem
	 * Vollstaendigkeitskritiker auf: Ein Betreiber, dem die Schemabilder im
	 * Inserter nicht gefallen, haette das Plugin aendern muessen.
	 *
	 * VOREINGESTELLT AN. Die Vorschau ist eine Bedienhilfe im Editor; sie
	 * beruehrt weder den Bestand noch das Frontend. Die Konstante
	 * `CREA_BOOTSTRAP_BLOCKS_INSERTER_PREVIEWS` hat Vorrang vor der Option, wie
	 * ueberall in diesem Plugin.
	 */
	public static function enabled(): bool {
		return crea_bootstrap_blocks_setting_enabled( 'inserter_previews', true );
	}

	/**
	 * Whether the current request may see a preview instead of real markup.
	 *
	 * Siehe den Docblock oben: `$is_preview` allein ist ein GET-Parameter.
	 */
	public static function may_preview(): bool {
		return is_user_logged_in() && current_user_can( self::CAPABILITY );
	}

	/**
	 * Builds the replacement markup. Pure — the suite drives this directly.
	 *
	 * Escapt wird genau hier, am Ausgabepunkt, wie ueberall im Renderpfad. Die
	 * Abmessungen stehen im SVG selbst; `width`/`height` am Tag halten den Platz,
	 * bevor die Datei geladen ist, und verhindern das Springen der Vorschau.
	 *
	 * @param string $url Preview image URL.
	 * @param string $alt Already translated alt text.
	 * @return string Markup, or an empty string when the URL is empty.
	 */
	public static function image_tag( string $url, string $alt ): string {
		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<img class="creabb-block-preview" src="%s" alt="%s" width="500" height="400" decoding="async" />',
			esc_url( $url ),
			esc_attr( $alt )
		);
	}

	/**
	 * The alt text for one block's preview image.
	 *
	 * @param string $title Block title as registered (already translated).
	 * @return string
	 */
	public static function alt_text( string $title ): string {
		if ( '' === $title ) {
			return __( 'Block preview', 'crea-bootstrap-blocks' );
		}

		/* translators: %s: block title. */
		return sprintf( __( 'Preview: %s', 'crea-bootstrap-blocks' ), $title );
	}

	/**
	 * Decides whether this render pass gets a preview image. Pure.
	 *
	 * Getrennt vom Filter, damit eine Suite jede der vier Bedingungen einzeln
	 * fahren kann. Ein Callback, der die Entscheidung in sich traegt, ueberlebt
	 * jede Mutation daran.
	 *
	 * @param bool   $is_preview Blockstudio's preview flag.
	 * @param bool   $may        Whether the viewer holds the capability.
	 * @param string $name       Registered block name.
	 * @param string $url          Preview URL, '' when there is none.
	 * @param bool   $ist_beispiel Whether this render is the inserter's example block.
	 * @return bool
	 */
	public static function should_swap( bool $is_preview, bool $may, string $name, string $url, bool $ist_beispiel ): bool {
		return $is_preview
			&& $ist_beispiel
			&& $may
			&& str_starts_with( $name, self::BLOCK_NAMESPACE )
			&& '' !== $url;
	}

	/**
	 * `render_block_data` callback: merkt sich den Beispielblock des Inserters.
	 *
	 * Er ist daran zu erkennen, dass er das Attribut `preview` FLACH und auf
	 * `true` traegt — so baut Gutenberg ihn aus `example.attributes`. Ein
	 * gespeicherter Block traegt es nie: Der Vertrag gibt ihm den Default
	 * `false`, und geschrieben wird nur im Blockstudio-Container.
	 *
	 * @param mixed $block The parsed block.
	 * @return mixed The same block, untouched.
	 */
	public static function merke_beispielblock( $block ) {
		if ( ! is_array( $block ) ) {
			return $block;
		}

		$name = $block['blockName'] ?? '';

		if ( ! is_string( $name ) || ! str_starts_with( $name, self::BLOCK_NAMESPACE ) ) {
			return $block;
		}

		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];

		if ( true !== ( $attrs['preview'] ?? false ) ) {
			return $block;
		}

		self::$beispiel_offen[ $name ] = ( self::$beispiel_offen[ $name ] ?? 0 ) + 1;

		return $block;
	}

	/**
	 * Whether a pending example render exists for this block — and consumes it.
	 *
	 * VERBRAUCHEND, und das ist Absicht: Zu jedem gemerkten Beispielblock gehoert
	 * genau ein Renderdurchlauf. Bliebe die Marke stehen, tauschte der naechste
	 * Block desselben Typs im selben Request ebenfalls — und der koennte aus
	 * einem Muster stammen.
	 *
	 * @param string $name Registered block name.
	 * @return bool
	 */
	public static function beispiel_verbrauchen( string $name ): bool {
		if ( ( self::$beispiel_offen[ $name ] ?? 0 ) <= 0 ) {
			return false;
		}

		--self::$beispiel_offen[ $name ];

		return true;
	}

	/**
	 * Leert die Merkliste. Nur fuer die Suite.
	 */
	public static function beispiele_zuruecksetzen(): void {
		self::$beispiel_offen = [];
	}

	/**
	 * `blockstudio/blocks/meta` callback: gives every block an `example`.
	 *
	 * Ohne `example` zeigt der Inserter "No preview available." und ruft gar
	 * keinen Render auf — der Filter unten kaeme nie zum Zug. Die Form ist die
	 * des Originals (`{"attributes": {"preview": true}}`); `preview` ist ein
	 * Vertragsattribut mit `{"type": "boolean", "default": false}` und steht
	 * gemessen in der registrierten Attributkarte, ueberlebt die Sanitierung des
	 * Editors also. Getragen wird die Entscheidung trotzdem von `$is_preview`,
	 * nicht von diesem Wert.
	 *
	 * Ein Block ohne Vorschaubild bekommt KEIN `example`: Er soll dann bei
	 * "No preview available." bleiben statt eine leere Flaeche zu zeigen.
	 *
	 * VERENGT WIRD MIT `instanceof`, nicht mit `is_object()` — dieselbe
	 * Begruendung wie bei den Callbacks auf 10, 20 und 30: Dies ist ein
	 * GLOBALER Filter, sein Parameter ist `mixed`.
	 *
	 * @param mixed $block The registered block type.
	 * @return mixed The same block.
	 */
	public static function add_example( $block ) {
		if ( $block instanceof \WP_Block_Type ) {
			$name = is_string( $block->name ) ? $block->name : '';
		} elseif ( is_array( $block ) ) {
			$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
		} else {
			return $block;
		}

		if ( ! self::enabled() || '' === self::image_path( $name ) ) {
			return $block;
		}

		$example = [ 'attributes' => [ 'preview' => true ] ];

		if ( $block instanceof \WP_Block_Type ) {
			// Ein bereits gesetztes `example` bleibt stehen — `media-grid-image`
			// traegt es seit jeher in seiner eigenen block.json.
			if ( null === $block->example ) {
				$block->example = $example;
			}

			return $block;
		}

		if ( ! isset( $block['example'] ) ) {
			$block['example'] = $example;
		}

		return $block;
	}

	/**
	 * `blockstudio/blocks/render` callback: swaps markup for the preview image.
	 *
	 * @param mixed $markup     The rendered block markup.
	 * @param mixed $block_type The registered block type.
	 * @param mixed $is_editor  Whether this is the editor canvas.
	 * @param mixed $is_preview Whether this is an inserter preview.
	 * @return mixed The markup, possibly replaced.
	 */
	public static function swap_in_preview( $markup, $block_type, $is_editor, $is_preview ) {
		if ( ! is_string( $markup ) ) {
			return $markup;
		}

		if ( $block_type instanceof \WP_Block_Type ) {
			$name  = is_string( $block_type->name ) ? $block_type->name : '';
			$title = is_string( $block_type->title ) ? $block_type->title : '';
		} elseif ( is_array( $block_type ) ) {
			$name  = isset( $block_type['name'] ) && is_string( $block_type['name'] ) ? $block_type['name'] : '';
			$title = isset( $block_type['title'] ) && is_string( $block_type['title'] ) ? $block_type['title'] : '';
		} else {
			return $markup;
		}

		/*
		 * DER MERKER WIRD IMMER VERBRAUCHT, auch wenn danach gar nicht getauscht
		 * wird. Sonst bleibt er liegen und der naechste Block desselben Typs im
		 * selben Request erbt ihn — und der kaeme aus einem Muster. Genau das ist
		 * am 2026-09-04 passiert: Ein Renderlauf OHNE Vorschaumodus merkte sich
		 * den Beispielblock und stieg vor dem Verbrauch aus; der spaetere
		 * Musterblock fand den Merker vor und wurde getauscht. Der Ebene-3-Lauf
		 * hat es gefunden, keine Suite.
		 */
		$ist_beispiel = self::beispiel_verbrauchen( $name );

		/*
		 * HIER ENDET DER AUFRUF IM FRONTEND. Dieser Filter feuert bei JEDEM
		 * gerenderten Blockstudio-Block, auf jeder Seite. Dort ist `$is_preview`
		 * immer false — der Aufruf endet nach einem Vergleich und einem
		 * Array-Zugriff, VOR dem Dateisystemzugriff in image_url() und vor der
		 * Capability-Abfrage. (Bis zum 2026-09-04 stand image_url() darueber, und
		 * dieser Kommentar behauptete trotzdem das Gegenteil.)
		 */
		if ( true !== $is_preview || ! self::enabled() ) {
			return $markup;
		}

		$url = self::image_url( $name );

		if ( ! self::should_swap( true, self::may_preview(), $name, $url, $ist_beispiel ) ) {
			return $markup;
		}

		return self::image_tag( $url, self::alt_text( $title ) );
	}
}

// Die Include-Liste in crea-bootstrap-blocks.php laedt diese Datei genau
// einmal; init() ist zusaetzlich gegen einen zweiten Aufruf gesichert.
Previews::init();
