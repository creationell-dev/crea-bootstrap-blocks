# CreaBootstrapBlocks

**Plugin Name:** CreaBootstrapBlocks  
**Plugin URI:** https://github.com/creationell-dev/crea-bootstrap-blocks  
**Description:** Rückwärtskompatibler Ersatz für das stillgelegte Plugin All Bootstrap Blocks — dieselben Blöcke, dieselben Attribute, dasselbe Markup, gebaut auf Blockstudio.  
**Version:** 1.0.0  
**Author:** creationell® – die Werbeagentur <marketing@creationell.de>  
**Author URI:** https://www.creationell.de/  
**Contributors:** creationell-dev, JPKCom  
**Tags:** bootstrap, blocks, gutenberg, layout, grid  
**Requires at least:** 6.9  
**Tested up to:** 7.1  
**Requires PHP:** 8.3  
**Requires Plugins:** blockstudio  
**Stable tag:** 1.0.0  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  
**Text Domain:** crea-bootstrap-blocks  
**Domain Path:** /languages

Ersetzt das am 18. Juni 2026 dauerhaft stillgelegte Plugin **All Bootstrap Blocks** ohne Datenverlust und ohne sichtbare Layoutänderung.

---

## Description

**CreaBootstrapBlocks setzt [Blockstudio](https://blockstudio.dev) ab Version 7.6 voraus.** Blockstudio ist das Block-Framework, auf dem sämtliche Blöcke dieses Plugins registriert und gerendert werden. Ohne aktives Blockstudio registriert CreaBootstrapBlocks keinen einzigen Block, zeigt eine Hinweismeldung im Backend und bleibt im Übrigen untätig — es gibt keinen Ersatzmodus und keine abgespeckte Variante.

Blockstudio steht unter **GPL-2.0** und ist quelloffen unter [github.com/inline0/blockstudio](https://github.com/inline0/blockstudio) verfügbar; es braucht keinen Lizenzschlüssel, um zu laufen. Es liegt allerdings **nicht auf wordpress.org** und ist in diesem Paket nicht enthalten — es wird getrennt installiert.

**CreaBootstrapBlocks** ist der rückwärtskompatible Nachfolger des eingestellten Plugins *All Bootstrap Blocks* (Blocknamensraum `areoi/*`). Es baut dessen **47 Blöcke** nach — mit identischen Attributnamen, -typen und -defaults, äquivalentem Markup, denselben CSS-Klassen und demselben generierten Inline-CSS. Bestehende Seiteninhalte werden per WP-CLI migriert; für nicht migrierte Fundstellen gibt es einen Render-Fallback und einen abschaltbaren `areoi/*`-Alias.

Bootstrap CSS und JavaScript kommen standardmäßig vom Theme. Optional liefert das Plugin eine exakt gepinnte Bootstrap-5.3.x-Fassung samt Icons mit; CSS und JavaScript stammen dabei aus derselben Version.

### Key Features

- **Rückwärtskompatibler Attributvertrag** — Attributnamen, -typen und -defaults werden 1:1 aus dem Vorgänger übernommen, auch nirgends belegte
- **Äquivalentes Markup** — Blöcke geben `creabb-*` und zusätzlich die alten `areoi-*`-Klassen aus, damit vorhandene Theme-Styles unverändert weiter greifen. Das ist eine dauerhaft unterstützte Betriebsart, kein Provisorium (siehe *Muss ich mein Theme anpassen?*)
- **Generiertes Inline-CSS** — `.block-<kennung>`-Selektoren, nach Breakpoint in `@media`-Blöcken gebündelt, mit validierten Werten
- **Legacy-Alias** — zu jedem der 47 Blöcke ein ausgeblendetes `areoi/*`-Gegenstück, abschaltbar per Konstante oder Backend-Schalter
- **WP-CLI-Migration** — `wp creabb doctor`, `migrate scan|run|verify|rollback` und `cleanup`; der Lauf ist idempotent, schreibt ein Backup und rechnet sich selbst nach
- **Eigene Blockstile und Frontend-Logik** — die fünf blockeigenen Stylesheets des Vorgängers und sein Frontend-JavaScript (Tabs, Popover, Tooltip, Modal, Collapse, Offcanvas, Toast), in Vanilla-JS statt jQuery
- **Optionale Bootstrap-Assets** — CSS, JS-Bundle und Icons einzeln zuschaltbar, standardmäßig aus, kein CDN, kein SCSS-Compiler
- **Agentendokumentation** — maschinenlesbarer Blockvertrag und Volltextreferenz unter `llm/`, plus lesende Fähigkeiten über die WordPress Abilities API
- **Kein Lizenz-Gate, keine Telemetrie** — GPL-2.0-or-later, alle Funktionen dauerhaft aktiv

**Geplant, noch nicht enthalten:** ein Self-Hosted-Updater, der Updates direkt aus GitHub-Releases mit SHA-256-Manifest-Verifikation bezieht.

---

## Installation

1. **Blockstudio ≥ 7.6 installieren und aktivieren.** Ohne diese Abhängigkeit lässt sich CreaBootstrapBlocks nicht aktivieren (`Requires Plugins`-Header) und registriert keine Blöcke.
2. Das ZIP des jüngsten Releases herunterladen und unter *Plugins → Installieren → Plugin hochladen* einspielen.
3. Plugin aktivieren.
4. Unter *Design → CreaBootstrapBlocks* prüfen, ob die Diagnose Blockstudio erkennt.
5. Bestehende `areoi/*`-Inhalte rendern ab sofort über die mitgelieferten Aliasblöcke weiter — **es ist kein Eingriff nötig, um eine bestehende Seite lauffähig zu halten.** Für die dauerhafte Umstellung siehe den nächsten Abschnitt.

---

## Vom Alt-Plugin auf CreaBootstrapBlocks umstellen

Die Umstellung tauscht **nur das Plugin**. Inhalte, Theme und Layout bleiben, wie sie sind: Der Blocknamensraum in `post_content` wird umgeschrieben, die Attribute wandern in den Blockstudio-Container, und die alten `areoi-*`-CSS-Klassen werden weiterhin ausgegeben. Am Theme ist nichts zu tun.

**Beide Plugins laufen während der Umstellung nebeneinander.** Das ist beabsichtigt und für die Schritte 3 bis 6 sogar nötig.

### Vorbereitung

```bash
# 1 — Vorbedingungen. Schreibt nichts.
wp creabb doctor

# 2 — Inventar: welche areoi-Blöcke, in welchen Fundstellen, wie oft.
wp creabb migrate scan
wp creabb migrate scan --format=json > inventar-vorher.json
```

`doctor` gibt zehn Zeilen aus. Zwei davon dürfen an dieser Stelle `warn` sein und sind es meist auch: *Alt-Plugin (all-bootstrap-blocks)* — es läuft absichtlich noch mit — und *Legacy-CSS-Klassen*, wenn das Theme gegen `areoi-*` stylt. Jede rote Zeile beendet den Lauf.

**Nehmen Sie die Seite für die Dauer des Laufs vom Netz.** `migrate run` hat keine Nebenläufigkeitssicherung: Wer währenddessen einen Beitrag speichert, dessen Wert überschreibt der Lauf.

### Durchführen

```bash
# 3 — Trockenlauf. Schreibt nichts, rechnet aber vollständig nach.
wp creabb migrate run --dry-run

# 4 — Migration mit Backup.
wp creabb migrate run --backup=/pfad/zum/backup-JJJJ-MM-TT.sql.gz

# 5 — Nachkontrolle. Darf null areoi-Treffer melden.
wp creabb migrate verify
```

**`--backup` erwartet einen Dateipfad, kein Verzeichnis.** Das Kommando weist ein bereits vorhandenes Ziel ab — es zu überschreiben zerstörte den Rückweg eines früheren Laufs.

Der Lauf ist **idempotent**: Blöcke, die bereits einen `blockstudio`-Schlüssel tragen, werden übersprungen. Ein zweiter Durchgang ändert nichts.

Angefasst wird ausschließlich der Blockbegrenzer. Alles zwischen den Begrenzern bleibt Byte für Byte stehen; eine vollständige Reserialisierung des Contents findet nicht statt.

### Nachweisen

```bash
# 6 — HTML und Inline-CSS vor und nach der Migration vergleichen.
wp creabb snapshot urls --out=urls.txt
wp creabb snapshot create --out=snapshot-vorher --urls=urls.txt   # VOR Schritt 4
wp creabb snapshot create --out=snapshot-nachher --urls=urls.txt  # nach Schritt 5
wp creabb snapshot diff snapshot-vorher snapshot-nachher
```

Der Diff normalisiert Nonces, Cache-Buster und Zeitstempel weg und meldet jede verbleibende Abweichung. Die Reihenfolge innerhalb von `class`-Attributen wird **nicht** normalisiert — sie ist Teil des Vertrags, und eine Verschiebung soll auffallen.

### Abschließen

```bash
# 7 — Alt-Plugin deaktivieren und entfernen.
wp plugin deactivate all-bootstrap-blocks

# 8 — Alt-Optionen räumen. Erst nach der Abnahme: vorher sind sie der Rückweg.
wp creabb cleanup --legacy-options
```

Ab Schritt 7 rendert ausschließlich CreaBootstrapBlocks. `wp creabb cleanup` weigert sich, solange `migrate verify` nicht sauber durchläuft.

### Wenn etwas schiefgeht

```bash
wp creabb migrate rollback --from=/pfad/zum/backup-JJJJ-MM-TT.sql.gz
```

Danach das Alt-Plugin wieder aktivieren. Der Rückweg ist so lange offen, wie Schritt 8 nicht gelaufen ist.

---

## FAQ

**Brauche ich eine kostenpflichtige Blockstudio-Lizenz?**
Nein. Blockstudio steht unter GPL-2.0, ist quelloffen unter [github.com/inline0/blockstudio](https://github.com/inline0/blockstudio) verfügbar und läuft ohne Lizenzschlüssel. Es ist aber die Laufzeitgrundlage aller Blöcke, liegt nicht auf wordpress.org und ist in diesem Paket nicht enthalten — installieren müssen Sie es also selbst.

**Muss ich mein Theme anpassen?**
Nein. Solange `CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES` auf `true` steht — und das ist der Standard —, geben alle Blöcke die alten `areoi-*`-Klassen zusätzlich zu den neuen `creabb-*`-Klassen aus, an derselben Position im `class`-Attribut. Ein Theme, das gegen `.areoi-element`, `.areoi-element.container` oder `.areoi-has-url` stylt, greift unverändert weiter. **Das ist eine dauerhaft unterstützte Betriebsart.** Wer sein Theme irgendwann auf `creabb-*` umstellt, ergänzt dort zuerst die neuen Selektoren, rollt aus, sieht nach — und schaltet erst danach die Konstante auf `false`. Umgekehrt verlöre die Seite zwischen beiden Schritten ihr Layout.

**Können beide Plugins gleichzeitig laufen?**
Ja, und während der Umstellung ist es nötig. Solange beide aktiv sind, drucken beide ihr generiertes Inline-CSS in denselben Kopf; bei gleicher Spezifität gewinnt die später gedruckte Regel. Eine Abnahme des erzeugten CSS ist deshalb erst aussagekräftig, **nachdem** das Alt-Plugin deaktiviert ist (Schritt 7).

**Bringt das Plugin Bootstrap mit?**
Optional. Standardmäßig sind alle drei Asset-Schalter aus, Bootstrap kommt dann vom Theme. Wer sie einschaltet, bekommt eine exakt gepinnte 5.3.x-Fassung, bei der CSS und JavaScript aus derselben Version stammen.

**Ändert die Migration meine Inhalte?**
Sie ersetzt den Blocknamensraum und hebt die Attribute in den Blockstudio-Container. Alles zwischen den Blockbegrenzern bleibt Byte für Byte stehen. Der Lauf ist idempotent und wird über einen Schlüssel- und Wertevergleich je `block_id` gegen das Backup verifiziert.

**Was passiert mit nicht migrierten Seiten?**
Sie rendern weiter: Zu jedem Block gibt es ein ausgeblendetes `areoi/*`-Gegenstück, und ein Render-Fallback hebt flach gespeicherte Attribute zur Laufzeit in den Blockstudio-Container. Im Editor gehen die Werte allerdings verloren, sobald ein solcher Block gespeichert wird — der Fallback ist ein Netz, kein Ersatz für die Migration.

**Werden auch Revisionen migriert?**
Ja, standardmäßig. `--skip-revisions` lässt sie stehen; dann braucht auch die Nachkontrolle das Flag: `wp creabb migrate verify --skip-revisions`.

**Was ist mit FSE-Templates im Theme?**
`templates/*.html` und `parts/*.html` liegen als Dateien im Theme, nicht in der Datenbank — die Migration fasst sie nicht an. `wp creabb doctor` meldet, ob dort `areoi/`-Blöcke stehen; sie sind von Hand im Theme-Repo umzustellen.

---

## Developer Reference

### Konstanten

Alle Konstanten lassen sich in der `wp-config.php` vorbelegen und haben dann Vorrang vor der Backend-Einstellung.

| Konstante | Standardwert | Zweck |
|---|---|---|
| `CREA_BOOTSTRAP_BLOCKS_VERSION` | `1.0.0` | Aktuelle Plugin-Version |
| `CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES` | `true` | Gibt zusätzlich zu `creabb-*` auch die alten `areoi-*`-Klassen aus. Darf dauerhaft `true` bleiben |
| `CREA_BOOTSTRAP_BLOCKS_LEGACY_BLOCKS` | `true` | Registriert zu jedem Block ein ausgeblendetes `areoi/*`-Gegenstück |
| `CREA_BOOTSTRAP_BLOCKS_DEBUG` | `false` | Schaltet die Protokollierung des Plugins frei |

### Mitgelieferte Fremdbestandteile

Unter `assets/vendor/bootstrap/` liegen Bootstrap und Bootstrap Icons, beide unter der MIT-Lizenz und damit mit GPL-2.0-or-later verträglich. Die Lizenztexte, die Bezugsquellen (`UPSTREAM.txt`) und die Prüfsummen jeder ausgelieferten Datei (`CHECKSUMS.txt`) liegen daneben. CSS und JavaScript stammen zwingend aus derselben Bootstrap-Version.

---

## Changelog

### 1.0.0

Erste veröffentlichte Fassung. (Eine Version 0.1.0 ist nie ausgeliefert worden; ihr
Changelog-Eintrag ist hier aufgegangen.)

- **Alle 47 Blöcke** des Vorgängers samt vollständigem Attributvertrag — Layout (`container`, `row`, `column`, `div`), Karten und Buttons, die drei Stripblöcke sowie die 33 Blöcke der zweiten Ausbaustufe (Akkordeon, Navigation, die zehn Overlays, Karussell, Fortschritt, die fünf großen Strips, `breadcrumb` und `post-grid`).
- Zu jedem Block ein ausgeblendeter `areoi/*`-Aliasblock, abschaltbar über Konstante oder Backend-Schalter.
- **WP-CLI-Migration:** `wp creabb doctor`, `migrate scan|run|verify|rollback` und `cleanup --legacy-options`, dazu `snapshot urls|create|diff` für den Vorher-Nachher-Vergleich von HTML und Inline-CSS.
- Generiertes Inline-CSS je Breakpoint, Render-Fallback für flach gespeicherte Attribute, Backend unter *Design → CreaBootstrapBlocks* mit den Tabs Assets, Kompatibilität und Diagnose.
- Die fünf blockeigenen Stylesheets des Vorgängers und sein Frontend-JavaScript, in Vanilla-JS statt jQuery.
- Übersetzungen für `de_DE` und `de_DE_formal`, Agentendokumentation unter `llm/`, sechs lesende Fähigkeiten der WordPress Abilities API.
- Optionale Bootstrap-Assets (CSS, JS-Bundle, Icons), alle drei standardmäßig aus.
- **Editor-Seitenleiste:** Die Felder stehen in benannten Gruppen statt als lange Liste — 39 der 47 Blöcke haben sie, die übrigen führen ein oder zwei Felder. Jedes Feld trägt einen Hilfetext, die wichtigste Gruppe steht oben und startet aufgeklappt, und die Breakpoint-Optionen liegen in einer Reiterleiste XS bis XXL.
- **Vorschaubilder im Inserter** für alle 47 Blöcke, als SVG-Schema statt als Screenshot (188 kB statt 2,2 MB); über `CREA_BOOTSTRAP_BLOCKS_INSERTER_PREVIEWS` abschaltbar.
- **Drei eigene Editor-Bedienelemente**, damit die gespeicherte Form dem Vertrag entspricht: Auswahllisten und Farbwähler schreiben denselben Wert wie das Vorgängerplugin, und die Bildauswahl von `media-grid-image` öffnet die Mediathek, statt eine Anhang-ID zu verlangen.
- **Kindblock-Schranken wie im Original:** 21 Blöcke geben vor, was in sie hinein darf, und füllen sich beim Einfügen mit ihrer Grundstruktur.
- Das Plugin-CSS wirkt jetzt auch in der Editor-Leinwand — Abstände und Layout sehen dort aus wie im Frontend.
- Noch nicht enthalten: der Self-Hosted-Updater.
