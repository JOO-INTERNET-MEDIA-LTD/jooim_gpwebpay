# Postup instalace - modul GP webpay / ČSOB

## 1. Stažení modulu

Stáhněte release ZIP z GitHub repozitáře:

`https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay`

ZIP musí obsahovat složku modulu s přesným názvem:

`jooim_gpwebpay`

Tuto složku nepřejmenovávejte. PrestaShop používá název složky jako technický název modulu.

## 2. Instalace v administraci PrestaShopu

1. Přihlaste se do administrace PrestaShopu.
2. Přejděte do **Moduly > Správce modulů**.
3. Klikněte na **Nahrát modul**.
4. Nahrajte ZIP balíček.
5. Po nahrání klikněte na **Instalovat**.
6. Otevřete **Konfigurovat**.

Alternativní ruční instalace:

1. Rozbalte ZIP lokálně.
2. Nahrajte složku `jooim_gpwebpay` do `/modules/` na serveru.
3. V administraci vyhledejte `jooim_gpwebpay` nebo `GP webpay`.
4. Klikněte na **Instalovat**.

## 3. Nastavení testovacích údajů

Vyplňte požadované údaje z GP webpay / ČSOB:

- číslo obchodníka,
- kód providera,
- soukromý klíč PEM,
- heslo k soukromému klíči,
- veřejný klíč GP webpay PEM,
- testovací režim,
- povolené měny,
- stavy objednávek.

Soukromý a veřejný klíč můžete vložit jako text nebo nahrát ze souboru. Pokud nahrajete soubor, jeho obsah má přednost před textovým polem.

## 4. Testování před produkcí

Před zapnutím produkčního režimu otestujte minimálně:

- úspěšnou platbu,
- neúspěšnou nebo zrušenou platbu,
- návrat zákazníka do obchodu,
- změnu stavu objednávky,
- ověření podpisu odpovědi,
- log platby v detailu objednávky,
- cron synchronizaci, pokud ji používáte,
- refundaci, pokud ji zapnete.

## 5. Přechod do produkce

Po úspěšném testování a certifikaci s bankou/acquirerem:

1. Vypněte **Testovací režim**.
2. Nahraďte testovací klíče produkčními klíči.
3. Ověřte kód providera a číslo obchodníka u banky.
4. Proveďte nízkou testovací platbu v produkčním režimu.

## Podpora

Bezplatná GitHub verze neobsahuje individuální podporu. Placená podpora je dostupná přes Joobox:

`https://joobox.eu/cs-cz/podpora`
