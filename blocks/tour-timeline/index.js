import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/tour-timeline', {
    edit: ({ attributes, setAttributes }) => {
        const { 
            tourId,
            showPastOnly,
            showUpcomingOnly
        } = attributes;
        const blockProps = useBlockProps();

        // Get tours for select control
        const tours = useSelect((select) => {
            return select('core').getEntityRecords('taxonomy', 'tour', { per_page: -1 });
        }, []);

        const tourOptions = [
            { label: __('Select a tour...', 'jww-theme'), value: 0 }
        ];

        if (tours) {
            tours.forEach((tour) => {
                tourOptions.push({
                    label: tour.name,
                    value: tour.id
                });
            });
        }

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Tour Options', 'jww-theme')} initialOpen={true}>
                        <SelectControl
                            label={__('Tour', 'jww-theme')}
                            value={tourId}
                            options={tourOptions}
                            onChange={(value) => setAttributes({ tourId: parseInt(value) })}
                        />

                        <ToggleControl
                            label={__('Show Past Shows Only', 'jww-theme')}
                            checked={showPastOnly}
                            onChange={(value) => {
                                setAttributes({ 
                                    showPastOnly: value,
                                    showUpcomingOnly: value ? false : showUpcomingOnly
                                });
                            }}
                        />

                        <ToggleControl
                            label={__('Show Upcoming Shows Only', 'jww-theme')}
                            checked={showUpcomingOnly}
                            onChange={(value) => {
                                setAttributes({ 
                                    showUpcomingOnly: value,
                                    showPastOnly: value ? false : showPastOnly
                                });
                            }}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="tour-timeline-preview">
                    <h3>{__('Tour Timeline Preview', 'jww-theme')}</h3>
                    <div className="preview-info">
                        <p><strong>{__('Tour:', 'jww-theme')}</strong> {
                            tourOptions.find(opt => opt.value === tourId)?.label || __('Not selected', 'jww-theme')
                        }</p>
                        <p><strong>{__('Show Past Only:', 'jww-theme')}</strong> {showPastOnly ? __('Yes', 'jww-theme') : __('No', 'jww-theme')}</p>
                        <p><strong>{__('Show Upcoming Only:', 'jww-theme')}</strong> {showUpcomingOnly ? __('Yes', 'jww-theme') : __('No', 'jww-theme')}</p>
                    </div>
                    <p><em>{__('Note: Full timeline will be displayed on the frontend', 'jww-theme')}</em></p>
                </div>
            </div>
        );
    },

    save: () => {
        // Server-side rendering, so return null
        return null;
    }
});
