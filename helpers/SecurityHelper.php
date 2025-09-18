<?php

namespace Plugin\axytos_payment\helpers;

use JTL\Shop;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * Security helper for API endpoints with payload validation
 */
class SecurityHelper
{
    private const PAYLOAD_SIZE_LIMIT = 1048576; // 1MB in bytes

    public function __construct()
    {
        // No dependencies needed for basic security features
    }

    /**
     * Validate request payload size
     */
    public function validatePayloadSize(string $body): ?JsonResponse
    {
        $bodySize = strlen($body);

        if ($bodySize > self::PAYLOAD_SIZE_LIMIT) {
            $this->logSecurityEvent('payload_size_exceeded', [
                'size' => $bodySize,
                'limit' => self::PAYLOAD_SIZE_LIMIT,
                'ip' => $this->getClientIP()
            ]);

            // Try to create JsonResponse, return null if not available (for testing)
            if (class_exists('\Laminas\Diactoros\Response\JsonResponse')) {
                return new \Laminas\Diactoros\Response\JsonResponse([
                    'success' => false,
                    'error' => 'Request payload too large. Maximum size is 1MB.'
                ], 413);
            } else {
                // For testing/development, just return null (caller should handle the error)
                return null;
            }
        }

        return null;
    }





    /**
     * Get client IP address
     */
    public function getClientIP(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1'; // fallback
    }




}