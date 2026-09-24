# Historyczne wyniki IRB

Tabela: `{prefiks_WordPressa}bb_irb_results`, np. `wp_bb_irb_results`.

Jeden rekord to końcowe podsumowanie IRB uczestnika w danej edycji. Tabelę uzupełniamy po zamknięciu edycji Biegu Belfrów oraz podczas importu starych podsumowań.

## Kolumny

| Kolumna | Typ | Wymagana | Klucz | Opis |
| --- | --- | --- | --- | --- |
| bbIrbResultId | BIGINT UNSIGNED | Tak | PK | Identyfikator historycznego wyniku. |
| bbParticipantId | BIGINT UNSIGNED | Tak | FK, UNIQUE | Zapis uczestnika na daną edycję. |
| walkDistance | INT UNSIGNED | Tak | — | Sumaryczny dystans spaceru w metrach. `0` oznacza brak. |
| runDistance | INT UNSIGNED | Tak | — | Sumaryczny dystans biegu w metrach. `0` oznacza brak. |
| bikeDistance | INT UNSIGNED | Tak | — | Sumaryczny dystans jazdy na rowerze w metrach. `0` oznacza brak. |
| irbNick | VARCHAR(255) | Tak | — | Nick IRB zapisany w podsumowaniu edycji. |
| irbGroupName | VARCHAR(255) | Nie | — | Grupa zapisana w podsumowaniu edycji. |
| email | VARCHAR(254) | Nie | — | E-mail uczestnika zapisany w podsumowaniu edycji. |

## Powiązania

- Aktywności uczestnika w danej edycji wskazuje [[bb_participant_activities]]. Nie ograniczamy wyniku do jednego typu aktywności.

- `bbParticipantId` → [[bb_participants]].`bbParticipantId`. Edycja wynika z rekordu uczestnika.
- Jeden zapis uczestnika ma jedno końcowe podsumowanie IRB.

## Zapis wyników

- Po zamknięciu edycji sumujemy dystanse z [[bb_irb_entries]] i zapisujemy je wraz z nickiem, grupą i e-mailem uczestnika.
- Dla starych edycji importujemy dostępne podsumowania. Nie odtwarzamy wpisów dziennych.
- Rankingi archiwalne korzystają z tej tabeli.
- Późniejsze zmiany danych uczestnika nie zmieniają automatycznie zapisanego podsumowania.
