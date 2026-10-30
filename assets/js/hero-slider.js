(function () {
    var slider = document.getElementById('heroSlider');
    var track = document.getElementById('heroTrack');
    var dotsBox = document.getElementById('heroDots');
    if (!slider || !track) { return; }

    var slides = track.children;
    var total = slides.length;
    var index = 0;
    var timer = null;
    var startX = 0;
    var deltaX = 0;
    var dragging = false;
    var moved = false;

    for (var i = 0; i < total; i++) {
        (function (n) {
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('aria-label', 'Go to slide ' + (n + 1));
            b.addEventListener('click', function () { goTo(n); restart(); });
            dotsBox.appendChild(b);
        })(i);
    }

    function render() {
        track.style.transform = 'translateX(' + (-index * 100) + '%)';
        var dots = dotsBox.children;
        for (var i = 0; i < dots.length; i++) {
            dots[i].classList.toggle('active', i === index);
        }
    }

    function goTo(n) {
        index = (n + total) % total;
        render();
    }

    function start() {
        if (total > 1) { timer = setInterval(function () { goTo(index + 1); }, 3000); }
    }

    function stop() { clearInterval(timer); }

    function restart() { stop(); start(); }

    function onDown(x) {
        dragging = true;
        moved = false;
        startX = x;
        deltaX = 0;
        slider.classList.add('dragging');
        stop();
    }

    function onMove(x) {
        if (!dragging) { return; }
        deltaX = x - startX;
        if (Math.abs(deltaX) > 5) { moved = true; }
        var pct = (deltaX / slider.clientWidth) * 100;
        track.style.transform = 'translateX(' + (-index * 100 + pct) + '%)';
    }

    function onUp() {
        if (!dragging) { return; }
        dragging = false;
        slider.classList.remove('dragging');
        if (Math.abs(deltaX) > slider.clientWidth * 0.15) {
            goTo(deltaX < 0 ? index + 1 : index - 1);
        } else {
            render();
        }
        start();
    }

    slider.addEventListener('touchstart', function (e) { onDown(e.touches[0].clientX); }, { passive: true });
    slider.addEventListener('touchmove', function (e) { onMove(e.touches[0].clientX); }, { passive: true });
    slider.addEventListener('touchend', onUp);
    slider.addEventListener('mousedown', function (e) { onDown(e.clientX); });
    window.addEventListener('mousemove', function (e) { onMove(e.clientX); });
    window.addEventListener('mouseup', onUp);

    slider.addEventListener('click', function (e) {
        if (moved) { e.preventDefault(); moved = false; }
    }, true);

    render();
    start();
})();