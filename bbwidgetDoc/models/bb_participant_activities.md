# Aktywności uczestnika

Tabela: `{prefiks_WordPressa}bb_participant_activities`.

| Kolumna | Typ | Wymagana | Klucz | Opis |
| --- | --- | --- | --- | --- |
| bbParticipantId | BIGINT UNSIGNED | Tak | PK, FK | Zapis uczestnika na edycję: [[bb_participants]]. |
| activityType | ENUM | Tak | PK | [[activityType]]: walk, run, bike, iron_teacher. |

- Para uczestnik + aktywność jest unikalna.
- Bieg + rower: dwa rekordy, `run` i `bike`.
- Iron Teacher: rekord `iron_teacher`. Umożliwia wpisywanie wszystkich trzech dystansów i zachowuje informację potrzebną do przyznania nagrody.
- Nie zastępujemy `iron_teacher` rekordami spaceru, biegu i roweru. Sam zestaw trzech aktywności nie oznacza Iron Teachera.
- Eksport API pozostaje z jedną aktywnością. Import tworzy jeden rekord z wartością otrzymaną w JSON-ie.
- Dotychczasowe wartości z uczestników i podsumowań IRB przenosimy tutaj przed usunięciem starych kolumn. Pakiety pozostają osobnym powiązaniem zakupowym.

## Ręczna edycja

- Administrator zaznacza i odznacza aktywności w formularzu uczestnika, niezależnie od pakietu. Może wybrać kilka lub usunąć wszystkie.
- Zapis zastępuje listę aktywności wybranym zestawem. Nie zmienia pakietu ani dystansów IRB.
