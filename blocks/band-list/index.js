import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/band-list', {
    edit: ({ attributes, setAttributes }) => {
        const { showTitle, title, titleLevel, showDates } = attributes;
        const blockProps = useBlockProps();

        // Get bands for preview
        const bands = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'band', {
                per_page: 10,
                orderby: 'date',
                order: 'asc'
            });
        }, []);

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Title Options', 'jww-theme')} initialOpen={true}>
                        <ToggleControl
                            label={__('Show Title', 'jww-theme')}
                            checked={showTitle}
                            onChange={(value) => setAttributes({ showTitle: value })}
                        />

                        {showTitle && (
                            <>
                                <TextControl
                                    label={__('Title Text', 'jww-theme')}
                                    value={title}
                                    onChange={(value) => setAttributes({ title: value })}
                                    help={__('Leave empty to use default title', 'jww-theme')}
                                />

                                <RangeControl
                                    label={__('Title Level', 'jww-theme')}
                                    value={titleLevel}
                                    onChange={(value) => setAttributes({ titleLevel: value })}
                                    min={1}
                                    max={6}
                                />
                            </>
                        )}
                    </PanelBody>

                    <PanelBody title={__('Display Options', 'jww-theme')} initialOpen={false}>
                        <ToggleControl
                            label={__('Show Dates', 'jww-theme')}
                            checked={showDates}
                            onChange={(value) => setAttributes({ showDates: value })}
                            help={__('Display founded and disbanded years for each band', 'jww-theme')}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="band-list-preview">
                    <h3>{__('Band List Preview', 'jww-theme')}</h3>

                    {/* Show title settings */}
                    {showTitle && (
                        <div className="preview-title">
                            <strong>{__('Title:', 'jww-theme')} </strong>
                            {title ? title : __('Bands', 'jww-theme')}
                            <small> (H{titleLevel})</small>
                        </div>
                    )}

                    {bands && bands.length > 0 ? (
                        <div className="preview-bands">
                            <p><strong>{__('Found', 'jww-theme')} {bands.length} {__('band(s)', 'jww-theme')}</strong></p>
                            <div className="preview-band-list">
                                {bands.slice(0, 5).map((band) => (
                                    <div key={band.id} className="preview-band">
                                        <strong>{band.title.rendered}</strong>
                                        <br />
                                        <small>{__('ID:', 'jww-theme')} {band.id}</small>
                                    </div>
                                ))}
                                {bands.length > 5 && (
                                    <div className="preview-more">
                                        <em>+ {__('more bands...', 'jww-theme')}</em>
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <p>{__('No bands found', 'jww-theme')}</p>
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

