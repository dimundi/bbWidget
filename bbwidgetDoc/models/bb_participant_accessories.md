# Akcesoria uczestnika

Tabela: `{prefiks_WordPressa}bb_participant_accessories`, np. `wp_bb_participant_accessories`.

Jeden rekord przypisuje pozycję zamówienia z akcesorium do konkretnego uczestnika.

## Kolumny

| Kolumna | Typ | Wymagana | Klucz | Opis |
| --- | --- | --- | --- | --- |
| bbParticipantAccessoryId | BIGINT UNSIGNED | Tak | PK | Identyfikator przypisania akcesorium. |
| bbParticipantId | BIGINT UNSIGNED | Tak | FK | Uczestnik, dla którego kupiono akcesorium. |
| orderItemId | BIGINT UNSIGNED | Tak | UNIQUE | Pozycja zamówienia WooCommerce z akcesorium. |

## Powiązania

- `bbParticipantId` → [[bb_participants]].`bbParticipantId`.
- `orderItemId` wskazuje pozycję zamówienia WooCommerce zawierającą produkt, wariant (np. rozmiar koszulki), ilość i dane personalizacji.
- Jeden uczestnik może mieć wiele akcesoriów, również z późniejszych zamówień.

## Przypisanie

- Pozycja zamówienia dotyczy jednego uczestnika. Akcesoria dla różnych uczestników zapisujemy jako osobne pozycje, także gdy produkt i wariant są takie same.
- Produktu, rozmiaru, ilości i personalizacji nie kopiujemy do tej tabeli — odczytujemy je z pozycji zamówienia.
- Pakiet startowy pozostaje powiązany przez `orderItemId` w [[bb_participants]].
