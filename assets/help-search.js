document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('help-search-input');
    const topicList = document.getElementById('help-topic-list');
    const noResults = document.getElementById('no-results');

    if (!searchInput || !topicList) {
        return;
    }

    const topics = topicList.querySelectorAll('li');
    const searchForm = document.getElementById('help-search-form');

    if (searchForm) {
        searchForm.addEventListener('submit', (e) => e.preventDefault());
    }

    searchInput.addEventListener('input', () => {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        topics.forEach(topic => {
            const isVisible = topic.textContent.toLowerCase().includes(searchTerm);
            topic.classList.toggle('hidden', !isVisible);
            if (isVisible) visibleCount++;
        });

        noResults.classList.toggle('hidden', visibleCount > 0);
    });
});