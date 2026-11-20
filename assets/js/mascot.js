/* ==================================================================
   Pixel mascot for the home banner.
   Loop: sit on books -> hop down -> run -> wave "Hi!" ->
         funny move (dance / backflip / dizzy spin) -> run back -> sit
   Drawn on a <canvas> with pixel blocks (no image files needed).
   ================================================================== */
(function () {
  'use strict';
  var cv = document.getElementById('mascot');
  if (!cv || !cv.getContext) return;
  var ctx = cv.getContext('2d');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var COL = {
    K: '#1e1b4b', W: '#ffffff', S: '#dbe4f3', P: '#f9a8d4', C: '#0f172a', Y: '#facc15',
    V: '#8b5cf6', G: '#cbd5e1', E: '#0f172a'
  };

  var W, H, P, ground, seatY, booksX, leftX, speed;

  function resize() {
    var r = cv.getBoundingClientRect();
    var dpr = Math.min(2, window.devicePixelRatio || 1);
    W = r.width; H = r.height;
    cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.imageSmoothingEnabled = false;
    P = W < 560 ? 3 : 4;                 // size of one "pixel"
    ground = H - 4;
    seatY = ground - 8 * P;              // top of the book stack
    booksX = W * (W < 560 ? 0.80 : 0.84);
    leftX = W * (W < 560 ? 0.20 : 0.16);
    speed = W < 560 ? 95 : 140;          // running speed (px per second)
  }

  // ---------------------------------------------------------------- drawing helpers
  // px(col, row, w, h, color) in sprite grid units; sprite is 16 x 20, origin = bottom-center
  var px = function (c, r, w, h, k) {
    ctx.fillStyle = COL[k] || k;
    ctx.fillRect((c - 8) * P, (r - 20) * P, w * P, h * P);
  };

  function drawSprite(s) {
    var f = s.frame;
    ctx.save();
    ctx.translate(Math.round(s.x), Math.round(s.y + (s.pose === 'sit' ? 2 * P : 0)));
    if (s.rot) {                       // rotate around the body's middle, not the feet
      ctx.translate(0, -10 * P); ctx.rotate(s.rot); ctx.translate(0, 10 * P);
    }
    ctx.scale(s.face < 0 ? -1 : 1, 1);

    // --- graduation cap + tassel
    px(5, 0, 6, 1, 'C'); px(2, 1, 12, 1, 'C'); px(1, 2, 14, 1, 'C'); px(4, 3, 8, 1, 'C');
    var swing = (s.pose === 'run' || s.pose === 'dance') ? (f % 2) : 0;
    px(14, 2, 1, 4, 'Y'); px(13 + swing, 6, 2, 1, 'Y');

    // --- head
    px(4, 4, 8, 1, 'K'); px(3, 5, 1, 6, 'K'); px(12, 5, 1, 6, 'K'); px(4, 11, 8, 1, 'K');
    px(4, 5, 8, 6, 'W'); px(4, 10, 8, 1, 'S');
    // eyes
    if (s.eyes === 'closed') { px(5, 8, 2, 1, 'E'); px(9, 8, 2, 1, 'E'); }
    else if (s.eyes === 'happy') { px(5, 8, 1, 1, 'E'); px(6, 7, 1, 1, 'E'); px(9, 7, 1, 1, 'E'); px(10, 8, 1, 1, 'E'); }
    else if (s.eyes === 'x') { px(5, 7, 1, 1, 'E'); px(7, 7, 1, 1, 'E'); px(6, 8, 1, 1, 'E'); px(8, 7, 1, 1, 'E'); px(10, 7, 1, 1, 'E'); px(9, 8, 1, 1, 'E'); }
    else { px(6, 7, 1, 2, 'E'); px(9, 7, 1, 2, 'E'); }
    // cheeks + mouth
    px(5, 9, 1, 1, 'P'); px(10, 9, 1, 1, 'P');
    if (s.mouth === 'open') { px(7, 9, 2, 2, 'K'); } else { px(7, 9, 2, 1, 'K'); }

    // --- body (purple shirt)
    px(4, 12, 1, 5, 'K'); px(11, 12, 1, 5, 'K'); px(5, 12, 6, 5, 'V'); px(6, 12, 4, 1, 'W');

    // --- arms
    var arms = s.arms;
    if (arms === 'swing') {
      if (f % 2) { px(2, 12, 1, 1, 'W'); px(2, 13, 2, 1, 'W'); px(12, 14, 1, 1, 'W'); px(13, 15, 1, 1, 'W'); }
      else { px(13, 12, 1, 1, 'W'); px(12, 13, 2, 1, 'W'); px(3, 14, 1, 1, 'W'); px(2, 15, 1, 1, 'W'); }
    } else if (arms === 'wave') {
      px(3, 13, 1, 3, 'W'); px(12, 12, 1, 1, 'W');
      if (f % 2) { px(13, 9, 1, 3, 'W'); px(13, 8, 1, 1, 'Y'); }
      else { px(13, 11, 1, 1, 'W'); px(14, 9, 1, 2, 'W'); px(14, 8, 1, 1, 'Y'); }
    } else if (arms === 'up') {
      px(2, 9, 1, 4, 'W'); px(13, 9, 1, 4, 'W');
    } else if (arms === 'dance') {
      if (f % 2) { px(2, 9, 1, 4, 'W'); px(12, 13, 1, 3, 'W'); }
      else { px(13, 9, 1, 4, 'W'); px(3, 13, 1, 3, 'W'); }
    } else {
      px(3, 13, 1, 3, 'W'); px(12, 13, 1, 3, 'W');
    }

    // --- legs
    var legs = s.legs;
    if (legs === 'run') {
      if (f % 2) { px(4, 17, 2, 1, 'W'); px(3, 18, 2, 1, 'W'); px(2, 19, 3, 1, 'G'); px(10, 17, 2, 2, 'W'); px(11, 19, 2, 1, 'G'); }
      else { px(10, 17, 2, 1, 'W'); px(11, 18, 2, 1, 'W'); px(11, 19, 3, 1, 'G'); px(4, 17, 2, 2, 'W'); px(3, 19, 2, 1, 'G'); }
    } else if (legs === 'sit') {
      px(5, 17, 2, 1, 'W'); px(9, 17, 2, 1, 'W'); px(4, 18, 3, 1, 'G'); px(9, 18, 3, 1, 'G');
    } else if (legs === 'tuck') {
      px(5, 17, 6, 1, 'W'); px(5, 18, 2, 1, 'G'); px(9, 18, 2, 1, 'G');
    } else {
      px(5, 17, 2, 2, 'W'); px(9, 17, 2, 2, 'W'); px(5, 19, 2, 1, 'G'); px(9, 19, 2, 1, 'G');
    }
    ctx.restore();
  }

  function drawBooks() {
    var x = Math.round(booksX), y = ground;
    var b = function (c, r, w, h, k) { ctx.fillStyle = k; ctx.fillRect(x + c * P, y + r * P, w * P, h * P); };
    b(-10, -4, 20, 4, '#2563eb'); b(-9, -3, 1, 2, '#facc15'); b(-10, -1, 20, 1, '#1e40af'); b(8, -4, 2, 4, '#e2e8f0');
    b(-8, -8, 17, 4, '#a855f7'); b(-7, -7, 1, 2, '#fde68a'); b(-8, -5, 17, 1, '#7e22ce'); b(7, -8, 2, 4, '#f1f5f9');
  }

  function drawBubble(x, y, text) {
    ctx.font = '700 ' + (P === 3 ? 12 : 14) + 'px Inter, system-ui, sans-serif';
    var tw = ctx.measureText(text).width, bw = tw + 18, bh = P === 3 ? 22 : 26;
    var bx = Math.round(x - bw / 2), by = Math.round(y - bh);
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(bx, by, bw, bh, 8); else ctx.rect(bx, by, bw, bh);
    ctx.fill();
    ctx.beginPath(); ctx.moveTo(x - 5, by + bh); ctx.lineTo(x + 5, by + bh); ctx.lineTo(x, by + bh + 6); ctx.fill();
    ctx.fillStyle = '#172554'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(text, x, by + bh / 2 + 1);
  }

  function drawStar(x, y, k) {
    ctx.fillStyle = k || '#facc15';
    var q = Math.max(2, P - 1);
    ctx.fillRect(x - q / 2, y - q * 1.5, q, q * 3);
    ctx.fillRect(x - q * 1.5, y - q / 2, q * 3, q);
  }

  // ---------------------------------------------------------------- behaviour
  var s = { x: 0, y: 0, face: 1, rot: 0, frame: 0, pose: 'sit', arms: 'down', legs: 'sit', eyes: 'open', mouth: 'smile' };
  var queue = [], act = null, t = 0, clock = 0, funny = 0, extras = [];
  var FUNNY = ['dance', 'flip', 'dizzy'];

  function plan() {
    queue = [
      { a: 'sit', d: 3 },
      { a: 'hopDown', d: 0.6 },
      { a: 'run', to: leftX },
      { a: 'wave', d: 2.4 },
      { a: FUNNY[funny++ % FUNNY.length] },
      { a: 'run', to: booksX - 14 * P },
      { a: 'hopUp', d: 0.6 }
    ];
  }

  function next() {
    if (!queue.length) plan();
    act = queue.shift(); t = 0;
    if (act.a === 'dance') act.d = 2.8;
    if (act.a === 'flip') act.d = 1.6;
    if (act.a === 'dizzy') act.d = 2.6;
  }

  function set(pose, arms, legs, eyes, mouth) {
    s.pose = pose; s.arms = arms; s.legs = legs; s.eyes = eyes || 'open'; s.mouth = mouth || 'smile';
  }

  function arc(k, h) { return Math.sin(Math.PI * k) * h; }

  function update(dt) {
    t += dt; clock += dt;
    s.rot = 0; s.frame = 0;
    var k = act.d ? Math.min(1, t / act.d) : 0;
    var blink = (clock % 2.6) < 0.12;

    switch (act.a) {
      case 'sit':
        s.x = booksX; s.y = seatY; s.face = 1;
        set('sit', 'down', 'sit', blink ? 'closed' : 'open');
        break;
      case 'hopDown':
      case 'hopUp': {
        var down = act.a === 'hopDown';
        var x0 = down ? booksX : booksX - 14 * P, x1 = down ? booksX - 14 * P : booksX;
        var y0 = down ? seatY : ground, y1 = down ? ground : seatY;
        s.x = x0 + (x1 - x0) * k; s.y = y0 + (y1 - y0) * k - arc(k, 10 * P); s.face = down ? -1 : 1;
        set('jump', 'up', 'tuck', 'happy', 'open');
        break;
      }
      case 'run': {
        var dir = act.to < s.x ? -1 : 1;
        s.face = dir; s.x += dir * speed * dt;
        s.frame = Math.floor(clock * 10);
        s.y = ground - Math.abs(Math.sin(clock * 20)) * P;
        set('run', 'swing', 'run', 'open');
        if (Math.random() < dt * 8) extras.push({ type: 'dust', x: s.x - dir * 6 * P, y: ground - P, life: 0.5, t: 0 });
        if ((dir < 0 && s.x <= act.to) || (dir > 0 && s.x >= act.to)) { s.x = act.to; return next(); }
        return;
      }
      case 'wave':
        s.face = 1; s.y = ground; s.frame = Math.floor(t * 5);
        set('stand', 'wave', 'stand', 'happy', 'open');
        break;
      case 'dance':
        s.face = Math.floor(t * 2) % 2 ? -1 : 1; s.frame = Math.floor(t * 5);
        s.y = ground - Math.abs(Math.sin(t * 10)) * 3 * P;
        s.rot = Math.sin(t * 10) * 0.12;
        set('dance', 'dance', 'stand', 'happy', 'open');
        if (Math.random() < dt * 3) extras.push({ type: 'note', x: s.x + (Math.random() * 16 - 8) * P, y: s.y - 22 * P, life: 1.2, t: 0 });
        break;
      case 'flip': {
        var jk = (t % 0.8) / 0.8;                        // two flips
        s.y = ground - arc(jk, 16 * P) - (jk > 0.05 && jk < 0.95 ? 3 * P : 0);
        s.rot = jk * Math.PI * 2;
        set('jump', 'up', 'tuck', 'closed', 'open');
        break;
      }
      case 'dizzy':
        if (t < 0.9) {                                     // spin fast
          s.face = Math.sin(t * 30) > 0 ? 1 : -1;
          set('stand', 'up', 'stand', 'open', 'open');
        } else {                                           // dizzy wobble with stars
          s.face = 1; s.rot = Math.sin(t * 6) * 0.15;
          set('stand', 'down', 'stand', 'x', 'open');
        }
        s.y = ground;
        break;
    }
    if (act.d && t >= act.d) next();
  }

  function render() {
    ctx.clearRect(0, 0, W, H);
    drawBooks();

    // dust & music notes
    for (var i = extras.length - 1; i >= 0; i--) {
      var e = extras[i];
      e.t += 1 / 60;
      var a = 1 - e.t / e.life;
      if (a <= 0) { extras.splice(i, 1); continue; }
      ctx.globalAlpha = a;
      if (e.type === 'dust') {
        ctx.fillStyle = 'rgba(255,255,255,.55)';
        var r = (1 + e.t * 6) * P / 2;
        ctx.fillRect(e.x - r, e.y - r, r * 2, r * 2);
      } else {
        ctx.fillStyle = '#fde68a';
        ctx.font = '700 ' + (P * 5) + 'px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(e.t * 7 % 2 > 1 ? '♪' : '♫', e.x, e.y - e.t * 30);
      }
      ctx.globalAlpha = 1;
    }

    drawSprite(s);

    if (act && act.a === 'wave') drawBubble(s.x, s.y - 21 * P - 4, 'Hi! 👋');
    if (act && act.a === 'dizzy' && t > 0.9) {
      for (var j = 0; j < 3; j++) {
        var ang = t * 5 + j * (Math.PI * 2 / 3);
        drawStar(s.x + Math.cos(ang) * 8 * P, s.y - 21 * P + Math.sin(ang) * 2 * P);
      }
    }
  }

  // ---------------------------------------------------------------- loop
  var last = 0;
  function loop(now) {
    var dt = Math.min(0.05, (now - last) / 1000 || 0);
    last = now;
    update(dt);
    render();
    requestAnimationFrame(loop);
  }

  resize();
  window.addEventListener('resize', function () {
    var oldW = W;
    resize();
    if (reduceMotion) { update(0); render(); return; }
    // Phones fire "resize" when the address bar hides while scrolling - only
    // restart the routine when the width really changed (e.g. rotating the phone).
    if (Math.abs(W - oldW) > 2) { queue = []; next(); }
  });

  next();
  if (reduceMotion) {                                // just sit and stay still
    update(0); render();
  } else {
    requestAnimationFrame(function (n) { last = n; requestAnimationFrame(loop); });
  }
})();
