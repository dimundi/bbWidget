# Edycje

Tabela: `{prefiks_WordPressa}bb_editions`, np. `wp_bb_editions`. Edycje to nazwa dokumentu.

Jeden rekord opisuje jedną edycję Biegu Belfrów, bieżącą lub archiwalną.

## Kolumny

| Kolumna               | Typ             | Wymagana     | Klucz | Opis                                            |
| --------------------- | --------------- | ------------ | ----- | ----------------------------------------------- |
| bbeditionId           | BIGINT UNSIGNED | Tak          | PK    | Identyfikator edycji używany w innych tabelach. |
| editionNumber         | INT UNSIGNED    | Tak          | —     | Numer edycji.                                   |
| isCurrent | BOOLEAN | Tak | — | Czy jest to bieżąca edycja. |
| startDate             | DATE            | Do ustalenia | —     | Data rozpoczęcia edycji.                        |
| endDate               | DATE            | Do ustalenia | —     | Data zakończenia edycji.                        |
| registrationStartDate | DATE            | Do ustalenia | —     | Data rozpoczęcia zapisów.                       |
| registrationEndDate   | DATE            | Do ustalenia | —     | Data zakończenia zapisów.                       |
| primaryColor | CHAR(7) | Do ustalenia | — | Kolor główny: przyciski, linki i wyróżnienia. HEX, np. `#d93400`. |
| accentColor | CHAR(7) | Do ustalenia | — | Kolor pomocniczy: dodatkowe akcenty. HEX, np. `#ffcf00`. |
| backgroundColor | CHAR(7) | Do ustalenia | — | Kolor tła strony. HEX, np. `#ffffff`. |
| logo | BIGINT UNSIGNED | Do ustalenia | — | ID obrazka z biblioteki mediów WordPressa. W panelu wybieramy lub wgrywamy grafikę. |

## Powiązania

Inne tabele wskazują edycję przez `bbeditionId`. Ich definicje dodamy osobno.

## Bieżąca edycja

Flagę `isCurrent` można ręcznie zaznaczać i odznaczać. Bez ograniczenia liczby zaznaczonych edycji i bez automatycznej zmiany flag w innych rekordach.

## Do ustalenia

- Wymagalność dat, kolorów i logo, również dla edycji archiwalnych.

Wymaganie: [[Start#Wymagania|Start — punkt 1]].
