import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

import './editor.scss';
import './style.scss';

registerBlockType( 'jww/upcoming-shows', {
	edit: () => {
		const blockProps = useBlockProps();
		return (
			<div { ...blockProps }>
				<div className="upcoming-shows-editor-preview">
					<p><strong>{ __( 'Upcoming Shows', 'jww-theme' ) }</strong></p>
					<p>{ __( 'The 5 next upcoming shows will be listed here with links. Content is rendered on the frontend.', 'jww-theme' ) }</p>
				</div>
			</div>
		);
	},
	save: () => null,
} );
