/* Wariant E „Warstwice” (mapa drogowa projektu) — tło sekcji „Struktura koła” (O nas).
   Dwa canvasy rysowane jednorazowo (i po zmianie rozmiaru sekcji): szum
   wartościowy → pole → warstwice (marching squares). Bez pętli rAF i bez nasłuchu wskaźnika — ruch to wolny dryf CSS. */
(function () {
  'use strict';

  /* ---------- parametry do strojenia (tablice: [a — drobne, b — grubsze i rzadsze]) ---------- */
  var SEED = [11, 29];                                  // ziarno szumu: ten sam rysunek przy każdym wejściu
  var CELL = [14, 26];                                  // px: bok komórki siatki pola; koszt rośnie z 1/CELL², nie schodzić niżej
  var SCALE = [300, 520];                               // px: odstęp węzłów szumu w 1. oktawie (większy = większe, spokojniejsze formy)
  var LEVELS = [12, 6];                                 // liczba poziomów (warstwic)
  var ALPHA = [0.20, 0.14], ALPHA_MAIN = [0.30, 0.26];  // krycie linii zwykłej i „głównej”; ≤ .30, żeby h2 zachował kontrast
  var WIDTH = 1, WIDTH_MAIN = 1.6, MAIN_EVERY = 3;      // px; co 3. poziom jest „główny”
  var OCTAVES = 4, PERSISTENCE = 0.5, TWIST = 0.9;      // oktawy szumu; amplituda kolejnej ×PERSISTENCE; obrót siatki oktawy (rad)
  var BLEED = 0.06;                                     // nadmiar canvasu z każdej strony — musi pasować do inset: -6% w CSS
  var COLORS = [[229, 24, 94], [139, 44, 156], [29, 70, 224]], PURPLE_AT = 0.45;   // gradient marki: róż → fiolet (45%) → niebieski
  var MAX_DPR = 1.5, RESIZE_MS = 150;

  var sekcja = document.getElementById('struktura');
  if (!sekcja || sekcja.getAttribute('data-tlo') !== 'warstwice' || sekcja.querySelector('.tlo-warstwice')) return;
  if (!window.ResizeObserver || !window.IntersectionObserver) return;

  var LAT = 128;                                        // bok tablicy węzłów szumu (potęga 2: zawijanie maską bitową)
  // Tablica przypadków: bity rogów 8 lewy-górny, 4 prawy-górny, 2 prawy-dolny, 1 lewy-dolny; krawędzie 0 góra, 1 prawa, 2 dół,
  // 3 lewa. Wpis = pary krawędzi, między którymi biegnie odcinek; null = siodło (5, 10), rozstrzyga je środek komórki.
  var CASES = [[], [3, 2], [2, 1], [3, 1], [0, 1], null, [0, 2], [0, 3], [0, 3], [0, 2], null, [0, 1], [3, 1], [2, 1], [3, 2], []];
  var SADDLE_A = [0, 3, 2, 1], SADDLE_B = [0, 1, 3, 2]; // A odcina rogi lewy-górny i prawy-dolny, B prawy-górny i lewy-dolny
  var EA = [0, 1, 3, 0], EB = [1, 2, 2, 3], OX = [0, 1, 0, 0], OY = [0, 0, 1, 0];   // krawędź → indeksy rogów (kierunek: w prawo / w dół) i początek
  var COS = [], SIN = [], cv = [0, 0, 0, 0];
  var ptx, pty, seg1, seg2, segA = [], segB = [];         // punkty przecięcia i odcinki przy krawędziach (po id krawędzi), odcinki bieżącego poziomu
  for (var o = 0; o < OCTAVES; o++) { COS[o] = Math.cos(o * TWIST); SIN[o] = Math.sin(o * TWIST); }

  var kont = document.createElement('div'), warstwy = [], W = 0, H = 0, timer = 0, inView = false;
  kont.className = 'tlo-warstwice';
  kont.setAttribute('aria-hidden', 'true');
  ['tlo-warstwice__a', 'tlo-warstwice__b'].forEach(function (cls, n) {
    var canvas = document.createElement('canvas');
    canvas.className = cls;
    kont.appendChild(canvas);
    warstwy.push({ n: n, canvas: canvas, ctx: canvas.getContext('2d'), noise: makeNoise(SEED[n]) });
  });
  if (!warstwy[0].ctx || !warstwy[1].ctx) return;       // bez canvasa nic nie wstawiamy do strony
  sekcja.insertBefore(kont, sekcja.firstChild);

  /* ---------- szum i pole ---------- */
  // xorshift32; ziarno rozrzucone mnożeniem, bo małe (11, 29) dają słabe pierwsze wartości
  function prng(seed) {
    var s = Math.imul(seed, 2654435761) | 0 || 1;
    return function () { s ^= s << 13; s ^= s >>> 17; s ^= s << 5; return (s >>> 0) / 4294967296; };
  }
  // Szum wartościowy 2D: losowe wartości w węzłach, między nimi interpolacja ze smoothstep (brak załamań warstwic na krawędziach siatki)
  function makeNoise(seed) {
    var rnd = prng(seed), tab = new Float32Array(LAT * LAT), k;
    for (k = 0; k < tab.length; k++) tab[k] = rnd();
    return function (x, y) {
      var ix = Math.floor(x), iy = Math.floor(y), fx = x - ix, fy = y - iy, M = LAT - 1;
      var x0 = ix & M, x1 = (ix + 1) & M, r0 = (iy & M) * LAT, r1 = ((iy + 1) & M) * LAT;
      var a = tab[r0 + x0], b = tab[r0 + x1], c = tab[r1 + x0], d = tab[r1 + x1];
      fx = fx * fx * (3 - 2 * fx);
      fy = fy * fy * (3 - 2 * fy);
      return a + (b - a) * fx + (c - a) * fy + (a - b - c + d) * fx * fy;
    };
  }
  // Pole = suma oktaw (każda 2× gęstsza, słabsza i obrócona o TWIST: bez osiowej „kratki” typowej dla szumu wartościowego).
  // Przesunięcie o·37.7 / o·61.3 rozłącza oktawy, które dzielą jedną tablicę węzłów.
  function makeField(noise, scale, nx, ny, cell) {
    var f = new Float64Array((nx + 1) * (ny + 1)), n = 0, i, j, k, v, sum, amp, fr, x, y;
    for (j = 0; j <= ny; j++) {
      for (i = 0; i <= nx; i++) {
        v = 0; sum = 0; amp = 1; fr = 1 / scale; x = i * cell; y = j * cell;
        for (k = 0; k < OCTAVES; k++, amp *= PERSISTENCE, fr *= 2) {
          v += amp * noise((x * COS[k] + y * SIN[k]) * fr + k * 37.7, (y * COS[k] - x * SIN[k]) * fr + k * 61.3);
          sum += amp;
        }
        f[n++] = v / sum;
      }
    }
    return f;
  }
  // Progi = kwantyle pola: pasma między warstwicami mają równą powierzchnię, więc linie rozkładają się po całej sekcji, a każdy
  // poziom ma kontur. Próg to środek między sąsiednimi próbkami, nigdy próbka — inaczej odcinki mogłyby mieć zerową długość.
  function thresholds(f, count) {
    var s = new Float64Array(f).sort(), out = [], k, p;
    for (k = 1; k <= count; k++) { p = Math.floor(k / (count + 1) * (s.length - 1)); out.push((s[p] + s[p + 1]) / 2); }
    return out;
  }

  /* ---------- warstwice ---------- */
  // Przecięcie progu t z krawędzią e komórki (i, j): zapisuje punkt (współrzędne siatki) pod id krawędzi i zwraca to id. Id wynika
  // z numeru próbki, więc dwie sąsiednie komórki mają to samo id, a punkt liczony od lewego/górnego rogu jest co do bitu ten sam.
  function edge(e, i, j, t, q, w) {
    var a = cv[EA[e]], u = (t - a) / (cv[EB[e]] - a), id = 2 * (q + OX[e] + OY[e] * w) + (e & 1);
    ptx[id] = i + OX[e] + (e & 1 ? 0 : u);
    pty[id] = j + OY[e] + (e & 1 ? u : 0);
    return id;
  }
  // Pole f ma (nx+1)×(ny+1) próbek; każdy odcinek warstwicy trafia do emit(idKrawędziA, idKrawędziB).
  function trace(f, nx, ny, t, emit) {
    var w = nx + 1, i, j, q, m, s, k;
    for (j = 0; j < ny; j++) {
      for (i = 0; i < nx; i++) {
        q = j * w + i;
        cv[0] = f[q]; cv[1] = f[q + 1]; cv[2] = f[q + w + 1]; cv[3] = f[q + w];
        m = (cv[0] >= t ? 8 : 0) | (cv[1] >= t ? 4 : 0) | (cv[2] >= t ? 2 : 0) | (cv[3] >= t ? 1 : 0);
        if (m === 0 || m === 15) continue;
        s = CASES[m] || (((cv[0] + cv[1] + cv[2] + cv[3]) / 4 >= t) === (m === 5) ? SADDLE_A : SADDLE_B);
        for (k = 0; k < s.length; k += 2) emit(edge(s[k], i, j, t, q, w), edge(s[k + 1], i, j, t, q, w));
      }
    }
  }
  function link(id, s) { if (seg1[id] < 0) seg1[id] = s; else seg2[id] = s; }
  // Odcinki poziomu składamy w łamane (otwarte od końca bez sąsiada, potem pętle). Osobne odcinki z okrągłymi końcówkami
  // nakładałyby się na styku i dawały ciemniejsze kropki (alfa liczona dwa razy); w łamanej styk to zwykły join.
  function chain(ctx, cell) {
    var n = segA.length, used = new Uint8Array(n), s, e, cur, to, next, pass;
    seg1.fill(-1); seg2.fill(-1);
    for (s = 0; s < n; s++) { link(segA[s], s); link(segB[s], s); }
    for (pass = 0; pass < 2; pass++) {
      for (s = 0; s < n; s++) {
        e = pass ? segA[s] : seg2[segA[s]] < 0 ? segA[s] : seg2[segB[s]] < 0 ? segB[s] : -1;   // -1: nie jest końcem łamanej
        if (used[s] || e < 0) continue;
        ctx.moveTo(ptx[e] * cell, pty[e] * cell);
        for (cur = s; cur >= 0; e = to, cur = next) {
          used[cur] = 1;
          to = segA[cur] === e ? segB[cur] : segA[cur];
          next = seg1[to] === cur ? seg2[to] : seg1[to];
          if (next >= 0 && used[next]) { ctx.closePath(); break; }   // wróciliśmy do początku pętli
          ctx.lineTo(ptx[to] * cell, pty[to] * cell);
        }
      }
    }
  }
  function brand(t) {                                   // t ∈ 0–1 wzdłuż gradientu marki → kolor 'rgb(r,g,b)'
    var up = t > PURPLE_AT, a = COLORS[up ? 1 : 0], b = COLORS[up ? 2 : 1], u = up ? (t - PURPLE_AT) / (1 - PURPLE_AT) : t / PURPLE_AT;
    return 'rgb(' + [0, 1, 2].map(function (c) { return Math.round(a[c] + (b[c] - a[c]) * u); }).join(',') + ')';
  }

  function paint(L) {
    var n = L.n, ctx = L.ctx, cell = CELL[n], grow = 1 + 2 * BLEED, cw = W * grow, ch = H * grow;
    var dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR), nx = Math.ceil(cw / cell), ny = Math.ceil(ch / cell);
    var f = makeField(L.noise, SCALE[n], nx, ny, cell), th = thresholds(f, LEVELS[n]), ne = 2 * (nx + 1) * (ny + 1);
    ptx = new Float64Array(ne); pty = new Float64Array(ne); seg1 = new Int32Array(ne); seg2 = new Int32Array(ne);
    L.canvas.width = Math.round(cw * dpr);              // przypisanie czyści canvas i zeruje stan kontekstu
    L.canvas.height = Math.round(ch * dpr);
    ctx.setTransform(L.canvas.width / cw, 0, 0, L.canvas.height / ch, 0, 0);
    ctx.lineCap = ctx.lineJoin = 'round';
    th.forEach(function (t, k) {
      var main = (k + 1) % MAIN_EVERY === 0;
      segA.length = segB.length = 0;
      trace(f, nx, ny, t, function (a, b) { segA.push(a); segB.push(b); });
      ctx.beginPath();                                  // jedna ścieżka i jeden stroke na poziom
      chain(ctx, cell);
      ctx.strokeStyle = brand(th.length > 1 ? k / (th.length - 1) : 0);
      ctx.globalAlpha = main ? ALPHA_MAIN[n] : ALPHA[n];
      ctx.lineWidth = main ? WIDTH_MAIN : WIDTH;
      ctx.stroke();
    });
  }

  /* ---------- rozmiar, widoczność ---------- */
  // Wysokość sekcji zmienia się po wczytaniu treści z API, a szerokość z oknem: rysujemy od nowa tylko przy zmianie rozmiaru
  function render() {
    var w = sekcja.clientWidth, h = sekcja.clientHeight;
    if (!w || !h || (w === W && h === H)) return;
    W = w; H = h;
    warstwy.forEach(paint);
  }
  // Dryf CSS biegnie tylko, gdy sekcja jest na ekranie, a karta aktywna — poza tym animacja stoi i nie kosztuje nic
  function sync() {
    if (inView && !document.hidden) kont.setAttribute('data-widoczna', ''); else kont.removeAttribute('data-widoczna');
  }

  new ResizeObserver(function () {
    clearTimeout(timer);
    if (W) timer = setTimeout(render, RESIZE_MS); else render();   // pierwszy pomiar od razu, dalsze z debounce
  }).observe(sekcja);
  new IntersectionObserver(function (e) { inView = e[e.length - 1].isIntersecting; sync(); }).observe(sekcja);
  document.addEventListener('visibilitychange', sync);
})();
