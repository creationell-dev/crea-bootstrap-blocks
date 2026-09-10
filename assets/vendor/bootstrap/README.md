# Gevendortes Bootstrap

| Was | Version | Lizenz |
|---|---|---|
| Bootstrap (CSS + JS-Bundle) | `5.3.8` | MIT, `LICENSE-bootstrap.txt` |
| Bootstrap Icons (CSS + Webfonts) | `1.13.1` | MIT, `LICENSE-bootstrap-icons.txt` |

Beide sind mit GPL-2.0-or-later verträglich; die Lizenztexte werden
mitausgeliefert.

## Alle drei Assets sind standardmäßig AUS

Geschaltet wird unter **Design → CreaBootstrapBlocks → Assets**, je ein eigener
Schalter für CSS, JS-Bundle und Icons. Ohne gesetzten Schalter lädt das Plugin
nichts — Bootstrap kommt dann wie bisher vom Theme.

## CSS und JS stammen aus derselben Version

Das Alt-Plugin lieferte CSS 5.3.3 gegen JS 5.0.2 aus. `bin/vendor-bootstrap.sh`
zieht beide aus **einem** Archiv, und `tests/test-bootstrap-vendor.php` liest die
Versionsbanner beider Dateien und vergleicht sie. Ein Auseinanderdriften lässt
die Suite rot werden.

## Aktualisieren

```bash
bash bin/vendor-bootstrap.sh                     # neueste 5.3.x
BS_VERSION=5.3.9 bash bin/vendor-bootstrap.sh    # exakt gepinnt
php tests/test-bootstrap-vendor.php
```

Das Skript verweigert alles außerhalb von 5.3.x: Ein Sprung auf 6.x ändert
Klassennamen und bricht Ebene 2 des Kompatibilitätsvertrags. Ein Wechsel der
Hauptversion ist eine eigene Entscheidung, kein Nebeneffekt eines Updates.

Nach jedem Lauf gehören die neuen Versionsnummern in die README des Plugins
(`09-spike-und-bauplan.md`, Teil 12: „Bootstrap-Version wird beim Vendoring exakt
gepinnt und in der README genannt").

## Herkunft und Prüfsummen

- `UPSTREAM.txt` — Bezugs-URL und sha256 der heruntergeladenen Archive, plus die
  einzige Nachbearbeitung (entfernter `sourceMappingURL`-Kommentar).
- `CHECKSUMS.txt` — sha256 jeder ausgelieferten Datei. Der Test prüft nicht nur,
  dass jede gelistete Datei stimmt, sondern auch, dass keine ungelistete Datei
  im Verzeichnis liegt.

Die `.map`-Dateien werden bewusst nicht mitgeliefert — zusammen über ein
Megabyte für ein Asset, das standardmäßig aus ist. Damit kein Browser vergeblich
danach sucht, ist der `sourceMappingURL`-Kommentar entfernt.

## Release-Allowlist

`assets/vendor/**` gehört auf die Staging-Allowlist des Sync-Workflows —
`bin/**` ausdrücklich **nicht**.

---

Diese Datei erzeugt `bin/vendor-bootstrap.sh` bei jedem Lauf neu. Änderungen von
Hand überleben den nächsten Lauf nicht — dafür kann in der Tabelle oben keine
Version stehen, die `VERSION` oder `ICONS-VERSION` widerspricht. Teil 8 von
`tests/test-bootstrap-vendor.php` prüft genau das.
