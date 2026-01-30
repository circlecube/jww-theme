import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';
import './style.scss';

registerBlockType('jww/song-stats-table', {
	edit: () => {
		const blockProps = useBlockProps();
		return (
			<div {...blockProps}>
				<div className="song-stats-table-preview">
					<h3>{__('Song Stats Table', 'jww-theme')}</h3>
					<p>{__('Displays all songs with live performance stats in a sortable table (song name, first published, # played, first/last played with date, location, venue, show link, days since last played).', 'jww-theme')}</p>
					<p><em>{__('Table is rendered on the frontend.', 'jww-theme')}</em></p>
				</div>
			</div>
		);
	},
	save: () => null,
});
