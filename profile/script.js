const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            const cards = e.target.querySelectorAll('.pillar-card, .program-card, .contact-item');
            cards.forEach((c, i) => {
                c.style.transitionDelay = (i * 0.08) + 's';
                c.classList.add('visible');
            });
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));