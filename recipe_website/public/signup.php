<?php 
session_start();

require_once __DIR__ . '/../src/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
  <title>Sign up</title>
</head>
<body>
  <h1>Sign Up</h1>
  <form id="signupForm">
      <input type="email" id="email" placeholder="Email" required /><br/>
      <input type="text" id="username" placeholder="Username" required /><br/>
      <input type="password" id="password" placeholder="Password" required /><br/>
      <button type="submit">Sign Up</button>
  </form>
  <p id="message"></p>

  <script>
  document.getElementById("signupForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const email    = document.getElementById("email").value.trim();
    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    try {
      const res = await fetch("signup_handler.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, username, password }),
      });

      // **always** parse the JSON, even on 4xx/5xx
      const result = await res.json();
      const msgEl  = document.getElementById("message");
      msgEl.textContent = result.message;

      // If it was a 201 Created (success), redirect
      if (res.ok && result.message === "Sign up successful!") {
        setTimeout(() => {
          window.location.href = "index.php";
        }, 1500);
      }
      // otherwise—409, 400, 500—we leave the message in place
    } catch (err) {
      console.error("Network or parsing error:", err);
      document.getElementById("message").textContent = "Something went wrong.";
    }
  });
</script>
</body>
</html>
