Czas wykonania: około 45 minut.

Cała recenzja została wykonana przez orkiestrację agentów AI (równoległi recenzenci + adwersaryjna weryfikacja znalezisk); weryfikacje uruchomieniowe (composer, docker php) sprawdziłem ręcznie po analizie ich wyników.

# REVIEW: Powiadomienie o obniżce ceny

Kolejność: od najważniejszego. Cztery pierwsze problemy razem oznaczają, że funkcja nie działa w żadnym miejscu przepływu: nie da się zapisać subskrypcji, nie da się jej potwierdzić, a nawet potwierdzony i wyzwolony alert nie wysyła maila o spadku raty.

---

### [KRYTYCZNY] Subskrypcja nie działa: komenda SubscribeToPriceAlertCommand nie ma odbiorcy

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:58 (oraz src/Modern/PriceAlert/Domain/PriceAlert.php:48)

Co jest nie tak:
Kontroler wysyła komendę przez CommandBus, ale nikt jej nie obsługuje. Jedyny handler w całym module to `PriceAlert::confirm()`. Żeby w Ecotone powstał nowy agregat, klasa `PriceAlert` musi mieć statyczną metodę fabrykującą oznaczoną `#[CommandHandler]`. Takiej metody nie ma, a konstruktora nie wywołuje nic poza testem. Komenda i agregat leżą w tym module, więc to nie jest kwestia niepełnego wycinka kodu.

Jak to się objawi:
Wysłanie formularza kończy się błędem 500 i żaden alert nie trafia do bazy. Uwaga na kolejność objawów: w praktyce najpierw wywali się autoload (problem PSR-4 opisany niżej), bo `new SubscribeToPriceAlertCommand(...)` nie znajdzie klasy. Błąd „brak handlera" zobaczymy dopiero po naprawie autoloadu.

Poprawka:
```php
#[CommandHandler]
public static function subscribe(SubscribeToPriceAlertCommand $c): self
{
    return new self($c->car, $c->email, $c->thresholdPrice);
}
```

### [KRYTYCZNY] Nie da się potwierdzić alertu: nic nie wysyła ConfirmPriceAlertCommand

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:59

Co jest nie tak:
Alert powstaje ze statusem `pending` i ma być potwierdzany linkiem z maila (double opt-in). Worker bierze pod uwagę wyłącznie alerty `confirmed`: `findActiveForCar` filtruje po statusie, a `reactToNewPrice` dodatkowo sprawdza `isConfirmed()`. Problem: nigdzie nie ma trasy ani kodu, który wysłałby `ConfirmPriceAlertCommand`. Co gorsza, jedyny mail modułu wychodzi dopiero PO potwierdzeniu (reaguje na `PriceAlertConfirmed`) i mówi „Twoje powiadomienie jest aktywne". Czyli mail z linkiem potwierdzającym miałby wyjść w reakcji na potwierdzenie, którego bez tego maila nie da się zrobić. Błędne koło.

Jak to się objawi:
Każdy alert wisi w `pending` na zawsze. Rata spada, `findActiveForCar` zwraca 0 wierszy, nikt nie dostaje powiadomienia.

Poprawka:
(1) po subskrypcji mail z linkiem zawierającym id/token alertu (nowe zdarzenie, np. `PriceAlertRequested`, + handler mailowy), (2) trasa `GET /alert-cenowy/potwierdz/{alertId}` wysyłająca `ConfirmPriceAlertCommand`, (3) dopiero po potwierdzeniu obecny mail „aktywne". Alternatywa: świadomie zrezygnować z double opt-in i tworzyć alert od razu jako `confirmed`.

### [KRYTYCZNY] Po spadku ceny nie wychodzi żaden mail: worker tylko zmienia status

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:81 (oraz Application/Service/PriceWatchWorker.php:31)

Co jest nie tak:
`reactToNewPrice()` ustawia status `triggered` i zwraca `true`. Nie zapisuje żadnego zdarzenia (w `confirm()` jest `recordThat`, tutaj go nie ma). Worker po trafieniu robi tylko `save()` i zwiększa licznik. Klasa `PriceAlertNotifier`, jedyne miejsce z treścią maila „Rata tego auta spadła", nie jest nigdzie wstrzykiwana ani wywoływana. Do tego worker woła metodę agregatu bezpośrednio, z pominięciem CommandBusa, więc nawet zapisane zdarzenie nie zostałoby przez Ecotone opublikowane. Komentarz nad metodą („so the caller knows a notification went out") opisuje kod, którego nie ma.

Jak to się objawi:
Rata spada poniżej progu, status w bazie zmienia się na `triggered` i nic więcej się nie dzieje. Gorzej: taki alert znika z `findActiveForCar`, więc użytkownik bezpowrotnie traci swoje powiadomienie. To główna funkcja modułu i nie działa nawet w najprostszym scenariuszu.

Poprawka:
w `reactToNewPrice()` dodać `recordThat(new PriceAlertTriggered($this->id, $this->email, $newPrice))` i asynchroniczny `#[EventHandler]` wysyłający mail (analogicznie do `PriceAlertConfirmed`); obsługę poprowadzić przez komendę i bus, żeby zdarzenia faktycznie się publikowały. Wersja minimalna: wywołać notifier w workerze, gdy `reactToNewPrice()` zwróci `true`.

### [KRYTYCZNY] Trzy klasy komend w jednym pliku: autoload ich nie widzi (PSR-4)

Plik:
src/Modern/PriceAlert/Application/Command/PriceAlertCommands.php:9

Co jest nie tak:
Plik `PriceAlertCommands.php` zawiera trzy klasy: `SubscribeToPriceAlertCommand`, `ConfirmPriceAlertCommand` i `DeletePriceAlertCommand`. Żadna nie nazywa się tak jak plik, a autoloader PSR-4 szuka klasy w pliku o jej nazwie. Efekt: żadnej z tych klas nie da się załadować. Przy zoptymalizowanej classmapie composer wprost je pomija (warning „does not comply with psr-4 autoloading standard. Skipping."), więc na produkcji też nie zadziała.

Jak to się objawi:
POST formularza kończy się fatalem „Class not found" i błędem 500 (możliwe, że wywali się już bootstrap Ecotone przy skanowaniu atrybutów). Testy przechodzą, bo żaden nie używa tych klas.

Poprawka:
rozbić na trzy pliki: `SubscribeToPriceAlertCommand.php`, `ConfirmPriceAlertCommand.php`, `DeletePriceAlertCommand.php`. Ta sama wada (łagodniejsza) w `PriceDropCalculator.php` (interfejs + 2 klasy w jednym pliku).

Weryfikacja (uruchomieniowa):
sprawdzone na minimalnym projekcie z autoloadem PSR-4 i plikiem o innej nazwie niż klasy: `composer dump-autoload` + `class_exists` → `bool(false)`; `composer dump-autoload -o` → warning `Class App\Command\SubscribeToPriceAlertCommand located in ./src/Command/PriceAlertCommands.php does not comply with psr-4 autoloading standard (rule: App\ => ./src). Skipping.` i nadal `bool(false)`.

### [KRYTYCZNY] Cała encja Car (z listą ofert) wkładana do sesji

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:32

Co jest nie tak:
`$session->set('price_alert_car', $car)` zapisuje do sesji cały obiekt encji razem z załadowaną (linia 34) listą ofert; sesja przy zapisie to serializuje. Czy wybuchnie od razu, zależy od budowy klasy `Car` (proxy w środku): w doctrine/orm 2.x (sprawdzono źródła 2.19.7 i 2.20.0; wersji nie widać w wycinku — brak composer.json) sama kolekcja serializuje się bez awarii, bo `PersistentCollection::__sleep()` zapisuje tylko `['collection','initialized']`. Pewne jest natomiast, że: dane w sesji szybko się starzeją; po deployu zmieniającym klasę `Car`/`Offer` odczyt sesji się psuje (`__PHP_Incomplete_Class` albo TypeError przy typowanych polach, błąd 500 aż do wyczyszczenia ciasteczka); odczytany obiekt jest odłączony od Doctrine; sesja puchnie o cały graf obiektów przy każdym wejściu.

Jak to się objawi:
w najgorszym razie 500 już przy pierwszym otwarciu formularza (błąd serializacji); w łagodniejszym 500 u użytkowników z aktywną sesją po każdym deployu zmieniającym encję oraz nieaktualne dane w „Ostatnio oglądane".

Poprawka:
trzymać w sesji tylko id: `$session->set('price_alert_car_id', $carId)` (ewentualnie małą tablicę `['id','brand','model']`) i dociągać resztę przy renderze.

Weryfikacja (źródło biblioteki):
`vendor/doctrine/orm/src/PersistentCollection.php:586` (tag 2.19.7; identycznie w 2.20.0, w 3.x ta sama treść ok. :497): `__sleep()` zwraca tylko `['collection','initialized']`, co obala popularne „na pewno wybuchnie na PDO". Dokumentacja Doctrine („Serializing entities") i tak wprost odradza serializację encji z proxy i kolekcjami. Pełnej próby (serialize realnej encji `Car`) nie wykonano; do sprawdzenia `php -r` z załadowanym ORM: `serialize($car)` po `$car->getOffers()->first()`.

### [KRYTYCZNY] clear() w save() odłącza wszystkie encje, a komentarz twierdzi odwrotnie

Plik:
src/Modern/PriceAlert/Infrastructure/Doctrine/PriceAlertRepository.php:27

Co jest nie tak:
Komentarz mówi, że `clear()` czyści tylko wewnętrzną mapę Doctrine i nie rusza obiektów trzymanych przez wołającego. Jest dokładnie odwrotnie: `clear()` odłącza od Doctrine wszystkie zarządzane encje; obiekty u wołającego przestają być śledzone. `save()` jednego alertu resetuje więc przy okazji cały stan współdzielonego EntityManagera.

Jak to się objawi:
kod A trzyma encję X, kod B woła `save($alert)`. `flush()` bez argumentów zapisuje od razu wszystkie oczekujące zmiany (także X, przedwcześnie), a `clear()` odłącza X. Każda zmiana X po tym momencie przepada bez żadnego błędu, bo kolejny `flush()` już jej nie widzi. Cicha utrata danych (stąd KRYTYCZNY wg skali). Możliwy też `ORMInvalidArgumentException` przy ponownym `persist` odłączonej encji. W workerze działa tylko przypadkiem, bo każdy alert jest pobierany na nowo po `clear()`.

Poprawka:
usunąć `clear()` z `save()`. Jeśli worker potrzebuje oszczędzać pamięć przy dużych partiach, wołać `clear()` świadomie w jego pętli co N rekordów. Usunąć błędny komentarz.

Weryfikacja (źródło biblioteki):
`vendor/doctrine/orm/src/EntityManager.php:604-605` (tag 2.19.7; w 2.20.0: 613-614), opis `clear()`: „Clears the EntityManager. All entities that are currently managed by this EntityManager become detached."

---

### [POWAŻNY] catch(\Throwable) w handlerze maila: retry nigdy nie zadziała, mail przepada

Plik:
src/Modern/PriceAlert/Application/EventHandler/SendPriceAlertEmailHandler.php:38

Co jest nie tak:
Komentarz obiecuje „The message is retried automatically until it succeeds". Ale mechanizm ponowień Ecotone (retry, error channel, dead letter) uruchamia się tylko wtedy, gdy wyjątek wyleci z handlera; wtedy wiadomość nie jest zdejmowana z kolejki. Tutaj `catch (\Throwable)` łapie wszystko i tylko loguje. Dla konsumenta wygląda to na sukces, więc wiadomość znika z kanału `async`.

Jak to się objawi:
chwilowa awaria SMTP i mail potwierdzający ginie bezpowrotnie. W logach błąd, w kolejce pusto. Komentarz wprowadza następnego czytelnika w błąd.

Poprawka:
usunąć try/catch (albo logować i rzucać dalej `throw $exception`); ponowienia skonfigurować na kanale async (retry template / dead letter).

### [POWAŻNY] Kolizje klucza cache w calculateDrop: zwracany bywa wynik innej pary cen

Plik:
src/Modern/PriceAlert/Application/Service/PriceDropCalculator.php:38

Co jest nie tak:
Klucz cache to `spl_object_hash($this).$oldPrice.$newPrice`, czyli sklejone liczby bez separatora. PHP przy zamianie float na string gubi `.0`, więc pary `(1.0, 12.0)` i `(11.0, 2.0)` dają ten sam klucz (`...112`). `spl_object_hash($this)` jest zawsze taki sam w obrębie instancji (a cache jest polem tej instancji), więc niczego nie rozróżnia. Cache rośnie też bez ograniczeń; komentarz o „bounded" jest mylący.

Jak to się objawi:
`calculateDrop(1.0, 12.0)` zwraca `-1100.0` i zapisuje wynik pod kluczem „112". Potem `calculateDrop(11.0, 2.0)` zwraca z cache `-1100.0` zamiast `81.82`. Błędny wynik liczbowy bez żadnego błędu, zależny od kolejności wywołań w długo żyjącym procesie.

Poprawka:
usunąć cache w całości (odejmowanie, dzielenie i round są tańsze niż lookup); jeśli ma zostać, klucz `$oldPrice.'|'.$newPrice`.

Weryfikacja (docker `php:8.4-cli`):
`1.0 . 12.0` → `string(3) "112"`, `11.0 . 2.0` → `string(3) "112"`, `===` → `bool(true)`.

### [POWAŻNY] Identyfikator z ConfirmPriceAlertCommand nie trafia do agregatu

Plik:
src/Modern/PriceAlert/Application/Command/PriceAlertCommands.php:22

Co jest nie tak:
Ecotone dopasowuje identyfikator agregatu z komendy na dwa sposoby: atrybutem `#[TargetIdentifier]` albo po identycznej nazwie właściwości. Agregat ma pole `$id`, komenda ma `$alertId` bez atrybutu. Nic się nie dopasuje; Ecotone czeka wtedy na nagłówek `aggregate.id`, którego zwykłe `$commandBus->send($command)` nie ustawia.

Jak to się objawi:
gdy powstanie trasa potwierdzająca, wysłanie komendy skończy się wyjątkiem (brak identyfikatora / AggregateNotFound). Potwierdzenie niemożliwe.

Poprawka:
`#[TargetIdentifier('id')] public string $alertId` albo zmiana nazwy właściwości na `$id`.

Weryfikacja (źródło biblioteki):
`vendor/ecotone/ecotone/src/Modelling/AggregateIdentifierRetrevingServiceBuilder.php:188-222`: mapowanie tylko przez atrybut lub identyczną nazwę; przy braku dopasowania mapa identyfikatorów jest pusta i identyfikator musi przyjść w nagłówku `aggregate.id`.

### [POWAŻNY] Ecotone nie ma zarejestrowanego repozytorium dla agregatu: confirm() nie ma skąd go wczytać

Plik:
src/Modern/PriceAlert/Infrastructure/Doctrine/PriceAlertRepository.php:11 (oraz Domain/PriceAlert.php:17)

Co jest nie tak:
Skoro `PriceAlert` jest `#[Aggregate]` z handlerem na sobie, Ecotone musi umieć go wczytać i zapisać. Do tego potrzebuje repozytorium zarejestrowanego u siebie: implementacji `StandardRepository` albo włączonej integracji z Doctrine ORM. `PriceAlertRepository` to zwykła klasa bez atrybutu `#[Repository]` i bez interfejsu Ecotone. To osobny bloker, niezależny od problemu z identyfikatorem wyżej, na tej samej ścieżce.

Jak to się objawi:
nawet po naprawie identyfikatora i dodaniu fabryki dispatch skończy się błędem „There is no repository available for aggregate: ...PriceAlert" (`AllAggregateRepository`), a na ścieżce potwierdzenia `AggregateNotFoundException`. Chyba że aplikacja nadrzędna ma włączoną integrację ORM; tego z wycinka nie widać, trzeba potwierdzić w PR.

Poprawka:
włączyć integrację Doctrine ORM Ecotone albo dodać implementację `StandardRepository` opartą na istniejącym repozytorium.

### [POWAŻNY] 500 zamiast 404 dla nieistniejącego auta i auta bez ofert

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:28 i :34

Co jest nie tak:
`find($carId)` zwraca `null` dla nieznanego id i nikt tego nie sprawdza przed `$car->getOffers()`. Dalej `first()` na pustej kolekcji zwraca `false`, a kod woła na tym `->getMonthlyInstalment()`. Oba przypadki można wywołać zwykłym adresem (stary link, auto wycofane z oferty). „Pierwsza oferta" jest przy okazji przypadkowa, o ile mapowanie `Offers` w klasie `Car` (aplikacja nadrzędna, poza wycinkiem) nie ma `#[ORM\OrderBy]`; w wycinku sortowania nie widać.

Jak to się objawi:
`GET /auta/999999/alert-cenowy` daje 500 zamiast 404; auto bez ofert też 500.

Poprawka:
`if (!$car) { throw $this->createNotFoundException(); }` oraz `$offer = $car->getOffers()->first(); if (false === $offer) { throw $this->createNotFoundException(); }`.

### [POWAŻNY] Zamiast przycisku submit jest div z onclick: walidacja ominięta, Enter martwy, ryzyko wysłania cudzego formularza

Plik:
templates/price_alert/form.html.twig:34

Co jest nie tak:
Trzy niezależne skutki. Po pierwsze, `form.submit()` z JS pomija walidację przeglądarki, więc `required` i `type=email` nic nie dają. Po drugie, formularz nie ma przycisku submit, a ma dwa pola blokujące wysyłanie Enterem (email i number); według specyfikacji HTML Enter ma wtedy nie robić nic, a diva nie da się sfokusować, więc klawiaturą nie da się wysłać formularza w ogóle. Po trzecie, `document.querySelector('form')` bierze pierwszy formularz na stronie; jeśli `base.html.twig` ma np. wyszukiwarkę w nagłówku, kliknięcie wyśle tamten formularz.

Jak to się objawi:
pusty e-mail przechodzi mimo `required`; Enter nic nie robi; użytkownik bez myszki nie zapisze alertu; przy dodatkowym formularzu w layoucie przycisk wysyła nie to, co trzeba.

Poprawka:
zamienić diva na prawdziwy przycisk submit (usunąć onclick):

```html
<button type="submit" class="mt-2 rounded-card bg-brand-500 px-4 py-2 text-white">Zapisz powiadomienie</button>
```

Źródła: MDN `HTMLFormElement.submit()` („constraint validation is not triggered"), WHATWG HTML §4.10.22.2 „Implicit submission" (kotwica `#implicit-submission`; numeracja sekcji WHATWG przesuwa się w czasie).

### [POWAŻNY] Dynamicznie sklejana klasa Tailwind: przycisk bez tła

Plik:
templates/price_alert/form.html.twig:35

Co jest nie tak:
Tailwind (JIT) bierze do wynikowego CSS tylko te klasy, które znajdzie w plikach jako pełne, nieprzerwane napisy. `bg-brand-500` nigdzie tak nie występuje, bo w szablonie jest rozerwane wstawką `{{ buttonVariant|default('brand') }}`, a config nie ma `safelist`. Klasa nie zostanie wygenerowana. Do tego kontroler nigdy nie przekazuje `buttonVariant`, więc cała ta dynamika niczemu nie służy.

Jak to się objawi:
przycisk „Zapisz powiadomienie" to biały tekst (`text-white`) bez tła, praktycznie niewidoczny. Działałby tylko przypadkiem, gdyby inny plik w repo zawierał dosłowne `bg-brand-500`.

Poprawka:
dosłownie `class="... bg-brand-500 ..."`; wariantowość (gdy potrzebna) przez mapę pełnych nazw klas albo safelist.

Źródło: docs Tailwind, „Dynamic class names": „Tailwind only finds classes that exist as complete unbroken strings in your source files".

### [POWAŻNY] confirm() bez sprawdzenia stanu: można reaktywować zużyty alert i zdublować mail

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:59

Co jest nie tak:
Metoda zawsze ustawia `confirmed` i emituje `PriceAlertConfirmed`, niezależnie od aktualnego stanu. Drugie wywołanie to drugi event i drugi mail. Wywołanie na alercie `triggered` cofa go do `confirmed`, więc jednorazowy alert uzbraja się ponownie i wystrzeli drugi raz.

Jak to się objawi:
dwa kliknięcia linku potwierdzającego to dwa maile; kliknięcie starego linku po wyzwoleniu ożywia alert. Dziś to uśpione (nic nie wysyła tej komendy), ale wybuchnie zaraz po dobudowaniu potwierdzania.

Poprawka:
`if ('pending' !== $this->status) { return; }` na początku `confirm()`.

### [POWAŻNY] PriceWatchWorker nie jest przez nic uruchamiany

Plik:
src/Modern/PriceAlert/Application/Service/PriceWatchWorker.php:19

Co jest nie tak:
`applyNewPrices()` nie ma w module żadnego wywołania: nie ma komendy konsolowej, `#[Scheduled]` ani konsumenta zdarzeń o zmianie cen. Wywołanie może istnieć w pełnym repo, ale PR dodający workera powinien dodać też jego wyzwalacz.

Jak to się objawi:
ceny się zmieniają, alerty stoją; nikt nie uruchamia sprawdzania.

Poprawka:
komenda konsolowa / handler Ecotone konsumujący feed cen, wywołujący `applyNewPrices()`, albo potwierdzenie w PR, gdzie leży wyzwalacz.

### [POWAŻNY] Brak deduplikacji: ten sam e-mail i auto można zapisać dowolnie wiele razy

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:57 (oraz Domain/PriceAlert.php:17)

Co jest nie tak:
każdy poprawny POST tworzy nowy alert. Encja nie ma unique constraint na (car_id, email), repozytorium nie ma metody sprawdzającej istnienie, kontroler niczego nie sprawdza.

Jak to się objawi:
po naprawie przepływu N wysłań to N wierszy i N maili (potwierdzających i o spadku) dla tej samej osoby.

Poprawka:
unique constraint (car_id, email) + sprawdzenie istnienia przed dispatch.

### [POWAŻNY] Alert odpala się przy racie RÓWNEJ progowi, a UI obiecuje „spadnie poniżej"

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:73

Co jest nie tak:
warunek `if ($newPrice > $this->thresholdPrice) return false;` przepuszcza równość, a etykieta w formularzu mówi „gdy rata spadnie poniżej".

Jak to się objawi:
próg 1200 i rata dokładnie 1200,00: alert się wyzwala i zużywa (po naprawie KRYTYCZNEGO #3 wyjdzie też mail „rata spadła poniżej 1200 zł"), choć rata nie spadła.

Poprawka:
`>=` w warunku albo zmiana tekstu na „do X zł lub niżej"; kod i tekst muszą się zgadzać.

### [POWAŻNY] uniqid() jako identyfikator agregatu

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:50

Co jest nie tak:
`uniqid('pa_', true)` opiera się na zegarze; dokumentacja PHP mówi wprost, że nie gwarantuje unikalności. Kolumna ma `length: 36`, jakby projektowano ją pod UUID (uniqid daje ok. 26 znaków).

Jak to się objawi:
rzadka kolizja klucza głównego pod obciążeniem i wyjątek unique constraint przy zapisie.

Poprawka:
`Symfony\Component\Uid\Uuid::v4()` (symfony/uid to osobny, lekki pakiet — `composer require symfony/uid`; nie jest domyślną zależnością framework-bundle ani skeletonu, więc sprawdzić w aplikacji nadrzędnej).

### [POWAŻNY] DivisionByZeroError: oldPrice=0 w strategii procentowej i pusta tablica w averagePrice

Plik:
src/Modern/PriceAlert/Application/Service/PriceDropCalculator.php:16 i :64

Co jest nie tak:
dzielenie przez `$oldPrice` i przez `count($prices)` bez zabezpieczenia. W PHP 8 dzielenie przez zero to `DivisionByZeroError` (fatal), nie warning jak w PHP 7. Oba fragmenty nie mają dziś produkcyjnych wywołań, co ogranicza skutki, ale to błąd logiczny i mina na przyszłość. Sprawdzone (docker php:8.4-cli): `(100.0-90.0)/0.0` rzuca `DivisionByZeroError`.

Jak to się objawi:
pierwsze produkcyjne użycie `calculateDrop` z ceną wyjściową 0 albo `averagePrice([])` kończy się fatalem.

Poprawka:
zabezpieczenie przed 0 / pustą tablicą, albo usunięcie martwych metod (sekcja A).

### [POWAŻNY] Formularz gubi wpisane dane przy błędzie walidacji; threshold bez required/min

Plik:
templates/price_alert/form.html.twig:23 i :29 (oraz PriceAlertController.php:65)

Co jest nie tak:
przy błędzie walidacji formularz renderuje się od nowa, ale pole e-maila nie ma `value`, a pole progu ma na sztywno `value="{{ suggestedThreshold }}"`. Wszystko, co użytkownik wpisał, przepada; własny próg po cichu wraca do sugerowanego. Pole progu nie ma też `required` ani `min`, więc po naprawie przycisku pusta albo ujemna wartość dalej przejdzie przeglądarkę i zatrzyma się dopiero na serwerze.

Jak to się objawi:
użytkownik z literówką w e-mailu dostaje z powrotem pusty formularz i sugerowany próg zamiast własnego; wpisane dane trzeba wpisywać od nowa.

Poprawka:
wypełnianie `value` z requestu przy błędzie; `required min="1"` na polu progu.

---

### [DROBNY] Pieniądze jako float: kolumna, cast wejścia, porównania

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:36 (+ PriceAlertController.php:40)

Co jest nie tak:
próg jest floatem w kolumnie i w porównaniach, a kontroler rzutuje `(float)` wprost z requestu, przez co „1100,50" (przecinek) staje się po cichu `1100.0`. Porównania graniczne na floatach potrafią minimalnie odbiegać od intencji (IEEE-754). Zastrzeżenie: pole jest `type="number"`, więc przeglądarka przecinka nie wyśle (poprawną wartość wyśle z kropką, zepsutą zamieni na pusty string, czyli `0.0`, co łapie warunek `<= 0`); string z przecinkiem dotrze do PHP głównie POST-em spoza przeglądarki. Problem walidacji na wejściu pozostaje.

Jak to się objawi:
próg bez groszy przy POST spoza formularza; niedeterministyczne zachowanie przy racie dokładnie na progu.

Poprawka:
grosze jako int (w bazie decimal), normalizacja wejścia (`str_replace(',', '.', ...)` + walidacja).

### [DROBNY] Sugerowany próg liczony ręcznie, inaczej niż w kalkulatorze i bez zaokrąglenia

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:36

Co jest nie tak:
kontroler liczy próg ręcznie (`$currentPrice - ($currentPrice / 10)`), choć ma wstrzyknięty kalkulator z metodą `suggestThreshold()`, która robi to samo plus `round(..., 2)`. Niezaokrąglony wynik trafia do pola ze `step="0.01"`. Przykład: `1234.56 - 1234.56/10` to `1111.104`.

Jak to się objawi:
sama wartość domyślna jest formalnie poprawna (bez atrybutu `min` step base = wartość z atrybutu `value`, WHATWG §4.10.5.3.10), ale zatruwa step base: gdy użytkownik wpisze normalną kwotę z dwoma miejscami (np. `1100.00`), `stepMismatch` odpala względem bazy 1111.104 i po naprawie przycisku na prawdziwy submit przeglądarka zablokuje wysłanie. A po dodaniu proponowanego wyżej `min="1"` niezaokrąglony default sam staje się niepoprawny.

Poprawka:
`$suggested = $this->calculator->suggestThreshold($currentPrice);`.

### [DROBNY] previouslyViewed pokazuje to samo auto, które użytkownik właśnie ogląda

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:31

Co jest nie tak:
sesja jest nadpisywana bieżącym autem przy każdym wejściu, a po POST następuje przekierowanie na tę samą stronę. GET po przekierowaniu odczytuje więc auto zapisane ułamek sekundy wcześniej.

Jak to się objawi:
w „Ostatnio oglądane" widnieje to samo auto, które użytkownik ma przed oczami; po odświeżeniu i po każdym zapisie formularza.

Poprawka:
nadpisywać tylko, gdy id jest inne od bieżącego (i trzymać id, nie encję; patrz problem sesji).

### [DROBNY] N+1 i nadmiarowe kolumny w pętli workera

Plik:
src/Modern/PriceAlert/Application/Service/PriceWatchWorker.php:24

Co jest nie tak:
`findActiveForCar` pobiera `id, email, threshold_price`, ale worker używa tylko `id`. Potem dla każdego wiersza robi osobne `find()` (drugie zapytanie) i przy każdym zapisie pełny `flush()+clear()` przez `save()`.

Jak to się objawi:
przy spadku ceny popularnego modelu tysiące pojedynczych zapytań i flushy; wolno, choć poprawnie.

Poprawka:
jedno zapytanie ORM o encje (`WHERE car = :id AND status = 'confirmed'`), zbiorczy flush co N.

### [DROBNY] Cena wyświetlana dwa razy (policzona w dwóch miejscach) + osierocona ikona close.svg

Plik:
templates/price_alert/form.html.twig:10-11 i :40

Co jest nie tak:
obok siebie renderowane są `formattedPrice` z kontrolera i `currentPrice|number_format`; wychodzi „1 234,56 zł miesięcznie (1 234,56 zł)". Format jest ten sam, tylko liczony w dwóch miejscach (PHP w kontrolerze i filtr Twig). Na dole wisi znacznik `img` z `src="/build/images/close.svg"` bez funkcji, linku i alt; wygląda na pozostałość po modalu.

Poprawka:
jedno miejsce formatowania (filtr Twig, usunąć `formattedPrice`), usunąć img.

### [DROBNY] Walidacja e-maila po długości i „@" zamiast filter_var

Plik:
src/Modern/PriceAlert/Ui/Http/Web/PriceAlertController.php:44

Co jest nie tak:
walidacja to tylko `strlen < 5` i `strpos('@')`; przechodzą np. `a b@c.co`, `a,b@c.co` czy `a@b.,`. Zły adres czyni alert bezużytecznym. Adresy typu `a b@c.co` rzucą `RfcComplianceException` dopiero w handlerze maila, przy `->to()` (to wywołanie stoi poza blokiem try, więc wyjątek wyleci z konsumenta i wiadomość będzie ponawiana bez szans na sukces). Uwaga: akurat `a@b.,` przechodzi nawet walidację `Address` Symfony (egulias 4.x używa MessageIDValidation, która to akceptuje) i dojdzie aż do `->send()`, gdzie odrzut transportu połknie catch — mail cicho przepadnie.

Poprawka:
`filter_var($email, FILTER_VALIDATE_EMAIL)`, jedna linia z biblioteki standardowej.

### [DROBNY] Maile budowane bez from()

Plik:
src/Modern/PriceAlert/Application/EventHandler/SendPriceAlertEmailHandler.php:31 (i PriceAlertNotifier.php:40)

Co jest nie tak:
maile powstają bez `->from(...)`. Jeśli globalny nadawca nie jest ustawiony w konfiguracji (`framework.mailer.headers.from`), Symfony Mailer rzuca `LogicException: An email must have a From or a Sender header`. Konfiguracji nie widać w wycinku (stąd DROBNY); w handlerze ten wyjątek zostałby połknięty, więc maile po prostu cicho by nie wychodziły.

Poprawka:
jawne `->from(...)` albo potwierdzenie globalnej konfiguracji.

### [DROBNY] Tokeny ink-*, brand-100, rounded-card nieobecne w załączonym tailwind.config.js

Plik:
assets/tailwind.config.js:8

Co jest nie tak:
szablon używa `text-ink-900/500`, `border-brand-100`, `rounded-card`, ale config definiuje tylko `brand-500/600` i `spacing.18`. Jeśli to jedyny config w buildzie, te klasy w ogóle nie powstaną. Jeśli tokeny siedzą w configu aplikacji nadrzędnej, ten plik jest zdublowanym i niekompletnym źródłem prawdy.

Poprawka:
jeden config; jeśli ten wchodzi do builda, dodać brakujące tokeny.

### [DROBNY] PSR-4 złamane także w tests/PriceAlertTest.php

Plik:
tests/PriceAlertTest.php:5

Co jest nie tak:
plik deklaruje namespace `App\Tests\Modern\PriceAlert`, ale leży prosto w `tests/`. Przy typowym mapowaniu `App\Tests\ => tests/` powinien być w `tests/Modern/PriceAlert/`. `composer dump-autoload -o` pominie go z takim samym warningiem jak klasy komend; PHPUnit tego nie zauważa, bo ładuje pliki testów po ścieżce, nie przez autoload.

Poprawka:
przenieść do `tests/Modern/PriceAlert/PriceAlertTest.php` (trzeci przypadek tej samej wady, obok `PriceAlertCommands.php` i `PriceDropCalculator.php`).

### [DROBNY] Encja Doctrine (Car) w komendzie: SubscribeToPriceAlertCommand niesie ją przez CommandBus

Plik:
src/Modern/PriceAlert/Application/Command/PriceAlertCommands.php:12

Co jest nie tak:
komenda ma `public Car $car`, czyli niesie przez bus całą encję Doctrine z modułu Legacy. Działa, dopóki komendy są synchroniczne i w tym samym procesie; przy przejściu na async (jak zdarzenia w tym module) obiekt musiałby się serializować i by pękł. Nowy DTO jest też związany z encją ze starego kodu. Uwaga: fabryka proponowana przy KRYTYCZNYM #1 (`new self($c->car, ...)`) utrwala ten kształt.

Poprawka:
`int $carId` w komendzie; fabryka/handler dociąga encję z repozytorium.

### [DROBNY] Brak indeksu (car_id, status) pod findActiveForCar

Plik:
src/Modern/PriceAlert/Domain/PriceAlert.php:19

Co jest nie tak:
tabela `price_alert` nie ma żadnego indeksu poza kluczem głównym. `findActiveForCar` filtruje po (car_id, status) w pętli dla każdego auta, `countPending` po samym statusie. Na PostgreSQL klucz obcy nie tworzy indeksu automatycznie, więc każde auto z feedu to pełny skan tabeli.

Poprawka:
`#[ORM\Index(columns: ['car_id', 'status'])]` na encji + migracja.

---

## Które błędy testy by wykryły, a których nie

Testy przechodzą, bo omijają każde zepsute miejsce. Nie wykryłyby żadnego z powyższych problemów:

- `testSendsEmail`: atrapa. Tworzy mocka `MailerInterface` z `expects(once)`, sam na nim woła `send()` i kończy `assertTrue(true)`. Nie tworzy `SendPriceAlertEmailHandler`, więc testuje PHPUnita, nie nasz kod. Przeszedłby po skasowaniu całego modułu.

- `testConfirmDoesNotThrow`: wbrew nazwie nie wywołuje `confirm()`; sprawdza tylko, że konstruktor ustawia `pending`. Nie wykryje braku sprawdzenia stanu, złego mapowania identyfikatora ani braku zdarzenia przy wyzwoleniu.

- `testSuggestThreshold`: jedyna w pełni uczciwa asercja (1200 → 1080), ale testuje metodę, której produkcja nie używa (kontroler liczy próg ręcznie, bez zaokrąglenia); zielony wynik nic nie mówi o aplikacji.

- `testCalculateDrop`: uczciwa asercja, ale jedno wywołanie nie ma szans wykryć kolizji cache (trzeba dwóch kolidujących par na tej samej instancji) ani DivisionByZeroError.

Najważniejsze: testy przechodzą MIMO zepsutego PSR-4, bo żaden nie dotyka klas komend (samo `use` i type-hint w niewywoływanej metodzie nie uruchamiają autoloadu). Dlatego zielone testy współistnieją z aplikacją, która wywala się na POST. `reactToNewPrice`, jedyna logika z rozgałęzieniami, nie ma żadnego testu.

Testy, które wykryłyby najwięcej najtaniej: WebTestCase wysyłający POST (łapie PSR-4 i brak handlera jednym strzałem), test Ecotone Lite wysyłający `ConfirmPriceAlertCommand` (mapowanie identyfikatora), test workera z asercją wysyłki maila, test `calculateDrop` z parami (1, 12) i (11, 2).

## Sekcja A: kod, który nic nie wnosi

- Cała klasa `PriceAlertNotifier` (notify, wasSentTo, buildBody, pole $sent): nigdzie nie jest wstrzykiwana ani wywoływana. `wasSentTo` trzyma deduplikację w pamięci jednego procesu, czyli rozwiązuje nieistniejący problem. Styl PHP5 (brak typów, `array()`, `strcmp` w pętli `for`) zdradza drugą, porzuconą implementację obok handlera Ecotone. UWAGA: to jedyne miejsce z treścią maila „Rata tego auta spadła"; najpierw wpiąć/przenieść treść przy naprawie KRYTYCZNEGO #3, potem usunąć.

- `DeletePriceAlertCommand`: sama definicja; żaden handler jej nie przyjmuje, nic jej nie wysyła, funkcji usuwania nie ma nigdzie w module.

- `PriceAlertRepository::countPending()`: jedyne miejsce, gdzie występuje, to jej definicja.

- `PriceDropCalculator::averagePrice()`: zero wywołań (także w testach) + uśpiony DivisionByZeroError.

- Wstrzyknięty `$calculator` w `PriceAlertController`: `$this->calculator` nie występuje w ciele klasy ani razu; próg liczony ręcznie linijkę niżej. UWAGA: nie usuwać; naprawa progu (krok 5 planu) polega właśnie na użyciu `suggestThreshold()`. Martwe jest obecne nieużywanie, nie sama zależność.

- Cała memoizacja w `calculateDrop` (pole `$cache`, klucz, lookup): cache jednego odejmowania, dzielenia i round jest droższy od samego obliczenia, a klucz wręcz psuje wyniki (kolizje); `spl_object_hash($this)` w kluczu cache per-instancja niczego nie wnosi. Usunięcie = działanie identyczne, ale poprawne.

- `PriceDropStrategyInterface` + wymienna strategia: jedna implementacja, nigdy nie wstrzykiwana z zewnątrz (jedyne użycie to wartość domyślna konstruktora); `calculateDrop` woła zresztą tylko test.

- `buttonVariant|default('brand')`: kontroler nigdy nie przekazuje tej zmiennej, default wygrywa zawsze, a wstawka przy okazji psuje skaner Tailwinda.

- Blok `@layer components` w assets/price-alert.css (`.price-alert-card*`): zero użyć w szablonach; jedyny szablon modułu używa czystych utilities.

- znacznik `img` z `src="/build/images/close.svg"`: bez handlera, linku, alt; niczego nie zamyka, pozostałość po modalu.

- Kolumny `email`, `threshold_price` w SELECT `findActiveForCar`: jedyny wywołujący czyta wyłącznie `$row['id']`.

- `spacing: {18: '4.5rem'}` i `brand-600` w tailwind.config.js: zero użyć w wycinku (zastrzeżenie: config może być współdzielony z resztą repo; przed usunięciem sprawdzić w pełnym repo).

- `tests/PriceAlertTest.php::testSendsEmail`: usunięcie nie zmniejsza pokrycia niczego produkcyjnego (test mocka na własnym mocku).

## Sekcja B: werdykt (NIE PRZYJMOWAĆ)

PR w obecnym kształcie odrzucić. Szkielet (agregat, zdarzenie, async handler, repozytorium) jest dobrym punktem wyjścia; brakuje ogniw, nie architektury. Przepływ aplikacyjny trzeba jednak wokół niego w dużej mierze napisać od nowa. Deklaracja autora, że „funkcja działa", jest nieprawdziwa: przepływ urywa się na KAŻDYM etapie: POST bez handlera (i bez działającego autoloadu komend), potwierdzenie nieosiągalne, wyzwolony alert nie wysyła maila. Zielone testy tego nie wykrywają, bo niczego istotnego nie testują.

Kolejność naprawy:

1. Rozbić `PriceAlertCommands.php` na pliki per klasa (PSR-4; to samo w `tests/`, katalog zgodny z namespace) + statyczna fabryka `#[CommandHandler]` dla subskrypcji; bez tego nic nie ruszy. W komendzie `int $carId` zamiast encji `Car`.

2. Zarejestrować repozytorium agregatu w Ecotone (integracja Doctrine ORM albo `StandardRepository`; bez tego fabryka i confirm nie mają persystencji), po czym dobudować przepływ potwierdzenia: mail z linkiem przy subskrypcji, trasa potwierdzająca, `#[TargetIdentifier]` w `ConfirmPriceAlertCommand`, sprawdzenie stanu w `confirm()`.

3. Zdarzenie `PriceAlertTriggered` + handler mailowy po spadku ceny (sedno funkcji); wpiąć lub usunąć `PriceAlertNotifier`. Warunek wstępny: istniejący i konsumowany kanał `async` + polityka retry/dead-letter na nim (konfiguracja poza wycinkiem; potwierdzić w PR).

4. Usunąć encję z sesji (trzymać id) i `try/catch` z async handlera (retry musi widzieć wyjątek); usunąć `clear()` z `save()`.

5. Formularz: prawdziwy przycisk submit (`button type="submit"`), dosłowna klasa `bg-brand-500`, 404 dla braku auta/ofert, próg z `suggestThreshold()` (inaczej niezaokrąglony default zatruwa step base pola: każda normalna wartość wpisana przez użytkownika wpadnie w stepMismatch po naprawie przycisku, a z dodanym `min="1"` default sam będzie niepoprawny), wypełnianie pól po błędzie walidacji.

6. Wyzwalacz workera (komenda konsolowa / konsument feedu cen; bez niego kroki 1-5 dalej nie wysyłają żadnego alertu), unique constraint (car_id, email) + sprawdzenie duplikatu przed dispatch; wyciąć martwy kod z sekcji A (kalkulator zostaje, używany od kroku 5); zastąpić testy pozorne realnymi (Ecotone Lite dla przepływu komenda→event→mail, WebTestCase na POST, test jednostkowy `reactToNewPrice` z granicą progu).
