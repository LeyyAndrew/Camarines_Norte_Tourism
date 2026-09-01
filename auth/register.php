<?php
session_start();
// Expects register_process.php to set $_SESSION['register_error'] on failure and redirect back here.
$error = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Camarines Norte Tourism — Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="auth-style.css">
</head>
<body>

<div class="card">

  <div class="scene">
    <div class="sun"></div>
    <div class="canopy"></div>
    <div class="wave w1"></div>
    <div class="wave w2"></div>
    <div class="scene-copy">
      <p class="eyebrow">Camarines Norte</p>
      <h1>Bicol's untouched coast</h1>
      <p>Create an account to build your itinerary and unlock local guides across Camarines Norte.</p>
    </div>
  </div>

  <div class="form-side">

    <div class="tabs">
      <a class="tab" href="login.php">Log in</a>
      <a class="tab active" href="register.php">Register</a>
    </div>

    <h2 class="form-title">Create your account</h2>
    <p class="form-subtitle">Join to build your Camarines Norte itinerary.</p>

    <?php if ($error): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="register_process.php" method="POST">
      <div class="field">
        <label for="reg-firstname">First name</label>
        <input type="text" id="reg-firstname" name="firstname" placeholder="Juan" required>
      </div>
      <div class="field">
        <label for="reg-lastname">Last name</label>
        <input type="text" id="reg-lastname" name="lastname" placeholder="Dela Cruz" required>
      </div>
      <div class="field">
        <label for="reg-email">Email</label>
        <input type="email" id="reg-email" name="email" placeholder="name@example.com" required>
      </div>
      <div class="field">
        <label for="reg-password">Password</label>
        <input type="password" id="reg-password" name="password" placeholder="Create a password" required>
      </div>

      <div class="row-between" style="margin-bottom: 8px;">
        <label class="checkbox-row"><input type="checkbox" name="agree" required> I agree to the terms and privacy policy</label>
      </div>

      <button type="submit" class="submit">Create account</button>
    </form>

    <p class="switch-note">Already have an account? <a href="login.php">Log in</a></p>
  </div>

</div>

</body>
</html>