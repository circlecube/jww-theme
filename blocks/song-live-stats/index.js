import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/song-live-stats', {
    edit: ({ attributes, setAttributes }) => {
        const { statType, songId } = attributes;
        const blockProps = useBlockProps();

        // Get songs for select control
        const songs = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'song', { per_page: 50 });
        }, []);

        const songOptions = [
            { label: __('Current song (if on song page)', 'jww-theme'), value: 0 }
        ];

        if (songs) {
            songs.forEach((song) => {
                songOptions.push({
                    label: song.title.rendered,
                    value: song.id
                });
            });
        }

        const statTypeOptions = [
            { label: __('Total Times Played', 'jww-theme'), value: 'play_count' },
            { label: __('Last Played', 'jww-theme'), value: 'last_played' },
            { label: __('First Played', 'jww-theme'), value: 'first_played' },
            { label: __('Days Since Last Played', 'jww-theme'), value: 'days_since' }
        ];

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Display Options', 'jww-theme')} initialOpen={true}>
                        <SelectControl
                            label={__('Song', 'jww-theme')}
                            value={songId}
                            options={songOptions}
                            onChange={(value) => setAttributes({ songId: parseInt(value) })}
                        />

                        <SelectControl
                            label={__('Statistic Type', 'jww-theme')}
                            value={statType}
                            options={statTypeOptions}
                            onChange={(value) => setAttributes({ statType: value })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="song-live-stats-preview">
                    <h3>{__('Song Live Statistics Preview', 'jww-theme')}</h3>
                    <div className="preview-info">
                        <p><strong>{__('Song:', 'jww-theme')}</strong> {
                            songOptions.find(opt => opt.value === songId)?.label || __('Current song', 'jww-theme')
                        }</p>
                        <p><strong>{__('Statistic Type:', 'jww-theme')}</strong> {
                            statTypeOptions.find(opt => opt.value === statType)?.label || statType
                        }</p>
                    </div>
                    <p><em>{__('Note: Full statistic will be displayed on the frontend', 'jww-theme')}</em></p>
                </div>
            </div>
        );
    },

    save: () => {
        // Server-side rendering, so return null
        return null;
    }
});
