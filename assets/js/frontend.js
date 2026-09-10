/**
 * Frontend behaviour of the interactive blocks.
 *
 * ERSATZ FUER assets/js/bootstrap-extra.js DES ALT-PLUGINS. Das Original haengt
 * die Datei per wp_add_inline_script() an sein Bootstrap-Handle. Sie leistet
 * drei Dinge, die Bootstrap 5 nicht von selbst tut:
 *
 *   1. Popover und Tooltip werden instanziiert. Bootstrap 5 tut das aus
 *      Ruecksicht auf die Startzeit NICHT automatisch — ohne diese Schleife
 *      passiert beim Ueberfahren eines `data-bs-toggle="tooltip"` nichts.
 *   2. Ein gewoehnlicher Link `href="#kennung"` oeffnet das Modal, das
 *      Collapse, das Offcanvas oder den Toast mit dieser Kennung. Bootstrap
 *      verlangt dafuer sonst `data-bs-toggle` samt `data-bs-target`.
 *   3. Die Reiterlogik von `creabb/tabs`. Ohne sie stehen ALLE Reiterinhalte
 *      gleichzeitig sichtbar untereinander, und ein Klick auf einen Reiter tut
 *      nichts — der Block ist unbenutzbar.
 *
 * OHNE JQUERY. Das Original benutzt jQuery fuer Punkt 3. Hier steht dafuer
 * gewoehnliches DOM; das spart eine Abhaengigkeit, die das Plugin sonst
 * nirgends braucht. Die Selektorsemantik ist dabei Zeichen fuer Zeichen
 * dieselbe — siehe die Anmerkung zu `:first-of-type` weiter unten.
 *
 * BEIDE KLASSENSAETZE. Die Bloecke geben `creabb-tabs` UND `areoi-tabs` aus,
 * solange der Schalter `legacy_classes` an ist. Diese Datei kann den Schalter
 * nicht kennen und sucht deshalb beide. Waere hier nur `areoi-tabs` gesucht,
 * fiele die Reiterlogik in dem Moment aus, in dem eine Seite ihre Themes
 * umgestellt hat und die Legacy-Klassen abschaltet.
 *
 * @package Creationell\BootstrapBlocks
 */

( function () {
	'use strict';

	/**
	 * Die Reiterhuellen — beide Klassensaetze, siehe oben.
	 */
	var TABS = '.creabb-tabs, .areoi-tabs';

	/**
	 * Ist Bootstrap da?
	 *
	 * Alle drei Bootstrap-Schalter des Plugins sind standardmaessig AUS; die
	 * uebliche Lage ist, dass das Theme Bootstrap mitbringt. Fehlt es ganz,
	 * duerfen die Punkte 1 und 2 nicht mit einem TypeError abbrechen — sonst
	 * risse der Fehler auch Punkt 3 mit, und der braucht Bootstrap gar nicht.
	 */
	function bs() {
		return typeof window !== 'undefined' && window.bootstrap ? window.bootstrap : null;
	}

	/**
	 * Direkte Kindelemente eines Knotens, die ein `div` sind.
	 *
	 * Entspricht `$( container ).find( '> div' )` des Originals. `querySelectorAll`
	 * taugt dafuer nicht: Es sucht den ganzen Teilbaum und traefe damit auch die
	 * `div` INNERHALB eines Reiterinhalts.
	 */
	function childDivs( node ) {
		var out = [];
		var kids = node.children || [];
		var i;

		for ( i = 0; i < kids.length; i++ ) {
			if ( 'DIV' === kids[ i ].tagName ) {
				out.push( kids[ i ] );
			}
		}

		return out;
	}

	/**
	 * Das Ziel eines `#kennung`-Verweises innerhalb einer Huelle.
	 *
	 * BEWUSST BEWACHT. Das Original ruft `$( this ).find( href )` ungeprueft.
	 * Steht dort eine gewoehnliche Adresse statt eines Fragments, wirft der
	 * Selektor — und weil das im `each` des Originals passiert, bricht der
	 * ganze `ready`-Rumpf ab: Auch die Klickbehandlung wird dann nie
	 * registriert. Hier faellt in diesem Fall nur dieser eine Reiter aus.
	 */
	function fragmentTarget( container, href ) {
		if ( ! href || '#' !== href.charAt( 0 ) || href.length < 2 ) {
			return null;
		}

		try {
			return container.querySelector( href );
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Punkt 1 — Popover und Tooltip instanziieren.
	 */
	function initOverlays() {
		var api = bs();

		if ( ! api ) {
			return;
		}

		[ [ 'popover', 'Popover' ], [ 'tooltip', 'Tooltip' ] ].forEach( function ( pair ) {
			if ( ! api[ pair[ 1 ] ] ) {
				return;
			}

			Array.prototype.slice
				.call( document.querySelectorAll( '[data-bs-toggle="' + pair[ 0 ] + '"]' ) )
				.forEach( function ( el ) {
					new api[ pair[ 1 ] ]( el );
				} );
		} );
	}

	/**
	 * Punkt 2 — `href="#kennung"` verdrahten.
	 *
	 * Die Optionen sind die des Originals, einschliesslich `keyboard` bei
	 * Collapse, Offcanvas und Toast. Bootstrap wertet den Schluessel dort nicht
	 * aus; er steht hier, weil der Vertrag aequivalentes Verhalten verlangt und
	 * ein wirkungsloser Schluessel nichts daran aendert.
	 *
	 * Das Original erzeugt bei JEDEM Klick eine neue Instanz. Das bleibt so:
	 * Bootstrap haelt seine Instanzen an einer Datenkarte am Element und
	 * ersetzt den Eintrag; ein `getOrCreateInstance` waere sauberer, aber eben
	 * ein anderes Verhalten.
	 */
	function initLinkTargets() {
		var api = bs();

		if ( ! api ) {
			return;
		}

		var wiring = [
			{ cls: 'modal', component: 'Modal', options: { keyboard: true }, method: 'show' },
			{ cls: 'collapse', component: 'Collapse', options: { keyboard: false }, method: 'toggle' },
			{ cls: 'offcanvas', component: 'Offcanvas', options: { keyboard: false }, method: 'show' },
			{ cls: 'toast', component: 'Toast', options: { keyboard: false }, method: 'show' }
		];

		wiring.forEach( function ( spec ) {
			if ( ! api[ spec.component ] ) {
				return;
			}

			var elements = Array.prototype.slice.call( document.getElementsByClassName( spec.cls ) );

			elements.forEach( function ( element ) {
				var id = element.getAttribute( 'id' );

				if ( ! id ) {
					return;
				}

				var links = Array.prototype.slice.call(
					document.querySelectorAll( '[href="#' + id + '"]' )
				);

				links.forEach( function ( link ) {
					link.addEventListener(
						'click',
						function ( event ) {
							event.preventDefault();

							var target = document.getElementById( id );

							if ( ! target ) {
								return;
							}

							var instance = new api[ spec.component ]( target, spec.options );

							instance[ spec.method ]();
						},
						false
					);
				} );
			} );
		} );
	}

	/**
	 * Punkt 3 — die Reiter.
	 *
	 * `:first-of-type` IST HIER KEIN SCHOENHEITSFEHLER, SONDERN DIE REGEL.
	 * Das Original sucht `.nav a.active:first-of-type`. Das ist NICHT „der
	 * erste Treffer", sondern „ein aktiver Link, der zugleich das erste `a`
	 * unter seinen Geschwistern ist". `creabb/nav-and-tab-item` rendert nackte
	 * `<a>` als direkte Geschwister im `<nav class="nav">` — trifft die Regel
	 * also nur den ERSTEN Link. Ist ein spaeterer Reiter als aktiv markiert,
	 * oeffnet das Original nichts und nimmt ihm zusaetzlich das `active`
	 * (die Zeile mit `:not(:first-of-type)`).
	 *
	 * Das ist nachgebaut, nicht repariert. Vertragsebene 3 verlangt
	 * aequivalentes Verhalten, nicht das bessere; wer es aendert, aendert das
	 * Aussehen bestehender Seiten.
	 *
	 * Diese Funktion braucht Bootstrap NICHT — sie schiebt nur Klassen.
	 */
	function initTabs() {
		Array.prototype.slice.call( document.querySelectorAll( TABS ) ).forEach( function ( container ) {
			var active = container.querySelector( '.nav a.active:first-of-type' );

			Array.prototype.slice
				.call( container.querySelectorAll( '.nav a.active:not(:first-of-type)' ) )
				.forEach( function ( link ) {
					link.classList.remove( 'active' );
				} );

			childDivs( container ).forEach( function ( panel ) {
				panel.classList.add( 'tab-pane', 'd-none' );
			} );

			var target = active ? fragmentTarget( container, active.getAttribute( 'href' ) ) : null;

			if ( target ) {
				target.classList.remove( 'd-none' );
			}
		} );
	}

	/**
	 * Der Klick auf einen Reiter.
	 *
	 * Delegiert am Dokument, wie im Original — so wirken auch Reiter, die erst
	 * nach dem Laden in die Seite kommen.
	 */
	function bindTabClicks() {
		document.addEventListener( 'click', function ( event ) {
			var node = event.target;

			if ( ! node || ! node.closest ) {
				return;
			}

			var link = node.closest( 'a' );

			if ( ! link ) {
				return;
			}

			var container = link.closest( TABS );
			var nav = link.closest( '.nav' );

			// `.creabb-tabs .nav a` — das `a` muss unter einem `.nav` liegen,
			// und dieses `.nav` unter der Huelle.
			if ( ! container || ! nav || ! container.contains( nav ) ) {
				return;
			}

			var href = link.getAttribute( 'href' );

			if ( ! href || '#' !== href.charAt( 0 ) ) {
				return;
			}

			event.preventDefault();

			var target = fragmentTarget( container, href );

			Array.prototype.slice.call( container.querySelectorAll( '.nav a' ) ).forEach( function ( other ) {
				other.classList.remove( 'active' );
			} );

			link.classList.add( 'active' );

			/*
			 * ERST ALLES ZU, DANN DAS ZIEL AUF — auch wenn es das Ziel nicht
			 * gibt. Das Original prueft an dieser Stelle ein jQuery-Objekt, und
			 * ein leeres jQuery-Objekt ist wahr; es schliesst also ebenfalls
			 * alles und oeffnet dann nichts.
			 */
			childDivs( container ).forEach( function ( panel ) {
				panel.classList.add( 'd-none' );
			} );

			if ( target ) {
				target.classList.remove( 'd-none' );
			}
		} );
	}

	function start() {
		initOverlays();
		initLinkTargets();
		initTabs();
		bindTabClicks();
	}

	// Die Datei laeuft mit `defer` im Fussbereich, der Baum steht also. Der
	// Zweig darunter ist die Absicherung fuer den Fall, dass sie jemand anders
	// einbindet.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
