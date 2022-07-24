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
		if (ClipboardJS.isSupported()) {
			const clipper = new ClipboardJS(
				'table.upgrades tbody tr img.clip',
				{
					text: function (icon) {
						const row  = icon.parentElement.parentElement.parentElement;
						const item = row.querySelector( 'td.cmd > pre.item' );
						return jQuery( item ).text();
					}
				}
			);
			clipper.on(
				'success',
				function (e) {
					const template = document.getElementById( 'acknowledgement_template' );
					const ack      = template.firstChild.cloneNode( true );
					ack.classList.remove( 'template' );
					ack.classList.remove( 'hidden' );
					ack.classList.add( 'visible' );
					e.trigger.parentElement.appendChild( ack );
				}
			)
		} else {
			const table = icon.parentElement.parentElement.parentElement.parentElement;
			jQuery( table ).find( 'tr img.clip' ).hide();
		}
	}
);
