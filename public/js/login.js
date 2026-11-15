const passwordInput = document.getElementById('password');
const toggleButton = document.getElementById('toggle-password');

if (passwordInput && toggleButton) {
    toggleButton.addEventListener('click', () => {
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        toggleButton.textContent = showPassword ? 'Masquer' : 'Afficher';
        toggleButton.setAttribute('aria-pressed', String(showPassword));
    });
}
