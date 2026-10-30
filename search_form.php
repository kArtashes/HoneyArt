<!-- Search: slides down over the header. Typing shows live results (search.php);
     Enter opens the products page filtered by the search text. -->
<form action="products.php" method="GET" id="search-row" role="search">
    <div class="search-inner">
        <div class="search-bar">
            <svg class="search-bar-icon" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M15.5 15.5 20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <input id="searchInput" type="search" name="q" placeholder="Search honey…" autocomplete="off"
                   aria-label="Search products" aria-controls="searchResults" enterkeyhint="search">
            <button class="search-close" type="button" onclick="searchClose()" aria-label="Close search">×</button>
        </div>
        <div id="searchResults" aria-live="polite"></div>
    </div>
</form>
<div id="search-overlay" onclick="searchClose()"></div>

<script>
    (function () {
        const row = document.getElementById('search-row');
        const overlay = document.getElementById('search-overlay');
        const input = document.getElementById('searchInput');
        const results = document.getElementById('searchResults');
        let timer = null;
        let request = null;

        window.searchShow = function () {
            row.classList.add('show');
            overlay.classList.add('show');
            input.focus();
        };

        window.searchClose = function () {
            row.classList.remove('show');
            overlay.classList.remove('show');
            input.blur();
        };

        function showResults(html) {
            results.innerHTML = html;
            row.classList.toggle('has-results', html.trim() !== '');
        }

        // Wait until typing pauses, then ask search.php; a newer search cancels the older one
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const query = input.value.trim();
            if (query === '') {
                if (request) request.abort();
                showResults('');
                return;
            }
            timer = setTimeout(() => {
                if (request) request.abort();
                request = new AbortController();
                row.classList.add('loading');
                fetch('search.php?q=' + encodeURIComponent(query), { signal: request.signal })
                    .then(response => response.text())
                    .then(showResults)
                    .catch(error => { if (error.name !== 'AbortError') showResults(''); })
                    .finally(() => row.classList.remove('loading'));
            }, 200);
        });

        // An empty search does nothing instead of opening an empty results page
        row.addEventListener('submit', (event) => {
            if (input.value.trim() === '') event.preventDefault();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && row.classList.contains('show')) searchClose();
        });
    })();
</script>
