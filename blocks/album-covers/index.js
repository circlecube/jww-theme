import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, CheckboxControl, RangeControl, SelectControl, ToggleControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/album-covers', {
    edit: ({ attributes, setAttributes }) => {
        const { releases, postsPerPage, orderBy, order, showTitle, title, titleLevel, artist } = attributes;
        const blockProps = useBlockProps();

        // Get available release taxonomy terms
        const availableReleases = useSelect((select) => {
            return select('core').getEntityRecords('taxonomy', 'release', {
                per_page: -1,
                orderby: 'name',
                order: 'asc'
            });
        }, []);

        // Get available bands/artists
        const availableArtists = useSelect((select) => {
            return select('core').getEntityRecords('postType', 'band', {
                per_page: -1,
                orderby: 'title',
                order: 'asc'
            });
        }, []);

        // Get albums for preview
        const albums = useSelect((select) => {
            const queryArgs = {
                per_page: postsPerPage === -1 ? 10 : postsPerPage,
                orderby: orderBy,
                order: order.toLowerCase(),
                post_type: 'album'
            };

            // Add release filter if releases are selected
            if (releases && releases.length > 0) {
                queryArgs.release = releases.join(',');
            }

            return select('core').getEntityRecords('postType', 'album', queryArgs);
        }, [releases, postsPerPage, orderBy, order, artist]);

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
                                    help={__('Leave empty to use default "Appears on:" text', 'jww-theme')}
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

                    <PanelBody title={__('Filter Options', 'jww-theme')} initialOpen={false}>
                        <p>{__('Select release types to filter albums. Leave empty to show all albums.', 'jww-theme')}</p>
                        {availableReleases && availableReleases.map((release) => (
                            <CheckboxControl
                                key={release.id}
                                label={release.name}
                                checked={releases ? releases.includes(release.id.toString()) : false}
                                onChange={(checked) => {
                                    const currentReleases = releases || [];
                                    let newReleases;

                                    if (checked) {
                                        newReleases = [...currentReleases, release.id.toString()];
                                    } else {
                                        newReleases = currentReleases.filter(id => id !== release.id.toString());
                                    }

                                    setAttributes({ releases: newReleases });
                                }}
                            />
                        ))}

                        <SelectControl
                            label={__('Artist/Band', 'jww-theme')}
                            value={artist || ''}
                            options={[
                                { label: __('All Artists', 'jww-theme'), value: '' },
                                ...(availableArtists ? availableArtists.map((band) => ({
                                    label: band.title.rendered,
                                    value: band.id.toString()
                                })) : [])
                            ]}
                            onChange={(value) => setAttributes({ artist: value })}
                            help={__('Filter albums by a specific artist/band. Leave empty to show all artists.', 'jww-theme')}
                        />
                    </PanelBody>

                    <PanelBody title={__('Display Options', 'jww-theme')} initialOpen={false}>
                        <RangeControl
                            label={__('Number of Albums', 'jww-theme')}
                            value={postsPerPage}
                            onChange={(value) => setAttributes({ postsPerPage: value })}
                            min={-1}
                            max={50}
                            help={__('Set to -1 to show all albums', 'jww-theme')}
                        />

                        <SelectControl
                            label={__('Order By', 'jww-theme')}
                            value={orderBy}
                            options={[
                                { label: __('Date', 'jww-theme'), value: 'date' },
                                { label: __('Title', 'jww-theme'), value: 'title' },
                                { label: __('Menu Order', 'jww-theme'), value: 'menu_order' },
                                { label: __('Random', 'jww-theme'), value: 'rand' }
                            ]}
                            onChange={(value) => setAttributes({ orderBy: value })}
                        />

                        <SelectControl
                            label={__('Order', 'jww-theme')}
                            value={order}
                            options={[
                                { label: __('Descending', 'jww-theme'), value: 'DESC' },
                                { label: __('Ascending', 'jww-theme'), value: 'ASC' }
                            ]}
                            onChange={(value) => setAttributes({ order: value })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="album-covers-preview">
                    <h3>{__('Album Covers Preview', 'jww-theme')}</h3>

                    {/* Show title settings */}
                    {showTitle && (
                        <div className="preview-title">
                            <strong>{__('Title:', 'jww-theme')} </strong>
                            {title ? title : __('Appears on:', 'jww-theme')}
                            <small> (H{titleLevel})</small>
                        </div>
                    )}

                    {/* Show selected releases */}
                    {releases && releases.length > 0 && (
                        <div className="preview-releases">
                            <strong>{__('Filtering by releases:', 'jww-theme')} </strong>
                            {availableReleases &&
                                releases.map(releaseId => {
                                    const release = availableReleases.find(r => r.id.toString() === releaseId);
                                    return release ? release.name : releaseId;
                                }).join(', ')
                            }
                        </div>
                    )}

                    {/* Show selected artist */}
                    {artist && (
                        <div className="preview-artist">
                            <strong>{__('Filtering by artist:', 'jww-theme')} </strong>
                            {availableArtists && availableArtists.find(b => b.id.toString() === artist)
                                ? availableArtists.find(b => b.id.toString() === artist).title.rendered
                                : artist
                            }
                        </div>
                    )}

                    {albums && albums.length > 0 ? (
                        <div className="preview-albums">
                            <p><strong>{__('Found', 'jww-theme')} {albums.length} {__('album(s)', 'jww-theme')}</strong></p>
                            <div className="preview-album-list">
                                {albums.slice(0, 3).map((album) => (
                                    <div key={album.id} className="preview-album">
                                        <strong>{album.title.rendered}</strong>
                                        <br />
                                        <small>{__('ID:', 'jww-theme')} {album.id}</small>
                                    </div>
                                ))}
                                {albums.length > 3 && (
                                    <div className="preview-more">
                                        <em>+ {albums.length - 3} {__('more albums...', 'jww-theme')}</em>
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <p>{__('No albums found', 'jww-theme')}</p>
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
