# Postup inštalácie - modul GP webpay / ČSOB

## 1. Stiahnutie modulu

Stiahnite release ZIP z GitHub repozitára:

`https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay`

ZIP musí obsahovať priečinok modulu s presným názvom:

`jooim_gpwebpay`

Tento priečinok nepremenúvajte. PrestaShop používa názov priečinka ako technický názov modulu.

## 2. Inštalácia v administrácii PrestaShopu

1. Prihláste sa do administrácie PrestaShopu.
2. Prejdite do **Moduly > Správca modulov**.
3. Kliknite na **Nahrať modul**.
4. Nahrajte ZIP balík.
5. Po nahratí kliknite na **Inštalovať**.
6. Otvorte **Konfigurovať**.

Alternatívna ručná inštalácia:

1. Rozbaľte ZIP lokálne.
2. Nahrajte priečinok `jooim_gpwebpay` do `/modules/` na serveri.
3. V administrácii vyhľadajte `jooim_gpwebpay` alebo `GP webpay`.
4. Kliknite na **Inštalovať**.

## 3. Nastavenie testovacích údajov

Vyplňte požadované údaje z GP webpay / ČSOB:

- číslo obchodníka,
- kód providera,
- súkromný kľúč PEM,
- heslo k súkromnému kľúču,
- verejný kľúč GP webpay PEM,
- testovací režim,
- povolené meny,
- stavy objednávok.

Súkromný a verejný kľúč môžete vložiť ako text alebo nahrať zo súboru. Ak nahráte súbor, jeho obsah má prednosť pred textovým poľom.

## 4. Testovanie pred produkciou

Pred zapnutím produkčného režimu otestujte minimálne:

- úspešnú platbu,
- neúspešnú alebo zrušenú platbu,
- návrat zákazníka do obchodu,
- zmenu stavu objednávky,
- overenie podpisu odpovede,
- log platby v detaile objednávky,
- cron synchronizáciu, ak ju používate,
- refundáciu, ak ju zapnete.

## 5. Prechod do produkcie

Po úspešnom testovaní a certifikácii s bankou/acquirerom:

1. Vypnite **Testovací režim**.
2. Nahraďte testovacie kľúče produkčnými kľúčmi.
3. Overte kód providera a číslo obchodníka v banke.
4. Spravte nízku testovaciu platbu v produkčnom režime.

## Podpora

Bezplatná GitHub verzia neobsahuje individuálnu podporu. Platená podpora je dostupná cez Joobox:

`https://joobox.eu/sk-sk/podpora`
