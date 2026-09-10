<?php
/**
 * Legacy alias template for `areoi/post-grid`.
 *
 * GENERIERT VON bin/build-aliases.php — NICHT VON HAND AENDERN.
 *
 * Der Aliasblock rendert mit dem Template seines Originals. `require`, nicht
 * `require_once`: Zwei Bloecke desselben Typs auf einer Seite muessen zweimal
 * rendern. Das eingebundene Template sieht denselben Scope, also
 * $attributes, $block und $inner_blocks von Blockstudio — NICHT $content,
 * das im Frontend immer leer ist (E-33).
 *
 * @package Creationell\BootstrapBlocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require CREA_BOOTSTRAP_BLOCKS_DIR . 'blocks/post-grid/index.php';
