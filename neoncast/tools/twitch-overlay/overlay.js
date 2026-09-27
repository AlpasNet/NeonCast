(() => {
  'use strict';

  const BASE_WIDTH = 1920;
  const BASE_HEIGHT = 1080;
  const MAX_CHAT_MESSAGES = 3;
  const RECONNECT_DELAY = 5000;

  const overlay = document.getElementById('overlay');
  const avatar = document.getElementById('avatar');
  const avatarFallback = document.getElementById('avatarFallback');
  const cover = document.getElementById('gameCover');
  const coverFallback = document.getElementById('coverFallback');
  const dateNode = document.getElementById('currentDate');
  const timeNode = document.getElementById('currentTime');
  const chat = document.getElementById('twitchChat');
  const chatStatus = document.getElementById('chatStatus');

  let twitchSocket = null;
  let reconnectTimer = null;
  let manuallyClosing = false;

  function resizeOverlay() {
    const scale = Math.min(window.innerWidth / BASE_WIDTH, window.innerHeight / BASE_HEIGHT);
    overlay.style.transform = `translate(-50%, -50%) scale(${scale})`;
  }

  function showImageFallback(image, fallback) {
    image.addEventListener('error', () => {
      image.style.display = 'none';
      fallback.style.display = 'grid';
    });
    image.addEventListener('load', () => {
      image.style.display = 'block';
      fallback.style.display = 'none';
    });
  }

  function updateClock() {
    const now = new Date();
    dateNode.textContent = new Intl.DateTimeFormat('en-GB', {
      day: '2-digit', month: 'short', year: 'numeric'
    }).format(now);
    timeNode.textContent = new Intl.DateTimeFormat('en-GB', {
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
    }).format(now);
  }

  function parseTags(rawTags) {
    const tags = {};
    for (const item of rawTags.split(';')) {
      const idx = item.indexOf('=');
      if (idx === -1) { tags[item] = ''; continue; }
      const key = item.slice(0, idx);
      const value = item.slice(idx + 1)
        .replaceAll('\\s', ' ')
        .replaceAll('\\:', ';')
        .replaceAll('\\r', '\r')
        .replaceAll('\\n', '\n')
        .replaceAll('\\\\', '\\');
      tags[key] = value;
    }
    return tags;
  }

  function parsePrivmsg(line) {
    const match = line.match(/^@([^ ]+) :([^!]+)![^ ]+ PRIVMSG #[^ ]+ :(.*)$/);
    if (!match) return null;
    const tags = parseTags(match[1]);
    const rawMessage = match[3] || '';
    return {
      user: tags['display-name'] || match[2],
      color: tags.color || '#ffffff',
      message: rawMessage,
      emotes: tags.emotes || '',
      action: rawMessage.startsWith('\u0001ACTION ') && rawMessage.endsWith('\u0001')
    };
  }

  function cleanMessage(message, isAction) {
    if (!isAction) return message;
    return message.replace(/^\u0001ACTION /, '').replace(/\u0001$/, '');
  }

  function parseEmoteRanges(emotesTag, offset = 0) {
    if (!emotesTag) return [];

    const ranges = [];
    for (const group of emotesTag.split('/')) {
      if (!group) continue;
      const separator = group.indexOf(':');
      if (separator === -1) continue;

      const id = group.slice(0, separator);
      const positions = group.slice(separator + 1);
      if (!id || !positions) continue;

      for (const position of positions.split(',')) {
        const [rawStart, rawEnd] = position.split('-');
        const start = Number.parseInt(rawStart, 10) - offset;
        const end = Number.parseInt(rawEnd, 10) - offset;
        if (!Number.isInteger(start) || !Number.isInteger(end) || start < 0 || end < start) continue;
        ranges.push({ id, start, end });
      }
    }

    return ranges.sort((a, b) => a.start - b.start || a.end - b.end);
  }

  function appendMessageWithEmotes(container, message, emotesTag, isAction) {
    const cleaned = cleanMessage(message, isAction);
    const actionOffset = isAction ? '\u0001ACTION '.length : 0;
    const ranges = parseEmoteRanges(emotesTag, actionOffset)
      .filter(range => range.start < cleaned.length && range.end < cleaned.length);

    if (!ranges.length) {
      container.textContent = cleaned;
      return;
    }

    let cursor = 0;
    for (const range of ranges) {
      if (range.start < cursor) continue;

      if (range.start > cursor) {
        container.appendChild(document.createTextNode(cleaned.slice(cursor, range.start)));
      }

      const emoteText = cleaned.slice(range.start, range.end + 1);
      const img = document.createElement('img');
      img.className = 'chat-emote';
      img.src = `https://static-cdn.jtvnw.net/emoticons/v2/${encodeURIComponent(range.id)}/default/dark/2.0`;
      img.alt = emoteText;
      img.title = emoteText;
      img.loading = 'eager';
      img.decoding = 'async';
      img.referrerPolicy = 'no-referrer';
      img.addEventListener('error', () => {
        img.replaceWith(document.createTextNode(emoteText));
      }, { once: true });
      container.appendChild(img);

      cursor = range.end + 1;
    }

    if (cursor < cleaned.length) {
      container.appendChild(document.createTextNode(cleaned.slice(cursor)));
    }
  }

  function trimChat() {
    const messages = chat.querySelectorAll('.chat-message');
    if (messages.length <= MAX_CHAT_MESSAGES) return;
    const oldest = messages[0];
    oldest.classList.add('removing');
    window.setTimeout(() => oldest.remove(), 220);
  }

  function appendChatMessage(data) {
    if (!data.message.trim()) return;
    if (chatStatus) chatStatus.style.display = 'none';

    const row = document.createElement('div');
    row.className = `chat-message${data.action ? ' action' : ''}`;

    const user = document.createElement('span');
    user.className = 'chat-user';
    user.textContent = `${data.user}:`;
    user.style.setProperty('--chat-user-color', data.color || '#ffffff');

    const text = document.createElement('span');
    text.className = 'chat-text';
    appendMessageWithEmotes(text, data.message, data.emotes, data.action);

    row.append(user, text);
    chat.appendChild(row);
    trimChat();
  }

  function scheduleReconnect() {
    if (manuallyClosing || reconnectTimer) return;
    reconnectTimer = window.setTimeout(() => {
      reconnectTimer = null;
      connectTwitchChat();
    }, RECONNECT_DELAY);
  }

  function connectTwitchChat() {
    const channel = (chat?.dataset.channel || '').trim().toLowerCase();
    if (!chat || !channel) return;

    if (chatStatus) {
      chatStatus.style.display = 'block';
      chatStatus.textContent = 'CONNECTING TO CHAT…';
    }

    const nick = `justinfan${Math.floor(10000 + Math.random() * 89999)}`;
    const ws = new WebSocket('wss://irc-ws.chat.twitch.tv:443');
    twitchSocket = ws;

    ws.addEventListener('open', () => {
      ws.send('CAP REQ :twitch.tv/tags twitch.tv/commands');
      ws.send('PASS SCHMOOPIIE');
      ws.send(`NICK ${nick}`);
      ws.send(`JOIN #${channel}`);
      if (chatStatus) chatStatus.textContent = 'CHAT CONNECTED';
      window.setTimeout(() => {
        if (chatStatus) chatStatus.style.display = 'none';
      }, 1200);
    });

    ws.addEventListener('message', event => {
      for (const line of String(event.data || '').split('\r\n')) {
        if (!line) continue;
        if (line.startsWith('PING ')) {
          ws.send(line.replace('PING', 'PONG'));
          continue;
        }
        const message = parsePrivmsg(line);
        if (message) appendChatMessage(message);
      }
    });

    ws.addEventListener('close', () => {
      if (chatStatus) {
        chatStatus.style.display = 'block';
        chatStatus.textContent = 'RECONNECTING…';
      }
      scheduleReconnect();
    });

    ws.addEventListener('error', () => {
      try { ws.close(); } catch (_) {}
    });
  }

  showImageFallback(avatar, avatarFallback);
  showImageFallback(cover, coverFallback);
  resizeOverlay();
  updateClock();
  connectTwitchChat();

  window.addEventListener('resize', resizeOverlay);
  window.setInterval(updateClock, 1000);
  window.addEventListener('beforeunload', () => {
    manuallyClosing = true;
    if (reconnectTimer) window.clearTimeout(reconnectTimer);
    try { twitchSocket?.close(); } catch (_) {}
  });
})();
