console.log("✅ script.js loaded");

document.addEventListener("DOMContentLoaded", function () {
  console.log("🚀 DOM is ready");

  // Safe to access DOM elements now
  const usernameInput = document.getElementById("username");
  const passwordInput = document.getElementById("password");

  if (usernameInput && passwordInput) {
    const username = usernameInput.value;
    const password = passwordInput.value;

    console.log("Sending:", { username, password });
  }

  // Highlight recipe items on hover (safe to run now)
  const recipeItems = document.querySelectorAll("ul li");
  recipeItems.forEach(item => {
    item.addEventListener("mouseover", () => {
      item.style.backgroundColor = "#f0f8ff";
    });
    item.addEventListener("mouseout", () => {
      item.style.backgroundColor = "transparent";
    });
  });
});