import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

// Import styles
import './style.scss';
import './editor.scss';

registerBlockType('jww-theme/random-lyrics', {
    edit: function (props) {
        const { attributes, setAttributes } = props;
        const { showSongTitle, showArtist, refreshOnLoad } = attributes;
        const blockProps = useBlockProps();

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Display Options', 'jww-theme')}>
                        <ToggleControl
                            label={__('Show Song Title', 'jww-theme')}
                            checked={showSongTitle}
                            onChange={(value) => setAttributes({ showSongTitle: value })}
                        />
                        <ToggleControl
                            label={__('Show Artist', 'jww-theme')}
                            checked={showArtist}
                            onChange={(value) => setAttributes({ showArtist: value })}
                        />
                        <ToggleControl
                            label={__('Enable Refresh Button', 'jww-theme')}
                            checked={refreshOnLoad}
                            onChange={(value) => setAttributes({ refreshOnLoad: value })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="random-lyrics-editor-preview">
                    <blockquote className="random-lyrics-quote">
                        <p className="random-lyrics-text">
                            {__('Random lyrics will appear here...', 'jww-theme')}
                        </p>
                        {(showSongTitle || showArtist) && (
                            <footer className="random-lyrics-attribution">
                                {showArtist && (
                                    <cite className="random-lyrics-artist">
                                        {__('Jesse Welles', 'jww-theme')}
                                    </cite>
                                )}
                                {showSongTitle && (
                                    <span className="random-lyrics-song">
                                        {showArtist && '— '}
                                        "{__('Song Title', 'jww-theme')}"
                                    </span>
                                )}
                            </footer>
                        )}
                    </blockquote>

                    {refreshOnLoad && (
                        <div className="random-lyrics-controls">
                            <button
                                type="button"
                                className="random-lyrics-refresh-btn"
                                disabled
                            >
                                <span className="refresh-icon">↻</span>
                                <span className="refresh-text">
                                    {__('New Lyrics', 'jww-theme')}
                                </span>
                            </button>
                        </div>
                    )}

                    <div className="random-lyrics-editor-note">
                        <p>
                            {__('This block will display a random lyric line from a random song on your site.', 'jww-theme')}
                        </p>
                    </div>
                </div>
            </div>
        );
    },

    save: function () {
        // This block is rendered server-side, so we don't need a save function
        return null;
    },
});
