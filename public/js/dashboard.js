document.querySelectorAll('.nav-link').forEach((link) => {
    link.addEventListener('click', () => document.body.classList.remove('menu-open'));
});
