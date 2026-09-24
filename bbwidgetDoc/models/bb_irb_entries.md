# Wpisy IRB

Tabela: `{prefiks_WordPressa}bb_irb_entries`, np. `wp_bb_irb_entries`.

Jeden rekord zawiera dystanse jednego uczestnika z jednego dnia.

## Kolumny

| Kolumna         | Typ             | Wymagana | Klucz | Opis                                                                       |
| --------------- | --------------- | -------- | ----- | -------------------------------------------------------------------------- |
| bbIrbEntryId    | BIGINT UNSIGNED | Tak      | PK    | Identyfikator wpisu.                                                       |
| bbParticipantId | BIGINT UNSIGNED | Tak      | FK    | Uczestnik, którego dotyczy wpis.                                           |
| activityDate    | DATE            | Tak      | —     | Dzień aktywności.                                                          |
| walkDistance    | Do ustalenia    | Tak      | —     | Łączny dystans spaceru w danym dniu, w metrach. `0` oznacza brak.          |
| runDistance     | Do ustalenia    | Tak      | —     | Łączny dystans biegu w danym dniu, w metrach. `0` oznacza brak.            |
| bikeDistance    | Do ustalenia    | Tak      | —     | Łączny dystans jazdy na rowerze w danym dniu, w metrach. `0` oznacza brak. |

## Powiązania

- `bbParticipantId` → [[bb_participants]].`bbParticipantId`.
- Edycja wynika z zapisu uczestnika w [[bb_participants]].

## Zasady

- Para (`bbParticipantId`, `activityDate`) jest unikalna: jeden wpis dziennie na uczestnika.
- Użytkownik modyfikuje istniejący wpis zamiast dodawać kolejny dla tego samego dnia.
- Wpisy uzupełnia i edytuje konto wskazane przez `managedByUserId` uczestnika.
- Można modyfikować wpisy z dnia bieżącego i trzech poprzednich dni, włącznie. Przykład: 24 września można edytować wpisy z 21–24 września.
- Wpisów starszych nie można modyfikować.

## Do ustalenia

- Typ liczbowy pól dystansu.

Wymaganie: [[Start#Wymagania|Start — punkt 4]].
