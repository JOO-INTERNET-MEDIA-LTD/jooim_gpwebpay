# Uživatelský manuál - modul GP webpay / ČSOB

## Účel modulu

Modul přidá do PrestaShopu platbu kartou přes GP webpay / ČSOB. Zákazník je přesměrován na platební bránu, po platbě se vrátí do obchodu a modul ověří podpis odpovědi z brány.

## Hlavní nastavení

### Testovací režim

Používejte ho při prvotním nastavení a testování s bankou/acquirerem. Produkční platby zapněte až po ověření celého testovacího průběhu.

### Číslo obchodníka

Identifikátor obchodníka přidělený GP webpay / ČSOB. Použijte hodnotu, kterou vám poskytla banka nebo acquirer.

### Kód providera

Kód providera platební brány. Pro ČSOB Slovensko je obvykle `0300`, ale správnou hodnotu si vždy ověřte u banky.

### Soukromý klíč PEM

Soukromý klíč používaný k podepisování platebních požadavků. Klíč můžete vložit do textového pole nebo nahrát ze souboru.

Tento klíč nezveřejňujte, neposílejte přes GitHub Issues a neukládejte ho do repozitáře.

### Heslo k soukromému klíči

Heslo k soukromému klíči. Použijte heslo přiřazené ke GP webpay klíči.

### Veřejný klíč GP webpay PEM

Veřejný klíč používaný k ověření `DIGEST` a `DIGEST1` v odpovědích z platební brány. Klíč můžete vložit do textového pole nebo nahrát ze souboru.

### Okamžité zaúčtování / deposit

Určuje, zda se platba zaúčtuje okamžitě. Použijte hodnotu podle nastavení vaší platební brány a dohody s bankou.

### Povolené měny

ISO kódy měn oddělené čárkou, například:

`EUR,CZK`

Prázdná hodnota znamená, že modul povolí všechny měny obchodu.

### Povolení dopravci

ID dopravců oddělené čárkou. Prázdná hodnota znamená, že platební metoda bude dostupná pro všechny dopravce.

### Povolené testovací IP adresy

IP adresy oddělené čárkou, které uvidí platební metodu v testovacím režimu. Prázdná hodnota znamená, že metodu uvidí každý i během testovacího režimu.

### Povolené GP webpay metody

Kódy platebních metod oddělené čárkou, například:

`CRD,APAY,GPAY`

Prázdná hodnota použije výchozí nastavení brány.

### Stavy objednávek

Namapujte výsledky platby na stavy objednávek v PrestaShopu:

- čekající platba,
- zaplaceno,
- neúspěšné,
- autorizováno,
- refundováno.

Používejte stavy, které dávají provozní smysl pro váš obchod. Neúspěšnou platbu nikdy nemapujte na stav zaplaceno.

## Logy plateb

Modul ukládá informace o platbách a logy. V detailu objednávky v administraci můžete zkontrolovat stav platby a údaje z odpovědi brány potřebné pro řešení problémů.

## Cron URL

Na konfigurační stránce je zobrazena cron URL. Spouštějte ji každých 5-15 minut, pokud chcete synchronizovat nedokončené platby přes kontrolu stavu.

## Refundace

Podpora refundací závisí na správném nastavení GP webpay a oprávněních banky/acquirera. Refundace otestujte dříve, než se na ně začnete spoléhat v produkci.

## Joobox podpora a atribuce

V konfiguraci modulu je panel podpory Joobox. Modul zároveň zobrazuje malou Joobox atribuci ve footeru. Modul neobsahuje přepínač v administraci pro vypnutí této atribuce.

## Aktualizace z GitHubu

Pro aktualizace používejte GitHub Releases. Nový ZIP nahrajte v administraci PrestaShopu nebo ručně nahraďte složku `/modules/jooim_gpwebpay`. Technický název složky musí zůstat stejný.

### Poznámka k refundacím

GP webpay rozlišuje operace podle stavu platby. U platby vytvořené s okamžitým stržením (`DEPOSITFLAG=1`) nemusí být klasická refundace dostupná hned po zaplacení, dokud není uzavřena dávka transakcí. Pokud GP webpay WS refundaci odmítne, modul nyní zobrazí chybu a objednávku nepřepne na refundovanou. U čerstvé platby před uzavřením dávky použijte v portálu GP webpay operaci pro zrušení zaplacení, pokud ji portál nabízí.
Od verze testfix12 modul lokálně eviduje akceptovanou refundovanou částku v poli `refunded_amount`. Při další refundaci nejprve zkontroluje, kolik už bylo refundováno, a nedovolí odeslat vyšší částku, než zbývá z původní platby. V detailu objednávky se zobrazuje již refundovaná částka i zbývající refundovatelná částka.
