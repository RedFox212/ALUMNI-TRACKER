document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const signInBtn = document.getElementById('signInBtn');
    const btnText = signInBtn.querySelector('.btn-text');
    const spinner = signInBtn.querySelector('.spinner');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    // Handle Password Visibility Toggle
    togglePassword.addEventListener('click', (e) => {
        e.preventDefault();
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePassword.textContent = type === 'password' ? 'SHOW' : 'HIDE';
    });

    // Handle Sign In Submission (Simulated)
    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();

        // Show/Hide spinner
        btnText.classList.add('hidden');
        spinner.classList.remove('hidden');
        signInBtn.disabled = true;

        // Simulate network request for 1.5s
        setTimeout(() => {
            // Reset state
            btnText.classList.remove('hidden');
            spinner.classList.add('hidden');
            signInBtn.disabled = false;
            
            // Log for demo
            console.log('Login attempt with:', {
                username: loginForm.querySelector('input[type="text"]').value,
                password: passwordInput.value
            });
            
            alert('This is a demo login. Access to the portal would happen here.');
        }, 1500);
    });

    // Input focus interaction (optional reinforcement)
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('focused');
        });
    });
});
