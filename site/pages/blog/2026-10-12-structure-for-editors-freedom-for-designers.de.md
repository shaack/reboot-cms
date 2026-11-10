---
title: Struktur für die Redaktion, Freiheit für das Design
description: Seiten aus Formular-Blöcken geben der Redaktion klare Felder zum Ausfüllen und überlassen das Design denen, die es gemacht haben. Ohne Page-Builder.
links: |
  - [Deine Inhalte, deine Dateien](blog/2026-09-14-your-content-your-files)
  - [Schnelle Seiten ohne Tuning](blog/2026-10-01-fast-pages-without-tuning)
  - [Markdown-Blöcke dieser Demo](blocks)
  - [Dokumentation](documentation)
---

Jedes Website-Team kennt die Spannung. Die Redaktion will ihre Seiten ändern, ohne jemanden zu fragen. Das Design soll so aussehen wie entworfen, auch sechs Monate nach dem Start. Page-Builder versuchen, beiden gerecht zu werden, und werden meist keinem gerecht. Die Redaktion bekommt hundert Optionen und das Design hundert Varianten.

## Blöcke als Vereinbarung

Reboot CMS löst das mit Blöcken. Wer das Design macht, legt die Bausteine der Site fest, ein Intro, eine Galerie, eine Reihe von Features, jeden als kleinen Ordner in `site/blocks/`. Darin liegen ein Template für das HTML und ein Blueprint, der die Felder benennt, eine Überschrift, einen Text, ein Bild, eine Liste von Einträgen.

Eine Seite ist eine Liste dieser Blöcke. Die Redaktion fügt einen Block hinzu, füllt seine Felder aus und schiebt ihn an die richtige Stelle. Sie entscheidet, was die Seite sagt und in welcher Reihenfolge. Der Block entscheidet, wie es aussieht. Keiner muss dem anderen ins Gehege kommen.

## Ausfüllen statt formatieren

Im Admin ist jeder Block ein kleines Formular. Eine Überschrift ist ein Textfeld, ein Bild wird aus der Mediathek gewählt, eine Liste wächst per Knopfdruck. Die Redaktion sieht nur die Felder, die für diesen Block zählen, und nichts, was sie eingibt, kann das Layout zerstören.

Neben dem Formular läuft eine Live-Vorschau der Seite. Sie scrollt mit dem Feld mit, das gerade bearbeitet wird, so sieht man sofort, wo der Text landet und wie er aussieht. Blöcke lassen sich einklappen, verschieben und eine Zeit lang ausschalten, ohne dass ihr Inhalt verloren geht.

Weil die Inhalte kein Design mitschleppen, berührt ein Redesign sie nicht. Ändere die Templates, und jede Seite zieht mit.

## Ein neuer Block ist ein Nachmittag

Braucht eine Seite etwas Neues, eine Vergleichstabelle oder ein Team-Raster, legt man einen Ordner mit zwei Dateien an. Der Blueprint listet die Felder in ein paar Zeilen YAML, und der Admin baut daraus das Formular. Das Template liest die Felder mit `$block->get()` und gibt sie in dem HTML aus, das das Design braucht. Es gibt kein Framework zu lernen und keinen Build-Schritt.

Datensätze, die auf mehreren Seiten erscheinen, wie die Seminare dieser Demo, liegen als Daten getrennt von den Seiten. Ein Block kann die nächsten drei Seminare zeigen, und sie bleiben aktuell, ohne dass jemand die Seite anfasst.

## Markdown, wenn du magst

Seiten in Markdown funktionieren weiterhin. Manche tippen lieber eine Raute, als ein Feld anzuklicken, und für sie legt der Admin weiterhin Markdown-Seiten an. Eine solche Seite lässt sich später in eine strukturierte Seite umwandeln, und ihre Markdown-Blöcke werden zu Formular-Blöcken, wo die Site welche bereitstellt.

Die [Seite mit den Markdown-Blöcken](blocks) dieser Demo zeigt jeden Markdown-Block neben dem Text, der ihn erzeugt.
