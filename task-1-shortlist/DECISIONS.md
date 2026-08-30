# DECISIONS

## 1. Czego nie zrobiłem i jak bym to zrobił

**Walidacja offerId względem katalogu przy dodawaniu.** Ręcznie spreparowany POST może
dodać dowolny identyfikator. Widok to przeżyje (pozycja pokazuje się jako niedostępna
i da się ją usunąć), ale zajmuje miejsce w limicie. Zrobiłbym sprawdzenie w katalogu
przed wysłaniem komendy; odpuszczone, bo z UI ta ścieżka nie istnieje.

**Sprzątanie porzuconych schowków.** Sesja anonimowa wygasa, wiersz w tabeli zostaje
na zawsze. Zrobiłbym kolumnę ze znacznikiem ostatniej zmiany i cykliczne polecenie
kasujące schowki starsze niż TTL sesji.

## 2. Ile czasu zajęło

Około 2 dni, licząc naukę mechanizmów Ecotone i ogólne wdrożenie w PHP;
na samo zadanie około 4 godzin.

## 3. Dodane biblioteki

`symfony/security-csrf` (7.4.*, jak reszta szkieletu) — ochrona CSRF formularzy POST
dodawania/usuwania ze schowka. Formularze pisane ręcznie (bez symfony/form), więc token
idzie przez `csrf_token('shortlist')` w szablonie i `isCsrfTokenValid()` w kontrolerze;
żądanie bez ważnego tokenu dostaje 400.

## 4. Zmiany w konfiguracji szkieletu

`src/Shared/EcotoneConfiguration.php` nietknięty. Jedyna zmiana w plikach konfiguracyjnych:
blok `when@test` w `config/services.yaml`, podmieniający w środowisku testowym
`OfferPopularityCounterInterface` na `RecordingPopularityCounter` z `tests/Support/`.
