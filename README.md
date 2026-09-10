# CreaBootstrapBlocks

**Plugin Name:** CreaBootstrapBlocks  
**Plugin URI:** https://github.com/creationell-dev/crea-bootstrap-blocks  
**Description:** Rückwärtskompatibler Ersatz für das stillgelegte Plugin All Bootstrap Blocks — dieselben Blöcke, dieselben Attribute, dasselbe Markup, gebaut auf Blockstudio.  
**Version:** 1.0.1  
**Author:** creationell® – die Werbeagentur <marketing@creationell.de>  
**Author URI:** https://www.creationell.de/  
**Contributors:** creationell-dev, JPKCom  
**Tags:** bootstrap, blocks, gutenberg, layout, grid  
**Requires at least:** 6.9  
**Tested up to:** 7.1  
**Requires PHP:** 8.3  
**Requires Plugins:** blockstudio  
**Stable tag:** 1.0.1  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  
**Text Domain:** crea-bootstrap-blocks  
**Domain Path:** /languages

Ersetzt das am 18. Juni 2026 dauerhaft stillgelegte Plugin **All Bootstrap Blocks** ohne Datenverlust und ohne sichtbare Layoutänderung.

---

## Description

**CreaBootstrapBlocks setzt [Blockstudio](https://blockstudio.dev) ab Version 7.6 voraus.** Blockstudio ist das Block-Framework, auf dem sämtliche Blöcke dieses Plugins registriert und gerendert werden. Ohne aktives Blockstudio registriert CreaBootstrapBlocks keinen einzigen Block, zeigt eine Hinweismeldung im Backend und bleibt im Übrigen untätig — es gibt keinen Ersatzmodus und keine abgespeckte Variante.

Blockstudio steht unter **GPL-2.0** und ist quelloffen unter [github.com/inline0/blockstudio](https://github.com/inline0/blockstudio) verfügbar; es braucht keinen Lizenzschlüssel, um zu laufen. Es liegt allerdings **nicht auf wordpress.org** und ist in diesem Paket nicht enthalten — es wird getrennt installiert.

**CreaBootstrapBlocks** ist der rückwärtskompatible Nachfolger des eingestellten Plugins *All Bootstrap Blocks* (Blocknamensraum `areoi/*`). Es baut dessen **47 Blöcke** nach — mit identischen Attributnamen, -typen und -defaults, äquivalentem Markup, denselben CSS-Klassen und demselben generierten Inline-CSS. Bestehende Seiteninhalte werden **einmalig per WP-CLI migriert** — dieser Schritt gehört zur Umstellung dazu und ist im Abschnitt *Installation* als Teil B beschrieben. Für noch nicht migrierte Fundstellen gibt es übergangsweise einen Render-Fallback und einen abschaltbaren `areoi/*`-Alias.

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

**Es gibt zwei Ausgangslagen, und sie verlangen unterschiedlich viel Arbeit.** Lesen Sie zuerst diese Tabelle:

| Ihre Ausgangslage | Was zu tun ist |
|---|---|
| **Neue Seite**, auf der noch nie Blöcke aus All Bootstrap Blocks standen | Nur **Teil A**. Danach sind Sie fertig. |
| **Bestehende Seite** mit `areoi/*`-Inhalten | **Teil A und Teil B.** Teil A allein genügt nicht — siehe den Kasten. |

> **Achtung: Der naheliegende Weg ist der falsche.**
>
> *Neues Plugin hochladen → aktivieren → altes Plugin deaktivieren* — damit ist die Umstellung **nicht** erledigt.
>
> Die Seite sieht danach im Frontend richtig aus, und genau darin liegt die Gefahr. Ihre Inhalte stehen weiterhin als `areoi/*` in der Datenbank und werden nur über ein mitgeliefertes Notfallnetz gerendert. **Sobald jemand einen solchen Block im Editor öffnet und die Seite speichert, sind dessen Einstellungen verloren** — Abstände, Farben, Spaltenbreiten, alles.
>
> Damit die Inhalte dauerhaft sicher sind, muss der Blocknamensraum einmalig in der Datenbank umgeschrieben werden. Das ist **Teil B**, es geschieht mit einem einzigen Befehl, und das alte Plugin muss dafür bis zum vorletzten Schritt **aktiv bleiben**.

### Teil A — Plugin installieren

**A1.** **Blockstudio ≥ 7.6 installieren und aktivieren.** Ohne diese Abhängigkeit lässt sich CreaBootstrapBlocks nicht aktivieren (`Requires Plugins`-Header) und registriert keine Blöcke.

**A2.** Das ZIP des jüngsten Releases herunterladen und unter *Plugins → Installieren → Plugin hochladen* einspielen.

**A3.** CreaBootstrapBlocks aktivieren. **Das alte Plugin All Bootstrap Blocks bleibt dabei aktiv.** Beide Plugins nebeneinander zu betreiben ist beabsichtigt und für Teil B sogar nötig.

**A4.** Unter *Design → CreaBootstrapBlocks* prüfen, ob die Diagnose Blockstudio erkennt.

Ihre Seite läuft ab hier unverändert weiter — es ist kein Eingriff nötig, um sie lauffähig zu **halten**. Stehen auf der Seite `areoi/*`-Inhalte, machen Sie jetzt mit Teil B weiter; sonst sind Sie fertig.

### Teil B — Bestehende Inhalte umstellen

Die Umstellung tauscht **nur das Plugin**. Inhalte, Theme und Layout bleiben, wie sie sind: Der Blocknamensraum in `post_content` wird umgeschrieben, die Attribute wandern in den Blockstudio-Container, und die alten `areoi-*`-CSS-Klassen werden weiterhin ausgegeben. **Am Theme ist nichts zu tun.**

Teil B läuft über die Kommandozeile (WP-CLI); über die WordPress-Oberfläche lässt er sich nicht erledigen. Rechnen Sie mit wenigen Minuten.

#### Vorbereiten

```bash
# B1 — Vorbedingungen prüfen. Schreibt nichts.
wp creabb doctor

# B2 — Inventar: welche areoi-Blöcke, in welchen Fundstellen, wie oft.
wp creabb migrate scan
wp creabb migrate scan --format=json > inventar-vorher.json
```

`doctor` gibt zehn Zeilen aus. Zwei davon dürfen an dieser Stelle `warn` sein und sind es meist auch: *Alt-Plugin (all-bootstrap-blocks)* — es läuft absichtlich noch mit — und *Legacy-CSS-Klassen*, wenn das Theme gegen `areoi-*` stylt. Jede rote Zeile beendet den Lauf.

```bash
# B3 — Den Zustand VOR der Umstellung festhalten, um später dagegen vergleichen zu können.
wp creabb snapshot urls --out=urls.txt
wp creabb snapshot create --out=snapshot-vorher --urls=urls.txt
```

**Nehmen Sie die Seite jetzt vom Netz** und lassen Sie sie bis nach B6 offline:

```bash
wp maintenance-mode activate
```

`migrate run` hat keine Nebenläufigkeitssicherung: Wer währenddessen einen Beitrag speichert, dessen Wert überschreibt der Lauf.

#### Durchführen

```bash
# B4 — Trockenlauf. Schreibt nichts, rechnet aber vollständig nach.
wp creabb migrate run --dry-run

# B5 — Die eigentliche Migration, mit Backup.
wp creabb migrate run --backup=/pfad/zum/backup-JJJJ-MM-TT.sql.gz

# B6 — Nachkontrolle. Muss null areoi-Treffer melden.
wp creabb migrate verify
```

**`--backup` erwartet einen Dateipfad, kein Verzeichnis.** Das Kommando weist ein bereits vorhandenes Ziel ab — es zu überschreiben zerstörte den Rückweg eines früheren Laufs.

> **Das Backup gehört nicht in den Webbaum.** Ein Datenbankabzug unter `wp-content/uploads/` ist über HTTP abrufbar — an einer echten Installation nachgemessen: `wp-content/uploads/probe.sql.gz` antwortete anonym mit **HTTP 200** und gab ihren Inhalt heraus. Ein Migrationsbackup enthält die vollständige Datenbank samt Benutzern und Passworthashes, und sein Name ist vorhersagbar. Wählen Sie ein Verzeichnis **außerhalb** des Webbaums, etwa `/var/backups/`. Geht das auf Ihrem Webspace nicht, nehmen Sie das Backup trotzdem — ein Backup an schlechter Stelle ist besser als keines — und **löschen Sie es in Schritt B10**. `wp creabb doctor` und `migrate run` weisen beide auf diesen Zustand hin.

Der Lauf ist **idempotent**: Blöcke, die bereits einen `blockstudio`-Schlüssel tragen, werden übersprungen, ein zweiter Durchgang ändert also nichts. Angefasst wird ausschließlich der Blockbegrenzer; alles zwischen den Begrenzern bleibt Byte für Byte stehen.

#### Nachweisen

```bash
# B7 — HTML und Inline-CSS vorher gegen nachher halten.
wp creabb snapshot create --out=snapshot-nachher --urls=urls.txt
wp creabb snapshot diff snapshot-vorher snapshot-nachher
```

Der Diff normalisiert Nonces, Cache-Buster und Zeitstempel weg und meldet jede verbleibende Abweichung. Die Reihenfolge innerhalb von `class`-Attributen wird **nicht** normalisiert — sie ist Teil des Vertrags, und eine Verschiebung soll auffallen.

> **Messen Sie zuerst die Rauschgrenze — die Fehlerzahl allein sagt nichts.** Viele Plugins erzeugen bei *jedem* Aufruf anderes Markup: zufällige Element-IDs von E-Mail-Verschleierern, Formular-IDs, Werbe- und Trackingbausteine. Auf einer echten Bestandsseite haben wir am 2026-09-10 **904 Markup-Abweichungen zwischen zwei Aufnahmen desselben, unveränderten Zustands** gemessen. Nehmen Sie deshalb **vor** der Migration zweimal hintereinander auf und vergleichen Sie die beiden:
>
> ```bash
> wp creabb snapshot create --out=snapshot-rauschen --urls=urls.txt
> wp creabb snapshot diff snapshot-vorher snapshot-rauschen
> ```
>
> Was dort schon rot ist, ist Eigenschaft der Seite und nicht Ihrer Migration. Erst was **darüber hinaus** in B7 auftaucht, ist ein Befund.
>
> **Und beide Seiten müssen denselben Cache-Zustand haben.** Eine aus dem Seitencache bediente Vorher-Aufnahme gegen eine frisch gerenderte Nachher-Aufnahme zu halten erzeugt Abweichungen, die niemand verursacht hat. Leeren Sie den Cache vor jeder Aufnahme.
>
> **Nach B8 ist der CSS-Teil des Vergleichs nicht mehr gültig.** Er hält das Stylesheet des Alt-Plugins gegen das des Nachbaus; ist das Alt-Plugin weg, fehlt die Vorher-Seite. Der Diff sagt das selbst — `css-reference-missing`. Wiederholen Sie den Vergleich also nicht nach dem Abschalten.

#### Abschließen

```bash
# B8 — Jetzt erst das Alt-Plugin deaktivieren.
wp plugin deactivate all-bootstrap-blocks
```

Ab hier rendert ausschließlich CreaBootstrapBlocks. Falls die Seite noch offline ist:

```bash
wp maintenance-mode deactivate
```

Solange beide Plugins aktiv waren, druckten beide ihr generiertes Inline-CSS in denselben Kopf; bei gleicher Spezifität gewann die später gedruckte Regel. **Eine Abnahme des erzeugten CSS ist deshalb erst nach B8 aussagekräftig.**

Das Alt-Plugin kann jetzt auch gelöscht werden (`wp plugin delete all-bootstrap-blocks`) — der Rückweg hängt am Backup, nicht am Plugin-Ordner.

```bash
# B9 — Alt-Optionen räumen. Erst nach Ihrer Abnahme: bis dahin sind sie der Rückweg.
wp creabb cleanup --legacy-options --yes
```

`wp creabb cleanup` weigert sich, solange `migrate verify` nicht sauber durchläuft. Ohne `--yes` zeigt es die Liste und fragt nach.

```bash
# B10 — Den Datenbankabzug aus dem Webbaum schaffen.
```

**Wenn das Backup in `wp-content/uploads/` oder sonst im Webbaum liegt, muss es nach der Abnahme weg** — oder wenigstens an einen Ort außerhalb des Webbaums. Solange es dort steht, ist Ihre vollständige Datenbank für jeden herunterladbar, der den Dateinamen errät. Das gilt auch für den Dump, den `wp creabb migrate rollback` gelesen hat.

#### Wenn etwas schiefgeht

```bash
wp creabb migrate rollback --from=/pfad/zum/backup-JJJJ-MM-TT.sql.gz --yes
```

Ohne `--yes` fragt das Kommando nach. Danach das Alt-Plugin wieder aktivieren. Der Rückweg ist so lange offen, wie B9 nicht gelaufen ist.

---

## FAQ

**Reicht es, das neue Plugin hochzuladen, zu aktivieren und das alte zu deaktivieren?**
Nein — das ist der häufigste Irrtum bei der Umstellung. Damit ist nur **Teil A** der Installation erledigt. Ihre Inhalte tragen danach immer noch den alten Blocknamensraum `areoi/*` und werden lediglich über ein Notfallnetz gerendert; beim nächsten Speichern im Editor verlieren sie ihre Einstellungen. Erst **Teil B** — `wp creabb migrate run` — schreibt den Namensraum dauerhaft um. Und das alte Plugin wird nicht am Anfang deaktiviert, sondern erst in Schritt B8, wenn die Umstellung nachgewiesen ist.

**Brauche ich eine kostenpflichtige Blockstudio-Lizenz?**
Nein. Blockstudio steht unter GPL-2.0, ist quelloffen unter [github.com/inline0/blockstudio](https://github.com/inline0/blockstudio) verfügbar und läuft ohne Lizenzschlüssel. Es ist aber die Laufzeitgrundlage aller Blöcke, liegt nicht auf wordpress.org und ist in diesem Paket nicht enthalten — installieren müssen Sie es also selbst.

**Muss ich mein Theme anpassen?**
Nein. Solange `CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES` auf `true` steht — und das ist der Standard —, geben alle Blöcke die alten `areoi-*`-Klassen zusätzlich zu den neuen `creabb-*`-Klassen aus, an derselben Position im `class`-Attribut. Ein Theme, das gegen `.areoi-element`, `.areoi-element.container` oder `.areoi-has-url` stylt, greift unverändert weiter. **Das ist eine dauerhaft unterstützte Betriebsart.** Wer sein Theme irgendwann auf `creabb-*` umstellt, ergänzt dort zuerst die neuen Selektoren, rollt aus, sieht nach — und schaltet erst danach die Konstante auf `false`. Umgekehrt verlöre die Seite zwischen beiden Schritten ihr Layout.

**Können beide Plugins gleichzeitig laufen?**
Ja, und während der Umstellung ist es nötig. Solange beide aktiv sind, drucken beide ihr generiertes Inline-CSS in denselben Kopf; bei gleicher Spezifität gewinnt die später gedruckte Regel. Eine Abnahme des erzeugten CSS ist deshalb erst aussagekräftig, **nachdem** das Alt-Plugin deaktiviert ist (Schritt B8).

**Bringt das Plugin Bootstrap mit?**
Optional. Standardmäßig sind alle drei Asset-Schalter aus, Bootstrap kommt dann vom Theme. Wer sie einschaltet, bekommt eine exakt gepinnte 5.3.x-Fassung, bei der CSS und JavaScript aus derselben Version stammen.

**Ändert die Migration meine Inhalte?**
Sie ersetzt den Blocknamensraum und hebt die Attribute in den Blockstudio-Container. Alles zwischen den Blockbegrenzern bleibt Byte für Byte stehen. Der Lauf ist idempotent und wird über einen Schlüssel- und Wertevergleich je `block_id` gegen das Backup verifiziert.

**Was passiert mit nicht migrierten Seiten?**
Im **Frontend** rendern sie unverändert weiter: Zu jedem Block gibt es ein ausgeblendetes `areoi/*`-Gegenstück, und ein Render-Fallback hebt flach gespeicherte Attribute zur Laufzeit in den Blockstudio-Container. Im **Editor** dagegen gehen die Werte verloren, sobald jemand einen solchen Block öffnet und speichert — die Einstellungen des Blocks fallen dann auf ihre Standardwerte zurück. Der Fallback ist ein Netz für den Übergang, kein Ersatz für die Migration. Wer eine Seite dauerhaft betreiben will, fährt Teil B.

**Wohin gehört das Migrationsbackup?**
Außerhalb des Webbaums — nicht nach `wp-content/uploads/`. Ein `.sql.gz` liegt dort öffentlich abrufbar; nachgemessen antwortet der Server anonym mit HTTP 200 und gibt die Datei heraus. Das Backup ist ein vollständiger Datenbankabzug samt Benutzern und Passworthashes, und der in dieser Anleitung vorgeschlagene Name `backup-JJJJ-MM-TT.sql.gz` ist zu erraten. Wenn Ihr Webspace kein Verzeichnis außerhalb erlaubt, nehmen Sie das Backup trotzdem und löschen es nach der Abnahme (Schritt B10). `wp creabb doctor` meldet ein öffentlich ausgeliefertes Backup-Ziel als `warn`, und `migrate run` wiederholt die Warnung beim Schreiben.

**Warum meldet der Snapshot-Diff Fehler, obwohl nichts kaputt ist?**
Weil viele Plugins bei jedem Seitenaufruf anderes Markup erzeugen — zufällige Element-IDs, Formular-IDs, Trackingbausteine. Gemessen: 904 Markup-Abweichungen zwischen zwei Aufnahmen desselben unveränderten Zustands. Messen Sie deshalb erst die Rauschgrenze (zwei Aufnahmen vor der Migration gegeneinander) und lesen Sie das Ergebnis von B7 dagegen. Achten Sie außerdem darauf, dass beide Aufnahmen denselben Cache-Zustand haben.

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
| `CREA_BOOTSTRAP_BLOCKS_VERSION` | `1.0.1` | Aktuelle Plugin-Version |
| `CREA_BOOTSTRAP_BLOCKS_LEGACY_CLASSES` | `true` | Gibt zusätzlich zu `creabb-*` auch die alten `areoi-*`-Klassen aus. Darf dauerhaft `true` bleiben |
| `CREA_BOOTSTRAP_BLOCKS_LEGACY_BLOCKS` | `true` | Registriert zu jedem Block ein ausgeblendetes `areoi/*`-Gegenstück |
| `CREA_BOOTSTRAP_BLOCKS_DEBUG` | `false` | Schaltet die Protokollierung des Plugins frei |

### Mitgelieferte Fremdbestandteile

Unter `assets/vendor/bootstrap/` liegen Bootstrap und Bootstrap Icons, beide unter der MIT-Lizenz und damit mit GPL-2.0-or-later verträglich. Die Lizenztexte, die Bezugsquellen (`UPSTREAM.txt`) und die Prüfsummen jeder ausgelieferten Datei (`CHECKSUMS.txt`) liegen daneben. CSS und JavaScript stammen zwingend aus derselben Bootstrap-Version.

---

## Changelog

### 1.0.1

Wartungsfassung. Der erste echte Migrationslauf auf einer Bestandsinstanz hat zwei Dinge
gefunden — eines davon sicherheitsrelevant.

- **Sicherheit: Das Migrationsbackup wird nicht mehr stillschweigend in ein öffentlich
  abrufbares Verzeichnis gelegt.** `wp creabb doctor` meldete ein Backup-Ziel bisher als
  `ok`, sobald es beschreibbar war — auch `wp-content/uploads/`, aus dem sich ein
  Datenbankabzug über HTTP herunterladen lässt. Die Zeile meldet jetzt `warn`, nennt den
  Grund und den Ausweg, und `wp creabb migrate run` wiederholt die Warnung an der Stelle,
  an der die Datei entsteht. Kein Abbruch: Wo es kein Verzeichnis außerhalb des Webbaums
  gibt, ist ein Backup an schlechter Stelle besser als keines — es muss nur nach der
  Abnahme wieder weg.
- **Die Anleitung zur Umstellung erreicht jetzt das Update-Manifest.** Der Abschnitt
  *Installation* endete bisher nach dem Aktivieren des Plugins; die Migration stand
  darunter in einem eigenen Kapitel und wurde beim Erzeugen des Manifests abgeschnitten.
  Wer nur die Installationsanweisung las, konnte „hochladen, aktivieren, altes Plugin
  deaktivieren" für vollständig halten — und verlöre beim nächsten Speichern im Editor die
  Blockeinstellungen. *Installation* führt jetzt **Teil A** (Plugin einspielen) und
  **Teil B** (Inhalte umstellen), benennt den falschen Weg ausdrücklich und zählt die
  Schritte eindeutig als A1–A4 und B1–B10.
- **Neu in der Anleitung:** die Rauschgrenze des Vorher-Nachher-Vergleichs wird vor der
  Migration gemessen, statt eine nackte Fehlerzahl zu deuten; beide Aufnahmen brauchen
  denselben Cache-Zustand; der CSS-Teil des Vergleichs ist nach dem Abschalten des
  Alt-Plugins gegenstandslos. Dazu der Wartungsmodus als konkreter Befehl und `--yes` bei
  `cleanup` und `rollback`.
- Keine Änderung an Blöcken, Attributen, Markup oder erzeugtem CSS. Ein Update von 1.0.0
  ändert an bestehenden Seiten nichts.

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
