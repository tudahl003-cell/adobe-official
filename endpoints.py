import os, time, urllib.request, urllib.parse, base64

class Endpoint:
    name = "base"
    def __init__(self):
        self._cool_until = 0.0
    def cooling(self):
        return time.time() < self._cool_until
    def fail(self, secs=30):
        self._cool_until = time.time() + secs
    def call(self, to):
        raise NotImplementedError
    def sms(self, to, body):
        raise NotImplementedError

def _post(url, fields, headers=None):
    data = urllib.parse.urlencode(fields).encode()
    h = {"Content-Type": "application/x-www-form-urlencoded"}
    if headers:
        h.update(headers)
    req = urllib.request.Request(url, data=data, headers=h)
    with urllib.request.urlopen(req, timeout=20) as r:
        return r.status

class TwilioEndpoint(Endpoint):
    def __init__(self, sid, token, from_number, twiml_url):
        self.sid = sid
        self.token = token
        self.from_number = from_number
        self.twiml_url = twiml_url
        self.name = "twilio:" + from_number
    def _auth(self):
        b = base64.b64encode(("%s:%s" % (self.sid, self.token)).encode()).decode()
        return {"Authorization": "Basic " + b}
    def call(self, to):
        try:
            st = _post("https://api.twilio.com/2010-04-01/Accounts/%s/Calls.json" % self.sid,
                       {"To": to, "From": self.from_number, "Url": self.twiml_url}, self._auth())
            return st == 200
        except Exception:
            return False
    def sms(self, to, body):
        try:
            st = _post("https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json" % self.sid,
                       {"To": to, "From": self.from_number, "Body": (body or "You have a new message.")[:320]},
                       self._auth())
            return st == 200
        except Exception:
            return False

class DemoEndpoint(Endpoint):
    def __init__(self):
        Endpoint.__init__(self)
        self.name = "demo"
    def call(self, to):
        return True
    def sms(self, to, body):
        return True

def get_pool():
    pool = []
    sid = os.environ.get("TWILIO_SID", "")
    tok = os.environ.get("TWILIO_TOKEN", "")
    twiml = os.environ.get("TWILML_URL", "http://127.0.0.1:8080/twiml")
    if sid and tok:
        for num in os.environ.get("TWILIO_FROMS", "").replace(" ", "").split(","):
            if num:
                pool.append(TwilioEndpoint(sid, tok, num, twiml))
    if not pool:
        pool.append(DemoEndpoint())
    return pool
