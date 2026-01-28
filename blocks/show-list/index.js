import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/show-list', {
    edit: ({ attributes, setAttributes }) => {
        const { 
            filterType, 
            tourId, 
            locationId, 
            showCount, 
            showSetlist, 
            showVenue, 
            showDate, 
            layout 
        } = attributes;
        const blockProps = useBlockProps();

        // Get available tours
        const availableTours = useSelect((select) => {
            return select('core').getEntityRecords('taxonomy', 'tour', {
                per_page: -1,
                orderby: 'name',
                order: 'asc'
            });
        }, []);

        // Get available locations
        const availableLocations = useSelect((select) => {
            return select('core').getEntityRecords('taxonomy', 'location', {
                per_page: -1,
                orderby: 'name',
                order: 'asc'
            });
        }, []);

        // Get shows for preview
        const shows = useSelect((select) => {
            const queryArgs = {
                per_page: showCount || 5,
                post_type: 'show',
                orderby: 'date',
                order: filterType === 'upcoming' ? 'asc' : 'desc'
            };

            return select('core').getEntityRecords('postType', 'show', queryArgs);
        }, [filterType, showCount]);

        const filterTypeOptions = [
            { label: __('All Shows', 'jww-theme'), value: 'all' },
            { label: __('Upcoming', 'jww-theme'), value: 'upcoming' },
            { label: __('Past Shows', 'jww-theme'), value: 'past' }
        ];

        const layoutOptions = [
            { label: __('List', 'jww-theme'), value: 'list' },
            { label: __('Grid', 'jww-theme'), value: 'grid' }
        ];

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Show List Options', 'jww-theme')} initialOpen={true}>
                        <SelectControl
                            label={__('Filter Type', 'jww-theme')}
                            value={filterType}
                            options={filterTypeOptions}
                            onChange={(value) => setAttributes({ filterType: value })}
                        />

                        <SelectControl
                            label={__('Tour', 'jww-theme')}
                            value={tourId || ''}
                            options={[
                                { label: __('All Tours', 'jww-theme'), value: '' },
                                ...(availableTours ? availableTours.map((tour) => ({
                                    label: tour.name,
                                    value: tour.id.toString()
                                })) : [])
                            ]}
                            onChange={(value) => setAttributes({ tourId: value || '' })}
                        />

                        <SelectControl
                            label={__('Location', 'jww-theme')}
                            value={locationId || ''}
                            options={[
                                { label: __('All Locations', 'jww-theme'), value: '' },
                                ...(availableLocations ? availableLocations.map((location) => ({
                                    label: location.name,
                                    value: location.id.toString()
                                })) : [])
                            ]}
                            onChange={(value) => setAttributes({ locationId: value || '' })}
                        />

                        <RangeControl
                            label={__('Number of Shows', 'jww-theme')}
                            value={showCount}
                            onChange={(value) => setAttributes({ showCount: value })}
                            min={1}
                            max={50}
                        />

                        <SelectControl
                            label={__('Layout', 'jww-theme')}
                            value={layout}
                            options={layoutOptions}
                            onChange={(value) => setAttributes({ layout: value })}
                        />

                        <ToggleControl
                            label={__('Show Date', 'jww-theme')}
                            checked={showDate}
                            onChange={(value) => setAttributes({ showDate: value })}
                        />

                        <ToggleControl
                            label={__('Show Venue', 'jww-theme')}
                            checked={showVenue}
                            onChange={(value) => setAttributes({ showVenue: value })}
                        />

                        <ToggleControl
                            label={__('Show Setlist Count', 'jww-theme')}
                            checked={showSetlist}
                            onChange={(value) => setAttributes({ showSetlist: value })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="show-list-preview">
                    <h3>{__('Show List Preview', 'jww-theme')}</h3>
                    <div className="preview-info">
                        <p><strong>{__('Filter Type:', 'jww-theme')}</strong> {
                            filterTypeOptions.find(opt => opt.value === filterType)?.label || filterType
                        }</p>
                        <p><strong>{__('Shows to Display:', 'jww-theme')}</strong> {showCount}</p>
                        <p><strong>{__('Layout:', 'jww-theme')}</strong> {
                            layoutOptions.find(opt => opt.value === layout)?.label || layout
                        }</p>
                    </div>
                    {shows && shows.length > 0 ? (
                        <p><em>{__('Note: Full list will be displayed on the frontend', 'jww-theme')}</em></p>
                    ) : (
                        <p>{__('No shows found', 'jww-theme')}</p>
                    )}
                </div>
            </div>
        );
    },

    save: () => {
        // Server-side rendering, so return null
        return null;
    }
});
