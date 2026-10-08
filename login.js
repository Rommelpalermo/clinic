const authForm = document.getElementById("authForm");
const authMessage = document.getElementById("authMessage");
const authPassword = document.getElementById("authPassword");
const authPasswordConfirm = document.getElementById("authPasswordConfirm");
const confirmPasswordRow = document.getElementById("confirmPasswordRow");
const authSubmit = document.getElementById("authSubmit");
let setupRequired = false;

async function initializeAuthPage() {
  try {
    const response = await fetch("api.php?action=auth-status");
    const status = await response.json();
    if (!response.ok) throw new Error(status.error || "Could not check admin setup.");
    if (status.authenticated) {
      window.location.replace("index.html");
      return;
    }

    setupRequired = status.setupRequired;
    document.getElementById("authTitle").textContent = setupRequired ? "Create admin account" : "Admin sign in";
    document.getElementById("authDescription").textContent = setupRequired
      ? "Create the first administrator account to secure this clinic. Use a password with at least 12 characters."
      : "Sign in to access the clinic dashboard.";
    confirmPasswordRow.hidden = !setupRequired;
    authPassword.autocomplete = setupRequired ? "new-password" : "current-password";
    authPassword.minLength = setupRequired ? 12 : 0;
    authPasswordConfirm.required = setupRequired;
    authSubmit.textContent = setupRequired ? "Create admin account" : "Sign in";
  } catch (error) {
    authMessage.textContent = error.message;
    authSubmit.disabled = true;
  }
}

authForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  authMessage.textContent = "";
  if (setupRequired && authPassword.value !== authPasswordConfirm.value) {
    authMessage.textContent = "The passwords do not match.";
    authPasswordConfirm.focus();
    return;
  }

  authSubmit.disabled = true;
  authSubmit.textContent = setupRequired ? "Creating account..." : "Signing in...";
  try {
    const response = await fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        type: "auth",
        action: setupRequired ? "setup" : "login",
        username: document.getElementById("authUsername").value.trim(),
        password: authPassword.value,
      }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Authentication failed.");
    window.location.replace("index.html");
  } catch (error) {
    authMessage.textContent = error.message;
    authSubmit.disabled = false;
    authSubmit.textContent = setupRequired ? "Create admin account" : "Sign in";
  }
});

initializeAuthPage();