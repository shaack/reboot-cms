---
title: Deine Inhalte, deine Dateien
description: Warum eine Website, deren Seiten einfache Dateien sind, jedes Redesign, jeden Hoster und jedes spätere CMS überdauert.
links: |
  - [Schnelle Seiten ohne Tuning](blog/2026-10-01-fast-pages-without-tuning)
  - [Struktur für die Redaktion, Freiheit für das Design](blog/2026-10-12-structure-for-editors-freedom-for-designers)
  - [Alle Beiträge](blog)
  - [Das Repository auf GitHub](https://github.com/shaack/reboot-cms)
---

Die meisten Content-Management-Systeme speichern deine Texte in einer Datenbank. Das klingt harmlos, bis zu dem Tag, an dem du wechseln willst. Dann liegen die Inhalte in Tabellen, die nur dieses eine System versteht, verpackt in sein Markup, seine Shortcodes und seine Plugins. Ein Umzug bedeutet einen Export, ein Migrationsskript und ein langes Wochenende.

Reboot CMS geht den umgekehrten Weg. Jede Seite ist eine Textdatei, eine kurze YAML-Datei mit den Blöcken der Seite oder eine Markdown-Datei, und der Pfad der Datei ist die Adresse der Seite. Der Text, den du schreibst, ist der Text, der gespeichert wird. Dazwischen liegt nichts, was du später übersetzen müsstest.

## Besitz, den man sieht

Öffne den Ordner `site/pages/`, und du siehst deine Website. Eine Seite über Preise ist `pricing.yml`, und daneben, in `site/data/blog/`, ist jeder Beitrag dieses Blogs eine eigene Datei. Jeder Editor öffnet sie, jede Suche findet sie, und in zehn Jahren liest sie sich so gut wie heute.

Das verändert, wie sich eine Website für ihre Besitzer anfühlt. Inhalte sind nicht mehr etwas, das das CMS für dich verwahrt. Sie sind ein Ordner, den du hast, wie die Fotos auf deinem Rechner.

## Versionsgeschichte gratis

Weil alles Text ist, passt die ganze Site in Git. Jede Änderung ist ein Commit mit Datum, Autor und Nachricht, und jeder frühere Stand jeder Seite ist einen Befehl entfernt. Zwei Leute können gleichzeitig an der Site arbeiten und ihre Änderungen zusammenführen wie Code.

Wer lieber im Browser arbeitet, für den führt der Admin ebenfalls eine Historie jeder Seite, und ein Backup ist ein einziger Klick, der die Inhalte in eine ZIP-Datei packt.

## Kein Lock-in, mit Absicht

An dem Tag, an dem Reboot CMS nicht mehr passt, kommen deine Inhalte mit. YAML und Markdown lesen statische Site-Generatoren, andere Flat-File-Systeme, Dokumentationswerkzeuge und jedes KI-Modell. Nichts muss exportiert werden, weil nie etwas eingesperrt war.

Das ist das leise Versprechen eines Flat-File-CMS. Die Software ist austauschbar, deine Inhalte sind es nicht, und sie bleiben, wo du sie hingelegt hast.
