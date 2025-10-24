import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

// Import styles
import './editor.scss';
import './style.scss';

registerBlockType('jww/day-counter', {
    edit: ({ attributes, setAttributes }) => {
        const { customText, showEmoji } = attributes;
        const blockProps = useBlockProps();

        // Get the latest song for preview
        const latestSong = useSelect((select) => {
            const queryArgs = {
                per_page: 1,
                orderby: 'date',
                order: 'desc',
                post_type: 'song',
                post_status: 'publish'
            };

            return select('core').getEntityRecords('postType', 'song', queryArgs);
        }, []);

        // Calculate days since latest song
        let daysSince = 0;
        let emoji = '😄'; // Default happy
        if (latestSong && latestSong.length > 0) {
            const latestDate = new Date(latestSong[0].date);
            const now = new Date();
            const diffTime = Math.abs(now - latestDate);
            daysSince = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            // Determine emoji based on days since
            if (showEmoji) {
                if (daysSince <= 1) {
                    emoji = '🎉'; // Very recent - celebration
                } else if (daysSince <= 2) {
                    emoji = '😄'; // Recent - very happy
                } else if (daysSince <= 4) {
                    emoji = '😊'; // This week - happy
                } else if (daysSince <= 8) {
                    emoji = '🙂'; // Two weeks - slightly happy
                } else if (daysSince <= 12) {
                    emoji = '😐'; // Two weeks - neutral
                } else if (daysSince <= 20) {
                    emoji = '😕'; // Three weeks - concerned
                } else if (daysSince <= 30) {
                    emoji = '😟'; // One month - worried
                } else if (daysSince <= 60) {
                    emoji = '😰'; // Two months - anxious
                } else {
                    emoji = '😱'; // Over two months - panic!
                }
            }
        }

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Display Options', 'jww-theme')}>
                        <TextControl
                            label={__('Custom Text', 'jww-theme')}
                            value={customText}
                            onChange={(value) => setAttributes({ customText: value })}
                            help={__('The text that appears after the day count.', 'jww-theme')}
                        />
                        <ToggleControl
                            label={__('Show Emoji', 'jww-theme')}
                            checked={showEmoji}
                            onChange={(value) => setAttributes({ showEmoji: value })}
                            help={__('Add a fun emoji to make it more humorous.', 'jww-theme')}
                        />
                    </PanelBody>
                </InspectorControls>

                <div className="day-counter-preview">
                    <h3>{__('Day Counter Preview', 'jww-theme')}</h3>

                    {latestSong && latestSong.length > 0 ? (
                        <div className="preview-content">
                            <div className="day-count">
                                <strong>{daysSince}</strong>
                                {showEmoji && <span className="emoji">{emoji}</span>}
                            </div>
                            <div className="counter-text">
                                {customText}
                            </div>
                        </div>
                    ) : (
                        <p>{__('No songs found to calculate days from.', 'jww-theme')}</p>
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
