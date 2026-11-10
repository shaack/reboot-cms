---
title: Schnelle Seiten ohne Tuning
description: Geschwindigkeit bemerken Besucher, bevor sie ein Wort lesen. Reboot CMS erreicht sie, indem es Dinge weglässt.
links: |
  - [Deine Inhalte, deine Dateien](blog/2026-09-14-your-content-your-files)
  - [Struktur für die Redaktion, Freiheit für das Design](blog/2026-10-12-structure-for-editors-freedom-for-designers)
  - [Alle Beiträge](blog)
  - [PageSpeed Insights](https://pagespeed.web.dev/)
---

Ein Besucher entscheidet in ein, zwei Sekunden, ob sich das Warten auf eine Seite lohnt. Jeder Moment mit leerem Bildschirm kostet Aufmerksamkeit, und Suchmaschinen wissen das. Die Ladezeit fließt längst in das Ranking von Google ein, und langsame Seiten verlieren Besucher, bevor diese den ersten Satz gesehen haben.

Die übliche Antwort ist Tuning. Ein Caching-Plugin, ein Bildoptimierer, ein CDN, ein zweites Caching-Plugin für das erste. Jedes behebt ein wenig von dem, was das System selbst langsam gemacht hat.

## Schnell, weil weniger da ist

Reboot CMS fängt am anderen Ende an. Es gibt keine Datenbank, die gefragt werden muss, kein Framework, das starten muss, und keine Plugin-Kette, die abläuft. Eine Anfrage liest eine Textdatei, rendert ihre Blöcke ins Template und schickt sie raus. Das dauert auf gewöhnlichem Shared Hosting wenige Millisekunden.

Beim Browser kommt an, was du ins Template geschrieben hast. Keine Admin-Skripte, kein Tracking, das du nicht gewählt hast, kein Stylesheet für Funktionen, die die Seite nicht nutzt. Die Demo, die du gerade ansiehst, lädt Bootstrap und ein kleines Stylesheet, und das ist alles.

## Ein Wert von 100

Mit Reboot CMS gebaute Sites erreichen bei Google PageSpeed Insights einen Performance-Wert von 100, [shaack.com](https://shaack.com) zum Beispiel. Nicht nach wochenlanger Optimierung, sondern weil es wenig zu optimieren gab.

Dieselbe Einfachheit hilft auch dort, wo niemand die Geschwindigkeit misst. Weniger bewegliche Teile heißen weniger Updates, weniger Sicherheitshinweise und weniger Überraschungen nach einem Serverumzug.

## Geschwindigkeit, die bleibt

Performance lässt mit der Zeit nach. Eine Site startet schnell, dann wachsen Plugins und Widgets, bis sie wieder langsam ist. Bei Reboot CMS ist jede Ergänzung eine bewusste Zeile in einem Template oder eine kleine PHP-Datei in `site/blocks/`. Nichts kommt von allein dazu, also wird die Site auch nicht von allein langsamer.
