<?php
/**
 * WP-CLI command registration for CreaBootstrapBlocks.
 *
 * Part of the normal include chain, but a no-op outside WP-CLI.
 *
 * Die Datei wird bei JEDEM Seitenaufruf eingebunden — auch im Frontend. Sie
 * kehrt deshalb zurueck, BEVOR sie eine Kommandoklasse laedt: Ohne WP-CLI
 * kostet sie zwei `defined()`-Aufrufe und sonst nichts.
 *
 * Die Kommandogruppe `wp creabb` entsteht implizit mit dem ersten
 * Unterkommando; WP-CLI legt den Namensraum an, sobald ein Kommando darunter
 * registriert wird. Spaetere Arbeitsgruppen ergaenzen hier je eine
 * `require_once`- und eine `add_command()`-Zeile fuer `creabb migrate`,
 * `creabb cleanup` und `creabb snapshot` — diese Datei bleibt die einzige
 * Stelle, an der ein Kommando dieses Plugins entsteht.
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

require_once __DIR__ . '/class-doctor-command.php';

/*
 * DER UMSCHREIBER GEHOERT DAZU. `Migration_Support` und die Eval-Skripte rufen
 * `Creationell\BootstrapBlocks\Migrator` auf; die Klasse steht in KEINER
 * Ladeliste des Plugins, weil sie im Frontend nichts zu suchen hat. Ohne diese
 * Zeile endet `wp creabb migrate scan` im Fatal Error — und zwar erst auf der
 * Zielinstanz, weil jede Suite die Datei selbst einbindet.
 */
require_once __DIR__ . '/../class-migrator.php';
require_once __DIR__ . '/class-migration-support.php';
require_once __DIR__ . '/class-migrate-command.php';
require_once __DIR__ . '/class-cleanup-command.php';

/*
 * DIE DREI SNAPSHOT-KLASSEN GEHOEREN EBENSO DAZU. `Snapshot_Command` ruft
 * `Snapshot_Urls`, `Snapshot_Diff` und `Migration_Support` auf; keine der drei
 * steht in einer Ladeliste des Plugins, weil sie im Frontend nichts zu suchen
 * haben. Ohne diese Zeilen endet `wp creabb snapshot urls` im Fatal Error —
 * und zwar erst auf der Zielinstanz, weil jede Suite ihre Klassen selbst
 * einbindet (E-110).
 */
require_once __DIR__ . '/class-snapshot-urls.php';
require_once __DIR__ . '/class-snapshot-diff.php';
require_once __DIR__ . '/class-snapshot-command.php';

WP_CLI::add_command( 'creabb doctor', new \Creationell\BootstrapBlocks\CLI\Doctor_Command() );
WP_CLI::add_command( 'creabb migrate', new \Creationell\BootstrapBlocks\CLI\Migrate_Command() );
WP_CLI::add_command( 'creabb cleanup', new \Creationell\BootstrapBlocks\CLI\Cleanup_Command() );
WP_CLI::add_command( 'creabb snapshot', new \Creationell\BootstrapBlocks\CLI\Snapshot_Command() );
