/* Telemetry collector + behavioral verdict fetch (Jev gate).
   Collects mouse/keyboard/scroll behavior, POSTs a compact JSON blob to
   /decide.php (one Jev verdict per visit), then redirects to download.php
   with the verdict-bound token.

   Timing:
     T0        page load
     T0+3400ms telemetry POST (enough behavior for a reliable verdict)
     fast      -> redirect immediately on response (~T0+3.7s)
     gray/low  -> hold until T0+5000ms (legacy pacing)
     no reply  -> hold until T0+5000ms with the ORIGINAL token (legacy gate)
   Every failure path degrades to the original 5s fixed-time flow. */
(function () {
  var TK = <?=json_encode($tk)?>;
  var T0 = Date.now();
  var T = { vp: innerWidth + 'x' + innerHeight,
            dpr: (window.devicePixelRatio || 1),
            sw: screen.width + 'x' + screen.height,
            ua: navigator.userAgent,
            mm: [], kd: [], wh: [], cl: [], ml: 0, top: 0 };
  var lastX = -1, lastY = -1, lastSampleT = 0;
  var posted = false, done = false;

  addEventListener('mousemove', function (e) {
    var t = Date.now() - T0;
    if (lastX >= 0) T.ml += Math.abs(e.clientX - lastX) + Math.abs(e.clientY - lastY);
    lastX = e.clientX; lastY = e.clientY;
    if (T.mm.length < 300 && (t - lastSampleT >= 25 || Math.random() < 0.2)) {
      T.mm.push([t, e.clientX | 0, e.clientY | 0]);
      lastSampleT = t;
    }
  }, { passive: true });
  addEventListener('mousedown', function (e) {
    if (T.cl.length < 32) T.cl.push([Date.now() - T0, (e.clientX | 0) + ',' + (e.clientY | 0)]);
  }, { passive: true });
  addEventListener('keydown', function () {
    if (T.kd.length < 300) T.kd.push(Date.now() - T0);
  }, { passive: true });
  addEventListener('wheel', function (e) {
    if (T.wh.length < 200) T.wh.push([Date.now() - T0, e.deltaY | 0]);
  }, { passive: true });

  function post() {
    if (posted) return;
    posted = true;
    // Safety net: if decide.php never answers (xhr died, Jev down), fall
    // back to the legacy flow 3.5s after the attempt — the original token
    // then keeps the normal fixed-time pacing.
    setTimeout(function () { finish(null); }, 3500);
    T.top = Date.now() - T0;
    try {
      var body = JSON.stringify(T);
      if (body.length > 24000) { finish(null); return; }
      var x = new XMLHttpRequest();
      x.open('POST', 'decide.php?tk=' + encodeURIComponent(TK), true);
      x.timeout = 2500;
      x.onload = function () {
        try { finish(JSON.parse(x.responseText)); } catch (err) { finish(null); }
      };
      x.onerror = x.ontimeout = function () { finish(null); };
      x.send(body);
    } catch (err) { finish(null); }
  }

  function finish(d) {
    if (done) return;
    done = true;
    var tk = (d && d.tk) ? d.tk : TK;
    var target = (d && d.profile === 'fast') ? 0 : 5000;
    var wait = target - (Date.now() - T0);
    if (wait < 0) wait = 0;
    setTimeout(function () {
      window.location.href = 'download.php?tk=' + encodeURIComponent(tk);
    }, wait);
  }

  setTimeout(post, 3400);
})();
