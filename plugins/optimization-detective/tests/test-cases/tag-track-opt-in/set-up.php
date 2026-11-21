<?php
return static function (): void {
	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ): void {
			$registry->register(
				'not-tracking-anything-return-false',
				static function (): bool {
					return false;
				}
			);

			$registry->register(
				'not-tracking-anything-return-void',
				static function ( OD_Tag_Visitor_Context $context ): void {}
			);

			$registry->register(
				'track-by-return-true',
				static function ( OD_Tag_Visitor_Context $context ): bool {
					return in_array(
						$context->processor->get_attribute( 'id' ),
						array(
							'tracked-by-return-true',
							'tracked-by-return-value-and-track-tag-method',
						),
						true
					);
				}
			);

			$registry->register(
				'track-by-track-tag-method',
				static function ( OD_Tag_Visitor_Context $context ): void {
					$should_track = in_array(
						$context->processor->get_attribute( 'id' ),
						array(
							'tracked-by-track-tag-method',
							'tracked-by-return-value-and-track-tag-method',
						),
						true
					);
					if ( $should_track ) {
						$context->track_tag();
					}
				}
			);
		}
	);
};
