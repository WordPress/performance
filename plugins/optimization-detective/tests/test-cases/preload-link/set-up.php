<?php
return static function (): void {

	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ): void {
			$registry->register(
				'img-preload',
				static function ( OD_Tag_Visitor_Context $context ): bool {
					if ( 'IMG' === $context->processor->get_tag() ) {
						$context->link_collection->add_link(
							array(
								'rel'  => 'preload',
								'as'   => 'image',
								'href' => $context->processor->get_attribute( 'src' ),
							)
						);
					}
					return false;
				}
			);
		}
	);
};
