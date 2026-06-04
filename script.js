const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("input-password");

togglePassword.addEventListener("click", () => {
    const isPassword = passwordInput.type === "password";

    passwordInput.type = isPassword ? "text" : "password";

    togglePassword.classList.toggle("ph-eye");
    togglePassword.classList.toggle("ph-eye-slash");
});