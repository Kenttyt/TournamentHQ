document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
    const eyeIcon = btn.querySelector('.eye-icon');
    const eyeOffIcon = btn.querySelector('.eye-off-icon');
    if (eyeIcon && eyeOffIcon) {
        eyeIcon.style.display = input.type === 'password' ? 'block' : 'none';
        eyeOffIcon.style.display = input.type === 'password' ? 'none' : 'block';
    }
}
