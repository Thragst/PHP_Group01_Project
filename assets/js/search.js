(function () {
    var input = document.getElementById('navSearchInput');
    var box = document.getElementById('searchSuggestions');
    if (!input || !box) { return; }

    var endpoint = input.getAttribute('data-endpoint');
    var base = input.getAttribute('data-base') || '';
    var form = input.form;
    var timer = null;
    var controller = null;
    var links = [];
    var active = -1;

    function hide() {
        box.hidden = true;
        box.textContent = '';
        links = [];
        active = -1;
    }

    function setActive(i) {
        links.forEach(function (el, idx) { el.classList.toggle('active', idx === i); });
        active = i;
    }

    function safeUrl(path) {
        if (!path || /^\s*javascript:/i.test(path)) { return ''; }
        return /^https?:\/\//i.test(path) ? path : base + path;
    }

    function addRow(href, build) {
        var a = document.createElement('a');
        a.className = 'search-suggestion';
        a.href = href;
        build(a);
        box.appendChild(a);
        links.push(a);
    }

    function render(data, query) {
        box.textContent = '';
        links = [];
        active = -1;

        if (data.didYouMean) {
            var dym = document.createElement('div');
            dym.className = 'search-dym';
            dym.appendChild(document.createTextNode('Did you mean '));
            var b = document.createElement('a');
            b.href = base + 'index.php?page=products&q=' + encodeURIComponent(data.didYouMean);
            b.textContent = data.didYouMean;
            dym.appendChild(b);
            dym.appendChild(document.createTextNode('?'));
            box.appendChild(dym);
        }

        (data.suggestions || []).forEach(function (s) {
            addRow(safeUrl(s.url), function (a) {
                var img = document.createElement('img');
                var src = safeUrl(s.image);
                if (src) { img.src = src; }
                img.alt = '';
                img.onerror = function () { img.style.visibility = 'hidden'; };
                a.appendChild(img);

                var info = document.createElement('span');
                info.className = 'search-suggestion-info';
                var name = document.createElement('strong');
                name.textContent = s.name;
                var meta = document.createElement('small');
                meta.textContent = [s.brand, s.category].filter(Boolean).join(' · ');
                info.appendChild(name);
                info.appendChild(meta);
                a.appendChild(info);

                var price = document.createElement('span');
                price.className = 'search-suggestion-price';
                price.textContent = '₱' + s.price;
                a.appendChild(price);
            });
        });

        if (!(data.suggestions || []).length) {
            var empty = document.createElement('div');
            empty.className = 'search-empty';
            empty.textContent = 'No quick matches. Press Enter to search everything.';
            box.appendChild(empty);
        } else {
            addRow(base + 'index.php?page=products&q=' + encodeURIComponent(query), function (a) {
                a.className += ' search-all';
                a.textContent = 'See all ' + (data.total || '') + ' results for "' + query + '"';
            });
        }
        box.hidden = false;
    }

    function fetchSuggestions() {
        var q = input.value.trim();
        if (q.length < 2) { hide(); return; }
        if (controller) { controller.abort(); }
        controller = new AbortController();
        fetch(endpoint + '?q=' + encodeURIComponent(q), { signal: controller.signal, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && input.value.trim() === q) { render(data, q); }
            })
            .catch(function () { /* ignore aborted / failed requests */ });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(fetchSuggestions, 200);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { hide(); return; }
        if (box.hidden || !links.length) { return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % links.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((active - 1 + links.length) % links.length); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = links[active].href; }
    });

    document.addEventListener('click', function (e) {
        if (!form.contains(e.target)) { hide(); }
    });
})();
