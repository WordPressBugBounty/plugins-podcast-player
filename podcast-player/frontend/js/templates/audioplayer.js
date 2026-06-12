function audioPlayer() {
    return `
    <div class="ppjs__offscreen">Audio Player</div>
    <div id="{{id}}-html5" class="ppjs__container pp-podcast-episode ppjs__audio">
        <div class="ppjs__inner">
            <div class="ppjs__mediaelement"></div>
            <div class="ppjs__controls">
                <div class="ppjs__time ppjs__currenttime-container" role="timer" aria-live="off"><span class="ppjs__currenttime">00:00</span></div>
                <div class="ppjs__time ppjs__duration-container"><span class="ppjs__duration">00:00</span></div>
                <div class="ppjs__audio-time-rail"><input type="range" class="ppjs__seek-slider" max="100" value="0"></div>
            </div>
        </div>
    </div>
    `;
}

function videoPlayer() {
    return `
    <div class="ppjs__offscreen">Video Player</div>
    <div id="{{id}}-html5" class="ppjs__container pp-podcast-episode ppjs__video">
        <div class="ppjs__inner">
            <div class="ppjs__mediaelement"></div>
            <div class="ppjs__layers">
                <div class="ppjs__poster ppjs__layer"></div>
                <div class="ppjs__overlay ppjs__layer ppjs__overlay-play">
                    <div class="ppjs__overlay-button"></div>
                </div>
                <div class="ppjs__overlay ppjs__layer ppjs__overlay-loading-layer" style="display: none;">
                    <div class="ppjs__overlay-loading"></div>
                </div>
            </div>
            <div class="ppjs__controls">
                <div class="ppjs__time ppjs__currenttime-container" role="timer" aria-live="off"><span class="ppjs__currenttime">00:00</span></div>
                <div class="ppjs__time-rail"><input type="range" class="ppjs__seek-slider" max="100" value="0"></div>
                <div class="ppjs__time ppjs__duration-container"><span class="ppjs__duration">00:00</span></div>
                <div class="ppjs__button ppjs__fullscreen-button">
                    <button type="button"><span class="ppjs__offscreen">Fullscreen</span></button>
                </div>
            </div>
        </div>
    </div>
    `;
}

export { audioPlayer, videoPlayer };
