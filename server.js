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
        name:        g.name,
        players:     g.players.length,
        maxPlayers:  g.maxPlayers,
        host:        g.players[0]?.name || '?',
        hasPassword: !!g.password
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

    // Mark player as disconnected in the connected-players list
    const slot = g.players.find(p => p.id === meta.playerId);
    if (slot) {
      slot.ws = null;
      slot.disconnectedAt = Date.now();
    }

    // If game not started yet — just remove from lobby
    if (!g.started) {
      g.players = g.players.filter(p => p.id !== meta.playerId);
      if (g.players.length === 0) { games.delete(meta.gameId); return; }
      // Transfer lobby host if needed
      if (g.hostWs === ws) {
        const nextConnected = g.players.find(p => p.ws && p.ws.readyState === 1);
        if (nextConnected) {
          g.hostWs = nextConnected.ws;
          g.hostPlayerId = nextConnected.id;
        }
      }
      broadcast(meta.gameId, {
        type: 'player_left',
        playerId: meta.playerId,
        players: g.players
          .filter(p => p.ws && p.ws.readyState === 1)
          .map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx }))
      });
      return;
    }

    // Game in progress — mark disconnected in state, don't splice
    if (g.state?.players) {
      const sp = g.state.players.find(p => p.id === meta.playerId);
      if (sp) { sp.connected = false; sp.disconnectedAt = Date.now(); }
    }

    // Transfer host if needed
    let newHostName = null;
    if (g.hostWs === ws || g.hostPlayerId === meta.playerId) {
      const nextConnected = g.players.find(p => p.id !== meta.playerId && p.ws && p.ws.readyState === 1);
      if (nextConnected) {
        g.hostWs = nextConnected.ws;
        g.hostPlayerId = nextConnected.id;
        newHostName = nextConnected.name;
        if (g.state) g.state.hostPlayerId = g.hostPlayerId;
        send(g.hostWs, { type: 'host_transferred', newHostName, state: g.state });
      }
    }

    // Broadcast disconnection — NOT player_left (don't splice)
    broadcast(meta.gameId, {
      type: 'player_disconnected',
      playerId: meta.playerId,
      newHostPlayerId: newHostName ? g.hostPlayerId : undefined,
      newHostName,
      state: g.state
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
        name:         msg.gameName || `${msg.playerName}'s game`,
        password:     msg.password ? String(msg.password).slice(0, 50) : '',
        hostWs:       ws,
        hostPlayerId: msg.playerId,
        maxPlayers:   Math.min(6, Math.max(2, msg.maxPlayers || 4)),
        players:      [{ ws, id: msg.playerId, name: msg.playerName, color: msg.color, idx: 0 }],
        started:      false,
        state:        null,
        seqMap:       {}
      };
      games.set(id, g);
      clientMeta.set(ws, { gameId: id, playerId: msg.playerId });
      send(ws, {
        type:        'game_created',
        gameId:      id,
        gameName:    g.name,
        hasPassword: !!g.password,
        playerIdx:   0,
        players:     g.players.map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx }))
      });
      break;
    }

    case 'join_game': {
      const g = games.get(msg.gameId);
      if (!g)         return send(ws, { type: 'error', message: 'Game not found' });
      if (g.started)  return send(ws, { type: 'error', message: 'Game already started' });
      if (g.players.length >= g.maxPlayers)
                      return send(ws, { type: 'error', message: 'Game is full' });
      // Password check
      if (g.password && msg.password !== g.password)
                      return send(ws, { type: 'error', message: 'Wrong password', code: 'wrong_password' });

      const idx = g.players.length;
      g.players.push({ ws, id: msg.playerId, name: msg.playerName, color: msg.color, idx });
      clientMeta.set(ws, { gameId: msg.gameId, playerId: msg.playerId });

      const players = g.players.map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx }));
      send(ws, { type: 'joined_game', gameId: msg.gameId, gameName: g.name, hasPassword: !!g.password, playerIdx: idx, players });
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
      g.hostPlayerId = g.hostPlayerId || g.players[0].id;
      if (g.state) g.state.hostPlayerId = g.hostPlayerId;
      if (!g.seqMap) g.seqMap = {};

      // Tell every player their index + the initial state
      g.players.forEach((p, i) => {
        send(p.ws, { type: 'game_started', state: msg.state, playerIdx: i });
      });
      break;
    }

    case 'state_update': {
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || !g.started) return;

      // Validate: sender must be in room
      const senderSlot = g.players.find(p => p.id === msg.playerId);
      if (!senderSlot) return;

      // Validate: sender must be host OR current turn player
      const curPlayerId = g.state?.players?.[g.state?.currentPlayerIdx]?.id;
      const senderIsHost = g.hostPlayerId === msg.playerId;
      const senderIsCurPlayer = msg.playerId === curPlayerId;
      if (!senderIsHost && !senderIsCurPlayer) return;

      // Reject stale updates (clientSeq must be > server's last seen seq for this sender)
      const senderSeq = msg.clientSeq ?? 0;
      const lastSeq   = g.seqMap?.[msg.playerId] ?? -1;
      if (senderSeq <= lastSeq && lastSeq > 0) return; // stale
      if (!g.seqMap) g.seqMap = {};
      g.seqMap[msg.playerId] = senderSeq;

      // Accept and store
      g.state = msg.state;
      // Propagate connected flags from server's presence registry
      if (g.state && g.state.players) {
        g.state.players.forEach(sp => {
          const slot = g.players.find(p => p.id === sp.id);
          sp.connected = slot ? !!(slot.ws && slot.ws.readyState === 1) : false;
        });
      }

      broadcast(meta.gameId, {
        type: 'state_update',
        state: g.state,
        fromPlayerId: msg.playerId
      }, ws);
      break;
    }

    case 'player_action': {
      // Deprecated routing through host — now treated as a direct state update
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || !g.started || !msg.state) return;
      const senderSlot = g.players.find(p => p.id === msg.playerId);
      if (!senderSlot) return;
      const curPlayerId = g.state?.players?.[g.state?.currentPlayerIdx]?.id;
      if (msg.playerId !== curPlayerId && g.hostPlayerId !== msg.playerId) return;
      g.state = msg.state;
      broadcast(meta.gameId, { type: 'state_update', state: g.state, fromPlayerId: msg.playerId }, ws);
      break;
    }

    case 'skip_disconnected_player': {
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || !g.started || !g.state) return;
      // Only current host can skip
      if (g.hostPlayerId !== meta.playerId) return;

      // Advance to next connected, non-bankrupt player
      const players = g.state.players;
      const total = players.length;
      if (!total) return;
      let next = (g.state.currentPlayerIdx + 1) % total;
      let safety = 0;
      while (safety++ < total) {
        const candidate = players[next];
        const slot = g.players.find(p => p.id === candidate.id);
        const isConnected = slot && slot.ws && slot.ws.readyState === 1;
        if (isConnected && !candidate.bankrupt) break;
        next = (next + 1) % total;
      }
      g.state.currentPlayerIdx = next;
      broadcast(meta.gameId, { type: 'state_update', state: g.state, fromPlayerId: meta.playerId });
      break;
    }

    case 'transfer_host': {
      // Host bankrupted in-game — voluntarily hands off host role to another player
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || g.hostWs !== ws) return; // only current host may transfer

      // Find the new host by playerId
      const newHostSlot = g.players.find(p => p.id === msg.newHostId && p.ws !== ws);
      if (!newHostSlot) return; // target not connected

      g.hostWs = newHostSlot.ws;
      g.state  = msg.state || g.state; // adopt latest state sent with the transfer

      send(g.hostWs, {
        type:        'host_transferred',
        newHostName: newHostSlot.name,
        state:       g.state
      });

      broadcast(meta.gameId, {
        type:        'host_changed',
        newHostName: newHostSlot.name,
        state:       g.state   // send updated state so all clients remove the bankrupt player
      }, g.hostWs);
      break;
    }

    case 'rejoin_game': {
      const g = games.get(msg.gameId);
      if (!g)         return send(ws, { type: 'error', message: 'Game not found' });
      if (!g.started) return send(ws, { type: 'error', message: 'Game not started yet' });
      if (!g.state)   return send(ws, { type: 'error', message: 'No state available' });

      // Find the player slot by stable ID
      const slot = g.players.find(p => p.id === msg.playerId);
      if (!slot) return send(ws, { type: 'error', message: 'Player not found in room' });

      // Restore websocket connection
      slot.ws = ws;
      slot.disconnectedAt = null;
      clientMeta.set(ws, { gameId: msg.gameId, playerId: msg.playerId });

      // Update state presence flag
      if (g.state?.players) {
        const sp = g.state.players.find(p => p.id === msg.playerId);
        if (sp) { sp.connected = true; sp.disconnectedAt = null; }
      }

      // Decide if this player becomes host
      const hostAlive = g.players.some(p => p.id === g.hostPlayerId && p.ws && p.ws.readyState === 1);
      if (!hostAlive) {
        g.hostPlayerId = msg.playerId;
        g.hostWs = ws;
        if (g.state) g.state.hostPlayerId = g.hostPlayerId;
      }
      const amHost = g.hostPlayerId === msg.playerId;

      // Recompute playerIdx from stable ID in state
      const playerIdx = g.state.players.findIndex(p => p.id === msg.playerId);

      send(ws, {
        type:       'rejoined_game',
        playerIdx,
        gameId:     msg.gameId,
        isHost:     amHost,
        state:      g.state,
        players:    g.players.map(p => ({ id: p.id, name: p.name, color: p.color, idx: p.idx, connected: !!(p.ws && p.ws.readyState === 1) }))
      });

      broadcast(msg.gameId, {
        type:        'player_rejoined',
        playerId:    msg.playerId,
        playerName:  slot.name,
        state:       g.state
      }, ws);
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
      const chatMsg = {
        type: 'chat',
        from: sender?.name || '?',
        text: String(msg.text || '').slice(0, 200)
      };
      broadcast(meta.gameId, chatMsg);
      send(ws, chatMsg); // echo back to sender
      break;
    }

    case 'loan_request':
    case 'loan_offer': {
      // Route directly to the target player
      const meta = clientMeta.get(ws);
      if (!meta) return;
      const g = games.get(meta.gameId);
      if (!g || !g.started) return;
      const targetSlot = g.players[msg.toIdx];
      if (targetSlot?.ws) send(targetSlot.ws, msg);
      break;
    }
  }
}

server.listen(PORT, () => {
  console.log(`\n🎮  MoneyPath server  →  http://localhost:${PORT}\n`);
  console.log('Share the URL with all players (same network or port-forwarded).\n');
});
