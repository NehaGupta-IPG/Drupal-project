/**
 * @file
 * Adds the 'js' class to the html element before the first paint.
 *
 * This file is attached to the header so it runs while the page is still
 * blank: CSS that styles markup a behavior will transform later (for example,
 * hiding it until the behavior has run) then applies before any content can
 * paint, preventing a flash of untransformed content. When JavaScript is
 * disabled this file never runs, such CSS never applies, and the
 * untransformed markup stays visible and usable.
 */
document.documentElement.classList.add('js');
