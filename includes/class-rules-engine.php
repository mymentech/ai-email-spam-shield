<?php
/**
 * Rules Engine — stateless rule-based spam scoring.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Rules_Engine {

    /** Suspicious TLDs that commonly host spam. */
    const SUSPICIOUS_TLDS = array( '.ru', '.xyz', '.click', '.top', '.tk', '.ml', '.ga', '.cf', '.cc', '.to', '.onion', '.su', '.at', '.biz' );

    /**
     * Darknet / underground market phrases that are near-certain spam for a US business site.
     * Matched separately and scored higher than generic spam phrases.
     */
    const DARKNET_PHRASES = array(
        'darknet', 'dark net', 'dark web', 'darkweb',
        'tor browser', 'tor network', '.onion',
        'working links', 'current links', 'mirror links', 'new mirrors',
        'safe entry', 'kraken', 'hydra market', 'silk road', 'alphabay',
        'dream market', 'empire market', 'captcha-', 'slon',
        'vpn for anonymity', 'hide your ip', 'anonymous access',
    );

    /**
     * Unicode regex patterns for non-Latin scripts uncommon on US-targeted sites.
     * Key = human-readable label, value = PCRE pattern.
     */
    const NON_LATIN_SCRIPTS = array(
        'cyrillic' => '/[\x{0400}-\x{04FF}]/u',
        'arabic'   => '/[\x{0600}-\x{06FF}]/u',
        'cjk'      => '/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u',
        'hebrew'   => '/[\x{0590}-\x{05FF}]/u',
        'devanagari' => '/[\x{0900}-\x{097F}]/u',
        'thai'     => '/[\x{0E00}-\x{0E7F}]/u',
    );

    /** Common spam phrases (lowercase). */
    const SPAM_PHRASES = array(
        'buy now', 'free money', 'crypto', 'investment opportunity',
        'make money fast', 'work from home', 'earn extra cash',
        'click here', 'limited time offer', 'act now', 'order now',
        'risk free', 'guaranteed', 'winner', 'you have been selected',
        'congratulations', 'claim your prize', 'no credit check',
        'lose weight', 'diet pill', 'miracle cure', 'casino',
        'online pharmacy', 'prescription', 'viagra', 'enlarge',
        'nigerian prince', 'wire transfer', 'bitcoin', 'urgent reply',
        // Adult / sexual content.
        'xxx', 'sexy', 'sexual', 'nude', 'naked', 'adult content',
        'porn', 'escort', 'call girl', 'hookup', 'hot singles', 'meet girls',
        'meet women', 'meet men', 'dating site', 'find a girl', 'find a woman',
        'find a man', 'local singles', 'sexiest',
    );

    /**
     * Compute the combined rule-based spam score.
     *
     * @param string $subject  Email subject.
     * @param string $body     Email body (plain text or HTML).
     * @param string $ip       Sender IP address.
     * @return float           Score capped at 1.0.
     */
    public static function score( string $subject, string $body, string $ip ): float {
        $combined = $subject . ' ' . $body;
        $score    = 0.0;

        $score += self::check_url_count( $body );
        $score += self::check_spam_phrases( $combined );
        $score += self::check_sexual_content( $combined );
        $score += self::check_darknet_phrases( $combined );
        $score += self::check_non_latin_script( $combined );
        $score += self::check_message_length( $body );
        $score += self::check_uppercase_ratio( $combined );
        $score += self::check_suspicious_tlds( $body );
        $score += self::check_repeated_chars( $combined );
        $score += self::check_ip_rate( $ip );
        $score += self::check_custom_phrases( $combined );

        return min( 1.0, $score );
    }

    /**
     * +0.25 if more than 3 URLs found in text.
     */
    public static function check_url_count( string $text ): float {
        preg_match_all( '/https?:\/\/\S+/i', $text, $matches );
        return count( $matches[0] ) > 3 ? 0.25 : 0.0;
    }

    /**
     * +0.20 if any common spam phrase is present.
     */
    public static function check_spam_phrases( string $text ): float {
        $lower = strtolower( $text );
        foreach ( self::SPAM_PHRASES as $phrase ) {
            if ( str_contains( $lower, $phrase ) ) {
                return 0.20;
            }
        }
        return 0.0;
    }

    /**
     * +0.15 if the message body is shorter than 20 characters.
     */
    public static function check_message_length( string $body ): float {
        $clean = trim( wp_strip_all_tags( $body ) );
        return strlen( $clean ) < 20 ? 0.15 : 0.0;
    }

    /**
     * +0.15 if uppercase characters exceed 40% of alphabetic chars.
     */
    public static function check_uppercase_ratio( string $text ): float {
        $alpha = preg_replace( '/[^a-zA-Z]/', '', $text );
        if ( strlen( $alpha ) < 10 ) {
            return 0.0;
        }
        $upper_count = strlen( preg_replace( '/[^A-Z]/', '', $alpha ) );
        return ( $upper_count / strlen( $alpha ) ) > 0.40 ? 0.15 : 0.0;
    }

    /**
     * +0.20 if any link uses a suspicious TLD.
     */
    public static function check_suspicious_tlds( string $text ): float {
        preg_match_all( '/https?:\/\/\S+/i', $text, $matches );
        foreach ( $matches[0] as $url ) {
            $host = wp_parse_url( $url, PHP_URL_HOST );
            if ( ! $host ) {
                continue;
            }
            foreach ( self::SUSPICIOUS_TLDS as $tld ) {
                if ( str_ends_with( strtolower( $host ), $tld ) ) {
                    return 0.20;
                }
            }
        }
        return 0.0;
    }

    /**
     * +0.10 if 4+ identical special characters appear consecutively,
     * or 3+ identical letters appear consecutively (e.g. "XXX").
     */
    public static function check_repeated_chars( string $text ): float {
        if ( preg_match( '/([!$?*#@]{4,})/u', $text ) ) {
            return 0.10;
        }
        if ( preg_match( '/([a-zA-Z])\1{2,}/u', $text ) ) {
            return 0.10;
        }
        return 0.0;
    }

    /**
     * Returns true if any hard-block signal is present.
     * These signals bypass the weighted AI/rule formula and always block.
     *
     * @param string $subject  Email subject.
     * @param string $body     Email body.
     * @return bool
     */
    public static function has_hard_block( string $subject, string $body ): bool {
        $combined = $subject . ' ' . $body;
        $custom    = get_option( 'aiess_phrases', array() );
        $custom_hb = (array) ( $custom['hard_block'] ?? array() );
        $lower     = strtolower( $combined );

        foreach ( $custom_hb as $phrase ) {
            $phrase = trim( (string) $phrase );
            if ( $phrase !== '' && str_contains( $lower, strtolower( $phrase ) ) ) {
                return true;
            }
        }

        return self::check_sexual_content( $combined ) > 0.0
            || self::check_darknet_phrases( $combined ) > 0.0;
    }

    /**
     * +0.50 if explicit sexual words are found as whole words.
     * Uses word-boundary regex to avoid matching "Essex", "sextet", etc.
     * Scored separately and higher because the BERT model misses this category.
     */
    public static function check_sexual_content( string $text ): float {
        $lower = strtolower( $text );
        $words = array( 'sex', 'penis', 'vagina', 'masturbat', 'orgasm', 'erotic', 'fetish', 'dildo', 'boobs', 'breasts', 'genitals' );
        foreach ( $words as $word ) {
            if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '/iu', $lower ) ) {
                return 0.50;
            }
        }
        return 0.0;
    }

    /**
     * +0.30 if any darknet/underground-market phrase is found.
     * These phrases are near-certain indicators of spam for a US business site.
     */
    public static function check_darknet_phrases( string $text ): float {
        $lower = strtolower( $text );
        foreach ( self::DARKNET_PHRASES as $phrase ) {
            if ( str_contains( $lower, $phrase ) ) {
                return 0.30;
            }
        }
        return 0.0;
    }

    /**
     * +0.40 for Cyrillic (Russian), +0.30 for Arabic, CJK, Hebrew, Devanagari, or Thai.
     * A US-targeted site should not normally receive emails in these scripts.
     */
    public static function check_non_latin_script( string $text ): float {
        if ( preg_match( self::NON_LATIN_SCRIPTS['cyrillic'], $text ) ) {
            return 0.40;
        }
        foreach ( array( 'arabic', 'cjk', 'hebrew', 'devanagari', 'thai' ) as $script ) {
            if ( preg_match( self::NON_LATIN_SCRIPTS[ $script ], $text ) ) {
                return 0.30;
            }
        }
        return 0.0;
    }

    /**
     * +0.30 if the same IP submits more than 2 times within 2 minutes.
     * Uses WordPress transients as a rate-limit counter.
     */
    public static function check_ip_rate( string $ip ): float {
        if ( empty( $ip ) ) {
            return 0.0;
        }

        $key   = 'aiess_ip_' . md5( $ip );
        $count = (int) get_transient( $key );

        if ( $count > 2 ) {
            return 0.30;
        }

        set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
        return 0.0;
    }

    /**
     * Score custom phrases stored in the aiess_phrases option.
     *
     * - hard_block tier: +0.30 on first match (mirrors check_darknet_phrases)
     * - spam tier:       +0.20 on first match (mirrors check_spam_phrases)
     *
     * Returns 0.0 when the option is absent or empty.
     *
     * @param string $text Combined subject + body.
     * @return float
     */
    public static function check_custom_phrases( string $text ): float {
        $phrases = get_option( 'aiess_phrases', array() );
        $lower   = strtolower( $text );

        foreach ( (array) ( $phrases['hard_block'] ?? array() ) as $phrase ) {
            $phrase = trim( (string) $phrase );
            if ( $phrase !== '' && str_contains( $lower, strtolower( $phrase ) ) ) {
                return 0.30;
            }
        }

        foreach ( (array) ( $phrases['spam'] ?? array() ) as $phrase ) {
            $phrase = trim( (string) $phrase );
            if ( $phrase !== '' && str_contains( $lower, strtolower( $phrase ) ) ) {
                return 0.20;
            }
        }

        return 0.0;
    }
}
