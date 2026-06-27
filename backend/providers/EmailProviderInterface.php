<?php
// backend/providers/EmailProviderInterface.php

interface EmailProviderInterface {
    /**
     * Look up the email address for a target person.
     * 
     * @param string $firstName
     * @param string $lastName
     * @param string $companyName
     * @param string $domain
     * @param string $title
     * @return array|null Returns ['email' => string, 'score' => int, 'status' => string] or null if not found/error
     */
    public function findEmail($firstName, $lastName, $companyName, $domain, $title = '');

    /**
     * Fetch the remaining credits from the provider if supported.
     * 
     * @return int|null Returns remaining credits or null if not supported
     */
    public function getRemainingCredits();
}
