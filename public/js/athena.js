(function () {
  'use strict';
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta.content;

  // Get a fresh security token (after logging in/out in another tab, or a long idle page)
  function refreshToken() {
    return fetch(window.ATHENA.routes.history, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.csrf) { csrf = d.csrf; csrfMeta.content = d.csrf; } });
  }

  function postJson(url, body, retried) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(body)
    }).then(function (r) {
      if (r.status === 419 && !retried) {
        return refreshToken().then(function () { return postJson(url, body, true); });
      }
      return r.json().then(function (d) { return { status: r.status, d: d }; });
    });
  }
  var A = window.ATHENA;

  /* ---------- mobile menu ---------- */
  var toggle = document.querySelector('.menu-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------- schedule filters ---------- */
  var chips = document.querySelectorAll('[data-filter]');
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      var f = chip.getAttribute('data-filter');
      chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
      document.querySelectorAll('.slot').forEach(function (s) {
        s.classList.toggle('is-hidden', f !== 'all' && s.getAttribute('data-cat') !== f);
      });
    });
  });

  var preCat = new URLSearchParams(location.search).get('cat');
  if (preCat) {
    var preChip = document.querySelector('[data-filter="' + preCat.replace(/[^a-z]/g, '') + '"]');
    if (preChip) preChip.click();
  }

  /* ---------- chat ---------- */
  var panel = document.getElementById('chat-panel');
  var launcher = document.querySelector('.chat-launcher');
  var log = panel.querySelector('.chat-log');
  var form = panel.querySelector('.chat-form');
  var input = panel.querySelector('#chat-input');
  var sendBtn = form.querySelector('button');
  var loaded = false;
  var busy = false;

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  var LABELS = { register: 'Register', login: 'Log in', schedule: 'Schedule', dashboard: 'My account',
    membership: 'Membership', coaches: 'Coaches', contact: 'Contact us' };
  function linkLabel(url) {
    var path = url.replace(/[?#].*$/, '').replace(/\/+$/, '').split('/').pop().toLowerCase();
    if (LABELS[path]) return LABELS[path];
    if (/facebook\.com/.test(url)) return 'Facebook page';
    if (/maps\.google|google\.com\/maps/.test(url)) return 'Map';
    return 'this link';
  }

  // light markdown: **bold**, *italic*, "- " and "1. " lists, paragraphs
  function render(text) {
    var lines = escapeHtml(text).split(/\r?\n/);
    var html = '', list = null;
    function close() { if (list) { html += '</' + list + '>'; list = null; } }
    lines.forEach(function (line) {
      var t = line.trim();
      var ul = t.match(/^[-*•]\s+(.*)/), ol = t.match(/^\d+[.)]\s+(.*)/);
      if (ul || ol) {
        var type = ul ? 'ul' : 'ol';
        if (list !== type) { close(); html += '<' + type + '>'; list = type; }
        html += '<li>' + (ul || ol)[1] + '</li>';
      } else if (t === '') {
        close();
      } else {
        close();
        html += '<p>' + t + '</p>';
      }
    });
    close();
    return html
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*\s][^*]*?)\*/g, '$1<em>$2</em>')
      // [Register](http://...) -> clickable word
      .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2">$1</a>')
      // bare links -> short friendly label
      .replace(/(^|[^"'>])(https?:\/\/[^\s<)]+[^\s<).,!?])/g, function (m, pre, url) {
        return pre + '<a href="' + url + '">' + linkLabel(url) + '</a>';
      });
  }

  function add(role, text, cls) {
    var div = document.createElement('div');
    div.className = 'msg ' + (role === 'user' ? 'msg-user' : role === 'note' ? 'msg-note' : 'msg-bot') + (cls ? ' ' + cls : '');
    if (role === 'user') div.textContent = text; else div.innerHTML = role === 'note' ? text : render(text);
    if (role === 'bot') {
      var row = document.createElement('div');
      row.className = 'msg-bot-row';
      var img = document.createElement('img');
      img.src = A.avatar; img.alt = '';
      row.appendChild(img); row.appendChild(div);
      log.appendChild(row);
    } else {
      log.appendChild(div);
    }
    log.scrollTop = log.scrollHeight;
    return div;
  }

  function greet() {
    if (A.name) {
      add('bot', 'Hey ' + A.name.split(' ')[0] + ', welcome back! 💪 What are we training this week? I can check classes, book a spot for you, or just chat about your goals.');
    } else {
      add('bot', "Hi there! I'm Athena AI from Infliction Gym 👋 Are you just getting started with the gym, or already training and looking for a new routine?");
    }
  }

  function loadHistory() {
    if (loaded) return;
    loaded = true;
    fetch(A.routes.history, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.csrf) { csrf = d.csrf; csrfMeta.content = d.csrf; }
        greet();
        (d.messages || []).forEach(function (m) { add(m.role === 'user' ? 'user' : 'bot', m.content); });
      })
      .catch(function () { greet(); });
  }

  function openChat() {
    panel.hidden = false;
    panel.classList.add('is-entering');
    setTimeout(function () { panel.classList.remove('is-entering'); }, 250);
    launcher.setAttribute('aria-expanded', 'true');
    loadHistory();
    input.focus();
  }
  function closeChat() {
    panel.hidden = true;
    launcher.setAttribute('aria-expanded', 'false');
    launcher.focus();
  }

  document.querySelectorAll('[data-open-chat]').forEach(function (b) { b.addEventListener('click', openChat); });
  panel.querySelector('[data-close-chat]').addEventListener('click', closeChat);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) closeChat(); });

  panel.querySelector('[data-chat-reset]').addEventListener('click', function () {
    postJson(A.routes.reset, {}).then(function () { log.innerHTML = ''; greet(); input.focus(); });
  });

  panel.querySelectorAll('[data-suggest]').forEach(function (b) {
    b.addEventListener('click', function () { send(b.getAttribute('data-suggest')); });
  });

  input.addEventListener('input', function () {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : send(input.value); }
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });

  function send(text) {
    text = (text || '').trim();
    if (!text || busy) return;
    busy = true;
    sendBtn.disabled = true;
    add('user', text);
    input.value = '';
    input.style.height = 'auto';
    var typing = add('bot', '');
    typing.innerHTML = '<span class="typing" aria-label="Athena AI is typing"><span></span><span></span><span></span></span>';

    postJson(A.routes.send, { message: text })
      .then(function (res) {
        (typing.parentNode && typing.parentNode.classList.contains('msg-bot-row') ? typing.parentNode : typing).remove();
        var d = res.d;
        if (res.status === 419) { add('bot', 'Your session expired. Reload the page and try again.', 'is-error'); return; }
        if (d.message && !d.reply) { add('bot', d.message, 'is-error'); return; }
        add('bot', d.reply, d.offline ? 'is-error' : '');
        if (d.offline && d.debug) console.warn('Athena AI offline:', d.debug);
        if (d.booking_changed) {
          add('note', 'Your bookings changed. <a href="' + A.routes.dashboard + '">See them in My account</a>');
        }
        if (d.lead_saved) {
          add('note', 'Your details were sent to the front desk.');
        }
      })
      .catch(function () {
        (typing.parentNode && typing.parentNode.classList.contains('msg-bot-row') ? typing.parentNode : typing).remove();
        add('bot', 'The message did not go through. Check your connection, then try again.', 'is-error');
      })
      .finally(function () { busy = false; sendBtn.disabled = false; input.focus(); });
  }
  /* ================= home page widgets ================= */

  // "Tap to ask" chips open Athena AI with that question
  document.querySelectorAll('[data-ask]').forEach(function (b) {
    b.addEventListener('click', function () {
      openChat();
      setTimeout(function () { send(b.getAttribute('data-ask')); }, loaded ? 50 : 600);
    });
  });

  // Live countdown to the next class
  var cd = document.querySelector('[data-countdown]');
  if (cd) {
    var target = new Date(cd.getAttribute('data-countdown')).getTime();
    var out = cd.querySelector('.countdown-time');
    var tick = function () {
      var diff = Math.round((target - Date.now()) / 1000);
      if (diff <= 0) { out.textContent = 'starting now'; return; }
      var h = Math.floor(diff / 3600), m = Math.floor((diff % 3600) / 60), sec = diff % 60;
      out.textContent = 'in ' + (h ? h + 'h ' : '') + (h || m ? m + 'm ' : '') + (h ? '' : sec + 's');
    };
    tick(); setInterval(tick, 1000);
  }

  // Plan finder
  var pf = document.getElementById('plan-finder');
  if (pf && window.GYM_PLANS) {
    var answers = {};
    var byName = function (n) { return window.GYM_PLANS.filter(function (p) { return p.name.toLowerCase() === n; })[0]; };
    pf.querySelectorAll('.pf-q').forEach(function (q) {
      q.querySelectorAll('button').forEach(function (b) {
        b.setAttribute('aria-pressed', 'false');
        b.addEventListener('click', function () {
          q.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
          answers[q.getAttribute('data-q')] = b.getAttribute('data-v');
          q.classList.add('is-done');
          showPlan();
        });
      });
    });
    var showPlan = function () {
      if (!answers.freq || !answers.classes || !answers.student) return;
      var pick, why;
      if (answers.freq === 'once') {
        pick = byName('day pass'); why = 'Pay only when you come in. Great for trying us out first.';
      } else if (answers.classes === 'many') {
        pick = byName('premium'); why = 'Unlimited classes plus a free personal training session every month.';
      } else if (answers.student === 'yes') {
        pick = byName('student'); why = 'Full gym access and 8 classes a month at the lowest monthly price. Bring your school ID.';
      } else if (answers.classes === 'none' || answers.freq === 'some') {
        pick = byName('basic'); why = 'Unlimited gym floor with a few classes a month when you want variety.';
      } else {
        pick = byName('premium'); why = 'You train often, so unlimited classes give you the most for your money.';
      }
      pick = pick || window.GYM_PLANS[0];
      var r = pf.querySelector('.pf-result');
      r.hidden = false;
      r.style.animation = 'none'; void r.offsetWidth; r.style.animation = '';
      r.querySelector('.pf-name').textContent = pick.name;
      r.querySelector('.pf-price').textContent = pick.price + ' / ' + pick.period;
      r.querySelector('.pf-why').textContent = why;
      r.querySelector('.pf-join').href = window.REGISTER_URL + '?plan=' + pick.id;
      r.querySelector('.pf-ask').onclick = function () {
        setTimeout(function () { send('Tell me more about the ' + pick.name + ' plan. Is it right for me?'); }, loaded ? 50 : 600);
      };
    };
  }

  // Plate calculator (bumper plate colors)
  var pc = document.getElementById('plate-calc');
  if (pc) {
    var PLATES = [
      { kg: 25, color: '#D7263D', h: 120, w: 22, label: '#fff' },
      { kg: 20, color: '#1D5FD0', h: 120, w: 20, label: '#fff' },
      { kg: 15, color: '#F2B705', h: 120, w: 18, label: '#1B1F24' },
      { kg: 10, color: '#2E9E5B', h: 120, w: 15, label: '#fff' },
      { kg: 5, color: '#F4F5F3', h: 84, w: 12, label: '#1B1F24' },
      { kg: 2.5, color: '#5B636E', h: 66, w: 10, label: '#fff' },
      { kg: 1.25, color: '#AEB4AD', h: 54, w: 8, label: '#1B1F24' }
    ];
    var bar = 20;
    var wIn = pc.querySelector('#pc-weight'), wRange = pc.querySelector('#pc-range');
    var svg = pc.querySelector('.pc-bar'), summary = pc.querySelector('#pc-summary');
    var NS = 'http://www.w3.org/2000/svg';
    var el = function (tag, attrs) { var e = document.createElementNS(NS, tag); for (var k in attrs) e.setAttribute(k, attrs[k]); return e; };

    var draw = function () {
      var target = Math.max(bar, Math.min(300, parseFloat(wIn.value) || bar));
      var side = (target - bar) / 2, left = side, loaded = [];
      PLATES.forEach(function (p) { while (left >= p.kg - 1e-9) { loaded.push(p); left = Math.round((left - p.kg) * 100) / 100; } });
      var actual = bar + 2 * loaded.reduce(function (a, p) { return a + p.kg; }, 0);

      while (svg.firstChild) svg.removeChild(svg.firstChild);
      var cy = 75;
      svg.appendChild(el('rect', { x: 20, y: cy - 4, width: 520, height: 8, rx: 4, fill: '#9AA1A9' }));      // bar
      svg.appendChild(el('rect', { x: 150, y: cy - 6, width: 260, height: 12, rx: 3, fill: '#7D848C' }));    // grip
      [[132, 1], [416, -1]].forEach(function (c) { svg.appendChild(el('rect', { x: c[0], y: cy - 14, width: 12, height: 28, rx: 3, fill: '#5B636E' })); }); // collars
      var drawSide = function (startX, dir) {
        var x = startX;
        loaded.forEach(function (p, i) {
          var px = dir === 1 ? x : x - p.w;
          var g = el('g', { class: 'plate' });
          g.appendChild(el('rect', { x: px, y: cy - p.h / 2, width: p.w, height: p.h, rx: 4, fill: p.color, stroke: 'rgba(0,0,0,.18)' }));
          if (p.h >= 84) {
            var t = el('text', { x: px + p.w / 2, y: cy + 4, 'text-anchor': 'middle', 'font-size': 10, 'font-weight': 800, fill: p.label,
              transform: 'rotate(-90 ' + (px + p.w / 2) + ' ' + cy + ')', 'font-family': 'Archivo, sans-serif' });
            t.textContent = p.kg;
            g.appendChild(t);
          }
          svg.appendChild(g);
          x += dir * (p.w + 2);
        });
      };
      drawSide(416 + 12 + 2, 1);
      drawSide(132 - 2, -1);

      summary.innerHTML = '';
      if (!loaded.length) {
        summary.textContent = 'Just the empty ' + bar + ' kg bar. Nice warm-up.';
      } else {
        var counts = {};
        loaded.forEach(function (p) { counts[p.kg] = (counts[p.kg] || 0) + 1; });
        summary.appendChild(document.createTextNode('Each side: '));
        PLATES.forEach(function (p) {
          if (!counts[p.kg]) return;
          var c = document.createElement('span');
          c.className = 'chip-mini';
          c.style.background = p.color; c.style.color = p.label; c.style.border = '1px solid rgba(0,0,0,.12)';
          c.textContent = counts[p.kg] + ' × ' + p.kg + ' kg';
          summary.appendChild(c);
        });
        if (Math.abs(actual - target) > 0.01) summary.appendChild(document.createTextNode(' (closest: ' + actual + ' kg)'));
      }
    };
    var setW = function (v) {
      v = Math.max(bar, Math.min(300, Math.round(v / 2.5) * 2.5));
      wIn.value = v; wRange.value = Math.min(200, v); draw();
    };
    wIn.addEventListener('change', function () { setW(parseFloat(wIn.value) || bar); });
    wIn.addEventListener('input', draw);
    wRange.addEventListener('input', function () { setW(parseFloat(wRange.value)); });
    pc.querySelectorAll('[data-step]').forEach(function (b) {
      b.addEventListener('click', function () { setW((parseFloat(wIn.value) || bar) + parseFloat(b.getAttribute('data-step'))); });
    });
    pc.querySelectorAll('[data-bar]').forEach(function (b) {
      b.addEventListener('click', function () {
        bar = parseFloat(b.getAttribute('data-bar'));
        pc.querySelectorAll('[data-bar]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        setW(parseFloat(wIn.value));
      });
    });
    draw();
  }
})();
