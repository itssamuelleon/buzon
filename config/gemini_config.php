<?php
// Google Gemini API configuration
//
// Set the GEMINI_API_KEY environment variable or replace the empty string below
// with your API key. Avoid committing the actual key to version control.

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-2.5-flash-lite');
}

if (!defined('GEMINI_API_BASE_URL')) {
    define('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta');
}

if (!function_exists('isGeminiConfigured')) {
    function isGeminiConfigured(): bool
    {
        return defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '';
    }
}
