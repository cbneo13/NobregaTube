(function () {
    var player = document.getElementById('playerBox');
    var video = document.getElementById('video');
    var playButton = document.getElementById('playButton');
    var muteButton = document.getElementById('muteButton');
    var playIcon = document.getElementById('playIcon');
    var pauseIcon = document.getElementById('pauseIcon');
    var muteIcon = document.getElementById('muteIcon');
    var unmuteIcon = document.getElementById('unmuteIcon');
    var seekProgress = document.getElementById('seekProgress');
    var seekHandle = document.getElementById('seekHandle');
    var contextMenu = document.getElementById('playerContextMenu');
    var contextAbout = document.getElementById('contextAbout');
    var contextMute = document.getElementById('contextMute');
    var contextLoop = document.getElementById('contextLoop');
    var muteTick = document.getElementById('muteTick');
    var loopTick = document.getElementById('loopTick');
    var aboutBox = document.getElementById('aboutBox');
    var aboutCloseButton = document.getElementById('aboutCloseBtn');
    if (!player || !video || !playButton || !seekHandle || !seekProgress) return;

    function setClass(element, name, enabled) {
        if (!element) return;
        var classes = (' ' + element.className + ' ').replace(' ' + name + ' ', ' ');
        if (enabled) classes += name + ' ';
        element.className = classes.replace(/^\s+|\s+$/g, '');
    }

    function updatePlayState() {
        var playing = !video.paused && !video.ended;
        setClass(playIcon, 'hidden', playing);
        setClass(pauseIcon, 'hidden', !playing);
    }

    function updateProgress() {
        var duration = video.duration;
        if (!isFinite(duration) || duration <= 0) return;
        var current = Math.max(0, Math.min(duration, video.currentTime || 0));
        seekHandle.max = Math.floor(duration * 1000);
        seekHandle.value = Math.floor(current * 1000);
        seekProgress.max = duration;
        seekProgress.value = current;
    }

    function seekToHandle() {
        if (isFinite(video.duration) && video.duration > 0) {
            video.currentTime = Number(seekHandle.value) / 1000;
        }
    }

    playButton.addEventListener('click', function () {
        if (video.paused || video.ended) {
            var result = video.play();
            if (result && result.catch) result.catch(updatePlayState);
        } else video.pause();
        updatePlayState();
    });
    if (muteButton) muteButton.addEventListener('click', function () {
        video.muted = !video.muted;
        setClass(muteIcon, 'hidden', video.muted);
        setClass(unmuteIcon, 'hidden', !video.muted);
    });
    seekHandle.addEventListener('input', seekToHandle);
    seekHandle.addEventListener('change', seekToHandle);
    video.addEventListener('loadedmetadata', updateProgress);
    video.addEventListener('durationchange', updateProgress);
    video.addEventListener('timeupdate', updateProgress);
    video.addEventListener('play', updatePlayState);
    video.addEventListener('pause', updatePlayState);
    video.addEventListener('ended', updatePlayState);
    video.addEventListener('click', function () { playButton.click(); });

    if (contextMenu) {
        player.addEventListener('contextmenu', function (event) {
            event.preventDefault();
            contextMenu.style.top = event.clientY + 'px';
            contextMenu.style.left = event.clientX + 'px';
            contextMenu.style.display = 'block';
        });
        document.addEventListener('click', function (event) {
            if (event.target !== contextMenu && !contextMenu.contains(event.target)) contextMenu.style.display = 'none';
        });
        document.addEventListener('scroll', function () { contextMenu.style.display = 'none'; });
    }
    if (contextMute) contextMute.addEventListener('click', function () {
        if (muteButton) muteButton.click();
        setClass(muteTick, 'hidden', !video.muted);
        contextMenu.style.display = 'none';
    });
    if (contextLoop) contextLoop.addEventListener('click', function () {
        video.loop = !video.loop;
        setClass(loopTick, 'hidden', !video.loop);
        contextMenu.style.display = 'none';
    });
    if (contextAbout && aboutBox) contextAbout.addEventListener('click', function () {
        contextMenu.style.display = 'none';
        aboutBox.style.display = 'flex';
    });
    if (aboutCloseButton && aboutBox) aboutCloseButton.addEventListener('click', function () { aboutBox.style.display = 'none'; });
    updatePlayState();
    video.load();
})();
