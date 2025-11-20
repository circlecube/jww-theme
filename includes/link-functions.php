<?php
/**
 * Music streaming and purchase link utility functions
 * 
 * @package JWW_Theme
 * 
 * AFFILIATE PROGRAMS CHECKLIST:
 * =============================
 * 
 * ✅ COMPLETED:
 * - Amazon Music (circubstu-20) - ACTIVE
 * 
 * 🔄 AVAILABLE - NEED TO SIGN UP:
 * - iTunes Store (Apple's affiliate program)
 * - 7digital (has affiliate program)
 * - HDtracks (high-res music, has affiliate program)
 * - Google Play Music (if still available)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

const ARTIST_NAME = 'Jesse Welles';
const AMAZON_AFFILIATE_TAG = 'circubstu-20';

/**
 * Helper function to properly encode search terms for URLs
 * Replaces spaces with %20 instead of letting urlencode() use + signs
 * 
 * @param string $query The search query to encode
 * @return string The properly encoded query
 */
function encode_search_query($query) {
    // Make "Jesse Welles Devil's Den" becomes "Jesse%20Welles%20Devil%27s%20Den"
    
    // Handle spaces and plus signs
    $query = str_replace('%', "%25", $query);  // Percent
    $query = str_replace(' ', "%20", $query);
    // $query = str_replace('+', "%20", $query);
    
    // Handle apostrophes and quotes
    $query = str_replace('’', "%27", $query); // Regular apostrophe to %27
    $query = str_replace('"', "'", $query);  // Double quote to regular
    
    // Handle other common punctuation
    $query = str_replace('&', "%26", $query);  // Ampersand
    // $query = str_replace('#', "%23", $query);  // Hash
    // $query = str_replace('$', "%24", $query);  // Dollar sign
    // $query = str_replace('@', "%40", $query);  // At symbol
    // $query = str_replace('!', "%21", $query);  // Exclamation
    // $query = str_replace('?', "%3F", $query);  // Question mark
    // $query = str_replace('(', "%28", $query);  // Left parenthesis
    // $query = str_replace(')', "%29", $query);  // Right parenthesis
    // $query = str_replace('[', "%5B", $query);  // Left bracket
    // $query = str_replace(']', "%5D", $query);  // Right bracket
    // $query = str_replace('{', "%7B", $query);  // Left brace
    // $query = str_replace('}', "%7D", $query);  // Right brace
    // $query = str_replace('=', "%3D", $query);  // Equals
    // $query = str_replace('|', "%7C", $query);  // Pipe
    // $query = str_replace('\\', "%5C", $query); // Backslash
    // $query = str_replace(':', "%3A", $query);  // Colon
    // $query = str_replace(';', "%3B", $query);  // Semicolon
    // $query = str_replace('<', "%3C", $query);  // Less than
    // $query = str_replace('>', "%3E", $query);  // Greater than
    // $query = str_replace(',', "%2C", $query);  // Comma
    // $query = str_replace('.', "%2E", $query);  // Period
    // $query = str_replace('/', "%2F", $query);  // Forward slash
    // $query = str_replace('~', "%7E", $query);  // Tilde
    // $query = str_replace('`', "%60", $query);  // Backtick
    // $query = str_replace('^', "%5E", $query);  // Caret
    // $query = str_replace('%2B', "%20", $query);  // Plus sign to space
    
    return $query;
}

/**
 * Generate Amazon search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @param string $amazon_domain The Amazon domain to use (default: amazon.com)
 * @return string The formatted Amazon search URL
 */
function get_amazon_album_search_url($album_title, $artist_name = ARTIST_NAME, $amazon_domain = 'amazon.com') {
    // Clean and prepare search terms
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    // Add "CD" or "vinyl" to help narrow down to physical music
    // $search_terms[] = 'CD';
    
    // Join terms with spaces
    $query = implode('+', $search_terms);
    
    // URL encode the search query with proper space encoding
    $encoded_query = encode_search_query($query);
    
    // Amazon search URL with affiliate tag
    return "https://www.{$amazon_domain}/s?k={$encoded_query}&tag=".AMAZON_AFFILIATE_TAG;
}

/**
 * Generate Apple Music search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Apple Music search URL
 */
function get_apple_music_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://music.apple.com/search?term={$encoded_query}";
}

/**
 * Generate Spotify search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Spotify search URL
 */
function get_spotify_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://open.spotify.com/search/{$encoded_query}";
}

/**
 * Generate Tidal search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Tidal search URL
 */
function get_tidal_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://tidal.com/search?q={$encoded_query}";
}

/**
 * Generate Qobuz search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Qobuz search URL
 */
function get_qobuz_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    //https://www.qobuz.com/us-en/search/tracks/
    //https://www.qobuz.com/us-en/search/albums/
    //https://www.qobuz.com/us-en/search/artists/
    return "https://www.qobuz.com/us-en/search/tracks/{$encoded_query}";
}

/**
 * Generate Deezer search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Deezer search URL
 */
function get_deezer_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://www.deezer.com/search/{$encoded_query}";
}

/**
 * Generate Bandcamp search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Bandcamp search URL
 */
function get_bandcamp_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://bandcamp.com/search?q={$encoded_query}";
}

/**
 * Generate YouTube Music search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted YouTube Music search URL
 */
function get_youtube_music_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://music.youtube.com/search?q={$encoded_query}";
}

/**
 * Generate SoundCloud search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted SoundCloud search URL
 */
function get_soundcloud_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://soundcloud.com/search?q={$encoded_query}";
}

/**
 * Generate Pandora search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Pandora search URL
 */
function get_pandora_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://www.pandora.com/search/{$encoded_query}";
}

/**
 * Generate iHeartRadio search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted iHeartRadio search URL
 */
function get_iheartradio_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://www.iheart.com/search/?q={$encoded_query}";
}

/**
 * Generate Last.fm search URL for album
 * 
 * @param string $album_title The title of the album
 * @param string $artist_name The name of the artist/band
 * @return string The formatted Last.fm search URL
 */
function get_lastfm_album_url($album_title, $artist_name = ARTIST_NAME) {
    $search_terms = array();
    
    if (!empty($artist_name)) {
        $search_terms[] = sanitize_text_field($artist_name);
    }
    
    if (!empty($album_title)) {
        $search_terms[] = sanitize_text_field($album_title);
    }
    
    $query = implode(' ', $search_terms);
    $encoded_query = encode_search_query($query);
    
    return "https://www.last.fm/search?q={$encoded_query}";
}

/**
 * Get all available music streaming services
 * 
 * @return array Array of service names and their corresponding functions
 */
function get_music_streaming_services() {
    return array(
        'amazon' => array(
            'name' => 'Amazon Music',
            'function' => 'get_amazon_album_search_url',
            'icon' => '<i class="fab fa-amazon"></i>',
            'affiliate' => true,
            'color' => '#ff9500'
        ),
        'apple_music' => array(
            'name' => 'Apple Music',
            'function' => 'get_apple_music_album_url',
            'icon' => '<i class="fab fa-apple"></i>',
            'affiliate' => false,
            'color' => '#fa243c'
        ),
        'spotify' => array(
            'name' => 'Spotify',
            'function' => 'get_spotify_album_url',
            'icon' => '<i class="fab fa-spotify"></i>',
            'affiliate' => false,
            'color' => '#1db954'
        ),
        'tidal' => array(
            'name' => 'Tidal',
            'function' => 'get_tidal_album_url',
            'icon' => '<i class="fab fa-tidal"></i>',
            'affiliate' => false,
            'color' => '#00ffff'
        ),
        'qobuz' => array(
            'name' => 'Qobuz',
            'function' => 'get_qobuz_album_url',
            'icon' => '<i class="fas fa-record-vinyl"></i>',
            'affiliate' => false,
            'color' => '#1e3a8a'
        ),
        'deezer' => array(
            'name' => 'Deezer',
            'function' => 'get_deezer_album_url',
            'icon' => '<i class="fab fa-deezer"></i>',
            'affiliate' => false,
            'color' => '#ad47ff'
        ),
        'bandcamp' => array(
            'name' => 'Bandcamp',
            'function' => 'get_bandcamp_album_url',
            'icon' => '<i class="fab fa-bandcamp"></i>',
            'affiliate' => false,
            'color' => '#629aa0'
        ),
        'youtube_music' => array(
            'name' => 'YouTube Music',
            'function' => 'get_youtube_music_album_url',
            'icon' => '<i class="fab fa-youtube"></i>',
            'affiliate' => false,
            'color' => '#ff0000'
        ),
        'soundcloud' => array(
            'name' => 'SoundCloud',
            'function' => 'get_soundcloud_album_url',
            'icon' => '<i class="fab fa-soundcloud"></i>',
            'affiliate' => false,
            'color' => '#ff5500'
        ),
        'pandora' => array(
            'name' => 'Pandora',
            'function' => 'get_pandora_album_url',
            'icon' => '<i class="fab fa-pandora"></i>',
            'affiliate' => false,
            'color' => '#224099'
        ),
        'iheartradio' => array(
            'name' => 'iHeartRadio',
            'function' => 'get_iheartradio_album_url',
            'icon' => '<i class="fas fa-heart"></i>',
            'affiliate' => false,
            'color' => '#c6002b'
        ),
        'lastfm' => array(
            'name' => 'Last.fm',
            'function' => 'get_lastfm_album_url',
            'icon' => '<i class="fab fa-lastfm"></i>',
            'affiliate' => false,
            'color' => '#d51007'
        )
    );
}

/**
 * Map ACF service names to internal service keys
 * 
 * @param string $acf_service_name The service name from ACF field
 * @return string|false The internal service key, or false if not found
 */
function map_acf_service_to_key($acf_service_name) {
    $mapping = array(
        'Amazon' => 'amazon',
        'Apple' => 'apple_music',
        'Spotify' => 'spotify',
        'Tidal' => 'tidal',
        'Qobuz' => 'qobuz',
        'Deezer' => 'deezer',
        'Bandcamp' => 'bandcamp',
        'YouTube' => 'youtube_music',
        'Soundcloud' => 'soundcloud',
        'Pandora' => 'pandora',
        'IHeartRadio' => 'iheartradio',
        'Last.fm' => 'lastfm'
    );
    
    return isset($mapping[$acf_service_name]) ? $mapping[$acf_service_name] : false;
}

/**
 * Process ACF song links repeater field into a keyed array
 * 
 * @param array $acf_links The ACF repeater field data
 * @return array Array of service_key => url pairs
 */
function process_acf_song_links($acf_links) {
    $processed_links = array();
    
    if (!is_array($acf_links) || empty($acf_links)) {
        return $processed_links;
    }
    
    foreach ($acf_links as $link_row) {
        if (isset($link_row['service']) && isset($link_row['link']) && !empty($link_row['link'])) {
            $service_key = map_acf_service_to_key($link_row['service']);
            if ($service_key) {
                $processed_links[$service_key] = esc_url_raw($link_row['link']);
            }
        }
    }
    
    return $processed_links;
}

/**
 * Generate all music service links for an album or song
 * 
 * @param string $title The title of the album or song
 * @param string $artist_name The name of the artist/band
 * @param string $type The type of content ('album' or 'song')
 * @param array $excluded_services Array of service keys to exclude
 * @param array $custom_links Array of service_key => url pairs to override generated links
 * @return string HTML output of all music service links
 */
function get_all_music_service_links($title, $artist_name = ARTIST_NAME, $type = 'album', $excluded_services = array(), $custom_links = array()) {
    $services = get_music_streaming_services();
    $links_html = '<div class="music-service-links">';
    
    foreach ($services as $key => $service) {
        // Skip excluded services
        if (in_array($key, $excluded_services)) {
            continue;
        }
        
        // Use custom link if available, otherwise generate one
        if (isset($custom_links[$key]) && !empty($custom_links[$key])) {
            $url = $custom_links[$key];
        } else {
            // Get the URL using the service's function
            $url = call_user_func($service['function'], $title, $artist_name);
        }
        
        // Build the link HTML
        $links_html .= sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="music-service-link music-service-link--%s" style="--service-color: %s;" title="Find on %s">%s <span class="service-name">%s</span></a>',
            esc_url($url),
            esc_attr($key),
            esc_attr($service['color']),
            esc_attr($service['name']),
            $service['icon'],
            esc_html($service['name'])
        );
    }
    
    $links_html .= '</div>';
    
    return $links_html;
}

/**
 * Generate music service links for a song (with album context)
 * 
 * @param string $song_title The title of the song
 * @param string $artist_name The name of the artist/band
 * @param string $album_title The title of the album (optional)
 * @param array $excluded_services Array of service keys to exclude
 * @param array $acf_links Optional ACF repeater field data for song_links
 * @return string HTML output of all music service links
 */
function get_song_music_service_links($song_title, $artist_name = ARTIST_NAME, $album_title = '', $excluded_services = array(), $acf_links = null) {
    // Use default artist name if none provided
    if (empty($artist_name)) {
        $artist_name = ARTIST_NAME;
    }
    
    // Filter out common default WordPress titles
    $default_titles = array('Hello world!', 'Hello World', 'Sample Page', 'Sample Post', 'Uncategorized');
    if (in_array($album_title, $default_titles)) {
        $album_title = '';
    }
    
    // For songs, we'll search for the song title primarily, but include album context if available
    $search_title = $song_title;
    if (!empty($album_title)) {
        $search_title = $song_title . ' ' . $album_title;
    }
    
    // Process ACF links if provided
    $custom_links = array();
    if ($acf_links !== null && $acf_links !== false && !empty($acf_links)) {
        $custom_links = process_acf_song_links($acf_links);
    }
    
    return get_all_music_service_links($search_title, $artist_name, 'song', $excluded_services, $custom_links);
}

/**
 * Generate Amazon search URL for any product
 * 
 * @param string $search_query The search terms
 * @param string $amazon_domain The Amazon domain to use (default: amazon.com)
 * @param string $category The Amazon category to search in (optional)
 * @return string The formatted Amazon search URL
 */
function get_amazon_search_url($search_query, $amazon_domain = 'amazon.com', $category = '') {
    // Clean and prepare search terms
    $query = sanitize_text_field($search_query);
    
    // URL encode the search query with proper space encoding
    $encoded_query = encode_search_query($query);
    
    // Build the URL
    $url = "https://www.{$amazon_domain}/s?k={$encoded_query}&tag=".AMAZON_AFFILIATE_TAG;
    
    // Add category if specified
    if (!empty($category)) {
        $url .= "&i=" . sanitize_text_field($category);
    }
    
    return $url;
}

/**
 * Get Amazon affiliate domains for different countries
 * 
 * @return array Array of country codes and their corresponding Amazon domains
 */
function get_amazon_domains() {
    return array(
        'US' => 'amazon.com',
        'UK' => 'amazon.co.uk',
        'CA' => 'amazon.ca',
        'DE' => 'amazon.de',
        'FR' => 'amazon.fr',
        'IT' => 'amazon.it',
        'ES' => 'amazon.es',
        'JP' => 'amazon.co.jp',
        'AU' => 'amazon.com.au',
        'IN' => 'amazon.in',
        'BR' => 'amazon.com.br',
        'MX' => 'amazon.com.mx',
    );
}
