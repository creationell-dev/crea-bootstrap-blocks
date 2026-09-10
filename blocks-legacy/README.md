# blocks-legacy — die `areoi/*`-Aliasbloecke

**Dieses Verzeichnis ist erzeugt.** Es entsteht vollstaendig aus `blocks/` durch

```bash
php bin/build-aliases.php
```

Von Hand geaenderte Dateien werden beim naechsten Lauf ueberschrieben;
`php bin/build-aliases.php --check` meldet jede Abweichung und endet mit
Exit-Code 1.

## Wozu

Zu jedem `creabb/*`-Block gehoert ein `areoi/*`-Gegenstueck mit identischem
Schema und Template. Es haelt nicht migrierten Bestandscontent renderfaehig
und taucht nicht im Inserter auf. Registriert wird das Verzeichnis nur, wenn
der Schalter `legacy_blocks` aktiv ist — Design -> CreaBootstrapBlocks, Tab
Kompatibilitaet, oder `CREA_BOOTSTRAP_BLOCKS_LEGACY_BLOCKS` in der
`wp-config.php`.

## Was umgeschrieben wird

| Schluessel | Aenderung |
|---|---|
| `name` | `creabb/<slug>` wird `areoi/<slug>` |
| `parent` | jeder `creabb/x`-Eintrag wird um `areoi/x` ergaenzt |
| `supports.inserter` | auf `false` gesetzt |

Alles andere bleibt unveraendert — insbesondere `blockstudio.attributes`,
`providesContext` und `usesContext`. Der Kontextschluessel heisst in beiden
Namensraeumen `creabb/isFlex`.

## index.php

Das Alias-Template enthaelt keine Renderlogik. Es bindet
`blocks/<slug>/index.php` ein, damit es je Block genau eine Renderquelle gibt.
