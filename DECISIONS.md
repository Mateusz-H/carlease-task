# DECISIONS

**Remove na sesji bez schowka.** `RemoveOfferFromShortlist` ma tylko handler instancyjny — gdy agregat dla `visitorSessionId` nie istnieje, Ecotone rzuca `AggregateNotFoundException`. To celowe: reguła "usunięcie nieobecnej pozycji jest no-opem" dotyczy pozycji w istniejącym schowku, a nie braku samego schowka. Warstwa web nie renderuje przycisku "usuń" bez istniejącej listy — akcja remove jest osiągalna wyłącznie z widoku schowka, który istnieje tylko wraz z agregatem, więc ta ścieżka wyjątku nie jest osiągalna z UI (mogłaby wystąpić jedynie przy ręcznie spreparowanym żądaniu).
