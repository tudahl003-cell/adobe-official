# FLOOD BOT

Telegram bot that floods a phone number with calls and/or SMS.

## Run
```
pip install -r requirements.txt
TG_BOT_TOKEN=<from @BotFather> python3 app.py
```

## Env
- `TG_BOT_TOKEN` - bot token (required)
- `PORT` - http port (default 8080)
- `TWILIO_SID`, `TWILIO_TOKEN` - enable real Twilio calls+SMS
- `TWILIO_FROMS` - comma list of From numbers (rotation/diversity)
- `TWILML_URL` - public URL of this service + /twiml
- `ADMINS` - comma list of extra admin Telegram IDs (first /start = owner)

Without Twilio keys it runs on a demo engine (fires but no real calls).

## Commands
/bomb <number> <minutes> <mode>   mode: call|sms|both
/status /stop /balance /add <uid> <amount> (admin)
