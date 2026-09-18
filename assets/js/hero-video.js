/* ====================================================================
   assets/js/hero-video.js  —  hero clips play only when visible

   The clips ship with preload="none" and no autoplay. This decides:
     - whether to load them at all (media query, connection, motion pref)
     - WHEN to play them (on screen) and when to pause (scrolled past)

   The pause half matters more than it looks. A clip left running in a
   panel the reader scrolled past three sections ago is decoding every
   frame for an empty room — CPU on a laptop, battery on a phone.
   ==================================================================== */
(function () {
  'use strict';

  var videos = document.querySelectorAll('video[data-hero-video]');
  if (!videos.length) return;

  /* Would any <source> actually match right now? A source with no media
     attribute always matches. If none match — a narrow screen — we must
     not touch the element at all: calling load() with no usable source
     puts the video into an error state and the browser paints black
     instead of showing the poster. */
  function hasMatchingSource(video) {
    var sources = video.querySelectorAll('source');
    if (!sources.length) return false;

    for (var i = 0; i < sources.length; i++) {
      var m = sources[i].getAttribute('media');
      if (!m) return true;
      if (window.matchMedia(m).matches) return true;
    }
    return false;
  }

  /* navigator.connection is Chrome and Edge only. Where it does not
     exist we assume the connection is fine — Safari users are not all
     on 2G. */
  function connectionIsPoor() {
    var c = navigator.connection ||
            navigator.mozConnection ||
            navigator.webkitConnection;
    if (!c) return false;
    if (c.saveData) return true;
    /* '3g' used to be on this list. Chrome reports 3g for plenty of
       ordinary mobile data connections, so it was freezing the hero on
       phones that could play the small bg-mobile.mp4 fine. Only 2G and
       Data Saver count as poor now. */
    return c.effectiveType === 'slow-2g' ||
           c.effectiveType === '2g';
  }

  var reduceMotion = window.matchMedia &&
                     window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (reduceMotion || connectionIsPoor()) return;   /* posters stay */

  function play(video) {
    if (!hasMatchingSource(video)) return;

    /* First time only: attach the source and fetch it. After that the
       file is in the browser cache and play() alone is enough. */
    if (!video.dataset.heroStarted) {
      video.dataset.heroStarted = '1';
      video.load();
    }

    var p = video.play();
    if (p && p.catch) p.catch(function () {
      /* Autoplay refused. The poster is still up; nothing to do. */
    });
  }

  function pause(video) {
    /* Never pause something that was never ours to start. */
    if (!video.dataset.heroStarted || video.paused) return;
    video.pause();
  }

  if (!('IntersectionObserver' in window)) {
    /* Old browser: play and leave them. Rare enough not to optimise for. */
    Array.prototype.forEach.call(videos, play);
    return;
  }

  /* NOTE: no unobserve. The observer stays live for the whole session so
     every scroll past resumes and every scroll away pauses. */
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) play(entry.target);
      else pause(entry.target);
    });
  }, { threshold: 0.15 });

  Array.prototype.forEach.call(videos, function (v) { io.observe(v); });

  /* A hidden tab is as invisible as a scrolled-past panel. Browsers
     throttle background tabs but do not reliably stop video decoding,
     so say it explicitly. */
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) return;
    Array.prototype.forEach.call(videos, pause);
  });
})();