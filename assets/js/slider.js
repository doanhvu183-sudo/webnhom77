document.addEventListener('DOMContentLoaded', function () {
    const slides = document.querySelectorAll('.slide');
    const prev = document.querySelector('.slide-btn.prev');
    const next = document.querySelector('.slide-btn.next');
    let index = 0;

    function showSlide(i) {
        slides.forEach((s, idx) => {
            s.classList.toggle('active', idx === i);
        });
    }

    function nextSlide() {
        index = (index + 1) % slides.length;
        showSlide(index);
    }

    function prevSlide() {
        index = (index - 1 + slides.length) % slides.length;
        showSlide(index);
    }

    if (prev && next && slides.length > 0) {
        prev.addEventListener('click', prevSlide);
        next.addEventListener('click', nextSlide);
        setInterval(nextSlide, 6000);
    }
});
