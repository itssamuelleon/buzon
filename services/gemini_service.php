<?php
require_once __DIR__ . '/../config/gemini_config.php';

if (!function_exists('generateGeminiResponse')) {
    /**
     * Calls the Gemini API with a system instruction and user prompt.
     *
     * @param string $systemInstruction System prompt content shared with the model.
     * @param string $userPrompt        User content sent for analysis.
     * @param array  $options           Optional overrides (generationConfig, safetySettings, responseMimeType).
     *
     * @return array{success:bool, content?:string, raw?:array, error?:string}
     */
    function generateGeminiResponse(string $systemInstruction, string $userPrompt, array $options = []): array
    {
        if (!isGeminiConfigured()) {
            return [
                'success' => false,
                'error' => 'Gemini API no configurada. Define la variable de entorno GEMINI_API_KEY o establece la constante GEMINI_API_KEY.',
            ];
        }

        $baseUrl = rtrim(GEMINI_API_BASE_URL, '/');
        $model = rawurlencode(GEMINI_MODEL);
        $url = sprintf('%s/models/%s:generateContent?key=%s', $baseUrl, $model, urlencode(GEMINI_API_KEY));

        $generationConfig = array_merge([
            'temperature' => 0.2,
            'topP' => 0.9,
            'maxOutputTokens' => 1024,
        ], $options['generationConfig'] ?? []);

        $mime = $options['responseMimeType'] ?? ($options['response_mime_type'] ?? 'application/json');
        if (!empty($mime)) {
            $generationConfig['responseMimeType'] = $mime;
        }

        $payload = [
            'system_instruction' => [
                'role' => 'system',
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        if (!empty($options['safetySettings']) && is_array($options['safetySettings'])) {
            $payload['safetySettings'] = $options['safetySettings'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            // SSL options for production environments
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Follow redirects if any
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'error' => 'Error de conexión al llamar a Gemini: ' . $error,
            ];
        }

        $httpStatus = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        if ($httpStatus >= 400) {
            $errorMessage = $decoded['error']['message'] ?? ('Error HTTP ' . $httpStatus);
            return [
                'success' => false,
                'error' => 'Gemini devolvió un error: ' . $errorMessage,
                'raw' => $decoded,
            ];
        }

        if (!isset($decoded['candidates'][0]['content']['parts'])) {
            return [
                'success' => false,
                'error' => 'Respuesta inesperada de Gemini. No se encontró contenido utilizable.',
                'raw' => $decoded,
            ];
        }

        $parts = $decoded['candidates'][0]['content']['parts'];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        return [
            'success' => true,
            'content' => $text,
            'raw' => $decoded,
        ];
    }
}
