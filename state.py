import os, json, threading, time

BASE = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.environ.get("DATA_DIR", BASE)
os.makedirs(DATA_DIR, exist_ok=True)
PATH = os.path.join(DATA_DIR, "data.json")
_lock = threading.Lock()

def _load():
    try:
        with open(PATH) as f:
            return json.load(f)
    except Exception:
        return {"balances": {}, "owner": None}

def _save(d):
    tmp = PATH + ".tmp"
    with open(tmp, "w") as f:
        json.dump(d, f)
    os.replace(tmp, PATH)

def balance(uid):
    with _lock:
        return float(_load()["balances"].get(str(uid), 0))

def set_balance(uid, amt):
    with _lock:
        d = _load()
        d["balances"][str(uid)] = float(amt)
        _save(d)

def debit(uid, amt):
    with _lock:
        d = _load()
        # Owner has unlimited credits — never blocks on balance.
        if str(uid) == str(d.get("owner")):
            return True
        cur = float(d["balances"].get(str(uid), 0))
        if cur < amt - 1e-9:
            return False
        d["balances"][str(uid)] = cur - amt
        _save(d)
        return True

def get_owner():
    with _lock:
        o = _load().get("owner")
        return str(o) if o is not None else None

def set_owner(uid):
    with _lock:
        d = _load()
        d["owner"] = str(uid)
        _save(d)

# ---- active bombs (in-memory) ----
_bombs = {}
_bomb_lock = threading.Lock()

def add_bomb(b):
    with _bomb_lock:
        _bombs[b["id"]] = b

def get_bomb(bid):
    with _bomb_lock:
        return _bombs.get(bid)

def user_bombs(uid):
    with _bomb_lock:
        return [b for b in _bombs.values() if b["user"] == str(uid)]

def remove_bomb(bid):
    with _bomb_lock:
        _bombs.pop(bid, None)

def parse_duration(s):
    import re
    m = re.match(r"^(\d+)([smhd])$", (s or "").strip().lower())
    if not m:
        return 120
    v = int(m.group(1))
    return {"s": 1, "m": 60, "h": 3600, "d": 86400}[m.group(2)] * v

PRICING = {"call": 0.5, "sms": 0.3, "both": 0.65}

def cost(mode, seconds):
    return round(seconds * PRICING.get(mode, 0.65), 2)

def upsert_bomb(bid, mode, seconds, user, msg_id=None):
    with _bomb_lock:
        b = _bombs.get(bid)
        if b is None:
            b = {"id": bid, "user": str(user), "mode": mode, "seconds": seconds,
                 "credits": 0.0, "started_at": time.time(), "stats": {
                     "calls": 0, "sms": 0, "ok": 0, "err": 0, "last": ""}}
            _bombs[bid] = b
        else:
            b["seconds"] = seconds
            b["mode"] = mode
            if msg_id:
                b["msg_id"] = msg_id
        return dict(b)

def stop_bomb(bid):
    with _bomb_lock:
        b = _bombs.get(bid)
        if b:
            b["seconds"] = 0
