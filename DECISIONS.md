# DECISIONS

## 1. Czego nie zrobiłem i jak bym to zrobił

## 2. Ile czasu zajęło

## 3. Dodane biblioteki

Żadnych — wystarczyło to, co w szkielecie.

## 4. Zmiany w konfiguracji szkieletu

`src/Shared/EcotoneConfiguration.php` nietknięty. Jedyna zmiana w plikach konfiguracyjnych:
blok `when@test` w `config/services.yaml`, podmieniający w środowisku testowym
`OfferPopularityCounterInterface` na `RecordingPopularityCounter` z `tests/Support/`.
