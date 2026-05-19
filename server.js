// server.js — MoneyPath multiplayer backend
// Run: node server.js  (requires: npm install ws)
//
// Copyright (c) 2025 Little Explorers sp. z o.o. — Jakub Krawczyk
// All rights reserved.
const http = require('http');
const fs   = require('fs');
const path = require('path');
const { WebSocketServer } = require('ws');

const PORT        = process.env.PORT        || 3000;
const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN || '*'; // set to your WP domain in production
const HTML        = path.join(__dirname, 'money_path.html');

// ── In-memory state ───────────────────────────────────────────
// games: Map<gameId, { id, name, hostWs, maxPlayers, players[], started, state }>
// players item: { ws, id, name, color, idx }
const games      = new Map();
const clientMeta = new Map(); // ws → { gameId, playerId }

function newId(n = 5) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let id = '';
  for (let i = 0; i < n; i++) id += chars[Math.floor(Math.random() * chars.length)];
  return id;
}

// ── Shared headers ────────────────────────────────────────────
function corsHeaders() {
  return {
    'Access-Control-Allow-Origin':  ALLOWED_ORIGIN,
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    // Allow embedding in WordPress iframe
    'X-Frame-Options':              'ALLOWALL',
    'Content-Security-Policy':      "frame-ancestors *",
  };
}

// ── HTTP server ───────────────────────────────────────────────
const server = http.createServer((req, res) => {
  // Preflight
  if (req.method === 'OPTIONS') {
    res.writeHead(204, corsHeaders());
    return res.end();
  }

  // Health check (Railway / Render ping)
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    return res.end('ok');
  }

  // Lobby REST endpoint
  if (req.url === '/api/lobby') {
    const list = [];
    for (const [id, g] of games) {
      if (!g.started) list.push({
        id,
        name:       g.name,
        players:    g.players.length,
        maxPlayers: g.maxPlayers,
        host:       g.players[0]?.name || '?'
      });
    }
    res.writeHead(200, { 'Content-Type': 'application/json', ...corsHeaders() });
    return res.end(JSON.stringify(list));
  }

  // Serve the game HTML (root or /game)
  fs.readFile(HTML, (err, data) => {
    if (err) { res.writeHead(404); return res.end('File not found'); }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', ...corsHeaders() });
    res.end(data);
  });
});

// ── WebSocket helpers ─────────────────────────────────────────
function send(ws, obj) {
  if (ws && ws.readyState === 1) ws.send(JSON.stringify(obj));
}

function broadcast(gameId, obj, skip = null) {
  const g = games.get(gameId);
  if (!g) return;
  for (const pl of g.players) {
    if (pl.ws !== skip) send(pl.ws, obj);
  }
}

// ── WebSocket server ──────────────────────────────────────────
const wss = new WebSocketServer({ server });

wss.on('connection', ws => {
  ws.on('message', raw => {
    let msg;
    try { msg = JSON.parse(raw); } catch { return; }
    handle(ws, msg);
  });

  ws.on('close', () => {
    const meta = clientMeta.get(ws);
    if (!meta) return;
    clientMeta.delete(ws);

    const g = games.get(meta.gameId);
    if (!g) return;

    const left = g.players.find(p => p.ws === ws);
    const wasHost = g.hostWs === ws;
    g.players = g.players.filter(p => p.ws !== ws);

    if (g.players.length === 0) {
      games.delete(meta.gameId);
      return;
    }

    // Transfer host if the host disconnected
    let newHostName = null;
    if (wasHost) {
      g.hostWs = g.players[0].ws;
      newHostName = g.players[0].name;
      // Notify the new host first — send current game state so they can take over
      send(g.hostWs, {
        type:        'host_transferred',
        newHostName: newHostName,
        state:       g.state
      });
    }

    // Re-index remaining players
    g.players.forEach((p, i) => { p.idx = i; });

    broadcast(meta.gameId, {
      type:        'player_left',
      playerId:    meta.playerId,
      playerName:  left?.name || '?',
      wasHost:     wasHost,
      newHostName: newHostName,
      players:     g.players.map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx }))
    });
  });
});

// ── Message handler ───────────────────────────────────────────
function handle(ws, msg) {
  switch (msg.type) {

    case 'create_game': {
      const id = newId();
      const g = {
        id,
        name:       msg.gameName || `${msg.playerName}'s game`,
        hostWs:     ws,
        maxPlayers: Math.min(6, Math.max(2, msg.maxPlayers || 4)),
        players:    [{ ws, id: msg.playerId, name: msg.playerName, color: msg.color, idx: 0 }],
        started:    false,
        state:      null
      };
      games.set(id, g);
      clientMeta.set(ws, { gameId: id, playerId: msg.playerId });
      send(ws, {
        type:      'game_created',
        gameId:    id,
        playerIdx: 0,
        players:   g.players.map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx }))
      });
      break;
    }

    case 'join_game': {
      const g = games.get(msg.gameId);
      if (!g)         return send(ws, { type: 'error', message: 'Game not found' });
      if (g.started)  return send(ws, { type: 'error', message: 'Game already started' });
      if (g.players.length >= g.maxPlayers)
                      return send(ws, { type: 'error', message: 'Game is full' });

      const idx = g.players.length;
      g.players.push({ ws, id: msg.playerId, name: msg.playerName, color: msg.color, idx });
      clientMeta.set(ws, { gameId: msg.gameId, playerId: msg.playerId });

      const players = g.players.map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx }));
      send(ws, { type: 'joined_game', gameId: msg.gameId, playerIdx: idx, players });
      broadcast(msg.gameId, { type: 'lobby_update', players }, ws);
      break;
    }

    case 'start_game': {
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || g.hostWs !== ws) return;
      if (g.players.length < 2)
        return send(ws, { type: 'error', message: 'Need at least 2 players to start' });

      g.started = true;
      g.state   = msg.state;

      // Tell every player their index + the initial state
      g.players.forEach((p, i) => {
        send(p.ws, { type: 'game_started', state: msg.state, playerIdx: i });
      });
      break;
    }

    case 'state_update': {
      // Host-only broadcast: only the host may push authoritative state to all players
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || !g.started) return;
      if (g.hostWs !== ws) return; // non-host must use player_action instead

      g.state = msg.state;
      broadcast(meta.gameId, { type: 'state_update', state: msg.state }, ws);
      break;
    }

    case 'player_action': {
      // Non-host submits their state to the host for validation and re-broadcast
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || !g.started) return;
      if (g.hostWs === ws) return; // host should never send player_action to itself

      // Route action state to host only
      send(g.hostWs, { type: 'player_action', state: msg.state });
      break;
    }

    case 'ping': {
      send(ws, { type: 'pong' });
      break;
    }

    case 'chat': {
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g) return;
      const sender = g.players.find(p => p.ws === ws);
      broadcast(meta.gameId, {
        type: 'chat',
        from: sender?.name || '?',
        text: String(msg.text || '').slice(0, 200)
      });
      // also echo back to sender
      send(ws, {
        type: 'chat',
        from: sender?.name || '?',
        text: String(msg.text || '').slice(0, 200)
      });
      break;
    }
  }
}

server.listen(PORT, () => {
  console.log(`\n🎮  MoneyPath server  →  http://localhost:${PORT}\n`);
  console.log('Share the URL with all players (same network or port-forwarded).\n');
});
