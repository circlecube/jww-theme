import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/song-list', {
    edit: ({ attributes, setAttributes }) => {
        const { listType, artistId, showHeaders, includeCovers } = attributes;
        const blockProps = useBlockProps();

        // Get available bands/artists
        const availableArtists = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'band', {
                per_page: -1,
                orderby: 'title',
                order: 'asc'
            });
        }, []);

        // Get songs for preview
        const songs = useSelect((select) => {
            const queryArgs = {
                per_page: 5,
                post_type: 'song',
                orderby: listType === 'chronological' ? 'date' : 'title',
                order: listType === 'chronological' ? 'desc' : 'asc'
            };

            // Add meta filter for artist if artistId is selected
            // Note: REST API filtering by ACF relationship fields may not be perfect
            // due to serialized storage format, but we'll attempt it
            if (artistId) {
                queryArgs.meta_key = 'artist';
                // Try to match the serialized format used by ACF
                queryArgs.meta_value = artistId.toString();
                queryArgs.compare = 'LIKE';
                // queryArgs.meta_query = [
                //     {
                //         key: 'artist',
                //         value: artistId.toString(),
                //         compare: 'LIKE'
                //     }
                // ];
            }

            return select('core').getEntityRecords('postType', 'song', queryArgs);
        }, [listType, artistId]);

        const listTypeOptions = [
            { label: __('Alphabetical', 'jww-theme'), value: 'alphabetical' },
            { label: __('Chronological', 'jww-theme'), value: 'chronological' },
            { label: __('Cover Songs', 'jww-theme'), value: 'covers' },
            { label: __('Grid', 'jww-theme'), value: 'grid' }
        ];

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Song List Options', 'jww-theme')} initialOpen={true}>
                        <SelectControl
                            label={__('List Type', 'jww-theme')}
                            value={listType}
                            options={listTypeOptions}
                            onChange={(value) => setAttributes({ listType: value })}
                            help={__('Choose how to display and organize the songs', 'jww-theme')}
                        />

                        <SelectControl
                            label={__('Artist/Band', 'jww-theme')}
                            value={artistId || ''}
                            options={[
                                { label: __('All Artists', 'jww-theme'), value: '' },
                                ...(availableArtists ? availableArtists.map((band) => ({
                                    label: band.title.rendered,
                                    value: band.id.toString()
                                })) : [])
                            ]}
                            onChange={(value) => setAttributes({ artistId: value || '' })}
                            help={__('Filter songs by a specific artist/band. Leave empty to show all artists.', 'jww-theme')}
                        />

                        {listType !== 'grid' && (
                            <ToggleControl
                                label={__('Show Section Headers', 'jww-theme')}
                                checked={showHeaders}
                                onChange={(value) => setAttributes({ showHeaders: value })}
                                help={__('Display section headers (letters, months, or artists) depending on list type', 'jww-theme')}
                            />
                        )}

                        {listType !== 'covers' && (
                            <ToggleControl
                                label={__('Include Cover Songs', 'jww-theme')}
                                checked={includeCovers}
                                onChange={(value) => setAttributes({ includeCovers: value })}
                                help={__('Include cover songs in the list along with original songs', 'jww-theme')}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>

                <div className="song-list-preview">
                    <h3>{__('Song List Preview', 'jww-theme')}</h3>

                    <div className="preview-info">
                        <p>
                            <strong>{__('List Type:', 'jww-theme')}</strong> {
                                listTypeOptions.find(opt => opt.value === listType)?.label || listType
                            }
                        </p>
                        {artistId && availableArtists && (
                            <p>
                                <strong>{__('Artist:', 'jww-theme')}</strong> {
                                    availableArtists.find(band => band.id.toString() === artistId)?.title.rendered || __('Unknown', 'jww-theme')
                                }
                            </p>
                        )}
                        {!artistId && (
                            <p>
                                <strong>{__('Artist:', 'jww-theme')}</strong> {__('All Artists', 'jww-theme')}
                            </p>
                        )}
                        {listType !== 'grid' && (
                            <p>
                                <strong>{__('Show Headers:', 'jww-theme')}</strong> {
                                    showHeaders ? __('Yes', 'jww-theme') : __('No', 'jww-theme')
                                }
                            </p>
                        )}
                        {listType !== 'covers' && (
                            <p>
                                <strong>{__('Include Covers:', 'jww-theme')}</strong> {
                                    includeCovers ? __('Yes', 'jww-theme') : __('No', 'jww-theme')
                                }
                            </p>
                        )}
                    </div>

                    {songs && songs.length > 0 ? (
                        <div className="preview-songs">
                            <p><strong>{__('Sample songs (showing up to 5):', 'jww-theme')}</strong></p>
                            <ul className="preview-song-list">
                                {songs.map((song) => (
                                    <li key={song.id}>
                                        {song.title.rendered}
                                    </li>
                                ))}
                            </ul>
                            <p className="preview-note">
                                <em>{__('Note: Full list will be displayed on the frontend', 'jww-theme')}</em>
                            </p>
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

