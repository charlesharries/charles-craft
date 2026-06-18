function VideoPlayer() {
    const videos = document.querySelectorAll('.content video');

    const icons = {
        play: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd" /></svg>',
        pause: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 0 1 .75-.75H9a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H7.5a.75.75 0 0 1-.75-.75V5.25Zm7.5 0A.75.75 0 0 1 15 4.5h1.5a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H15a.75.75 0 0 1-.75-.75V5.25Z" clip-rule="evenodd" /></svg>',
        mute: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 0 0 1.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06ZM17.78 9.22a.75.75 0 1 0-1.06 1.06L18.44 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06l1.72-1.72 1.72 1.72a.75.75 0 1 0 1.06-1.06L20.56 12l1.72-1.72a.75.75 0 1 0-1.06-1.06l-1.72 1.72-1.72-1.72Z" /></svg>',
        unmute: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 0 0 1.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06ZM18.584 5.106a.75.75 0 0 1 1.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 0 1-1.06-1.06 8.25 8.25 0 0 0 0-11.668.75.75 0 0 1 0-1.06Z" /><path d="M15.932 7.757a.75.75 0 0 1 1.061 0 6 6 0 0 1 0 8.486.75.75 0 0 1-1.06-1.061 4.5 4.5 0 0 0 0-6.364.75.75 0 0 1 0-1.06Z" /></svg>',
        restart: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M4.755 10.059a7.5 7.5 0 0 1 12.548-3.364l1.903 1.903h-3.183a.75.75 0 1 0 0 1.5h4.992a.75.75 0 0 0 .75-.75V4.356a.75.75 0 0 0-1.5 0v3.18l-1.9-1.9A9 9 0 0 0 3.306 9.67a.75.75 0 1 0 1.45.388Zm15.408 3.352a.75.75 0 0 0-.919.53 7.5 7.5 0 0 1-12.548 3.364l-1.902-1.903h3.183a.75.75 0 0 0 0-1.5H2.984a.75.75 0 0 0-.75.75v4.992a.75.75 0 0 0 1.5 0v-3.18l1.9 1.9a9 9 0 0 0 15.059-4.035.75.75 0 0 0-.53-.918Z" clip-rule="evenodd" /></svg>'
    };

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) return '–';
        seconds = Math.ceil(seconds);
        const m = String(Math.floor(seconds / 60)).padStart(1, '0');
        const s = String(seconds % 60).padStart(2, '0');
        return `${m}:${s}`;
    }

    function initVideo(video) {
        video.removeAttribute('controls');

        const wrapper = document.createElement('div');
        wrapper.className = 'video-player';
        video.parentNode.insertBefore(wrapper, video);
        wrapper.appendChild(video);

        const controls = document.createElement('div');
        controls.className = 'video-controls';
        controls.innerHTML = `
            <div class="video-controls_left">
                <button class="video-play" aria-label="Play">${icons.play}</button>
                <span class="video-time">–</span>
            </div>
            <div class="video-controls_right">
                <button class="video-mute" aria-label="Mute">${icons.unmute}</button>
            </div>
        `;
        wrapper.appendChild(controls);

        const playBtn = controls.querySelector('.video-play');
        const timeEl = controls.querySelector('.video-time');
        const muteBtn = controls.querySelector('.video-mute');

        function updatePlayIcon() {
            const ended = video.ended;
            playBtn.innerHTML = ended ? icons.restart : (video.paused ? icons.play : icons.pause);
            playBtn.setAttribute('aria-label', ended ? 'Restart' : (video.paused ? 'Play' : 'Pause'));
        }
        
        // Confusingly, the mute/unmute should show the current state, as opposed to
        // the play/pause, which does the opposite.
        function updateMuteIcon() {
            muteBtn.innerHTML = video.muted ? icons.mute : icons.unmute;
            muteBtn.setAttribute('aria-label', video.muted ? 'Unmute' : 'Mute');
        }

        function updateTime() {
            timeEl.textContent = formatTime(video.duration - video.currentTime);
        }

        function togglePlay() {
            if (video.ended) {
                video.currentTime = 0;
                video.play();
            } else {
                video.paused ? video.play() : video.pause();
            }
        }

        playBtn.addEventListener('click', togglePlay);
        video.addEventListener('click', togglePlay);

        muteBtn.addEventListener('click', () => {
            video.muted = !video.muted;
            updateMuteIcon();
        });

        video.addEventListener('play', updatePlayIcon);
        video.addEventListener('pause', updatePlayIcon);
        video.addEventListener('ended', updatePlayIcon);
        video.addEventListener('timeupdate', updateTime);
        video.addEventListener('loadedmetadata', updateTime);

        updatePlayIcon();
        updateMuteIcon();
    }

    videos.forEach(initVideo);
}

VideoPlayer();
