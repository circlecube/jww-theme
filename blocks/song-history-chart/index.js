import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/song-history-chart', {
    edit: ({ attributes, setAttributes }) => {
        const { 
            songId,
            limit,
            chartType
        } = attributes;
        const blockProps = useBlockProps();

        // Get songs for select control
        const songs = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'song', { per_page: 20 });
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

        const chartTypeOptions = [
            { label: __('List', 'jww-theme'), value: 'list' },
            { label: __('Timeline', 'jww-theme'), value: 'timeline' }
        ];

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Chart Options', 'jww-theme')} initialOpen={true}>
                        <SelectControl
                            label={__('Song', 'jww-theme')}
                            value={songId}
                            options={songOptions}
                            onChange={(value) => setAttributes({ songId: parseInt(value) })}
                        />

                        <SelectControl
                            label={__('Chart Type', 'jww-theme')}
                            value={chartType}
                            options={chartTypeOptions}
                            onChange={(value) => setAttributes({ chartType: value })}
                        />

                        <RangeControl
                            label={__('Number of Plays to Show', 'jww-theme')}
                            value={limit}
                            onChange={(value) => setAttributes({ limit: value })}
                            min={10}
                            max={100}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="song-history-chart-preview">
                    <h3>{__('Song History Chart Preview', 'jww-theme')}</h3>
                    <div className="preview-info">
                        <p><strong>{__('Song:', 'jww-theme')}</strong> {
                            songOptions.find(opt => opt.value === songId)?.label || __('Current song', 'jww-theme')
                        }</p>
                        <p><strong>{__('Chart Type:', 'jww-theme')}</strong> {
                            chartTypeOptions.find(opt => opt.value === chartType)?.label || chartType
                        }</p>
                        <p><strong>{__('Limit:', 'jww-theme')}</strong> {limit}</p>
                    </div>
                    <p><em>{__('Note: Full chart will be displayed on the frontend', 'jww-theme')}</em></p>
                </div>
            </div>
        );
    },

    save: () => {
        // Server-side rendering, so return null
        return null;
    }
});
