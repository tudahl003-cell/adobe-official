# RMM server — PHP port of the PowerShell original.
# Same API contract; JSON-file storage in a persistent volume.
#
# Endpoints:
#   POST /api/enroll                    agent enrollment -> {agentId, token}
#   POST /api/agents/{id}/heartbeat     -> {jobs:[...]}, updates lastSeen
#   GET  /api/jobs/{id}/file            agent downloads payload (X-Agent-Token)
#   POST /api/jobs/{id}/result          agent reports exit code
#   POST /api/agents/{id}/removed       agent confirms self-uninstall
#   GET  /api/agents                    (admin) list enrolled agents
#   POST /api/agents/{id}/remove        (admin) flag agent for self-uninstall
#   DEL  /api/agents/{id}               (admin) drop record immediately
#   GET  /api/jobs                      (admin) list jobs
#   POST /api/upload?fileName=X         (admin) upload payload, raw body
#   POST /api/deploy                    (admin) queue payload -> agent
#   GET  /                              dashboard
#
# Auth: X-Admin-Key for admin routes, X-Agent-Token for agent routes.
# Keys are minted on first run into DATA_DIR/config.json.
