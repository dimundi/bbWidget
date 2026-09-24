# Wymagania

Wszystkie dystanse zapisujemy w metrach — zarówno w pakietach startowych, jak i we wpisach IRB.

1. Dane bieżącej edycji przechowujemy centralnie w bazie danych: `bbeditionId` — identyfikator edycji używany w innych tabelach, numer edycji, data rozpoczęcia, data zakończenia, data rozpoczęcia zapisów, data zakończenia zapisów, definicje kolorów edycji oraz bieżące logo (z możliwością kilku wersji). W tej samej bazie zapisujemy również edycje archiwalne, aby każda miała własny `bbeditionId` do powiązania z danymi w innych tabelach.

2. Pakiety startowe definiujemy w bazie danych wraz z parametrami, takimi jak typ aktywności, długość dystansu itp. Cała strona powinna korzystać z tych danych. Pakiet startowy jest powiązany z produktem WooCommerce.

3. Akcesoria są produktami WooCommerce. Oferujemy je podczas zapisu na bieg oraz w sklepiku.

4. Prowadzimy Ranking Belfrów. Uczestnicy, którzy wyrazili wymagane zgody, codziennie wpisują pokonany dystans dla danej aktywności (bieg, rower itd.). Dane na bieżąco aktualizują listy rankingowe na żywo. Baza musi obsługiwać zgody oraz wpisy dystansów powiązane z uczestnikiem, datą, aktywnością i edycją (`bbeditionId`), a także dane potrzebne do wyświetlania rankingów.

5. Podczas rejestracji uczestnika na bieg (nie tworzenia konta) uczestnik podaje lub wybiera:

   - Imię i nazwisko — opcjonalnie.
   - Czy chce startować w Indywidualnym Rankingu Belfrów (dalej IRB).
   - Nick — wymagany przy udziale w IRB.
   - E-mail — opcjonalnie, niezależnie od udziału w IRB.
   - Grupę — opcjonalnie przy udziale w IRB.
   - Pakiet startowy — wymagany.
   - Akcesoria.

6. Użytkownik zakłada konto, podając obowiązkowo e-mail. Z jednego konta może zapisać na Bieg Belfrów wiele osób. Osoby te nie mają kont i nie mogą się logować. Użytkownik uzupełnia IRB w ich imieniu.

7. Konto użytkownika i uczestnik biegu są osobnymi rekordami. Osoba zarządzająca zapisem wybiera „Przekaż obsługę IRB”. Wysyłamy zaproszenie na e-mail uczestnika. Odbiorca zakłada konto lub loguje się i przyjmuje zaproszenie. Wtedy przejmuje zarządzanie IRB, a dotychczasowy zarządzający traci możliwość jego uzupełniania i edytowania. Samo utworzenie konta z pasującym e-mailem nie przekazuje dostępu. Zamówienie i płatność pozostają przy kupującym.

8. Ze starego systemu do nowej bazy danych przenosimy wyłącznie uczestników biorących udział w IRB.

9. Docelowo obsługę zamówień w WooCommerce łączymy z płatnościami i Apaczką. Potrzebujemy obsługi adresów wysyłki, drukowania podsumowań zamówień oraz ręcznej zmiany statusów zamówień i ich realizacji.
