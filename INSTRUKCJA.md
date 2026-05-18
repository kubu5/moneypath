# MoneyPath — Instrukcja wdrożenia

---

## Zawartość paczki

```
moneypath/
├── INSTRUKCJA.md                  ← ten plik
├── server.js                      ← serwer Node.js (backend gry)
├── package.json                   ← zależności npm
├── Procfile                       ← dla Railway / Heroku
├── .gitignore
├── money_path.html         ← cała gra (frontend, ~150 KB)
└── wordpress-plugin/
    └── moneypath-game/
        ├── moneypath-game.php     ← wtyczka WordPress
        └── moneypath-game.css     ← style osadzenia
```

---

## KROK 1 — Wgraj kod na GitHub (wymagane do deployment)

1. Utwórz darmowe konto na **github.com**
2. Kliknij **New repository** → nazwa: `moneypath` → Public → Create
3. Na swoim komputerze w folderze z grą:

```bash
git init
git add server.js package.json money_path.html Procfile .gitignore
git commit -m "MoneyPath v1"
git branch -M main
git remote add origin https://github.com/TWOJE-KONTO/moneypath.git
git push -u origin main
```

> Jeśli nie masz `git` — pobierz ze strony git-scm.com

---

## KROK 2 — Wdróż serwer gry na Railway (darmowy)

> Railway to platforma hostingowa dla aplikacji Node.js.
> Darmowy plan pozwala na 500 godzin miesięcznie — wystarczy na grę.

1. Wejdź na **railway.app** → zaloguj się przez GitHub
2. Kliknij **New Project**
3. Wybierz **Deploy from GitHub repo**
4. Wybierz repozytorium `moneypath`
5. Railway automatycznie:
   - wykryje Node.js
   - uruchomi `npm install`
   - uruchomi `node server.js`
6. Kliknij zakładkę **Settings** → **Networking** → **Generate Domain**
7. Otrzymasz adres np.:
   ```
   https://moneypath-production.up.railway.app
   ```
8. Wejdź na ten adres — powinna pojawić się gra ✅

---

## KROK 3 — Zainstaluj wtyczkę w WordPress

### 3a. Spakuj wtyczkę do ZIP

W folderze z grą wejdź do `wordpress-plugin/` i spakuj folder `moneypath-game`:
- **Mac**: kliknij prawym na folder `moneypath-game` → Kompresuj
- **Windows**: kliknij prawym → Wyślij do → Folder skompresowany

Plik wynikowy: `moneypath-game.zip`

### 3b. Zainstaluj w WordPress

1. Zaloguj się do **WP Admin** (twoja-strona.pl/wp-admin)
2. Przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę**
3. Wybierz plik `moneypath-game.zip`
4. Kliknij **Zainstaluj teraz → Aktywuj wtyczkę**

### 3c. Ustaw adres serwera

1. Przejdź do **Ustawienia → MoneyPath**
2. W polu **Game Server URL** wpisz adres z Railway, np.:
   ```
   https://moneypath-production.up.railway.app
   ```
3. Kliknij **Zapisz ustawienia**

---

## KROK 4 — Dodaj grę do strony WordPress

1. Przejdź do **Strony → Dodaj nową**
2. Tytuł: np. `Zagraj w MoneyPath`
3. W edytorze kliknij **+** → wyszukaj blok **Shortcode**
4. Wpisz:
   ```
   [moneypath fullscreen="yes"]
   ```
5. Kliknij **Opublikuj**

Gra jest dostępna pod adresem np.: `twoja-strona.pl/zagraj`

---

## Jak grać online (instrukcja dla graczy)

1. Wszyscy gracze wchodzą na **tą samą stronę** (np. `twoja-strona.pl/zagraj`)
2. Każdy klika **Play Online**
3. Jeden gracz (host) wpisuje swoje imię → klika **Create Game**
4. Pojawia się 5-literowy kod pokoju (np. `AB3K7`)
5. Pozostali gracze wpisują swoje imię → widzą grę na liście → klikają **JOIN**
6. Gdy wszyscy dołączą — host klika **Start Game**
7. Każdy gracz wybiera swoje marzenie na własnym ekranie
8. Gra się rozpoczyna — każdy gra na swoim urządzeniu!

---

## Granie lokalnie (bez internetu)

Otwórz plik `money_path.html` bezpośrednio w przeglądarce.
Wybierz **Play Local** — wszyscy grają na jednym ekranie.

> Tryb lokalny nie wymaga serwera ani internetu.

---

## Najczęstsze pytania

**Q: Gra się nie ładuje w iframe WordPress**
A: Upewnij się, że serwer działa — wejdź bezpośrednio na URL serwera Railway.

**Q: Połączenie WebSocket nie działa**
A: Sprawdź czy URL serwera zaczyna się od `https://` (nie `http://`).

**Q: Railway wyłączył serwer**
A: Darmowy plan działa 500h/miesiąc. Wejdź na railway.app → uruchom projekt ponownie.

**Q: Chcę własną domenę dla serwera gry**
A: W Railway: Settings → Networking → Custom Domain → wpisz np. `gra.twoja-strona.pl`
   Następnie dodaj rekord CNAME w DNS swojej domeny.

**Q: Mam VPS z WordPress**
A: Możesz uruchomić serwer gry bezpośrednio na VPS i skonfigurować Nginx jako proxy.
   Szczegóły w README.md.

---

## Wsparcie techniczne

Wymagania:
- Node.js 18+ (na serwerze — Railway instaluje automatycznie)
- WordPress 5.0+ z obsługą shortcode
- Dowolna nowoczesna przeglądarka (Chrome, Firefox, Safari, Edge)
