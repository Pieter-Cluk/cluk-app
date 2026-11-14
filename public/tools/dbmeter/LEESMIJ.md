# dB-meter – installatie op one.com

## Uploaden
1. Maak via het one.com File Manager (of FTP) een map aan, bv. `/db/` in je website-root.
2. Upload alle bestanden uit deze zip naar die map, **inclusief de mappen `data/`** (met het `.htaccess`-bestand erin) **en `lib/`** (nodig voor de PDF).
3. Open `https://jouwdomein/db/` – het moet **https** zijn, anders geeft de browser geen toegang tot de microfoon.

`relay.php` is alleen nodig voor het "online display op een ander toestel". De map `data/` moet schrijfbaar zijn voor PHP (bij one.com standaard zo).

## Gebruik
- **Start meting** → kies je microfoon (interne microfoon, USB-meetmicrofoon, audio-interface…).
- **Kalibratie-assistent**: met een akoestische kalibrator (94/114 dB), met een referentie-geluidsmeter (optioneel met roze ruis), of handmatig. De kalibratie wordt per microfoon bewaard.
- **Weergave**: tab "Dit scherm" en tab "Display 2" hebben elk hun eigen look en eigen waarden.
- **Tweede scherm**: "Open display op tweede scherm" opent een venster op het tweede scherm (Chrome/Edge vragen één keer toestemming voor vensterbeheer). Klik daar op ⛶, druk F of dubbelklik voor volledig scherm.
- **Live delen**: vink "Live meting delen via link" aan. Elke meting krijgt een nieuwe, unieke link, die je via e-mail, kopiëren of een QR-code deelt. Iedereen met de link volgt de meting live op gsm, tablet of pc (bv. de back-office). Stop je de meting, dan toont de link "meting beëindigd".
- **Stuur naar TV**: open op de TV (browser van de TV, Fire TV- of Google TV-stick) eenmalig `…/tools/dbmeter/tv/`. De TV toont een vaste code van 4 tekens. Tik in de meter of in de gedeelde link op 📺 Stuur naar TV en vul de code in: de TV toont de meting en haalt de gegevens zelf op. Na het stoppen van de meting keert de TV terug naar het codescherm en wacht op de volgende meting. Laat het TV-adres als startpagina staan op vaste schermen.
- **Export CSV**: waarden per seconde (LAeq, LCeq, LZeq, LAFmax, LCpeak), klaar voor Excel.
- **Meetopstelling**: meetpositie, speakers en correctiefactor (verschil tussen de microfoonpositie en de luidste publieksplek). De knop "Correctiefactor bepalen" stuurt roze ruis naar een gekozen uitgang (bv. je Scarlett naar de mengtafel) en berekent de factor.
- **Verslag (PDF)**: vul evenement en operators in (van–tot, of "Nu wisselen" tijdens het evenement) en klik op "Maak verslag". Je krijgt een totaalscore, het verloop per minuut, grafieken met overschrijdingen en per operator een score en een frequentieanalyse. Download als PDF of druk af.
- **Logo**: zet je logo als `logo.png` (of `logo.svg`) in dezelfde map als index.html, of kies per browser/profiel een eigen logo bij "Logo op het verslag".
- **Evenementprofielen**: bewaar per evenement de gegevens, meetopstelling, limiet en logo onder een naam en kies ze later in één klik. Wijzig je daarna iets, klik dan opnieuw op 💾 Bewaar.
- De meting wordt elke 30 s in de browser bewaard. Na een crash of herstart kan je verder meten of alsnog een verslag maken.

## Tips
- Gebruik Chrome of Edge voor de beste resultaten (geen automatische versterking op de microfoon).
- Tablet als vast display: zet de pagina via "Toevoegen aan beginscherm" erop; hij opent dan schermvullend en het scherm blijft aan.
- Interne microfoons van laptops en tablets zijn niet lineair bij hoge niveaus (> ~100 dB). Voor evenementen is een USB-meetmicrofoon (bv. miniDSP UMIK-1) aan te raden.
- Dit is geen gecertificeerde klasse 1- of klasse 2-geluidsmeter (IEC 61672-1). Voor de meetplicht onder VLAREM II is een klasse 2-meter of een begrenzer nodig.
