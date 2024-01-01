<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Altcha configuration, see application/libraries/Altcha.php ahd https://altcha.org/docs/
 */

/** HMAC key. We're using SHA-256, for which the ideal key is a 32-digit long hex number, I think. */
$config[Altcha::CONFIG_KEY_HMAC_KEY] = 'YOUR CUSTOM RANDOM SECRET';

/** The number of seconds any issued challenge is valid. */
$config[Altcha::CONFIG_KEY_CHALLENGE_EXPIRES_IN] = 3600;

/** Challenge complexity. The higher the number, the longer client-side calculation will take. */
$config[Altcha::CONFIG_KEY_MIN_COMPLEXITY] = 10000;
$config[Altcha::CONFIG_KEY_MAX_COMPLEXITY] = 100000;

/** Hash algorithm. Supported options: 'SHA-256', 'SHA-384' and 'SHA-512'. */
$config[Altcha::CONFIG_KEY_HASH_ALGORITHM] = 'SHA-256';

/** Name of the DB table where issued challenges will be stored. */
$config[Altcha::CONFIG_KEY_DB_TABLE_NAME] = 'altcha_challenges';
