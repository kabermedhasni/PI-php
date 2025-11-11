const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");

togglePassword.addEventListener("click", function () {
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    togglePassword.src = "../assets/images/eye-off-svgrepo-com.svg";
  } else {
    passwordInput.type = "password";
    togglePassword.src = "../assets/images/eye-show-svgrepo-com.svg";
  }
});
