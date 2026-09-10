/**
 * Editor control for the custom Blockstudio field type `creabb/color`.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * Blockstudios eigenes `color`-Feld speichert
 * `{ "value": "#ff0000", "name": "Red", "slug": "red" }`. Das Alt-Plugin legt
 * das vollstaendige `ColorResult` von react-color ab — sechs Schluessel in
 * konstanter Reihenfolge:
 *
 *   { "hex": "#01509f26",
 *     "rgb": { "r": 1, "g": 80, "b": 159, "a": 0.15 },
 *     "hsv": { "h": 210, "s": 99, "v": 62, "a": 0.15 },
 *     "hsl": { "h": 210, "s": 99, "l": 31, "a": 0.15 },
 *     "source": "hex",
 *     "oldHue": 210 }
 *
 * Gerendert wird ausschliesslich aus `rgb`; die uebrigen Schluessel sind fuer
 * die Ausgabe bedeutungslos, gehoeren aber zum gespeicherten Bestand. Deshalb
 * erzeugt dieses Control die VOLLSTAENDIGE Form — ein im Editor angefasster
 * Block darf keine aermere Struktur hinterlassen als ein unangetasteter.
 *
 * `hex` ist achtstellig, sobald Alpha kleiner als 1 ist, und sechsstellig bei
 * Alpha gleich 1. Die Schluesselreihenfolge ist Vertragsbestandteil;
 * JSON.stringify behaelt die Einfuegereihenfolge bei.
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

	if ( ! wp || ! wp.element || ! wp.components || ! blockstudio ) {
		return;
	}

	var el = wp.element.createElement;
	var BaseControl = wp.components.BaseControl;
	var ColorPicker = wp.components.ColorPicker;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;

	/**
	 * Begrenzt einen Kanalwert auf eine ganze Zahl zwischen 0 und 255.
	 *
	 * @param {*} value Rohwert.
	 * @return {number} Kanalwert.
	 */
	function clampByte( value ) {
		var number = Math.round( Number( value ) );

		if ( ! isFinite( number ) ) {
			return 0;
		}

		return Math.min( 255, Math.max( 0, number ) );
	}

	/**
	 * Begrenzt Alpha auf 0 bis 1.
	 *
	 * @param {*} value Rohwert.
	 * @return {number} Alpha.
	 */
	function clampAlpha( value ) {
		var number = Number( value );

		if ( ! isFinite( number ) ) {
			return 1;
		}

		return Math.min( 1, Math.max( 0, number ) );
	}

	/**
	 * Zweistellige Hex-Darstellung eines Kanalwerts.
	 *
	 * @param {number} value Kanalwert.
	 * @return {string} Zwei Hex-Zeichen.
	 */
	function byteToHex( value ) {
		var hex = clampByte( value ).toString( 16 );

		return hex.length === 1 ? '0' + hex : hex;
	}

	/**
	 * Farbton in Grad, ganzzahlig gerundet.
	 *
	 * @param {number} r Rot 0-255.
	 * @param {number} g Gruen 0-255.
	 * @param {number} b Blau 0-255.
	 * @return {number} Farbton 0-360.
	 */
	function rgbToHue( r, g, b ) {
		var rn = r / 255;
		var gn = g / 255;
		var bn = b / 255;
		var max = Math.max( rn, gn, bn );
		var min = Math.min( rn, gn, bn );
		var delta = max - min;
		var hue;

		if ( delta === 0 ) {
			return 0;
		}

		if ( max === rn ) {
			hue = 60 * ( ( ( gn - bn ) / delta ) % 6 );
		} else if ( max === gn ) {
			hue = 60 * ( ( bn - rn ) / delta + 2 );
		} else {
			hue = 60 * ( ( rn - gn ) / delta + 4 );
		}

		if ( hue < 0 ) {
			hue += 360;
		}

		return Math.round( hue );
	}

	/**
	 * HSV mit ganzzahligen Grad- und Prozentwerten.
	 *
	 * @param {number} r Rot 0-255.
	 * @param {number} g Gruen 0-255.
	 * @param {number} b Blau 0-255.
	 * @return {Object} { h, s, v }
	 */
	function rgbToHsv( r, g, b ) {
		var rn = r / 255;
		var gn = g / 255;
		var bn = b / 255;
		var max = Math.max( rn, gn, bn );
		var min = Math.min( rn, gn, bn );
		var delta = max - min;
		var saturation = max === 0 ? 0 : delta / max;

		return {
			h: rgbToHue( r, g, b ),
			s: Math.round( saturation * 100 ),
			v: Math.round( max * 100 )
		};
	}

	/**
	 * HSL mit ganzzahligen Grad- und Prozentwerten.
	 *
	 * @param {number} r Rot 0-255.
	 * @param {number} g Gruen 0-255.
	 * @param {number} b Blau 0-255.
	 * @return {Object} { h, s, l }
	 */
	function rgbToHsl( r, g, b ) {
		var rn = r / 255;
		var gn = g / 255;
		var bn = b / 255;
		var max = Math.max( rn, gn, bn );
		var min = Math.min( rn, gn, bn );
		var delta = max - min;
		var lightness = ( max + min ) / 2;
		var saturation;

		if ( delta === 0 ) {
			saturation = 0;
		} else if ( lightness > 0.5 ) {
			saturation = delta / ( 2 - max - min );
		} else {
			saturation = delta / ( max + min );
		}

		return {
			h: rgbToHue( r, g, b ),
			s: Math.round( saturation * 100 ),
			l: Math.round( lightness * 100 )
		};
	}

	/**
	 * Hex-Schreibweise: achtstellig bei Alpha < 1, sonst sechsstellig.
	 *
	 * @param {number} r Rot 0-255.
	 * @param {number} g Gruen 0-255.
	 * @param {number} b Blau 0-255.
	 * @param {number} a Alpha 0-1.
	 * @return {string} Hex-Wert mit fuehrendem #.
	 */
	function rgbToHex( r, g, b, a ) {
		var hex = '#' + byteToHex( r ) + byteToHex( g ) + byteToHex( b );

		if ( a >= 1 ) {
			return hex;
		}

		return hex + byteToHex( Math.round( a * 255 ) );
	}

	/**
	 * Baut das vollstaendige Alt-Farbobjekt.
	 *
	 * @param {Object} rgb      { r, g, b, a }.
	 * @param {string} source   Eingabeweg.
	 * @param {Object} previous Bisher gespeicherter Wert oder null.
	 * @return {Object} Sechsschluesseliges Farbobjekt.
	 */
	function buildColor( rgb, source, previous ) {
		var r = clampByte( rgb.r );
		var g = clampByte( rgb.g );
		var b = clampByte( rgb.b );
		var a = clampAlpha( rgb.a === undefined ? 1 : rgb.a );
		var hsv = rgbToHsv( r, g, b );
		var hsl = rgbToHsl( r, g, b );
		var hue = hsl.h;
		var oldHue = hue;

		// react-color merkte sich den zuletzt bunten Farbton, damit ein Zug auf
		// Schwarz, Weiss oder Grau den Farbtonregler nicht auf 0 Grad
		// zurueckwirft. Unbunte Farben haben keinen eigenen Farbton — dann
		// bleibt der vorherige stehen.
		if ( hsl.s === 0 && previous && typeof previous.oldHue === 'number' && isFinite( previous.oldHue ) ) {
			oldHue = previous.oldHue;
		}

		// DIE SCHLUESSELREIHENFOLGE IST VERTRAGSBESTANDTEIL (Regel 1.6):
		// hex, rgb, hsv, hsl, source, oldHue.
		return {
			hex: rgbToHex( r, g, b, a ),
			rgb: { r: r, g: g, b: b, a: a },
			hsv: { h: hue, s: hsv.s, v: hsv.v, a: a },
			hsl: { h: hue, s: hsl.s, l: hsl.l, a: a },
			source: source,
			oldHue: oldHue
		};
	}

	/**
	 * Liest `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`, `rgb()` und `rgba()`.
	 *
	 * @param {*} input Eingabewert.
	 * @return {Object|null} { r, g, b, a } oder null.
	 */
	function parseColorString( input ) {
		var value = String( input === null || input === undefined ? '' : input ).trim();
		var match;
		var hex;
		var i;
		var expanded;

		match = /^#([0-9a-f]{3,8})$/i.exec( value );

		if ( match ) {
			hex = match[ 1 ].toLowerCase();

			if ( hex.length === 3 || hex.length === 4 ) {
				expanded = '';
				for ( i = 0; i < hex.length; i++ ) {
					expanded += hex.charAt( i ) + hex.charAt( i );
				}
				hex = expanded;
			}

			if ( hex.length !== 6 && hex.length !== 8 ) {
				return null;
			}

			return {
				r: parseInt( hex.substring( 0, 2 ), 16 ),
				g: parseInt( hex.substring( 2, 4 ), 16 ),
				b: parseInt( hex.substring( 4, 6 ), 16 ),
				// Zwei Nachkommastellen, damit der Rueckweg nach Hex denselben
				// Wert ergibt: 0x26 / 255 = 0.14902 -> 0.15 -> 0x26.
				a: hex.length === 8 ? Math.round( parseInt( hex.substring( 6, 8 ), 16 ) / 255 * 100 ) / 100 : 1
			};
		}

		match = /^rgba?\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)\s*(?:,\s*([0-9.]+)\s*)?\)$/i.exec( value );

		if ( match ) {
			return {
				r: Number( match[ 1 ] ),
				g: Number( match[ 2 ] ),
				b: Number( match[ 3 ] ),
				a: match[ 4 ] === undefined ? 1 : Number( match[ 4 ] )
			};
		}

		return null;
	}

	/*
	 * DER AUFRUF MUSS WOERTLICH `__( 'Text', 'crea-bootstrap-blocks' )` LAUTEN.
	 *
	 * `wp i18n make-pot` liest den Quelltext STATISCH und erkennt einen
	 * Uebersetzungsaufruf am NAMEN der gerufenen Funktion. Bis zum 2026-09-08
	 * stand hier ein eigener Helfer, der `wp.i18n.__()` erst INNEN rief — an
	 * der Stelle des Literals sah der Extraktor eine Variable und trug nichts
	 * ein. Sein Docblock behauptete das Gegenteil.
	 *
	 * Gemessen am 2026-09-08: kein einziger Eintrag der `.pot` nannte eine
	 * JS-Datei als Quelle. `wp i18n make-json` erzeugt seine `.json` je
	 * Quelldatei — ohne JS-Referenz also gar keine, und `wp.i18n.__()` fand im
	 * Browser nichts nachzuschlagen. `Hex` und `Reset` standen deshalb seit dem
	 * 2026-09-02 englisch im Editor.
	 *
	 * DIE WACHE BLEIBT, denn sie ist der Grund, warum es den Helfer gibt: Fehlt
	 * `wp.i18n`, gibt er den Ausgangstext zurueck, statt eine Ausnahme zu
	 * werfen — ein TypeError risse im Editor das ganze Skriptbuendel mit, also
	 * eine leere Seitenleiste statt einer englischen Beschriftung.
	 *
	 * Der Regelfall ist sie nicht mehr: `wp_set_script_translations()` traegt
	 * `wp-i18n` von sich aus in die Abhaengigkeiten des Handles nach
	 * (`WP_Scripts::set_translations()`). Sie deckt den fremden Ort ab, an dem
	 * Blockstudio dieses Feld sonst noch zeichnet.
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

	blockstudio.registerFieldType( 'creabb/color', {
		component: function ( props ) {
			var current = ( props.value && typeof props.value === 'object' && ! Array.isArray( props.value ) )
				? props.value
				: null;
			var rgb = ( current && current.rgb && typeof current.rgb === 'object' ) ? current.rgb : null;
			var hex = '';

			if ( current && typeof current.hex === 'string' ) {
				hex = current.hex;
			} else if ( rgb ) {
				hex = rgbToHex(
					clampByte( rgb.r ),
					clampByte( rgb.g ),
					clampByte( rgb.b ),
					clampAlpha( rgb.a === undefined ? 1 : rgb.a )
				);
			}

			/**
			 * Nimmt eine Farbzeichenkette entgegen und speichert das
			 * vollstaendige Objekt. Unbrauchbare Eingaben werden ignoriert,
			 * damit ein halb getipptes Hex den Wert nicht zerstoert.
			 *
			 * @param {*} input Eingabewert.
			 */
			function commit( input ) {
				var parsed = parseColorString( input );

				if ( ! parsed ) {
					return;
				}

				// Alle Bestandswerte tragen `source: "hex"`. Dieses Control kennt
				// nur den Hex- beziehungsweise Textweg und schreibt deshalb
				// immer `hex`.
				props.onChange( buildColor( parsed, 'hex', current ) );
			}

			// OHNE `label` UND `help`. Das Feld behaelt seine Huelle — sie
			// fasst drei Bedienelemente zusammen —, aber nicht die
			// Beschriftung: Blockstudio umschliesst jedes Feld mit seinem
			// eigenen `Control`, und das zeichnet `label`, `help` und
			// `description` bereits (control/index.tsx:93). Ein zweites Label
			// hier erschiene untereinander doppelt. Blockstudios eigenes
			// Farbfeld setzt aus demselben Grund keines
			// (fields/components/color.tsx: nur `<Base>` um die Palette).
			// Das `Hex` weiter unten bleibt — es beschriftet ein INNERES
			// Bedienelement, nicht das Feld.
			return el(
				BaseControl,
				{
					__nextHasNoMarginBottom: true
				},
				el( ColorPicker, {
					color: hex === '' ? '#000000' : hex,
					enableAlpha: true,
					onChange: function ( next ) {
						commit( next );
					}
				} ),
				el( TextControl, {
					label: __( 'Hex', 'crea-bootstrap-blocks' ),
					value: hex,
					disabled: !! props.disabled,
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					onChange: function ( next ) {
						commit( next );
					}
				} ),
				el(
					Button,
					{
						variant: 'tertiary',
						disabled: !! props.disabled,
						onClick: function () {
							props.onChange( props.defaultValue === undefined ? null : props.defaultValue );
						}
					},
					__( 'Reset', 'crea-bootstrap-blocks' )
				)
			);
		}
	} );
} )();
