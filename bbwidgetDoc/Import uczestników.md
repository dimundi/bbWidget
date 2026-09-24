# Import uczestników

BB Widget → Uczestnicy → **Importuj z JSON**.

1. Wczytaj eksport `bb-api`, `schemaVersion: 1`, `scope: all_editions_active_participants`, `distanceUnit: m`.
2. Sprawdź podsumowanie edycji i aktywności. Edycje są dopasowywane po numerze. Pakiety nie są wymagane ani przypisywane.
3. Kliknij **Importuj**. Samo wczytanie pliku nie zapisuje uczestników.

- Import obejmuje wszystkich aktywnych uczestników, również bez IRB. Ustawia `active = true` i zachowuje `irbEnabled` oraz stare numery startowe.
- Nie tworzy kont ani zamówień. Nie przypisuje kont po adresie e-mail.
- Dla `irbEnabled = true` zapisuje podsumowanie w [[models/bb_irb_results]]. Nie odtwarza wpisów dziennych.
- Ponowny import pomija wcześniej przeniesionych uczestników, bez nadpisywania ich danych.
- Powtórzony numer startowy w pliku w tej samej edycji jest ustawiany na `NULL` u wszystkich uczestników z tym numerem. Import trwa dalej, bez nadawania im nowych numerów.
- Konflikt z numerem istniejącym już w bazie przerywa cały zapis. Nie łączymy osób na podstawie numeru ani e-maila.
- Brak edycji wymaga jej dodania przed zatwierdzeniem importu.
- `bbRacePackageId` pozostaje `NULL`. Eksport z API nadal zawiera jedno `activityType`. Import zapisuje tę wartość jako jeden rekord w [[models/bb_participant_activities]], również dla uczestników bez IRB. `iron_teacher` zachowujemy bez rozpisywania na trzy aktywności.
- Podgląd jest dostępny przez 30 minut wyłącznie dla administratora, który wczytał plik. Limit pliku: 5 MB, dodatkowo obowiązuje limit serwera PHP.

## Powiązanie z API

Tabela `{prefiks_WordPressa}bb_import_participants` przechowuje `sourceParticipantId` (PK, identyfikator z `bb-api`) oraz `bbParticipantId` (UNIQUE, identyfikator w [[models/bb_participants]]). Służy do wykrywania ponownego importu.
