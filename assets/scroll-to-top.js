document.addEventListener('DOMContentLoaded', () => {
    const scrollToTopButton = document.getElementById('scroll-to-top');

    if (!scrollToTopButton) {
        return;
    }

    const showButtonOnScroll = () => {
        if (window.scrollY > 300) {
            scrollToTopButton.classList.remove('hidden');
        } else {
            scrollToTopButton.classList.add('hidden');
        }
    };

    const scrollToTop = (event) => {
        event.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    };

    window.addEventListener('scroll', showButtonOnScroll);
    scrollToTopButton.addEventListener('click', scrollToTop);

    // Initial check to hide button on page load if at top
    showButtonOnScroll();
});