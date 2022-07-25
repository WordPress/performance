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
		if (ClipboardJS.isSupported()) {
			const clipper = new ClipboardJS(
				'table.upgrades tbody tr img.clip',
				{
					text: function (icon) {
						const row  = ancestor( icon, 3 );
						const item = row.querySelector( 'td.cmd > pre.item' );
						return jQuery( item ).text();
					}
				}
			);
			clipper.on(
				'success',
				function (e) {
					const trigger  = e.trigger;
					const panel    = ancestor( trigger, 6 );
					const template = panel.querySelector( 'div.acknowledgement-template' );
					const ack      = template.firstChild.cloneNode( true );
					ack.style.left = (trigger.x - 2 * trigger.width) + 'px';
					ack.style.top  = (trigger.y - 5) + 'px';
					ack.classList.remove( 'template' );
					ack.classList.remove( 'hidden' );
					ack.classList.add( 'visible' );
					panel.appendChild( ack );

					setTimeout(
						function (ack) {
							ack.remove();
						},
						1500,
						ack
					)
				}
			)
		} else {
			const table = icon.parentElement.parentElement.parentElement.parentElement;
			jQuery( table ).find( 'tr img.clip' ).hide();
		}
	}
);
