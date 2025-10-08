import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl, CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/latest-song', {
    edit: ({ attributes, setAttributes }) => {
        const { showTitle, showVideo, titleLevel, categories } = attributes;
        const blockProps = useBlockProps();

        // Get available categories
        const availableCategories = useSelect((select) => {
            return select('core').getEntityRecords('taxonomy', 'category', {
                per_page: -1,
                orderby: 'name',
                order: 'asc'
            });
        }, []);

        // Get the latest song for preview
        const latestSong = useSelect((select) => {
            const queryArgs = {
                per_page: 1,
                orderby: 'date',
                order: 'desc',
                meta_query: [
                    {
                        key: 'video',
                        value: '',
                        compare: 'NOT EMPTY'
                    }
                ]
            };

            // Add category filter if categories are selected
            if (categories && categories.length > 0) {
                queryArgs.categories = categories.join(',');
            }

            return select('core').getEntityRecords('postType', 'song', queryArgs);
        }, [categories]);

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Display Options', 'jww-theme')}>
                        <ToggleControl
                            label={__('Show Title', 'jww-theme')}
                            checked={showTitle}
                            onChange={(value) => setAttributes({ showTitle: value })}
                        />
                        <ToggleControl
                            label={__('Show Video', 'jww-theme')}
                            checked={showVideo}
                            onChange={(value) => setAttributes({ showVideo: value })}
                        />
                        {showTitle && (
                            <RangeControl
                                label={__('Title Level', 'jww-theme')}
                                value={titleLevel}
                                onChange={(value) => setAttributes({ titleLevel: value })}
                                min={1}
                                max={6}
                            />
                        )}
                    </PanelBody>

                    <PanelBody title={__('Category Filter', 'jww-theme')} initialOpen={false}>
                        <p>{__('Select categories to filter songs. Leave empty to show from all categories.', 'jww-theme')}</p>
                        {availableCategories && availableCategories.map((category) => (
                            <CheckboxControl
                                key={category.id}
                                label={category.name}
                                checked={categories ? categories.includes(category.id.toString()) : false}
                                onChange={(checked) => {
                                    const currentCategories = categories || [];
                                    let newCategories;

                                    if (checked) {
                                        newCategories = [...currentCategories, category.id.toString()];
                                    } else {
                                        newCategories = currentCategories.filter(id => id !== category.id.toString());
                                    }

                                    setAttributes({ categories: newCategories });
                                }}
                            />
                        ))}
                    </PanelBody>
                </InspectorControls>

                <div className="latest-song-preview">
                    <h3>{__('Latest Song Preview', 'jww-theme')}</h3>

                    {/* Show selected categories */}
                    {categories && categories.length > 0 && (
                        <div className="preview-categories">
                            <strong>{__('Filtering by categories:', 'jww-theme')} </strong>
                            {availableCategories &&
                                categories.map(catId => {
                                    const category = availableCategories.find(cat => cat.id.toString() === catId);
                                    return category ? category.name : catId;
                                }).join(', ')
                            }
                        </div>
                    )}

                    {latestSong && latestSong.length > 0 ? (
                        <div>
                            {showTitle && (
                                <div className="preview-title">
                                    {latestSong[0].title.rendered}
                                </div>
                            )}
                            {showVideo && (
                                <div className="preview-video">
                                    <em>{__('Video embed will appear here', 'jww-theme')}</em>
                                </div>
                            )}
                        </div>
                    ) : (
                        <p>{__('No songs found', 'jww-theme')}</p>
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
