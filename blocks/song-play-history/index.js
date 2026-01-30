import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import './editor.scss';
import './style.scss';

registerBlockType('jww/song-play-history', {
	edit: ({ attributes, setAttributes }) => {
		const { displayMode, songId, limit } = attributes;
		const blockProps = useBlockProps();

		const songs = useSelect((select) => {
			return select('core').getEntityRecords('postType', 'song', { per_page: 50 });
		}, []);

		const songOptions = [
			{ label: __('Current song (if on song page)', 'jww-theme'), value: 0 },
			...(songs ? songs.map((s) => ({ label: s.title?.rendered || s.title, value: s.id })) : []),
		];

		return (
			<div {...blockProps}>
				<InspectorControls>
					<PanelBody title={__('Display Options', 'jww-theme')} initialOpen={true}>
						<SelectControl
							label={__('Song', 'jww-theme')}
							value={songId}
							options={songOptions}
							onChange={(value) => setAttributes({ songId: parseInt(value, 10) })}
						/>
						<SelectControl
							label={__('Display', 'jww-theme')}
							value={displayMode}
							options={[
								{ label: __('List', 'jww-theme'), value: 'list' },
								{ label: __('Table', 'jww-theme'), value: 'table' },
							]}
							onChange={(value) => setAttributes({ displayMode: value })}
						/>
						<RangeControl
							label={__('Limit number of shows', 'jww-theme')}
							value={limit}
							onChange={(value) => setAttributes({ limit: value ?? 0 })}
							min={0}
							max={100}
							help={__('0 = show all', 'jww-theme')}
						/>
					</PanelBody>
				</InspectorControls>
				<div className="song-play-history-preview">
					<h3>{__('Song Play History Preview', 'jww-theme')}</h3>
					<p><strong>{__('Song:', 'jww-theme')}</strong> {songOptions.find((o) => o.value === songId)?.label || __('Current song', 'jww-theme')}</p>
					<p><strong>{__('Display:', 'jww-theme')}</strong> {displayMode === 'table' ? __('Table', 'jww-theme') : __('List', 'jww-theme')}</p>
					{limit > 0 && <p><strong>{__('Limit:', 'jww-theme')}</strong> {limit}</p>}
					<p><em>{__('Content is rendered on the frontend.', 'jww-theme')}</em></p>
				</div>
			</div>
		);
	},
	save: () => null,
});
