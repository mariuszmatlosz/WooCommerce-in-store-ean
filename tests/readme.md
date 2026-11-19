# Testy

Są cztery warstwy. PHPUnit ma dwa osobne zestawy. Jest sprawdza sam skrypt kasy. Playwright klika po prawdziwym ekranie w WordPressie.

## PHPUnit, testy jednostkowe

`pnpm test:unit` albo `composer test:unit`.

Plik: `tests/unit/class-documents-test.php`. Bootstrap ładuje tylko `includes/class-documents.php`. WordPress i baza nie są potrzebne.

Sprawdzane jest budowanie numeru dokumentu:

- które wzorce są poprawne przy zerowaniu ciągłym, miesięcznym i rocznym,
- jak `{number}`, miesiąc i rok składają się w gotowy numer, na przykład `S3/007/11/2026`.

## PHPUnit, testy zintegrowane

`composer test:install`, potem `pnpm test:integration` albo `composer test:integration`.

Pliki: `tests/integration/class-document-numbers-test.php` i `tests/integration/class-register-test.php`. Bootstrap stawia bibliotekę testową WordPressa, WooCommerce i wtyczkę. Potrzebna jest MySQL: baza `wordpress_test`, użytkownik `root`, hasło `root`, host `127.0.0.1`. `composer test:install` pobiera WordPressa i WooCommerce oraz zakłada tę bazę.

Numery dokumentów, już z opcjami WordPressa:

- licznik paragonu podsuwa następny numer,
- wpisany numer jest zwiększany bez czytania licznika,
- propozycja faktury zawiera bieżący miesiąc i rok,
- goła liczba jest dopełniana do wzorca faktury,
- tekst, który nie jest numerem, jest odrzucany.

Wyszukiwanie towaru w WooCommerce:

- ten sam SKU wpisany kilka razy daje jedną pozycję z taką ilością,
- nieznany kod przerywa przegląd sprzedaży.

## Jest

`pnpm test:js`.

Plik: `tests/js/register.test.js`. To test skryptu `assets/js/admin/admin.js` w jsdom, bez przeglądarki i bez WordPressa. Żądanie numeru jest podstawione.

Sprawdzane jest zachowanie pola numeru na kasie:

- zmiana typu dokumentu zapamiętuje wpisaną wartość i przywraca ją po powrocie,
- brak typu dokumentu ukrywa pole i przycisk następnego numeru,
- odpowiedź „użyj następnego numeru” podmienia wartość w polu,
- błąd resetu pokazuje komunikat z przycisku i zostawia dotychczasowy numer.

`pnpm test` uruchamia Jesta i jednostkowy PHPUnit. Zestawu zintegrowanego nie włącza.

## Playwright

`pnpm test:e2e`.

Plik: `tests/e2e/register.spec.mjs`. Środowisko podnosi `@wordpress/env` z `.wp-env.json` (Docker) pod `http://localhost:8888`. Po starcie `tests/e2e/setup.sh` włącza WooCommerce i tę wtyczkę. `tests/e2e/auth.setup.mjs` loguje przeglądarkę jako `admin` i zapisuje ciasteczka, z których korzystają kolejne testy. Przed każdym testem `tests/e2e/seed.php` przywraca adres sklepu, profil pracownika, licznik dokumentów i stan produktu SKU `EANTEST1` (5 sztuk), także gdy poprzedni przebieg zapisał sprzedaż.

Cztery przejścia:

- na kasie widać nagłówek i link „Add sale”,
- nieznany kod `0000000000000` dostaje komunikat, że nie ma takiego produktu,
- „Use next number” zmienia podpowiedź z `1` na `2`,
- dwukrotne skanowanie `EANTEST1` pokazuje „Barcode product” w ilości 2, a „Record paid sale” kończy się tekstem „Sale recorded.”.
