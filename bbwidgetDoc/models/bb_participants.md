# Uczestnicy

Tabela: `{prefiks_WordPressa}bb_participants`, np. `wp_bb_participants`.

Jeden rekord to zapis uczestnika na konkretną edycję. Rekord zostaje po zakończeniu edycji jako historia. Zapis tej samej osoby na kolejną edycję tworzy nowy rekord.

## Kolumny

| Kolumna            | Typ             | Wymagana           | Klucz | Opis                                                                                                            |
| ------------------ | --------------- | ------------------ | ----- | --------------------------------------------------------------------------------------------------------------- |
| bbParticipantId    | BIGINT UNSIGNED | Tak                | PK    | Identyfikator zapisu uczestnika.                                                                                |
| bbeditionId        | BIGINT UNSIGNED | Tak                | FK    | Edycja biegu.                                                                                                   |
| bbRacePackageId    | BIGINT UNSIGNED | Tak                | FK    | Wybrany pakiet startowy.                                                                                        |
| startNumber | INT UNSIGNED | Po opłaceniu zamówienia | — | Numer startowy uczestnika, nadawany po opłaceniu zamówienia z jego pakietem startowym. Przed opłaceniem pusty (`NULL`). |
| fullName           | VARCHAR(255)    | Nie                | —     | Imię i nazwisko uczestnika.                                                                                     |
| irbEnabled         | BOOLEAN         | Tak                | —     | Czy uczestnik bierze udział w IRB.                                                                              |
| irbNick            | VARCHAR(255)    | Przy udziale w IRB | —     | Nick uczestnika.                                                                                                |
| email              | VARCHAR(254)    | Nie                | —     | Opcjonalny e-mail uczestnika, niezależnie od udziału w IRB. Na ten adres wysyłamy zaproszenie do przejęcia obsługi IRB. |
| irbGroupName       | VARCHAR(255)    | Nie                | —     | Grupa uczestnika w tej edycji.                                                                                  |
| registeredByUserId | BIGINT UNSIGNED | Dla nowych zapisów | —     | Konto WordPressa, z którego zapisano uczestnika.                                                                |
| managedByUserId    | BIGINT UNSIGNED | Dla nowych zapisów | —     | Konto WordPressa uprawnione do uzupełniania i edytowania IRB uczestnika.                                        |
| orderItemId        | BIGINT UNSIGNED | Do ustalenia       | —     | Pozycja zamówienia WooCommerce dotycząca pakietu tego uczestnika.                                               |

## Powiązania

- `bbeditionId` → [[bb_editions]].`bbeditionId`.
- `bbRacePackageId` → [[bb_race_packages]].`bbRacePackageId`. Pakiet musi należeć do edycji wskazanej przez `bbeditionId`.
- `registeredByUserId` i `managedByUserId` wskazują konta WordPressa. Jedno konto może obsługiwać wielu uczestników.
- `orderItemId` wskazuje pozycję zamówienia z pakietem startowym, nie sam produkt.
- Akcesoria uczestnika są powiązane przez [[bb_participant_accessories]].
- Wpisy dystansów IRB przechowujemy w [[bb_irb_entries]], powiązane przez `bbParticipantId`.
- Końcowe podsumowanie IRB przechowujemy w [[bb_irb_results]], powiązane przez `bbParticipantId`.

## Obsługa IRB i historia

- Początkowo IRB obsługuje użytkownik, który zapisał uczestnika.
- Osoba zarządzająca zapisem wybiera „Przekaż obsługę IRB”. Wysyłamy zaproszenie na e-mail uczestnika.
- Odbiorca zakłada konto lub loguje się i przyjmuje zaproszenie. Dopiero wtedy zmieniamy `managedByUserId` na jego konto. Do tego czasu dostęp zachowuje dotychczasowy zarządzający.
- Po przyjęciu zaproszenia poprzedni zarządzający traci możliwość uzupełniania i edytowania IRB. `registeredByUserId`, powiązania zakupów oraz zamówienie i płatność kupującego pozostają bez zmian.
- Samo utworzenie konta z pasującym e-mailem nie przekazuje dostępu.
- Nick, grupa i pakiet dotyczą konkretnego zapisu. Nowa edycja nie nadpisuje danych poprzedniej.
- Importujemy wyłącznie dawnych uczestników IRB, bez automatycznego tworzenia kont. Powiązania z kontami mogą być puste dla importowanych rekordów.

## Do ustalenia

- Stany zapisu i wpływ opłacenia lub zwrotu zamówienia.
- Zakres danych i powiązań dostępnych przy migracji.

Wymagania: [[Start#Wymagania|Start — punkty 4–8]].
