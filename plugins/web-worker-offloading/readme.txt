=== Web Worker Offloading ===

Contributors:      wordpressdotorg
Tested up to:      6.6
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, JavaScript, web worker, partytown

Offload JavaScript execution to a Web Worker.

== Description ==

This plugin offloads JavaScript execution to a Web Worker, improving performance by freeing up the main thread.

In order to opt-in a script to be loaded in a worker, simply add `worker` script data to a registered script. For example,
if you have a script registered with the handle of `foo`, opt-in to offload it to a web worker by doing:

`
wp_script_add_data( 'foo', 'worker', true );
`

== Frequently Asked Questions ==

= Why are my offloaded scripts not working and I see a 404 error in the console for `partytown-sandbox-sw.html`? =

If you find that your offloaded scripts aren't working while also seeing a 404 error in the console for a file at `/wp-content/plugins/web-worker-offloading/build/partytown-sandbox-sw.html?1727389399791` then it's likely you have Chrome DevTools open with the "Bypass for Network" toggle enabled in the Application panel.
