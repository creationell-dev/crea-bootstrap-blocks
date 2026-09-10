/**
 * Editor control for the custom Blockstudio field type `creabb/media`.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * `blocks/media-grid-image/block.json` fuehrte das Attribut `id` als
 * `number`-Feld. Wer ein Bild einsetzen wollte, musste die Anhang-ID von Hand
 * eintippen; ohne sie steigt `blocks/media-grid-image/index.php` mit einem
 * blanken `return;` aus — kein Bild, kein Platzhalter, keine Meldung. Das
 * Alt-Plugin holte das Bild ueber `MediaPlaceholder` beziehungsweise
 * `MediaReplaceFlow` direkt in der Leinwand.
 *
 * WARUM NICHT BLOCKSTUDIOS `files`
 *
 * Der eingebaute Typ `files` legt `{"id":…,"url":…,"name":…,…}` ab. Der Bestand
 * des Alt-Plugins enthaelt an dieser Stelle die BLANKE ZAHL — `"id":600`. Er
 * steht deshalb auf der Sperrliste aus Regel 1.4.
 *
 * WARUM `creabb/media` DIESE SPERRLISTE NICHT AUFWEICHT
 *
 * Die Sperrliste verbietet keine Bedienelemente, sondern SPEICHERFORMEN: Ein
 * Feldtyp ist gesperrt, wenn er den Wert normalisiert, bevor er ihn ablegt.
 * Dieses Control legt ausschliesslich die Anhang-ID als JavaScript-Zahl ab —
 * genau die Form, die `blocks/_contracts/media-grid-image.json` fuer `id`
 * ohnehin vorschreibt (`{"type":"number"}`, ohne Default). Die Mediathek
 * liefert daneben URL, Name, Groessen und Alt-Text; nichts davon wird
 * gespeichert. Attributname, Attributtyp und Default bleiben unveraendert,
 * migrierte Inhalte sind nicht beruehrt. Dieselbe Begruendung tragen
 * `creabb/select` und `creabb/color`.
 *
 * WAS BEIM LEEREN GESCHRIEBEN WIRD
 *
 * `null`, nicht `0` — wie beim Zuruecksetzen von `creabb/color`. Der Vertrag
 * kennt fuer `id` keinen Default; `0` waere eine gesetzte Zahl, wo nie eine
 * gesetzt war. Fuer die Ausgabe ist der Unterschied folgenlos: Das Template
 * verlangt `is_numeric()` UND einen Wert groesser als null und erzeugt fuer
 * `null`, `0` und ein fehlendes Attribut dasselbe, naemlich kein Markup.
 *
 * OHNE MEDIATHEK BLEIBT EIN ZAHLENFELD STEHEN
 *
 * `wp.media` wird von `wp_enqueue_media()` bereitgestellt. Der Blockeditor ruft
 * das selbst auf; eine aeltere Instanz oder ein fremder Ort, an dem
 * Blockstudio dieses Feld zeichnet, muss es aber nicht. Fehlt `wp.media`, faellt
 * das Control auf ein einfaches Zahlenfeld zurueck — die ID bleibt von Hand
 * setzbar. Ein Feld, das dann gar nichts anboete, waere schlechter als der
 * Zustand vor diesem Feldtyp.
 *
 * `media-views` STEHT ABSICHTLICH NICHT IN DEN ABHAENGIGKEITEN. Es waere der
 * naheliegende Griff — und er machte den Rueckfall unerreichbar, ohne die Sache
 * zu heilen: Das Skript brachte zwar `wp.media` mit, aber nicht die
 * Unterstrich-Vorlagen des Dialogs. Die druckt `wp_print_media_templates()`,
 * und die haengt `wp_enqueue_media()` ein. Ohne sie oeffnete der Knopf einen
 * LEEREN Dialog, waehrend `hasMediaLibrary()` „ja" sagte. Genau deshalb ist
 * `wp.media` hier der richtige Anzeiger: Es existiert genau dort, wo auch die
 * Vorlagen stehen.
 *
 * KEIN BUNDLER, KEIN BUILD, KEIN JSX. Die Datei wird als einfaches Skript mit
 * den Abhaengigkeiten `blockstudio-blocks`, `wp-components` und `wp-element`
 * registriert und ueber den Schluessel `editor_script` der Feldtyp-Definition
 * eingebunden.
 */

( function () {
	'use strict';

	var wp = window.wp;
	var blockstudio = window.blockstudio;

	/*
	 * `useState` und `useEffect` stehen ausdruecklich in der Wache.
	 *
	 * Sie kommen aus derselben Abhaengigkeit wie `createElement` — `wp-element`
	 * reicht Reacts Hooks unveraendert durch. Entweder gibt es alle drei oder
	 * keinen; die Wache deckt damit denselben Fall wie bei den beiden anderen
	 * Controls ab und nicht etwa einen zusaetzlichen. Sie steht hier, weil das
	 * Control die Vorschau-URL zwischenspeichert: Gespeichert wird nur die ID,
	 * die Adresse des Bildes muss also nachgeschlagen werden.
	 */
	if (
		! wp ||
		! wp.element ||
		typeof wp.element.createElement !== 'function' ||
		typeof wp.element.useState !== 'function' ||
		typeof wp.element.useEffect !== 'function' ||
		! wp.components ||
		! blockstudio
	) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var BaseControl = wp.components.BaseControl;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;

	/*
	 * DER AUFRUF MUSS WOERTLICH `__( 'Text', 'crea-bootstrap-blocks' )` LAUTEN
	 * — die vollstaendige Begruendung steht in `color.js`. Kurz: `wp i18n
	 * make-pot` liest statisch und erkennt einen Uebersetzungsaufruf am NAMEN
	 * der gerufenen Funktion. Der Helfer, der bis zum 2026-09-08 hier stand,
	 * hiess anders und rief `wp.i18n.__()` erst innen; der Extraktor sah an der
	 * Stelle des Literals eine Variable. Alle fuenf Beschriftungen dieses
	 * Controls standen deshalb englisch im Editor.
	 *
	 * DIE WACHE BLEIBT: Fehlt `wp.i18n`, gibt der Helfer den Ausgangstext
	 * zurueck, statt eine Ausnahme zu werfen.
	 */

	/**
	 * Uebersetzt, wenn wp.i18n geladen ist.
	 *
	 * @param {string} text   Ausgangstext.
	 * @param {string} domain Textdomain.
	 * @return {string} Uebersetzung oder Ausgangstext.
	 */
	function __( text, domain ) {
		if ( wp.i18n && typeof wp.i18n.__ === 'function' ) {
			return wp.i18n.__( text, domain );
		}

		return text;
	}

	/**
	 * Liest den gespeicherten Wert als Anhang-ID.
	 *
	 * Ergebnis ist eine positive ganze Zahl oder 0 fuer „nichts gewaehlt".
	 * Neben der Vertragsform (Zahl) werden drei Nachlassformen entpackt: der
	 * Zahlenstring, das Objekt eines Blockstudio-`files`-Feldes und dessen
	 * Listenform. Ein solcher Block laesst sich damit reparieren, sobald ihn
	 * jemand anfasst — geschrieben wird danach wieder die blanke Zahl.
	 *
	 * @param {*} value Gespeicherter Wert.
	 * @return {number} Anhang-ID oder 0.
	 */
	function readId( value ) {
		var candidate = value;
		var number;

		if ( Array.isArray( candidate ) ) {
			candidate = candidate.length > 0 ? candidate[ 0 ] : null;
		}

		if ( candidate && typeof candidate === 'object' ) {
			// Nachlass eines `files`-Feldes: { id, url, name, … }.
			candidate = candidate.id !== undefined ? candidate.id : candidate.value;
		}

		if ( typeof candidate === 'string' ) {
			candidate = candidate.trim();

			if ( ! /^[0-9]+$/.test( candidate ) ) {
				return 0;
			}
		}

		if ( typeof candidate !== 'number' && typeof candidate !== 'string' ) {
			return 0;
		}

		number = Math.floor( Number( candidate ) );

		if ( ! isFinite( number ) || number <= 0 ) {
			return 0;
		}

		return number;
	}

	/**
	 * Ob die Mediathek in diesem Editor ueberhaupt zur Verfuegung steht.
	 *
	 * `wp.media` ist zugleich Funktion und Namensraum: `wp.media()` oeffnet
	 * einen Rahmen, `wp.media.attachment()` liefert ein Modell. Geprueft wird
	 * deshalb beides — ein halb geladenes Buendel ist der Fall, in dem eine
	 * blosse Existenzpruefung durchginge und der Klick danach einen TypeError
	 * wuerfe.
	 *
	 * @return {boolean} Ob gepickt werden kann.
	 */
	function hasMediaLibrary() {
		return typeof wp.media === 'function' && typeof wp.media.attachment === 'function';
	}

	/**
	 * Adresse fuer die Vorschau: Vorschaugroesse, sonst die Datei selbst.
	 *
	 * @param {Object} model Anhangsmodell der Mediathek.
	 * @return {string} URL oder Leerstring.
	 */
	function previewUrl( model ) {
		var sizes;

		if ( ! model || typeof model.get !== 'function' ) {
			return '';
		}

		sizes = model.get( 'sizes' );

		if ( sizes && sizes.thumbnail && typeof sizes.thumbnail.url === 'string' ) {
			return sizes.thumbnail.url;
		}

		return typeof model.get( 'url' ) === 'string' ? model.get( 'url' ) : '';
	}

	/**
	 * Schlaegt die Vorschauadresse zu einer ID nach.
	 *
	 * Gespeichert ist nur die Zahl — die Adresse muss also von der Mediathek
	 * kommen. Liegt das Modell schon im Speicher, wird sofort geantwortet;
	 * sonst wird einmal nachgeladen. Der Fehlerzweig ist besetzt: Ist der
	 * Anhang geloescht, bleibt es beim Leerstring, und das Feld zeigt seine
	 * Bedienknoepfe ohne Bild — statt einer unbeantworteten Zusage.
	 *
	 * @param {number}   id      Anhang-ID.
	 * @param {Function} receive Nimmt die Adresse entgegen.
	 */
	function resolveUrl( id, receive ) {
		var model;
		var pending;

		if ( ! hasMediaLibrary() ) {
			return;
		}

		model = wp.media.attachment( id );

		if ( ! model || typeof model.get !== 'function' ) {
			return;
		}

		if ( previewUrl( model ) !== '' ) {
			receive( previewUrl( model ) );

			return;
		}

		if ( typeof model.fetch !== 'function' ) {
			return;
		}

		pending = model.fetch();

		if ( ! pending || typeof pending.then !== 'function' ) {
			return;
		}

		pending.then(
			function () {
				receive( previewUrl( model ) );
			},
			function () {
				receive( '' );
			}
		);
	}

	/**
	 * Oeffnet die Mediathek und meldet die Auswahl zurueck.
	 *
	 * Die Auswahl ist auf Bilder beschraenkt (`library.type`) und einzeln
	 * (`multiple: false`) — das Attribut traegt genau eine ID.
	 *
	 * @param {number}   current  Bisher gewaehlte ID, 0 fuer keine.
	 * @param {Function} onSelect Nimmt das gewaehlte Anhangsmodell entgegen.
	 */
	function openPicker( current, onSelect ) {
		var frame;

		if ( ! hasMediaLibrary() ) {
			return;
		}

		frame = wp.media( {
			title: __( 'Select image', 'crea-bootstrap-blocks' ),
			button: { text: __( 'Use this image', 'crea-bootstrap-blocks' ) },
			library: { type: 'image' },
			multiple: false
		} );

		if ( ! frame || typeof frame.on !== 'function' || typeof frame.open !== 'function' ) {
			return;
		}

		frame.on( 'open', function () {
			var selection;

			if ( current <= 0 || typeof frame.state !== 'function' ) {
				return;
			}

			selection = frame.state().get( 'selection' );

			if ( selection && typeof selection.add === 'function' ) {
				selection.add( wp.media.attachment( current ) );
			}
		} );

		frame.on( 'select', function () {
			var selection;

			if ( typeof frame.state !== 'function' ) {
				return;
			}

			selection = frame.state().get( 'selection' );

			if ( ! selection || typeof selection.first !== 'function' ) {
				return;
			}

			onSelect( selection.first() );
		} );

		frame.open();
	}

	blockstudio.registerFieldType( 'creabb/media', {
		component: function ( props ) {
			var stored = readId( props.value );
			var disabled = !! props.disabled;
			var preview = useState( '' );
			var url = preview[ 0 ];
			var setUrl = preview[ 1 ];
			var children = [];

			/*
			 * DIE VORSCHAU HAENGT AN DER ID, NICHT AM RENDERLAUF.
			 * Ohne die Abhaengigkeitsliste liefe das Nachladen bei jedem
			 * Zeichnen erneut; mit ihr genau dann, wenn sich die gespeicherte
			 * Zahl geaendert hat.
			 */
			useEffect(
				function () {
					if ( 0 === stored ) {
						setUrl( '' );

						return;
					}

					resolveUrl( stored, setUrl );
				},
				[ stored ]
			);

			/**
			 * Speichert eine Auswahl. AUSSCHLIESSLICH die Zahl.
			 *
			 * @param {Object} model Anhangsmodell der Mediathek.
			 */
			function commit( model ) {
				var id = readId( model && typeof model.get === 'function' ? model.get( 'id' ) : null );

				if ( 0 === id ) {
					return;
				}

				setUrl( previewUrl( model ) );

				// Kein Objekt, keine URL, kein Alt-Text — das ist der ganze
				// Punkt dieses Feldtyps (Regel 1.4).
				props.onChange( id );
			}

			/**
			 * Leert die Auswahl.
			 */
			function clear() {
				setUrl( '' );
				props.onChange( props.defaultValue === undefined ? null : props.defaultValue );
			}

			/*
			 * OHNE MEDIATHEK BLEIBT DAS ZAHLENFELD.
			 * Der Rueckfall ist bewusst der bisherige Zustand und nicht ein
			 * leeres Feld: Die ID bleibt setzbar, und der Hinweis sagt, warum
			 * der Auswahldialog fehlt.
			 */
			if ( ! hasMediaLibrary() ) {
				return el(
					BaseControl,
					{ __nextHasNoMarginBottom: true },
					el( TextControl, {
						className: 'components-base-control',
						label: false,
						help: false,
						type: 'number',
						min: 0,
						value: 0 === stored ? '' : String( stored ),
						disabled: disabled,
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						onChange: function ( next ) {
							var id = readId( next );

							props.onChange(
								0 === id
									? ( props.defaultValue === undefined ? null : props.defaultValue )
									: id
							);
						}
					} ),
					el(
						'p',
						{ className: 'crea-bootstrap-blocks-media__notice' },
						__( 'The media library is not available here. Enter the attachment ID manually.', 'crea-bootstrap-blocks' )
					)
				);
			}

			if ( stored > 0 && '' !== url ) {
				children.push(
					el( 'img', {
						key: 'preview',
						className: 'crea-bootstrap-blocks-media__preview',
						src: url,
						alt: '',
						style: { display: 'block', maxWidth: '100%', height: 'auto' }
					} )
				);
			}

			children.push(
				el(
					Button,
					{
						key: 'pick',
						variant: 'secondary',
						disabled: disabled,
						onClick: function () {
							openPicker( stored, commit );
						}
					},
					stored > 0
						? __( 'Replace image', 'crea-bootstrap-blocks' )
						: __( 'Select image', 'crea-bootstrap-blocks' )
				)
			);

			if ( stored > 0 ) {
				children.push(
					el(
						Button,
						{
							key: 'clear',
							variant: 'tertiary',
							isDestructive: true,
							disabled: disabled,
							onClick: clear
						},
						__( 'Remove image', 'crea-bootstrap-blocks' )
					)
				);
			}

			// OHNE `label` UND `help` — Blockstudio umschliesst jedes Feld mit
			// seinem eigenen `Control`, das beide bereits zeichnet. Ein zweites
			// Label erschiene untereinander doppelt; dieselbe Begruendung wie
			// in select.js und color.js.
			return el(
				BaseControl,
				{ __nextHasNoMarginBottom: true },
				children
			);
		}
	} );
} )();
