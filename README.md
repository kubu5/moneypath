# MoneyPath 🎮

An educational multiplayer financial board game playable in the browser — locally or online with friends.

## Features

- 🏦 **Financial literacy gameplay** — manage cash flow, buy assets, pay expenses, reach your dream
- 🌐 **Online multiplayer** — real-time lobby, room codes, WebSocket sync
- 🖥️ **Local multiplayer** — all players on one screen, no server needed
- 📱 **Mobile friendly** — collapsible panels, sticky cash bar, responsive layout
- 🔌 **WordPress ready** — embed via `[moneypath]` shortcode in any page

---

## Quick Start

### Play locally (no server needed)

Open `online_viewer_net.html` directly in your browser and choose **Play Local**.

### Play online (multiplayer)

You need to run the Node.js server:

```bash
npm install
node server.js
```

Then open `http://localhost:3000` in the browser.

---

## Deployment

### 1. Deploy server to Railway (free)

1. Push this repo to GitHub (done ✅)
2. Go to [railway.app](https://railway.app) → log in with GitHub
3. **New Project → Deploy from GitHub repo → kubu5/moneypath**
4. Railway auto-detects Node.js and runs `node server.js`
5. Go to **Settings → Networking → Generate Domain**
6. You'll get a URL like `https://moneypath-production.up.railway.app`

### 2. Install WordPress plugin

1. Zip the `wordpress-plugin/moneypath-game/` folder
2. In WP Admin go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and activate
4. Go to **Settings → MoneyPath** and paste your Railway server URL
5. Add to any page: `[moneypath fullscreen="yes"]`

---

## How to play online

1. All players open the same URL (e.g. your WordPress page)
2. Click **Play Online**
3. One player (host) enters their name → clicks **Create Game**
4. A 5-letter room code appears (e.g. `AB3K7`)
5. Others enter their name → see the game in the lobby → click **JOIN**
6. Host clicks **Start Game**
7. Each player picks their dream on their own screen
8. Game begins — everyone plays on their own device!

---

## Project Structure

```
moneypath/
├── online_viewer_net.html     # Full game (single-file frontend)
├── server.js                  # Node.js multiplayer backend (WebSocket + HTTP)
├── package.json
├── Procfile                   # Railway / Heroku deployment
├── INSTRUKCJA.md              # Polish deployment guide
└── wordpress-plugin/
    └── moneypath-game/
        ├── moneypath-game.php # WordPress plugin with [moneypath] shortcode
        └── moneypath-game.css # Embed styles
```

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vanilla HTML/CSS/JS, Canvas API |
| Backend | Node.js, `ws` (WebSocket) |
| Hosting | Railway.app (free tier) |
| CMS integration | WordPress shortcode plugin |

---

## Requirements

- Node.js 18+ (Railway installs automatically)
- WordPress 5.0+ (for plugin)
- Any modern browser (Chrome, Firefox, Safari, Edge)

---

## License

MIT
