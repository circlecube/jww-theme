import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

import './editor.scss';
import './style.scss';

registerBlockType('jww/latest-shows', {
	edit: () => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<div className="latest-shows-editor-preview">
					<p><strong>{__('Latest Shows', 'jww-theme')}</strong></p>
					<p>{__('The 5 most recent shows will be listed here with links. Content is rendered on the frontend.', 'jww-theme')}</p>
				</div>
			</div>
		);
	},
	save: () => null,
});
