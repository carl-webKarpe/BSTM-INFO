/* =====================================================================
   STUDENT MASCOT – vector (SVG) character rig animated with GSAP
   ---------------------------------------------------------------------
   Same character as before (white round face, pink cheeks, blue
   graduation cap with a golden tassel, purple-and-blue hoodie jacket,
   black shorts, white sneakers, stack of books) redrawn as a smooth
   2D cartoon with separate parts so every limb can move.

   Usage: any element with data-mascot="..." is turned into a mascot.
     hero        home banner routine (sit, study, run, wave, funny moves)
     wave        idle + waves now and then          think   thinking pose
     celebrate   excited jumps + confetti           sad     sad face
     confused    confused face + "?"                login   reacts to the login form
     loader      running loader (used by the loading screens)
     sheet       character-sheet demos (data-view / data-expr / data-anim)
   Options: data-size (px width), data-view, data-expr, data-say.
   ===================================================================== */
(function () {
  'use strict';
  var G = window.gsap;
  if (!G) return;

  var RM = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var OUT = '#1b1f4b';                     // outline colour
  var uid = 0;

  function rand(a, b) { return a + Math.random() * (b - a); }
  function pick(list) { return list[Math.floor(Math.random() * list.length)]; }
  function wait(s) { return new Promise(function (r) { G.delayedCall(s, r); }); }

  // ------------------------------------------------------------------ artwork
  var S = ' stroke="' + OUT + '" stroke-linejoin="round" stroke-linecap="round"';

  function legArt() {
    return '<path d="M73 198 L100 198 L98 215 Q97 219 93 219 L79 219 Q75 219 75 215Z" fill="#1f2937"' + S + ' stroke-width="3"/>' +
      '<rect x="81" y="216" width="13" height="18" rx="6" fill="#fff"' + S + ' stroke-width="2.6"/>' +
      '<path d="M72 239 Q72 229 83 229 L93 229 Q105 229 105 240 L105 244 Q105 249 100 249 L77 249 Q72 249 72 244Z" fill="#fff"' + S + ' stroke-width="3"/>' +
      '<path d="M73.5 244 L103.5 244" stroke="#93c5fd" stroke-width="4"/>' +
      '<path d="M79 236 Q88 241 99 233" fill="none" stroke="#8b5cf6" stroke-width="2.8" stroke-linecap="round"/>' +
      '<path d="M84 231 L90 231" stroke="#c7d2fe" stroke-width="2" stroke-linecap="round"/>';
  }

  function armArt(id) {
    return '<path d="M83 150 Q66 152 62 176 L60 186 Q60 192 66 192 Q71 192 72 187 L78 166Z" fill="url(#' + id + '-jk)"' + S + ' stroke-width="3"/>' +
      '<path d="M65 162 Q63 170 62 178" fill="none" stroke="#a78bfa" stroke-width="2" opacity=".7"/>' +
      '<path d="M59.5 183 L73 185 L72 190 Q66 194 60 190Z" fill="#4338ca"' + S + ' stroke-width="2.2"/>' +
      '<circle cx="66" cy="197" r="8.2" fill="#fff"' + S + ' stroke-width="2.8"/>';
  }

  function svgArt(id) {
    var mirror = '<g transform="matrix(-1 0 0 1 200 0)">';
    return '' +
'<svg class="msc-svg" viewBox="0 0 200 262" aria-hidden="true" focusable="false">' +
'<defs>' +
  '<linearGradient id="' + id + '-cap" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b82f6"/><stop offset="1" stop-color="#1d4ed8"/></linearGradient>' +
  '<linearGradient id="' + id + '-jk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8b5cf6"/><stop offset="1" stop-color="#6d28d9"/></linearGradient>' +
  '<radialGradient id="' + id + '-head" cx=".42" cy=".36" r=".78"><stop offset=".55" stop-color="#ffffff"/><stop offset="1" stop-color="#dde4ff"/></radialGradient>' +
'</defs>' +
'<ellipse class="m-shadow" cx="100" cy="254" rx="42" ry="6.5" fill="#0f172a" opacity=".22"/>' +
'<g class="m-spin"><g class="m-root">' +
  // legs
  '<g class="m-legL">' + legArt() + '</g>' +
  '<g class="m-legR">' + mirror + legArt() + '</g></g>' +
  // body
  '<g class="m-body">' +
    '<path class="m-hood" d="M68 156 Q100 177 132 156 Q128 140 100 140 Q72 140 68 156Z" fill="#5b21b6"' + S + ' stroke-width="3"/>' +
    '<path d="M73 195 L127 195 L128 207 L72 207Z" fill="#1f2937"' + S + ' stroke-width="3"/>' +
    '<path d="M72 160 Q73 147 90 146 L110 146 Q127 147 128 160 L132 190 Q133 199 124 199 L76 199 Q67 199 68 190Z" fill="url(#' + id + '-jk)"' + S + ' stroke-width="3"/>' +
    '<g class="m-front">' +
      '<path d="M90.5 147.5 L109.5 147.5 L106 197.5 L94 197.5Z" fill="#e0e7ff"/>' +
      '<path d="M92 149 L100 165 L108 149" fill="none" stroke="#a78bfa" stroke-width="2.5" stroke-linejoin="round"/>' +
      '<path d="M91 148 L95 197 M109 148 L105 197" stroke="#3730a3" stroke-width="4" stroke-linecap="round"/>' +
      '<rect x="114" y="160" width="9" height="10" rx="2" fill="#facc15"' + S + ' stroke-width="1.6"/>' +
      '<path d="M116.5 163 L120.5 163" stroke="#b45309" stroke-width="1.4"/>' +
    '</g>' +
    '<path d="M69.5 189.5 L130.5 189.5" stroke="#4338ca" stroke-width="5" stroke-linecap="round"/>' +
    '<g class="m-back" opacity="0">' +
      '<path d="M76 151 Q100 188 124 151 Q112 160 100 160 Q88 160 76 151Z" fill="#6d28d9"' + S + ' stroke-width="2.5"/>' +
      '<rect x="93.5" y="170" width="13" height="11" rx="2.5" fill="#f0abfc"' + S + ' stroke-width="1.6"/>' +
    '</g>' +
  '</g>' +
  // open book (studying)
  '<g class="m-book" opacity="0">' +
    '<path d="M58 163 L100 170 L142 163 L142 194 L100 201 L58 194Z" fill="#2563eb"' + S + ' stroke-width="3"/>' +
    '<path d="M63 165 L98 170.5 L98 196 L63 190Z" fill="#fff"' + S + ' stroke-width="1.8"/>' +
    '<path d="M102 170.5 L137 165 L137 190 L102 196Z" fill="#fff"' + S + ' stroke-width="1.8"/>' +
    '<path d="M68 173 L92 177 M68 179 L92 183 M68 185 L86 188 M108 177 L132 173 M108 183 L132 179 M108 188 L126 185" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round"/>' +
    '<path d="M121 166 L121 160 L125 163 L129 159 L129 165" fill="#facc15"' + S + ' stroke-width="1.4"/>' +
    '<path class="m-page" d="M102 170.5 L137 165 L137 190 L102 196Z" fill="#f8fafc"' + S + ' stroke-width="1.8" opacity="0"/>' +
  '</g>' +
  // head
  '<g class="m-head"><g class="m-headI">' +
    '<ellipse cx="100" cy="98" rx="58" ry="52" fill="url(#' + id + '-head)"' + S + ' stroke-width="3.5"/>' +
    '<path d="M57 112 Q66 140 100 146" fill="none" stroke="#c7d2fe" stroke-width="3" opacity=".7" stroke-linecap="round"/>' +
    '<g class="m-face">' +
      '<ellipse class="m-cheekL" cx="68" cy="119" rx="9" ry="5.5" fill="#f9a8d4" opacity=".85"/>' +
      '<ellipse class="m-cheekR" cx="132" cy="119" rx="9" ry="5.5" fill="#f9a8d4" opacity=".85"/>' +
      '<g class="m-eyes">' +
        '<g class="m-eyeL"><ellipse cx="80" cy="102" rx="7.5" ry="10" fill="' + OUT + '"/><circle cx="82.6" cy="97.4" r="2.8" fill="#fff"/><circle cx="77.8" cy="106.2" r="1.3" fill="#fff"/></g>' +
        '<g class="m-eyeR"><ellipse cx="120" cy="102" rx="7.5" ry="10" fill="' + OUT + '"/><circle cx="122.6" cy="97.4" r="2.8" fill="#fff"/><circle cx="117.8" cy="106.2" r="1.3" fill="#fff"/></g>' +
      '</g>' +
      '<path class="m-eHappy" d="M71 105 Q80 93 89 105 M111 105 Q120 93 129 105" fill="none"' + S + ' stroke-width="4"/>' +
      '<path class="m-eExcited" d="M73 95 L87 102 L73 109 M127 95 L113 102 L127 109" fill="none"' + S + ' stroke-width="4"/>' +
      '<path class="m-eWink" d="M111 104 Q120 95 129 104" fill="none"' + S + ' stroke-width="4"/>' +
      '<path class="m-eClosed" d="M71 102 Q80 110 89 102 M111 102 Q120 110 129 102" fill="none"' + S + ' stroke-width="4"/>' +
      '<path class="m-bUp" d="M71 82 Q80 75 89 82 M111 82 Q120 75 129 82" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<path class="m-bConf" d="M71 87 L89 84 M111 80 Q120 73 129 80" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<path class="m-bSad" d="M72 88 L88 83 M128 88 L112 83" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<path class="m-bThink" d="M71 85 Q80 82 89 86 M111 81 Q120 77 129 82" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<path class="m-mSmile" d="M90 121 Q100 131 110 121" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<g class="m-mOpen"><path d="M87 119 L113 119 Q112 137 100 137 Q88 137 87 119Z" fill="#7f1d1d"' + S + ' stroke-width="3"/><path d="M92 131 Q100 125 108 131 Q105 136 100 136 Q95 136 92 131Z" fill="#fb7185"/></g>' +
      '<ellipse class="m-mO" cx="100" cy="127" rx="5.5" ry="7" fill="#7f1d1d"' + S + ' stroke-width="3"/>' +
      '<path class="m-mWavy" d="M88 127 Q93 121 98 127 T108 127" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<path class="m-mFlat" d="M93 126 Q101 123 108 127" fill="none"' + S + ' stroke-width="3.5"/>' +
      '<path class="m-mSad" d="M90 129 Q100 120 110 129" fill="none"' + S + ' stroke-width="3.5"/>' +
    '</g>' +
    // graduation cap + tassel
    '<g class="m-cap">' +
      '<path d="M53 72 C56 47 144 47 147 72 L147 82 C122 70 78 70 53 82Z" fill="#1e40af"' + S + ' stroke-width="3"/>' +
      '<path d="M100 23 L173 50 L100 77 L27 50Z" fill="url(#' + id + '-cap)"' + S + ' stroke-width="3.2"/>' +
      '<path d="M100 31 L156 50 L100 69 L44 50Z" fill="#fff" opacity=".12"/>' +
      '<path d="M40 54 L100 76 L160 54" fill="none" stroke="#93c5fd" stroke-width="1.6" opacity=".5"/>' +
      '<g class="m-tasselO"><g class="m-tassel">' +
        '<path d="M100 50 Q130 45 153 57 L153 84" fill="none" stroke="#f59e0b" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>' +
        '<circle cx="153" cy="86" r="4.6" fill="#fbbf24"' + S + ' stroke-width="2"/>' +
        '<path d="M147 89 L159 89 L162 113 Q153 118 144 113Z" fill="#fbbf24"' + S + ' stroke-width="2.4"/>' +
        '<path d="M150 93 L149 111 M153 93 L153 113 M156 93 L157 111" stroke="#d97706" stroke-width="1.6" stroke-linecap="round"/>' +
      '</g></g>' +
      '<circle cx="100" cy="50" r="5" fill="#fbbf24"' + S + ' stroke-width="2"/>' +
    '</g>' +
  '</g></g>' +
  // arms (drawn in front of the head so hands can cover the eyes / touch the chin)
  '<g class="m-armL"><g class="m-armLi">' + armArt(id) + '</g></g>' +
  '<g class="m-armR"><g class="m-armRi">' + mirror + armArt(id) + '</g></g></g>' +
  // idea lightbulb
  '<g class="m-bulb" opacity="0">' +
    '<path d="M168 4 L168 -2 M150 12 L145 8 M186 12 L191 8 M146 28 L140 28 M190 28 L196 28" stroke="#fde047" stroke-width="3" stroke-linecap="round"/>' +
    '<circle cx="168" cy="26" r="12" fill="#fde047"' + S + ' stroke-width="2.6"/>' +
    '<path d="M162 22 Q164 17 169 17" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>' +
    '<rect x="161.5" y="36" width="13" height="8" rx="2" fill="#94a3b8"' + S + ' stroke-width="2.2"/>' +
  '</g>' +
'</g></g>' +
'</svg>';
  }

  function booksArt() {
    return '<svg viewBox="0 0 160 72" aria-hidden="true">' +
      '<rect x="4" y="38" width="152" height="30" rx="5" fill="#7c3aed"' + S + ' stroke-width="3"/>' +
      '<rect x="10" y="43" width="132" height="20" rx="2" fill="#fef3c7"' + S + ' stroke-width="2"/>' +
      '<path d="M16 49 H136 M16 55 H136" stroke="#e7d7a8" stroke-width="1.6"/>' +
      '<rect x="142" y="38" width="10" height="30" fill="#ea580c" stroke="' + OUT + '" stroke-width="2"/>' +
      '<rect x="18" y="8" width="128" height="30" rx="5" fill="#2563eb"' + S + ' stroke-width="3"/>' +
      '<rect x="24" y="13" width="112" height="20" rx="2" fill="#fefce8"' + S + ' stroke-width="2"/>' +
      '<path d="M30 19 H130 M30 25 H130" stroke="#e7e2b8" stroke-width="1.6"/>' +
      '<path d="M108 6 L108 22 L113 18 L118 22 L118 6Z" fill="#facc15"' + S + ' stroke-width="2"/>' +
      '</svg>';
  }

  // ------------------------------------------------------------------ expressions / views
  var TOGGLES = ['eyes', 'eHappy', 'eExcited', 'eWink', 'eClosed', 'bUp', 'bConf', 'bSad', 'bThink', 'mSmile', 'mOpen', 'mO', 'mWavy', 'mFlat', 'mSad'];
  var EXPR = {
    smile:     { on: ['eyes', 'mSmile'] },
    happy:     { on: ['eHappy', 'mOpen'] },
    excited:   { on: ['eExcited', 'mOpen'] },
    surprised: { on: ['eyes', 'bUp', 'mO'], eye: 1.15 },
    confused:  { on: ['eyes', 'bConf', 'mWavy'], look: [3, -2] },
    thinking:  { on: ['eyes', 'bThink', 'mFlat'], look: [-4, -5] },
    wink:      { on: ['eyes', 'eWink', 'mSmile'], hideR: true },
    sad:       { on: ['eyes', 'bSad', 'mSad'], look: [0, 3] },
    sleepy:    { on: ['eClosed', 'mFlat'] },
    reading:   { on: ['eyes', 'mSmile'], look: [0, 5] }
  };

  // ------------------------------------------------------------------ Mascot class
  function Mascot(el, opts) {
    opts = opts || {};
    var id = 'msc' + (++uid);
    this.el = el;
    el.classList.add('msc');
    if (opts.size) el.style.setProperty('--msc-w', opts.size + 'px');
    el.innerHTML = '<div class="msc-bounce"><div class="msc-bubble"></div>' + svgArt(id) + '</div>';
    this.bounce = el.querySelector('.msc-bounce');
    this.bubble = el.querySelector('.msc-bubble');
    this.svg = el.querySelector('svg');
    var left = opts.bubble === 'left' || el.dataset.bubble === 'left';
    if (left) this.bubble.classList.add('msc-bubble--left');
    G.set(this.bubble, { xPercent: left ? -86 : -50, opacity: 0 });
    var q = this.svg.querySelector.bind(this.svg);
    var p = this.p = {
      spin: q('.m-spin'), root: q('.m-root'), body: q('.m-body'), legL: q('.m-legL'), legR: q('.m-legR'),
      armL: q('.m-armL'), armLi: q('.m-armLi'), armR: q('.m-armR'), armRi: q('.m-armRi'),
      head: q('.m-head'), headI: q('.m-headI'), face: q('.m-face'), eyes: q('.m-eyes'),
      eyeL: q('.m-eyeL'), eyeR: q('.m-eyeR'), cheekL: q('.m-cheekL'), cheekR: q('.m-cheekR'),
      cap: q('.m-cap'), tasselO: q('.m-tasselO'), tassel: q('.m-tassel'), book: q('.m-book'), page: q('.m-page'),
      bulb: q('.m-bulb'), shadow: q('.m-shadow'), front: q('.m-front'), back: q('.m-back'), hood: q('.m-hood')
    };
    this.t = {};
    TOGGLES.forEach(function (k) { this.t[k] = q('.m-' + k); }, this);

    // pivots (in SVG coordinates)
    G.set(p.spin, { svgOrigin: '100 160' });
    G.set(p.root, { svgOrigin: '100 250' });
    G.set(p.body, { svgOrigin: '100 199' });
    G.set(p.legL, { svgOrigin: '87 198' });
    G.set(p.legR, { svgOrigin: '113 198' });
    G.set([p.armL, p.armLi], { svgOrigin: '78 156' });
    G.set([p.armR, p.armRi], { svgOrigin: '122 156' });
    G.set([p.head, p.headI], { svgOrigin: '100 148' });
    G.set(p.face, { svgOrigin: '100 110' });
    G.set(p.cap, { svgOrigin: '100 50' });
    G.set([p.tasselO, p.tassel], { svgOrigin: '153 57' });
    G.set(p.eyeL, { svgOrigin: '80 102' });
    G.set(p.eyeR, { svgOrigin: '120 102' });
    G.set(p.page, { svgOrigin: '102 182' });
    G.set(p.book, { svgOrigin: '100 182' });
    G.set(p.bulb, { svgOrigin: '168 30' });
    G.set(p.shadow, { svgOrigin: '100 254' });

    this.cur = 'smile';
    this.curView = 'front';
    this.expression(opts.expr || 'smile');
    this.view(opts.view || 'front', 0);
    if (opts.sit) this.sit(true, 0);
    if (!RM && opts.idle !== false) this.startIdle();
    if (opts.interactive) this.interactive();
  }
  var M = Mascot.prototype;

  M.expression = function (name) {
    var e = EXPR[name] || EXPR.smile, t = this.t;
    this.cur = name;
    TOGGLES.forEach(function (k) { G.set(t[k], { opacity: e.on.indexOf(k) > -1 ? 1 : 0 }); });
    G.set(this.p.eyeR, { opacity: e.hideR ? 0 : 1 });
    G.to([this.p.eyeL, this.p.eyeR], { scale: e.eye || 1, duration: .15, overwrite: 'auto' });
    var look = e.look || [0, 0];
    G.to(this.p.eyes, { x: look[0], y: look[1], duration: .2, overwrite: 'auto' });
    return this;
  };

  // front | q34 | right | left | back
  M.view = function (v, d) {
    var p = this.p, dur = d == null ? .25 : d, side = v === 'right' || v === 'left';
    this.curView = v;
    G.set(this.svg, { scaleX: v === 'left' ? -1 : 1 });
    var fx = v === 'q34' ? 12 : side ? 34 : 0, fs = v === 'q34' ? .9 : side ? .72 : 1;
    G.to(p.face, { x: fx, scaleX: fs, opacity: v === 'back' ? 0 : 1, duration: dur, overwrite: 'auto' });
    G.to([p.eyeL, p.cheekL], { opacity: side ? 0 : v === 'q34' ? .8 : 1, duration: dur });
    G.to(p.body, { scaleX: side ? .74 : v === 'q34' ? .94 : 1, duration: dur });
    G.to(p.front, { opacity: v === 'back' ? 0 : 1, duration: dur });
    G.to(p.back, { opacity: v === 'back' ? 1 : 0, duration: dur });
    G.to(p.cap, { x: side ? 5 : v === 'q34' ? 3 : 0, scaleX: v === 'back' ? -1 : side ? -.9 : 1, duration: dur });   // side views: tassel hangs at the back of the head
    G.to(p.armL, { x: side ? 10 : v === 'q34' ? 5 : 0, opacity: side ? 0 : 1, duration: dur });
    G.to(p.armR, { x: side ? -24 : v === 'q34' ? -2 : 0, duration: dur });
    G.to(p.legL, { x: side ? 9 : v === 'q34' ? 4 : 0, duration: dur });
    G.to(p.legR, { x: side ? -9 : v === 'q34' ? -2 : 0, duration: dur });
    return this;
  };

  M.startIdle = function () {
    var p = this.p, self = this;
    this.idleTl = G.timeline({ repeat: -1, yoyo: true, defaults: { duration: 1.5, ease: 'sine.inOut' } })
      .to(p.body, { scaleY: 1.03, scaleX: .99 }, 0)
      .to(p.headI, { y: -2.5 }, 0)
      .to(p.armLi, { rotation: 4 }, 0)
      .to(p.armRi, { rotation: -4 }, 0)
      .to(p.tassel, { rotation: 9, duration: 1.2 }, 0);
    (function blink() {
      self.blinkCall = G.delayedCall(rand(2, 5), function () {
        if (!self.paused && EXPR[self.cur] && EXPR[self.cur].on.indexOf('eyes') > -1) {
          G.to([p.eyeL, p.eyeR], { scaleY: .1, duration: .07, yoyo: true, repeat: 1, ease: 'power1.in' });
        }
        blink();
      });
    })();
    (function look() {
      self.lookCall = G.delayedCall(rand(3, 6), function () {
        if (!self.paused && !self.busy && !self.hovering) {
          G.to(p.head, { rotation: rand(-6, 6), duration: .7, ease: 'sine.inOut', yoyo: true, repeat: 1, repeatDelay: rand(.4, 1.2) });
        }
        look();
      });
    })();
    // save CPU while off-screen
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        self.paused = !es[0].isIntersecting;
        if (self.idleTl) self.idleTl[self.paused ? 'pause' : 'resume']();
      }).observe(this.el);
    }
  };

  M.say = function (text, dur) {
    var b = this.bubble;
    b.textContent = text;
    G.killTweensOf(b);
    G.fromTo(b, { opacity: 0, y: 8, scale: .7 }, { opacity: 1, y: 0, scale: 1, duration: .28, ease: 'back.out(2.5)' });
    G.to(b, { opacity: 0, y: -4, duration: .25, delay: dur || 1.6 });
    return this;
  };

  M.burst = function (kind, n) {
    var el = this.el, sets = {
      stars: ['⭐', '✨', '🌟'], notes: ['♪', '♫'], confetti: ['■', '●', '▲'], hearts: ['💜', '💙']
    }, cols = ['#facc15', '#60a5fa', '#a78bfa', '#f472b6', '#34d399'];
    for (var i = 0; i < (n || 8); i++) {
      var s = document.createElement('span');
      s.className = 'msc-p';
      s.textContent = pick(sets[kind] || sets.stars);
      if (kind === 'confetti' || kind === 'notes') s.style.color = pick(cols);
      el.appendChild(s);
      var ang = rand(-Math.PI, 0), dist = rand(40, 90);
      G.fromTo(s, { x: 0, y: 0, scale: .4, opacity: 1, rotation: 0 },
        { x: Math.cos(ang) * dist, y: Math.sin(ang) * dist - 10, scale: rand(.8, 1.3), rotation: rand(-160, 160), opacity: 0,
          duration: rand(.8, 1.3), ease: 'power2.out', onComplete: s.remove.bind(s) });
    }
  };

  M.reset = function (d) {
    var p = this.p, dur = d == null ? .3 : d;
    G.to([p.legL, p.legR, p.armL, p.armR, p.tasselO, p.spin], { rotation: 0, duration: dur, overwrite: 'auto' });
    G.to(p.root, { rotation: 0, y: 0, scaleX: 1, scaleY: 1, duration: dur, overwrite: 'auto' });
    G.to(this.bounce, { y: 0, duration: dur });
    return this;
  };

  M.sit = function (on, d) {
    var p = this.p, dur = d == null ? .25 : d;
    this.sitting = on;
    G.to([p.legL, p.legR], { scaleY: on ? .58 : 1, duration: dur });
    G.to(p.shadow, { opacity: on ? 0 : .22, duration: dur });
    return this;
  };

  // walking / running cycle – returns a repeating timeline (kill it to stop)
  M.cycle = function (type) {
    var p = this.p, run = type === 'run', a = run ? 38 : 24, d = run ? .17 : .28, b = run ? 6 : 3;
    var tl = G.timeline({ repeat: -1, defaults: { ease: 'sine.inOut', duration: d } });
    [0, 1].forEach(function (i) {
      var s = i ? -1 : 1;
      tl.to(p.legL, { rotation: a * s }, i * d)
        .to(p.legR, { rotation: -a * s }, i * d)
        .to(p.armL, { rotation: -a * .9 * s }, i * d)
        .to(p.armR, { rotation: a * .9 * s }, i * d)
        .to(p.root, { y: -b, duration: d / 2, ease: 'sine.out', yoyo: true, repeat: 1 }, i * d);
    });
    G.to(p.root, { rotation: run ? 7 : 2, duration: .2 });
    G.to(p.tasselO, { rotation: run ? -30 : -12, duration: .3 });
    return tl;
  };

  M.runTo = function (target, x, opt) {
    opt = opt || {};
    var self = this, cur = G.getProperty(target, 'x'), dir = x < cur ? -1 : 1;
    var speed = opt.speed || 150, cyc;
    this.view(dir < 0 ? 'left' : 'right', .15);
    this.expression(opt.expr || 'smile');
    cyc = this.cycle(opt.walk ? 'walk' : 'run');
    return G.to(target, {
      x: x, duration: Math.abs(x - cur) / speed, ease: 'none',
      onComplete: function () { cyc.kill(); self.reset(.2); self.view('front', .25); }
    });
  };

  M.jump = function (opt) {
    opt = opt || {};
    var p = this.p, h = opt.h || 40, tl = G.timeline();
    if (opt.expr !== false) this.expression(opt.expr || 'excited');
    tl.to(p.root, { scaleY: .86, scaleX: 1.1, duration: .12, ease: 'power1.out' })
      .to(p.armL, { rotation: 110, duration: .18 }, '<')
      .to(p.armR, { rotation: -110, duration: .18 }, '<')
      .to(this.bounce, { y: -h, duration: .32, ease: 'power2.out' })
      .to(p.root, { scaleY: 1.07, scaleX: .95, duration: .16 }, '<')
      .to(p.legL, { rotation: -14, duration: .16 }, '<').to(p.legR, { rotation: 14, duration: .16 }, '<')
      .to(p.tasselO, { rotation: 25, duration: .3 }, '<');
    if (opt.flip) tl.to(p.spin, { rotation: 360, duration: .55, ease: 'power1.inOut' }, '<');
    tl.to(this.bounce, { y: 0, duration: .28, ease: 'power2.in' })
      .to(p.root, { scaleY: .88, scaleX: 1.08, duration: .1 })
      .to([p.legL, p.legR], { rotation: 0, duration: .1 }, '<')
      .set(p.spin, { rotation: 0 })
      .to(p.root, { scaleY: 1, scaleX: 1, duration: .3, ease: 'back.out(3)' })
      .to([p.armL, p.armR, p.tasselO], { rotation: 0, duration: .3 }, '<');
    return tl;
  };

  M.wave = function (n, text) {
    var p = this.p, self = this, tl = G.timeline();
    this.act = tl;
    this.expression('happy');
    if (text) this.say(text, (n || 3) * .4 + .8);
    tl.to(p.armR, { rotation: -150, duration: .3, ease: 'back.out(2)' })
      .to(p.head, { rotation: -6, duration: .3 }, '<');
    for (var i = 0; i < (n || 3); i++) {
      tl.to(p.armR, { rotation: -122, duration: .18, ease: 'sine.inOut' }).to(p.armR, { rotation: -156, duration: .18, ease: 'sine.inOut' });
    }
    tl.to(p.armR, { rotation: 0, duration: .35, ease: 'power2.inOut' })
      .to(p.head, { rotation: 0, duration: .35 }, '<')
      .add(function () { self.expression('smile'); });
    return tl;
  };

  M.think = function (dur, text) {
    var p = this.p, self = this, tl = G.timeline();
    this.act = tl;
    this.expression('thinking');
    if (text !== false) this.say(text || 'Hmm…', (dur || 2) - .3);
    tl.to(p.armR, { rotation: 150, duration: .4, ease: 'power2.out' })
      .to(p.head, { rotation: 7, duration: .4 }, '<')
      .to(p.headI, { rotation: 3, duration: .5, yoyo: true, repeat: 3, ease: 'sine.inOut' })
      .to({}, { duration: Math.max(0, (dur || 2) - 2.4) })
      .to([p.armR, p.head, p.headI], { rotation: 0, duration: .35 })
      .add(function () { self.expression('smile'); });
    return tl;
  };

  M.idea = function () {
    var p = this.p, self = this, tl = G.timeline();
    tl.add(function () { self.expression('surprised'); })
      .fromTo(p.bulb, { opacity: 0, scale: 0 }, { opacity: 1, scale: 1, duration: .4, ease: 'back.out(3)' })
      .add(function () { self.expression('happy'); }, '+=.25')
      .to(this.bounce, { y: -8, duration: .15, yoyo: true, repeat: 3, ease: 'sine.out' })
      .to(p.bulb, { opacity: 0, scale: .6, duration: .3 }, '+=.6');
    return tl;
  };

  M.study = function (dur) {
    var p = this.p, self = this, tl = G.timeline();
    this.act = tl;
    tl.to(p.book, { opacity: 1, scale: 1, duration: .35, ease: 'back.out(2)', startAt: { scale: .5 } })
      .to(p.armL, { rotation: 22, duration: .35 }, '<')
      .to(p.armR, { rotation: -22, duration: .35 }, '<')
      .add(function () { self.expression('reading'); })
      .to(p.head, { rotation: 0, y: 2, duration: .3 }, '<');
    for (var i = 0; i < 2; i++) {
      tl.set(p.page, { opacity: 1, scaleX: 1 }, '+=.9')
        .to(p.page, { scaleX: -1, duration: .55, ease: 'power1.inOut' })
        .set(p.page, { opacity: 0, scaleX: 1 })
        .to(p.headI, { rotation: i ? -3 : 3, duration: .4, yoyo: true, repeat: 1, ease: 'sine.inOut' }, '<');
    }
    tl.to({}, { duration: Math.max(0, (dur || 4) - 3.4) })
      .add(this.idea())
      .to(p.book, { opacity: 0, scale: .5, duration: .3 }, '-=.4')
      .to([p.armL, p.armR], { rotation: 0, duration: .35 }, '<')
      .to(p.head, { y: 0, duration: .3 }, '<')
      .add(function () { self.expression('smile'); });
    return tl;
  };

  M.dance = function (beats) {
    var p = this.p, self = this, tl = G.timeline();
    this.act = tl;
    this.expression('happy');
    for (var i = 0; i < (beats || 6); i++) {
      var s = i % 2 ? -1 : 1;
      tl.to(p.root, { rotation: 9 * s, duration: .24, ease: 'sine.inOut' }, i * .3)
        .to(this.bounce, { y: -10, duration: .12, yoyo: true, repeat: 1, ease: 'sine.out' }, i * .3)
        .to(p.armL, { rotation: s > 0 ? 125 : 10, duration: .2 }, i * .3)
        .to(p.armR, { rotation: s > 0 ? -10 : -125, duration: .2 }, i * .3)
        .to(p.legL, { rotation: s > 0 ? 12 : 0, duration: .2 }, i * .3)
        .to(p.legR, { rotation: s > 0 ? 0 : -12, duration: .2 }, i * .3)
        .add(function () { self.burst('notes', 2); }, i * .3);
    }
    tl.add(function () { self.reset(.3); self.expression('smile'); });
    return tl;
  };

  M.turnaround = function (rounds) {
    var self = this, tl = G.timeline(), seq = ['q34', 'right', 'back', 'left', 'front'];
    for (var r = 0; r < (rounds || 2); r++) {
      seq.forEach(function (v) { tl.add(function () { self.view(v, .1); }).to({}, { duration: .13 }); });
    }
    tl.add(function () { self.expression('confused'); self.burst('stars', 5); self.say('Whoa… dizzy!', 1.4); })
      .to(this.p.spin, { rotation: 8, duration: .25, yoyo: true, repeat: 5, ease: 'sine.inOut' })
      .set(this.p.spin, { rotation: 0 })
      .add(function () { self.expression('smile'); });
    return tl;
  };

  M.coverEyes = function (on) {
    var p = this.p;
    if (this.act) { this.act.kill(); this.act = null; }      // stop waving etc. first
    G.killTweensOf([p.armL, p.armR, this.bubble]);
    G.set(this.bubble, { opacity: 0 });
    G.to(p.armL, { rotation: on ? 170 : 0, y: on ? -18 : 0, x: on ? -15 : 0, duration: .35, ease: 'back.out(1.6)', overwrite: 'auto' });
    G.to(p.armR, { rotation: on ? -170 : 0, y: on ? -18 : 0, x: on ? 15 : 0, duration: .35, ease: 'back.out(1.6)', overwrite: 'auto' });
    this.expression(on ? 'happy' : 'smile');
  };

  M.lookAt = function (cx, cy) {
    var r = this.svg.getBoundingClientRect();
    var dx = Math.max(-1, Math.min(1, (cx - (r.left + r.width / 2)) / (r.width * 1.5)));
    var dy = Math.max(-1, Math.min(1, (cy - (r.top + r.height * .4)) / (r.height * 1.5)));
    var s = this.curView === 'left' ? -1 : 1;
    G.to(this.p.eyes, { x: dx * 6 * s, y: dy * 5, duration: .25, overwrite: 'auto' });
    G.to(this.p.head, { rotation: dx * 6 * s, duration: .4, overwrite: 'auto' });
  };

  M.react = function () {
    if (this.reacting) return;
    var self = this;
    this.reacting = true;
    var r = pick([
      function () { self.burst('stars', 8); self.say(pick(['Woohoo!', 'Yay!', 'Let\'s go!']), 1.2); return self.jump({ h: 36 }); },
      function () { return self.wave(2, pick(['Hi there! 👋', 'Hello!'])); },
      function () { self.expression('wink'); self.burst('hearts', 4); self.say('Study hard! 📚', 1.4); return wait(1.4); },
      function () { self.say('Ta-da!', 1.2); self.burst('stars', 6); return self.jump({ h: 50, flip: true, expr: 'excited' }); }
    ])();
    Promise.resolve(r).then(function () { self.expression('smile'); self.reacting = false; });
  };

  M.interactive = function () {
    var self = this, el = this.el;
    el.classList.add('is-interactive');
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    el.setAttribute('aria-label', 'Mascot – click to say hi');
    el.addEventListener('pointerenter', function () {
      self.hovering = true;
      if (!self.reacting && !self.busy) { self.expression('happy'); G.to(self.bounce, { y: -6, duration: .15, yoyo: true, repeat: 1 }); }
    });
    el.addEventListener('pointermove', function (e) { if (!self.busy) self.lookAt(e.clientX, e.clientY); });
    el.addEventListener('pointerleave', function () {
      self.hovering = false;
      if (!self.reacting && !self.busy) { self.expression('smile'); G.to(self.p.eyes, { x: 0, y: 0, duration: .3 }); G.to(self.p.head, { rotation: 0, duration: .3 }); }
    });
    el.addEventListener('click', function () { self.react(); });
    el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); self.react(); } });
  };

  window.Mascot = Mascot;

  // ================================================================== HOME BANNER ROUTINE
  function heroStage(stage) {
    stage.innerHTML = '<div class="m-books">' + booksArt() + '</div><div class="m-actor"></div>';
    var books = stage.querySelector('.m-books'), actorEl = stage.querySelector('.m-actor');
    var m = new Mascot(actorEl, { interactive: true });
    var L = {};

    function layout() {
      var W = stage.clientWidth, mob = W < 560;
      L.mw = actorEl.offsetWidth; L.s = L.mw / 200;
      L.bw = books.offsetWidth; L.bh = books.offsetHeight;
      L.bc = W * (mob ? .78 : .84);
      G.set(books, { x: L.bc - L.bw / 2 });
      L.sitX = L.bc - L.mw / 2;
      L.sitY = -(L.bh - (262 - 214) * L.s);          // hips rest on top of the books
      L.groundY = 9 * L.s;                             // feet on the floor line
      L.beside = L.bc - L.bw / 2 - L.mw * .8;
      L.left = W * (mob ? .04 : .1);
    }
    function sitOnBooks() { m.sit(true, 0); m.view('front', 0); G.set(actorEl, { x: L.sitX, y: L.sitY }); }
    function hop(toX, toY, sitAfter) {
      var fromY = G.getProperty(actorEl, 'y'), peak = Math.min(fromY, toY) - 34 * L.s * 3;
      m.busy = true;
      var tl = G.timeline({ onComplete: function () { m.busy = false; } });
      tl.add(function () { m.sit(false, .15); m.expression('happy'); })
        .to(m.p.root, { scaleY: .85, scaleX: 1.1, duration: .12 })
        .to(m.p.armL, { rotation: 100, duration: .2 }, '<').to(m.p.armR, { rotation: -100, duration: .2 }, '<')
        .to(actorEl, { x: toX, duration: .6, ease: 'none' })
        .to(actorEl, { y: peak, duration: .3, ease: 'power2.out' }, '<')
        .to(m.p.root, { scaleY: 1.06, scaleX: .96, duration: .15 }, '<')
        .to(actorEl, { y: toY, duration: .3, ease: 'power2.in' }, '>')
        .add(function () { if (sitAfter) m.sit(true, .12); })
        .to(m.p.root, { scaleY: .9, scaleX: 1.07, duration: .1 })
        .to(m.p.root, { scaleY: 1, scaleX: 1, duration: .25, ease: 'back.out(3)' })
        .to([m.p.armL, m.p.armR], { rotation: 0, duration: .25 }, '<')
        .add(function () { m.expression('smile'); });
      return tl;
    }

    var funny = [
      function () { return m.dance(8); },
      function () { m.say('Watch this!', 1); m.burst('stars', 6); return m.jump({ h: 46 * L.s * 3, flip: true }).then(function () { m.say('Ta-da! ✨', 1.2); return wait(1); }); },
      function () { return m.turnaround(2); },
      function () { return m.think(2.4, 'Hmm… 🤔').then(function () { return m.idea(); }).then(function () { m.say('Got it!', 1); return wait(.8); }); }
    ];

    async function routine() {
      var i = 0;
      layout(); sitOnBooks();
      for (;;) {
        await wait(1.4);
        m.busy = true; await m.study(4.6); m.busy = false;
        await wait(.5);
        await hop(L.beside, L.groundY, false);
        await m.runTo(actorEl, L.left, { speed: L.mw * 1.8 });
        m.busy = true; await m.wave(3, 'Hi! 👋'); m.busy = false;
        await wait(.3);
        m.busy = true; await funny[i++ % funny.length](); m.busy = false;
        await wait(.4);
        await m.runTo(actorEl, L.beside, { speed: L.mw * 1.8 });
        await hop(L.sitX, L.sitY, true);
        m.view('front', .2);
      }
    }

    var lastW = stage.clientWidth;
    window.addEventListener('resize', function () {
      if (Math.abs(stage.clientWidth - lastW) < 3) return;   // ignore mobile address-bar resizes
      lastW = stage.clientWidth;
      layout();
      if (m.sitting) G.set(actorEl, { x: L.sitX, y: L.sitY });
      else G.set(actorEl, { y: L.groundY, x: Math.min(G.getProperty(actorEl, 'x'), L.beside) });
    });

    if (RM) { layout(); sitOnBooks(); return; }
    // pause everything while the banner is off-screen
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) { G.globalTimeline[es[0].isIntersecting ? 'resume' : 'pause'](); }).observe(stage);
    }
    routine();
  }

  // ================================================================== LOADING SCREENS
  function loaderScreen(box) {
    var slot = box.querySelector('[data-mascot="loader"]');
    if (slot) {
      var m = new Mascot(slot, { idle: false });
      if (!RM) { m.view('right', 0); m.cycle('run'); }
    }
    return m;
  }

  function firstVisitLoader() {
    var box = document.getElementById('m-loader');
    if (!box) return;
    if (document.documentElement.classList.contains('m-seen')) { box.remove(); return; }   // only on the first visit
    try { sessionStorage.setItem('msc_seen', '1'); } catch (e) { /* private mode */ }
    loaderScreen(box);
    var start = Date.now();
    function hide() {
      G.delayedCall(Math.max(0, .9 - (Date.now() - start) / 1000), function () {
        G.to(box, { opacity: 0, duration: .35, onComplete: function () { box.remove(); } });
      });
    }
    if (document.readyState === 'complete') hide(); else window.addEventListener('load', hide);
  }

  // overlay shown while a form is being submitted
  function submitLoader() {
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (!f.hasAttribute('data-loader')) return;
      setTimeout(function () {
        if (e.defaultPrevented) return;            // validation stopped the submit
        var box = document.createElement('div');
        box.className = 'm-loader is-submit';
        box.innerHTML = '<div class="m-loader-in"><div data-mascot="loader" style="--msc-w:110px"></div><p>' +
          (f.getAttribute('data-loader') || 'Please wait…') + '</p><div class="m-dots"><i></i><i></i><i></i></div></div>';
        document.body.appendChild(box);
        loaderScreen(box);
        G.fromTo(box, { opacity: 0 }, { opacity: 1, duration: .2 });
      }, 0);
    });
    window.addEventListener('pageshow', function (e) {   // back button: remove stale overlay
      if (e.persisted) document.querySelectorAll('.m-loader.is-submit').forEach(function (n) { n.remove(); });
    });
  }

  // ================================================================== LOGIN PAGE
  function loginMascot(el) {
    var m = new Mascot(el, { size: +el.dataset.size || 110, interactive: true });
    var form = el.closest('form') || document.querySelector('form');
    var user = document.getElementById('username'), pass = document.getElementById('password');
    if (el.dataset.error) { m.expression('sad'); m.say('Oops! Try again.', 2.2); G.delayedCall(2.4, function () { m.expression('smile'); }); }
    else if (!RM) G.delayedCall(.6, function () { m.wave(2, 'Welcome back! 👋'); });
    if (user) {
      user.addEventListener('focus', function () { m.coverEyes(false); m.expression('smile'); G.to(m.p.eyes, { x: 3, y: 5, duration: .3 }); });
      user.addEventListener('input', function () { G.to(m.p.eyes, { x: Math.min(6, -4 + user.value.length * .5), y: 5, duration: .2 }); });
    }
    if (pass) {
      pass.addEventListener('focus', function () { m.busy = true; m.coverEyes(true); });
      pass.addEventListener('blur', function () { m.busy = false; m.coverEyes(false); });
    }
    if (form) form.addEventListener('submit', function () { m.coverEyes(false); m.expression('excited'); });
  }

  // ================================================================== AUTO-INIT
  async function forever(fn, gap) { for (;;) { await fn(); await wait(gap == null ? .6 : gap); } }

  function init(el) {
    var type = el.getAttribute('data-mascot');
    var size = +el.getAttribute('data-size') || null;
    if (type === 'hero') return heroStage(el);
    if (type === 'loader') return;                     // created by the loader screens
    if (type === 'login') return loginMascot(el);

    var m = new Mascot(el, { size: size, view: el.dataset.view, expr: el.dataset.expr, interactive: type !== 'sheet' || el.hasAttribute('data-interactive') });
    if (el.dataset.say) G.delayedCall(.5, function () { m.say(el.dataset.say, 2.6); });
    if (RM) return;

    switch (type) {
      case 'wave': forever(function () { return wait(rand(4, 7)).then(function () { return m.reacting ? null : m.wave(2); }); }, 0); break;
      case 'think': forever(function () { return m.reacting ? wait(1) : m.think(3, false).then(function () { return wait(2.5); }); }, 0); break;
      case 'celebrate':
        G.delayedCall(.3, function () { m.burst('confetti', 16); m.jump({ h: 34 }).then(function () { m.burst('stars', 8); return m.jump({ h: 26 }); }).then(function () { m.expression('happy'); }); });
        break;
      case 'confused': m.expression('confused'); forever(function () { return wait(4).then(function () { m.say('?', 1.4); return m.think(2.2, false).then(function () { m.expression('confused'); }); }); }, 0); break;
      case 'sad': m.expression('sad'); break;
      case 'sheet': sheetDemo(m, el.dataset.anim); break;
    }
  }

  // character-sheet animation demos
  function sheetDemo(m, anim) {
    if (!anim) return;
    if (anim === 'walk' || anim === 'run') { m.view('right', 0); m.cycle(anim); return; }
    if (anim === 'sit') { m.sit(true, 0); return; }
    var A = {
      jump: function () { return m.jump({ h: 34 }); },
      wave: function () { return m.wave(3); },
      think: function () { return m.think(2.6); },
      study: function () { m.sit(true, 0); return m.study(4.6); },
      dance: function () { return m.dance(8); },
      flip: function () { return m.jump({ h: 50, flip: true }); },
      turnaround: function () { return m.turnaround(1); },
      idea: function () { return m.think(1.8, false).then(function () { return m.idea(); }); }
    };
    if (A[anim]) forever(A[anim], 1);
  }

  function boot() {
    firstVisitLoader();
    submitLoader();
    document.querySelectorAll('[data-mascot]').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
