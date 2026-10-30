(function () {
    var video = document.getElementById('heroVideo');
    if (!video) { return; }

    function restartEarly() {
        if (video.duration && video.currentTime >= video.duration - 0.12) {
            video.currentTime = 0;
            video.play();
        }
    }

    video.addEventListener('timeupdate', restartEarly);
    video.addEventListener('ended', function () {
        video.currentTime = 0;
        video.play();
    });
    video.addEventListener('pause', function () {
        if (!document.hidden) { video.play(); }
    });
})();