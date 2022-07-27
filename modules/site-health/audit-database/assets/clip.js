/* eslint-disable */
/**
 * TODO handles clipboard for wpcli commands
 *
 * @since 1.0.4
 * @output wp-content/plugins/performance-lab/TODO
 *
 * @namespace performanceLabSiteHealthAuditDatabaseClip
 *
 * @requires jQuery, ClipboardJs
 */
jQuery( document ).ready(
	function () {
		function ancestor( element, count ) {
			for (let i = 0; i < count; i++) {
				element = element.parentElement;
			}
			return element;
		}
		function acknowledge (e) {
			const trigger = e.trigger;
			trigger.classList.remove( trigger.dataset.normal );
			trigger.classList.add( trigger.dataset.ack );
			setTimeout(
				function (trigger) {
					trigger.classList.remove( trigger.dataset.ack );
					trigger.classList.add( trigger.dataset.normal );
				},
				1500,
				trigger
			)
		}
		if (ClipboardJS.isSupported()) {
			const clipper = new ClipboardJS(
				'table.upgrades tbody tr td.icon div.clip',
				{
					text: function (icon) {
						const row  = ancestor( icon, 2 );
						const item = row.querySelector( 'td.cmd > pre.item' );
						return jQuery( item ).text();
					}
				}
			);
			clipper.on(
				'success',
				function (e) {
					acknowledge( e );
				}
			);
			const clip_all = new ClipboardJS(
				'table.upgrades thead tr img.clip',
				{
					text: function (icon) {
						const row   = ancestor( icon, 5 );
						const items = row.querySelectorAll( 'tbody> tr > td.cmd > pre.item' );
						const texts = [];
						for (const item of items) {
							texts.push( jQuery( item ).text() );
						}
						return texts.join( '\n' );
					}
				}
			);
			clip_all.on(
				'success',
				function (e) {
					acknowledge( e );
				}
			);
		} else {
			const table = icon.parentElement.parentElement.parentElement.parentElement;
			jQuery( table ).find( 'tr img.clip' ).hide();
		}
	}
);
