const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");

togglePassword.addEventListener("click", function () {
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    togglePassword.src = "assets/images/eye-off.svg";
  } else {
    passwordInput.type = "password";
    togglePassword.src = "assets/images/eye-show.svg";
  }
});
