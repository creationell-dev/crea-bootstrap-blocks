/**
 * Editor control for the custom Blockstudio field type `creabb/select`.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * Blockstudios eigenes `select`-Feld (und ebenso `radio`) speichert
 * `{ "value": "col-12", "label": "12" }`. Der Bestand des Alt-Plugins enthaelt
 * an derselben Stelle den ROHEN String `"col-12"`. Ein Block, der im Editor
 * angefasst wird, schriebe damit eine andere Datenform als ein unangetasteter —
 * und die Renderhelfer, die auf `'Default' === $value` pruefen, liefen ins
 * Leere.
 *
 * Dieses Control speichert deshalb ausschliesslich den Optionswert als String.
 * Ein bereits gespeichertes `{value,label}`-Objekt aus einem frueheren
 * Blockstudio-Feld wird beim Lesen entpackt, damit ein solcher Block sich
 * reparieren laesst, sobald ihn jemand anfasst.
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
	var SelectControl = wp.components.SelectControl;

	/**
	 * Liest den gespeicherten Wert als String.
	 *
	 * @param {*} value Gespeicherter Wert.
	 * @return {string} Optionswert.
	 */
	function readValue( value ) {
		if ( typeof value === 'string' ) {
			return value;
		}

		// Nachlass eines Blockstudio-select-Feldes: { value, label }.
		if ( value && typeof value === 'object' && typeof value.value === 'string' ) {
			return value.value;
		}

		if ( typeof value === 'number' ) {
			return String( value );
		}

		return '';
	}

	blockstudio.registerFieldType( 'creabb/select', {
		component: function ( props ) {
			var options = Array.isArray( props.options ) ? props.options : [];

			// LABEL UND HELP AUSDRUECKLICH AUF `false`, KEIN EIGENES
			// BaseControl. Blockstudio umschliesst JEDES Feld — auch ein per
			// `registerFieldType()` angemeldetes — mit seinem eigenen
			// `Control`, und das zeichnet `label`, `help` und `description`
			// bereits (control/index.tsx:93). Wer hier ein zweites Label
			// setzt, bekommt jede Beschriftung zweimal untereinander; im
			// Editor gemessen an `creabb/container`: „Containerbreite" stand
			// in zwei aufeinanderfolgenden Zeilen. Blockstudios eigenes
			// Select macht es deshalb genauso — `label={false}`,
			// `help={false}` (fields/components/select.tsx).
			return el( SelectControl, {
				className: 'components-base-control',
				label: false,
				help: false,
				value: readValue( props.value ),
				options: options,
				disabled: !! props.disabled,
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
				onChange: function ( next ) {
					// AUSSCHLIESSLICH der rohe Optionswert. Kein Objekt,
					// keine Normalisierung, keine Uebersetzung — der Wert
					// landet so im Blockkommentar.
					props.onChange( typeof next === 'string' ? next : readValue( next ) );
				}
			} );
		}
	} );
} )();
