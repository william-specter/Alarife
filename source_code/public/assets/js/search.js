/* Alarife offline search — reads a local JS index, never fetches.
   Works from file:// without a server. */
(function () {
    'use strict';

    function normalize(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function tokenize(value) {
        return normalize(value).split(/[^a-z0-9\u00c0-\u024f]+/i).filter(function (token) {
            return token.length > 1;
        });
    }

    function score(entry, terms) {
        var title = normalize(entry.t);
        var excerpt = normalize(entry.e || '');
        var keywords = normalize(entry.k || '');
        var total = 0;

        for (var i = 0; i < terms.length; i++) {
            var term = terms[i];
            var found = false;

            if (title === term) { total += 20; found = true; }
            else if (title.indexOf(' ' + term) !== -1) { total += 10; found = true; }
            else if (title.indexOf(term) !== -1) { total += 7; found = true; }

            if (excerpt.indexOf(term) !== -1) { total += 3; found = true; }
            if (keywords.indexOf(term) !== -1) { total += 1; found = true; }

            if (!found) return 0;
        }

        return total;
    }

    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function render(container, entries, terms, query) {
        container.innerHTML = '';
        var matches = entries
            .map(function (entry) { return { entry: entry, score: score(entry, terms) }; })
            .filter(function (result) { return result.score > 0; })
            .sort(function (a, b) { return b.score - a.score || a.entry.t.localeCompare(b.entry.t); })
            .slice(0, 50);

        if (!query) {
            container.appendChild(element('p', 'search-empty', 'Enter a word or phrase above to search the encyclopedia.'));
            return 0;
        }

        if (!matches.length) {
            container.appendChild(element('p', 'search-empty', 'No results matched “' + query + '”. Try a shorter phrase or a topic name.'));
            return 0;
        }

        matches.forEach(function (result) {
            var entry = result.entry;
            var article = element('article', 'search-result');
            var meta = element('div', 'search-result__meta', entry.u.indexOf('topics/') !== -1 ? 'Topic' : 'Article');
            var heading = element('h2');
            var link = element('a', null, entry.t);
            link.href = entry.u;
            heading.appendChild(link);
            article.appendChild(meta);
            article.appendChild(heading);
            if (entry.e) article.appendChild(element('p', null, entry.e));
            container.appendChild(article);
        });

        return matches.length;
    }

    function init() {
        var page = document.querySelector('[data-search-page]');
        if (!page || !window.ALARIFE_SEARCH_INDEX) return;

        var index = window.ALARIFE_SEARCH_INDEX;
        var entries = index.entries || [];
        var input = page.querySelector('[data-search-input]');
        var results = page.querySelector('[data-search-results]');
        var count = page.querySelector('[data-search-count]');
        var params = new URLSearchParams(window.location.search);
        var query = (params.get('q') || '').trim();
        var terms = tokenize(query);

        if (input) {
            input.value = query;
            input.addEventListener('input', function () {
                var value = input.value.trim();
                var liveTerms = tokenize(value);
                var found = render(results, entries, liveTerms, value);
                if (count) {
                    count.textContent = value
                        ? found + ' ' + (found === 1 ? 'result' : 'results') + ' for “' + value + '”'
                        : '';
                }
            });
        }

        if (query) {
            var found = render(results, entries, terms, query);
            if (count) {
                count.textContent = found + ' ' + (found === 1 ? 'result' : 'results') + ' for “' + query + '”';
            }
        } else {
            render(results, entries, [], '');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
