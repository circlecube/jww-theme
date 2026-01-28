import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/show-stats', {
    edit: ({ attributes, setAttributes }) => {
        const { 
            statType, 
            limit, 
            sortBy 
        } = attributes;
        const blockProps = useBlockProps();

        const statTypeOptions = [
            { label: __('Song Play Counts', 'jww-theme'), value: 'song_plays' },
            { label: __('Venue Statistics', 'jww-theme'), value: 'venue_stats' },
            { label: __('Tour Statistics', 'jww-theme'), value: 'tour_stats' },
            { label: __('Gap Analysis', 'jww-theme'), value: 'gap_analysis' }
        ];

        const sortByOptions = [
            { label: __('By Count', 'jww-theme'), value: 'count' },
            { label: __('By Name', 'jww-theme'), value: 'name' },
            { label: __('By Date', 'jww-theme'), value: 'date' }
        ];

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Statistics Options', 'jww-theme')} initialOpen={true}>
                        <SelectControl
                            label={__('Statistics Type', 'jww-theme')}
                            value={statType}
                            options={statTypeOptions}
                            onChange={(value) => setAttributes({ statType: value })}
                        />

                        <RangeControl
                            label={__('Number of Items', 'jww-theme')}
                            value={limit}
                            onChange={(value) => setAttributes({ limit: value })}
                            min={1}
                            max={50}
                        />

                        <SelectControl
                            label={__('Sort By', 'jww-theme')}
                            value={sortBy}
                            options={sortByOptions}
                            onChange={(value) => setAttributes({ sortBy: value })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="show-stats-preview">
                    <h3>{__('Show Statistics Preview', 'jww-theme')}</h3>
                    <div className="preview-info">
                        <p><strong>{__('Statistics Type:', 'jww-theme')}</strong> {
                            statTypeOptions.find(opt => opt.value === statType)?.label || statType
                        }</p>
                        <p><strong>{__('Items to Display:', 'jww-theme')}</strong> {limit}</p>
                        <p><strong>{__('Sort By:', 'jww-theme')}</strong> {
                            sortByOptions.find(opt => opt.value === sortBy)?.label || sortBy
                        }</p>
                    </div>
                    <p><em>{__('Note: Full statistics will be displayed on the frontend', 'jww-theme')}</em></p>
                </div>
            </div>
        );
    },

    save: () => {
        // Server-side rendering, so return null
        return null;
    }
});
