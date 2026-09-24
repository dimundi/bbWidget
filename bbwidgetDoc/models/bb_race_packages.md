# Pakiety startowe

Tabela: `{prefiks_WordPressa}bb_race_packages`, np. `wp_bb_race_packages`.

Jeden rekord opisuje pakiet startowy danej edycji. Strona korzysta z zapisanych tutaj parametrów pakietu.

Dla każdej edycji tworzymy osobne pakiety i osobne produkty WooCommerce. Nie wykorzystujemy produktu poprzedniej edycji przez zmianę jego danych. Stare produkty zachowujemy i wycofujemy ze sprzedaży.

## Kolumny

| Kolumna         | Typ             | Wymagana     | Klucz | Opis                                                       |
| --------------- | --------------- | ------------ | ----- | ---------------------------------------------------------- |
| bbRacePackageId | BIGINT UNSIGNED | Tak          | PK    | Identyfikator pakietu startowego.                          |
| bbeditionId     | BIGINT UNSIGNED | Tak          | FK    | Edycja, do której należy pakiet.                           |
| name            | VARCHAR(255)    | Tak          | —     | Nazwa pakietu.                                             |
| activityType | ENUM | Tak | — | Typ aktywności: [[activityType]]. |
| walkDistance | Do ustalenia | Do ustalenia | — | Dystans spaceru w metrach. `0` oznacza brak tej dyscypliny w pakiecie. |
| runDistance | Do ustalenia | Do ustalenia | — | Dystans biegu w metrach. `0` oznacza brak tej dyscypliny w pakiecie. |
| bikeDistance | Do ustalenia | Do ustalenia | — | Dystans jazdy na rowerze w metrach. `0` oznacza brak tej dyscypliny w pakiecie. |
| productId       | BIGINT UNSIGNED | Do ustalenia | —     | Identyfikator produktu WooCommerce powiązanego z pakietem. |

## Powiązania

- `bbeditionId` → [[bb_editions]].`bbeditionId`. Jedna edycja może mieć wiele pakietów.
- `productId` wskazuje produkt WooCommerce.

## Do ustalenia

- Typ liczbowy pól dystansu.
- Wymagalność powiązania z produktem dla pakietów archiwalnych.

Wymaganie: [[Start#Wymagania|Start — punkt 2]].
