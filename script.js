document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.header');
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const navMenu = document.getElementById('nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');

    // 1. Toggle mobile menu
    const toggleMenu = () => {
        const isOpen = hamburgerBtn.classList.toggle('open');
        navMenu.classList.toggle('active');
        hamburgerBtn.setAttribute('aria-expanded', isOpen);
    };

    hamburgerBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMenu();
    });

    // 2. Close menu on clicking links (useful for smooth scrolls or navigation)
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (navMenu.classList.contains('active')) {
                toggleMenu();
            }
        });
    });

    // 3. Close menu when clicking outside of the navbar
    document.addEventListener('click', (event) => {
        const isClickInsideMenu = navMenu.contains(event.target);
        const isClickInsideBurger = hamburgerBtn.contains(event.target);

        if (!isClickInsideMenu && !isClickInsideBurger && navMenu.classList.contains('active')) {
            toggleMenu();
        }
    });

    // 4. Header background changes on scroll
    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            header.classList.add('scroll-active');
        } else {
            header.classList.remove('scroll-active');
        }
    });

    // 5. Active link switching (for single-page navigation style)
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            // Add active class to the parent li of the clicked link
            this.parentElement.classList.add('active');
        });
    });
});
