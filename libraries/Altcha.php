<?php

declare(strict_types=1);

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Altcha
{
    public const CONFIG_KEY_HMAC_KEY = 'altcha_hmac_key';

    public const CONFIG_KEY_MIN_COMPLEXITY = 'altcha_min_complexity';

    public const CONFIG_KEY_MAX_COMPLEXITY = 'altcha_max_complexity';

    public const CONFIG_KEY_HASH_ALGORITHM = 'altcha_hash_algorithm';

    public const CONFIG_KEY_CHALLENGE_EXPIRES_IN = 'altcha_expires_in';

    public const CONFIG_KEY_DB_TABLE_NAME = 'altcha_db_table_name';

    private readonly CI_DB $database;

    private readonly string $hmacKey;

    private readonly int $minComplexity;

    private readonly int $maxComplexity;

    private readonly int $expiresIn;

    private readonly string $hashAlgorithm;

    private readonly string $phpHashAlgorithm;

    private readonly string $tableName;

    public function __construct()
    {
        $CI = get_instance();

        $CI->config->load('altcha');
        $this->hmacKey = $CI->config->item(self::CONFIG_KEY_HMAC_KEY);
        $this->minComplexity = $CI->config->item(self::CONFIG_KEY_MIN_COMPLEXITY);
        $this->maxComplexity = $CI->config->item(self::CONFIG_KEY_MAX_COMPLEXITY);
        $this->expiresIn = $CI->config->item(self::CONFIG_KEY_CHALLENGE_EXPIRES_IN);
        $this->hashAlgorithm = $CI->config->item(self::CONFIG_KEY_HASH_ALGORITHM);
        $this->phpHashAlgorithm = $this->getPhpAlgorithmName($this->hashAlgorithm);
        $this->tableName = $CI->config->item(self::CONFIG_KEY_DB_TABLE_NAME);

        $CI->load->helper('string');

        $this->database = $CI->db;
    }

    public function getChallenge(): array
    {
        $salt = random_string('alnum', 10);
        $secretNumber = random_int($this->minComplexity, $this->maxComplexity);
        $challenge = $this->getHash($salt, $secretNumber);
        $signature = $this->getSignature($challenge);

        $this->cleanupOldChallenges();

        $this->database->insert(
            $this->tableName,
            [
                'challenge' => $challenge,
                'expires_at' => time() + $this->expiresIn,
            ],
        );

        return [
            'algorithm' => $this->hashAlgorithm,
            'challenge' => $challenge,
            'salt' => $salt,
            'signature' => $signature,
        ];
    }

    public function verifySolution(string $payload): bool
    {
        $jsonPayload = base64_decode($payload);

        if (false === $jsonPayload) {
            return false;
        }

        try {
            $data = json_decode($jsonPayload, true, 2, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return false;
        }

        if (! is_array($data)) {
            return false;
        }

        if ($this->hashAlgorithm !== ($data['algorithm'] ?? null)) {
            return false;
        }

        if (! is_string($data['salt'] ?? null)) {
            return false;
        }

        if (! is_int($data['number'] ?? null)) {
            return false;
        }

        if (! is_string($data['challenge'] ?? null)) {
            return false;
        }

        if (! is_string($data['signature'] ?? null)) {
            return false;
        }

        if ($data['challenge'] !== $this->getHash($data['salt'], $data['number'])) {
            return false;
        }

        $challengeData = $this->database->select()
            ->from($this->tableName)
            ->where('challenge', $data['challenge'])
            ->where('expires_at >=', time())
            ->get()
            ->row();

        if (null === $challengeData) {
            return false;
        }

        if (! hash_equals($this->getSignature($data['challenge']), $data['signature'])) {
            return false;
        }

        $this->database->delete($this->tableName, [
            'id' => $challengeData->id,
        ]);

        $this->cleanupOldChallenges();

        return true;
    }

    private function getHash(string $salt, int $secretNumber): string
    {
        return hash($this->phpHashAlgorithm, $salt . ((string) $secretNumber));
    }

    private function getSignature(string $challenge): string
    {
        return hash_hmac($this->phpHashAlgorithm, $challenge, $this->hmacKey);
    }

    private function cleanupOldChallenges(): void
    {
        $this->database->delete(
            $this->tableName,
            [
                'expires_at <' => time(),
            ],
        );
    }

    private function getPhpAlgorithmName(string $algorithm): string
    {
        if (! in_array($algorithm, ['SHA-256', 'SHA-384', 'SHA-512'])) {
            throw new RuntimeException("Invalid hash algorithm: $algorithm!");
        }

        return str_replace('-', '', strtolower($algorithm));
    }
}
