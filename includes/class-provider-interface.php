<?php
/**
 * Provider_Interface — contract for all AI scoring providers.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

interface Provider_Interface {

    /**
     * Score an email for spam probability.
     *
     * @param string $subject  Email subject.
     * @param string $body     Email body.
     * @return float|null      Spam probability 0.0–1.0, or null on failure.
     */
    public function get_score( string $subject, string $body ): ?float;
}
