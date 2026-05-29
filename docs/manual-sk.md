# Používateľský manuál - modul GP webpay / ČSOB

## Účel modulu

Modul pridá do PrestaShopu platbu kartou cez GP webpay / ČSOB. Zákazník je presmerovaný na platobnú bránu, po platbe sa vráti do obchodu a modul overí podpis odpovede z brány.

## Hlavné nastavenia

### Testovací režim

Používajte ho pri prvotnom nastavení a testovaní s bankou/acquirerom. Produkčné platby zapnite až po overení celého testovacieho priebehu.

### Číslo obchodníka

Identifikátor obchodníka pridelený GP webpay / ČSOB. Použite hodnotu, ktorú vám poskytla banka alebo acquirer.

### Kód providera

Kód providera platobnej brány. Pre ČSOB Slovensko je zvyčajne `0300`, ale správnu hodnotu si vždy overte v banke.

### Súkromný kľúč PEM

Súkromný kľúč používaný na podpisovanie platobných požiadaviek. Kľúč môžete vložiť do textového poľa alebo nahrať zo súboru.

Tento kľúč nezverejňujte, neposielajte cez GitHub Issues a neukladajte ho do repozitára.

### Heslo k súkromnému kľúču

Heslo k súkromnému kľúču. Použite heslo priradené ku GP webpay kľúču.

### Verejný kľúč GP webpay PEM

Verejný kľúč používaný na overenie `DIGEST` a `DIGEST1` v odpovediach z platobnej brány. Kľúč môžete vložiť do textového poľa alebo nahrať zo súboru.

### Okamžité zaúčtovanie / deposit

Určuje, či sa platba zaúčtuje okamžite. Použite hodnotu podľa nastavenia vašej platobnej brány a dohody s bankou.

### Povolené meny

ISO kódy mien oddelené čiarkou, napríklad:

`EUR,CZK`

Prázdna hodnota znamená, že modul povolí všetky meny obchodu.

### Povolení dopravcovia

ID dopravcov oddelené čiarkou. Prázdna hodnota znamená, že platobná metóda bude dostupná pre všetkých dopravcov.

### Povolené testovacie IP adresy

IP adresy oddelené čiarkou, ktoré uvidia platobnú metódu v testovacom režime. Prázdna hodnota znamená, že metódu uvidí každý aj počas testovacieho režimu.

### Povolené GP webpay metódy

Kódy platobných metód oddelené čiarkou, napríklad:

`CRD,APAY,GPAY`

Prázdna hodnota použije predvolené nastavenie brány.

### Stavy objednávok

Namapujte výsledky platby na stavy objednávok v PrestaShope:

- čakajúca platba,
- zaplatené,
- neúspešné,
- autorizované,
- refundované.

Používajte stavy, ktoré dávajú prevádzkový zmysel pre váš obchod. Neúspešnú platbu nikdy nemapujte na stav zaplatené.

## Logy platieb

Modul ukladá informácie o platbách a logy. V detaile objednávky v administrácii môžete skontrolovať stav platby a údaje z odpovede brány potrebné na riešenie problémov.

## Cron URL

Na konfiguračnej stránke je zobrazená cron URL. Spúšťajte ju každých 5-15 minút, ak chcete synchronizovať nedokončené platby cez kontrolu stavu.

## Refundácie

Podpora refundácií závisí od správneho nastavenia GP webpay a povolení banky/acquirera. Refundácie otestujte skôr, ako sa na ne začnete spoliehať v produkcii.

## Joobox podpora a atribúcia

V konfigurácii modulu je panel podpory Joobox. Modul zároveň zobrazuje malú Joobox atribúciu vo footeri. Modul neobsahuje prepínač v administrácii na vypnutie tejto atribúcie.

## Aktualizácia z GitHubu

Na aktualizácie používajte GitHub Releases. Nový ZIP nahrajte v administrácii PrestaShopu alebo ručne nahraďte priečinok `/modules/jooim_gpwebpay`. Technický názov priečinka musí ostať rovnaký.

### Poznámka k refundáciám

GP webpay rozlišuje operácie podľa stavu platby. Pri platbe vytvorenej s okamžitým strhnutím (`DEPOSITFLAG=1`) nemusí byť klasická refundácia dostupná hneď po zaplatení, kým nie je uzavretá dávka transakcií. Ak GP webpay WS refundáciu odmietne, modul teraz zobrazí chybu a objednávku neprepne na refundovanú. Pri čerstvej platbe pred uzavretím dávky použite v portáli GP webpay operáciu na zrušenie zaplatenia, ak ju portál ponúka.
Od verzie testfix12 modul lokálne eviduje akceptovanú refundovanú sumu v poli `refunded_amount`. Pri ďalšej refundácii najprv skontroluje, koľko už bolo refundované, a nedovolí odoslať vyššiu sumu, než ostáva z pôvodnej platby. V detaile objednávky sa zobrazuje už refundovaná suma aj zostávajúca refundovateľná suma.
