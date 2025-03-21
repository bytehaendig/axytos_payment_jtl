<?php

namespace Plugin\axytos_payment\helpers;

class Encrypt
{
    private $encryption_key;

    public function __construct($encryption_key)
    {
        $this->encryption_key = $encryption_key;
    }

    public function encrypt($value): string
    {
        if (empty($value)) {
            return '';
        }

        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $encrypted = openssl_encrypt(
            $value,
            $method,
            $this->encryption_key,
            0,
            $iv
        );

        return base64_encode($iv . $encrypted);
    }

    public function decrypt($encrypted_value): string
    {
        if (empty($encrypted_value)) {
            return '';
        }

        $encrypted_value = base64_decode($encrypted_value);
        $method = 'aes-256-cbc';
        $ivlen = openssl_cipher_iv_length($method);

        $iv = substr($encrypted_value, 0, $ivlen);
        $encrypted = substr($encrypted_value, $ivlen);

        return openssl_decrypt(
            $encrypted,
            $method,
            $this->encryption_key,
            0,
            $iv
        );
    }
}
