import os, json, time, threading, urllib.request
from flask import Flask, request
import bot

app = Flask(__name__)
TG = os.environ.get("TG_BOT_TOKEN", "")

@app.route("/health")
def health():
    return "ok"

@app.route("/twiml")
def twiml():
    # Answered call holds the line with 10 min of silence (ties up the target).
    return ('<?xml version="1.0" encoding="UTF-8"?>'
            '<Response><Pause length="600"/></Response>',
            200, {"Content-Type": "text/xml"})

def poll_loop():
    off = 0
    while True:
        try:
            url = "https://api.telegram.org/bot%s/getUpdates?offset=%d&timeout=30" % (TG, off)
            with urllib.request.urlopen(url, timeout=40) as r:
                data = json.loads(r.read())
            for u in data.get("result", []):
                off = u["update_id"] + 1
                bot.handle_update(u)
        except Exception:
            time.sleep(2)

def main():
    if TG:
        threading.Thread(target=poll_loop, daemon=True).start()
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", 8080)))

if __name__ == "__main__":
    main()
