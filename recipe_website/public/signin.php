<?php 
session_start();
require_once __DIR__ . '/../src/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
    <header class="site-header">
        <h1>Sign in</h1>
        <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>
</head>
<body>
  <form id="signinForm">
    <input type="text" id="username" placeholder="Username" required /><br/>
    <input type="password" id="password" placeholder="Password" required /><br/>
    <button type="submit">Sign in</button>
  </form>
  <p id="message"></p>

  <!-- ✅ This script MUST be below the form and inside <body> -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      console.log("🟢 JS loaded");

      const form = document.getElementById("signinForm");
      const messageEl = document.getElementById("message"); // ✅ Define it here

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        messageEl.innerHTML = ""; // clear any old content

        const username = document.getElementById("username").value;
        const password = document.getElementById("password").value;

        console.log("Sending:", { username, password });

        try {
          const res = await fetch("signin_handler.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({ username, password }),
          });

          const raw = await res.text();
          console.log("Raw response:", raw);
          const result = JSON.parse(raw);
          messageEl.textContent = result.message;

          if (result.message === "Invalid username or password.") {
            const signupBtn = document.createElement("button");
        signupBtn.textContent = "Create an Account";
        signupBtn.style.marginLeft = "10px";
        signupBtn.onclick = () => window.location.href = "signup.php";
        messageEl.appendChild(signupBtn);
          }

          if (result.message === "Sign in successful!") {
            setTimeout(() => {
              window.location.href = "index.php";
            }, 1500);
          }

        } catch (err) {
          console.error("Sign in error:", err);
          document.getElementById("message").textContent = "Something went wrong.";
        }
      });
    });
  </script>
</body>
</html>