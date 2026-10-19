import { audioPlayer, videoPlayer } from "../templates/audioplayer";
import { _$ } from "./pplib";

class MediaElem {
    /**
	 * Wrapper Element ID.
	 */
	id = null;

    /**
	 * Wrapper Element.
	 */
	wrapper = null;

    /**
     * Audio Element Object.
     */
    media = null;

    /**
     * Controls Element.
     */
    controls = null;

    /**
     * Media container element.
     */
    container = null;

    /**
     * Video layers element.
     */
    layers = null;

    /**
     * hls.js instance for HLS playback.
     */
    hls = null;

    /**
     * Active media source URL.
     */
    sourceUrl = '';

    /**
     * Promise that resolves when the current source is attached.
     */
    sourceReady = Promise.resolve();

    /**
     * Whether the current media element is video.
     */
    isVideo = false;

    /**
     * Static property to keep track of the currently playing instance.
     */
    static currentlyPlayingInstance = null;

    /**
     * Shared hls.js loading promise.
     */
    static hlsLoading = null;

    /**
     * Create custom media element for audio/video files.
     *
     * @since 6.7.0
     */
    constructor(id) {
        this.id = id;
        this.media = document.querySelector(`#${id}`);
        if (!this.media || !this.isValidMediaElement(this.media)) return false;
        
        this.wrapper = this.media.parentElement;
        this.isVideo = this.media.tagName === 'VIDEO';
        this.createPlayerMarkup();
        this.attachEvents();
    }

    isValidMediaElement(mediaElement) {
        if (mediaElement.tagName === 'AUDIO' || mediaElement.tagName === 'VIDEO') return true;
        if (mediaElement.tagName === 'MEDIAELEMENTWRAPPER') {
            this.removeMeJs();
            this.media = document.querySelector(`#${this.id}`);
            return !!this.media && (this.media.tagName === 'AUDIO' || this.media.tagName === 'VIDEO');
        }
        return false;
    }

    attachEvents() {
        const seekSlider = this.controls.querySelector('.ppjs__seek-slider');
        const currentTimeContainer = this.controls.querySelector('.ppjs__currenttime');
        const fullscreenButton = this.controls.querySelector('.ppjs__fullscreen-button > button');
        if (!seekSlider || !currentTimeContainer) return;

        const updateCurrentTime = () => {
            if (this.media.readyState > 0) {
                currentTimeContainer.textContent = this.formatTime(seekSlider.value);
            }
        };
        const seekToTime = () => {
            if (this.media.readyState > 0) {
                this.media.currentTime = seekSlider.value;
            }
        };
        const setCurrentlyPlayingInstance = () => {
            if (MediaElem.currentlyPlayingInstance && MediaElem.currentlyPlayingInstance !== this) {
                MediaElem.currentlyPlayingInstance.media.pause();
            }
            MediaElem.currentlyPlayingInstance = this;
        };
        const updateSlider = () => {
            const { duration, currentTime, buffered } = this.media;
            if (!duration || isNaN(duration)) return;
            const percentPlayed = (currentTime / duration) * 100;

            seekSlider.value = Math.floor(currentTime);
            currentTimeContainer.textContent = this.formatTime(currentTime);
            seekSlider.style.setProperty('--pp-progress-value', `${percentPlayed}%`);

            if (duration > 0 && buffered.length) {
                const percentBuffered = (buffered.end(buffered.length - 1) / duration) * 100;
                seekSlider.style.setProperty('--buffered-width', `${percentBuffered}%`);
            }
        };
        const showLoading = () => this.toggleLoading(true);
        const hideLoading = () => this.toggleLoading(false);

        if (this.media.readyState > 0) {
            this.displayDuration();
            this.setSliderMax();
        } else {
            this.media.addEventListener('loadedmetadata', () => {
                this.displayDuration();
                this.setSliderMax();
            });
        }

        seekSlider.addEventListener('input', updateCurrentTime);
        seekSlider.addEventListener('change', seekToTime);
        this.media.addEventListener('play', setCurrentlyPlayingInstance);
        this.media.addEventListener('timeupdate', updateSlider);
        this.media.addEventListener('loadedmetadata', hideLoading);
        this.media.addEventListener('canplay', hideLoading);
        this.media.addEventListener('waiting', showLoading);

        if (this.isVideo) {
            this.media.addEventListener('play', () => this.updateVideoLayers());
            this.media.addEventListener('pause', () => this.updateVideoLayers());
            this.media.addEventListener('ended', () => this.updateVideoLayers());
            this.media.addEventListener('timeupdate', () => this.updateVideoLayers());
            this.fullscreenListener = () => this.updateFullscreenState();
            document.addEventListener('fullscreenchange', this.fullscreenListener);
            document.addEventListener('webkitfullscreenchange', this.fullscreenListener);
            if (fullscreenButton) {
                fullscreenButton.addEventListener('click', this.toggleFullscreen.bind(this));
            }
            this.updateVideoLayers();
        }
    }

    /**
     * Create player markup.
     */
    createPlayerMarkup() {
        const markup = _$.template( this.isVideo ? videoPlayer() : audioPlayer(), { id: this.id } );
        this.wrapper.insertAdjacentHTML('beforeend', markup);
        this.container = this.wrapper.querySelector(`#${this.id}-html5`);
        this.layers = this.wrapper.querySelector('.ppjs__layers');
        const mediaContainer = this.wrapper.querySelector(`.ppjs__mediaelement`);
        this.media.controls = false;
        mediaContainer.appendChild(this.media);
        this.controls = this.wrapper.querySelector('.ppjs__controls');

        const initialSource = this.getSourceElementUrl();
        const initialType = this.getSourceElementType();
        if (initialSource) {
            this.setSrc(initialSource, { mimeType: initialType });
        }

        if (this.isVideo) {
            this.setAspectRatio();
            this.setPoster(this.media.getAttribute('poster'));
        }
    }

    /**
     * Set the source URL for the audio element.
     * @param {string} url - The URL of the audio file.
     */
    setSrc(url, options = {}) {
        this.destroyHls();
        this.clearSourceElements();
        this.sourceUrl = url;

        const mimeType = options.mimeType || '';
        if (this.isHlsSource(url, mimeType) && !this.canPlayHlsNatively(mimeType)) {
            this.sourceReady = this.loadHlsLibrary().then(() => {
                if (url !== this.sourceUrl) return;

                if (window.Hls && window.Hls.isSupported()) {
                    this.attachHlsSource(url);
                } else {
                    this.media.src = url;
                    this.media.load();
                }
            }).catch(() => {
                if (url !== this.sourceUrl) return;

                this.media.src = url;
                this.media.load();
            });
        } else {
            this.media.src = url;
            this.sourceReady = Promise.resolve();
        }

        this.resetControls();
        this.updateVideoLayers();
    }

    /**
     * Get media src.
     */
    getSrc() {
        return this.sourceUrl || this.media.src;
    }

    /**
     * Load the audio.
     */
    load() {
        if (this.hls) return this.sourceReady;

        return this.sourceReady.then(() => {
            if (!this.hls) this.media.load();
        });
    }

    /**
     * Play current media after pending source setup.
     */
    play() {
        return this.sourceReady.then(() => this.media.play());
    }

    /**
     * Attach an HLS source using hls.js.
     */
    attachHlsSource(url) {
        this.hls = new window.Hls({
            capLevelToPlayerSize: this.isVideo,
            enableWorker: true
        });
        this.hls.attachMedia(this.media);
        this.hls.loadSource(url);
        this.attachHlsErrorHandler();
    }

    /**
     * Load hls.js only when a non-native HLS source needs it.
     */
    loadHlsLibrary() {
        if (window.Hls) return Promise.resolve();
        if (MediaElem.hlsLoading) return MediaElem.hlsLoading;

        const settings = window.ppmejsSettings || {};
        const scriptUrl = settings.hlsSrc || '';

        if (!scriptUrl) return Promise.reject(new Error('HLS script URL is not available.'));

        MediaElem.hlsLoading = new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-podcast-player-hls]');
            if (existing) {
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', reject);
                return;
            }

            const script = document.createElement('script');
            script.src = scriptUrl;
            script.async = true;
            script.dataset.podcastPlayerHls = '1';
            script.onload = () => resolve();
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return MediaElem.hlsLoading;
    }

    /**
     * Get initial source child URL.
     */
    getSourceElementUrl() {
        const source = this.media.querySelector('source');
        return source ? source.getAttribute('src') : this.media.getAttribute('src');
    }

    /**
     * Get initial source child MIME type.
     */
    getSourceElementType() {
        const source = this.media.querySelector('source');
        return source ? source.getAttribute('type') : '';
    }

    /**
     * Remove source children after assigning active source.
     */
    clearSourceElements() {
        this.media.querySelectorAll('source').forEach((source) => source.remove());
        this.media.removeAttribute('src');
        this.media.load();
    }

    /**
     * Check whether a source is HLS.
     */
    isHlsSource(url, mimeType = '') {
        const type = String(mimeType || '').toLowerCase();
        const hlsTypes = [
            'application/x-mpegurl',
            'application/vnd.apple.mpegurl',
            'audio/x-mpegurl',
            'audio/mpegurl',
            'video/x-mpegurl',
            'video/mpegurl'
        ];

        if (hlsTypes.includes(type)) return true;

        const path = String(url || '').split('?')[0].toLowerCase();
        return path.endsWith('.m3u8');
    }

    /**
     * Check native browser HLS support.
     */
    canPlayHlsNatively(mimeType = '') {
        const types = [
            mimeType,
            'application/vnd.apple.mpegurl',
            'application/x-mpegurl'
        ].filter(Boolean);

        return types.some((type) => '' !== this.media.canPlayType(type));
    }

    /**
     * Add hls.js error recovery.
     */
    attachHlsErrorHandler() {
        if (!this.hls || !window.Hls || !window.Hls.Events) return;

        this.hls.on(window.Hls.Events.ERROR, (event, data) => {
            if (!data || !data.fatal) return;

            if (data.type === window.Hls.ErrorTypes.MEDIA_ERROR) {
                this.hls.recoverMediaError();
            } else {
                this.destroyHls();
            }
        });
    }

    /**
     * Destroy active hls.js instance.
     */
    destroyHls() {
        if (this.hls && 'function' === typeof this.hls.destroy) {
            this.hls.destroy();
        }
        this.hls = null;
    }

    /**
     * Set video poster image.
     *
     * @param {string} url Poster URL.
     */
    setPoster(url) {
        if (!this.isVideo) return;

        const poster = url || '';
        const posterContainer = this.layers ? this.layers.querySelector('.ppjs__poster') : false;

        if (poster) {
            this.media.setAttribute('poster', poster);
            if (posterContainer) {
                posterContainer.innerHTML = '';
                const image = document.createElement('img');
                image.src = poster;
                image.alt = '';
                posterContainer.appendChild(image);
            }
        } else {
            this.media.removeAttribute('poster');
            if (posterContainer) posterContainer.innerHTML = '';
        }

        this.updateVideoLayers();
    }

    formatTime(secs) {
        if (!secs || isNaN(secs)) return '00:00';
        const hours = Math.floor(secs / 3600);
        const minutes = Math.floor((secs % 3600) / 60);
        const seconds = Math.floor(secs % 60);

        return (hours ? `${hours}:` : '') + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    };

    displayDuration() {
        const durationContainer = this.controls.querySelector('.ppjs__duration');
        if (durationContainer) {
            durationContainer.textContent = this.formatTime(this.media.duration);
        }
    };
    
    setSliderMax() {
        const secs = this.media.duration;
        if (isNaN(secs)) return;
        const seekSlider = this.controls.querySelector('.ppjs__seek-slider');
        seekSlider.max = Math.floor(secs);
    }

    resetControls() {
        if (!this.controls) return;

        const seekSlider = this.controls.querySelector('.ppjs__seek-slider');
        const currentTimeContainer = this.controls.querySelector('.ppjs__currenttime');
        const durationContainer = this.controls.querySelector('.ppjs__duration');

        if (seekSlider) {
            seekSlider.value = 0;
            seekSlider.style.setProperty('--pp-progress-value', '0%');
            seekSlider.style.setProperty('--buffered-width', '0%');
        }
        if (currentTimeContainer) {
            currentTimeContainer.textContent = '00:00';
        }
        if (durationContainer) {
            durationContainer.textContent = '00:00';
        }
    }

    setAspectRatio() {
        if (!this.container) return;

        const width = parseInt(this.media.getAttribute('width'), 10);
        const height = parseInt(this.media.getAttribute('height'), 10);
        if (width > 0 && height > 0) {
            this.container.style.aspectRatio = `${width} / ${height}`;
        }
    }

    toggleLoading(show) {
        if (!this.layers) return;

        const loading = this.layers.querySelector('.ppjs__overlay-loading-layer');
        if (loading) {
            loading.style.display = show ? '' : 'none';
        }
    }

    updateVideoLayers() {
        if (!this.isVideo || !this.layers) return;

        const showPoster = this.media.paused && (0 === this.media.currentTime || this.media.ended || 0 === this.media.readyState);
        const poster = this.layers.querySelector('.ppjs__poster');
        const playOverlay = this.layers.querySelector('.ppjs__overlay-play');
        const playButton = this.layers.querySelector('.ppjs__overlay-button');

        this.layers.style.display = '';

        if (poster) {
            poster.style.display = showPoster && poster.children.length ? '' : 'none';
        }
        if (playOverlay) {
            playOverlay.style.backgroundColor = this.media.paused ? 'rgba(0, 0, 0, 0.2)' : 'transparent';
        }
        if (playButton) {
            playButton.style.display = this.media.paused ? '' : 'none';
        }
    }

    toggleFullscreen() {
        if (!this.container) return;

        if (this.isFullscreen()) {
            this.exitFullscreen();
        } else {
            this.enterFullscreen();
        }
    }

    enterFullscreen() {
        const elem = this.container;
        const request = elem.requestFullscreen || elem.webkitRequestFullscreen || elem.mozRequestFullScreen || elem.msRequestFullscreen;

        if (request) {
            const result = request.call(elem);
            if (result && result.catch) result.catch(() => {});
        } else if (this.media.webkitEnterFullscreen) {
            this.media.webkitEnterFullscreen();
        }
    }

    exitFullscreen() {
        const exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
        if (exit) {
            const result = exit.call(document);
            if (result && result.catch) result.catch(() => {});
        }
    }

    isFullscreen() {
        const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
        return fullscreenElement === this.container;
    }

    updateFullscreenState() {
        if (!this.container || !this.controls) return;

        const fullscreen = this.isFullscreen();
        const button = this.controls.querySelector('.ppjs__fullscreen-button');
        this.container.classList.toggle('ppjs__container-fullscreen', fullscreen);
        if (button) {
            button.classList.toggle('ppjs__unfullscreen', fullscreen);
        }
    }

    destroy() {
        if (this.media) this.media.pause();
        this.destroyHls();
        if (this.fullscreenListener) {
            document.removeEventListener('fullscreenchange', this.fullscreenListener);
            document.removeEventListener('webkitfullscreenchange', this.fullscreenListener);
        }
        if (MediaElem.currentlyPlayingInstance === this) MediaElem.currentlyPlayingInstance = null;
    }

    removeMeJs() {
        const mejs = this.media.closest('.mejs__container');
        if (mejs && mejs.id && window.mejs?.players?.[mejs.id]?.remove) {
            window.mejs.players[mejs.id].remove();
        }
    }
}
export default MediaElem;
