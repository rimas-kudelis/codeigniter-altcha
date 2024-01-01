# ALTCHA for CodeIgniter 3

This is a small helper library to help you integrate [ALTCHA](https://altcha.org/) into your CodeIgniter 3 application
(and possibly CI2 as well). Why ALTCHA and not reCAPTCHA or some other web service? Well, for one, ALTCHA is
self-hosted, meaning no third parties will be involved and track your users. For that same reason, I would say it's
also simpler and quicker to validate, because no HTTP requests have to be made from your server to the said third
parties' servers to check the response. For more reasons and a more detailed explanation of how ALTCHA works, head on
to [altcha.org](https://altcha.org).

## Requirements

My code was written for PHP 8.1 and above. If you're stuck with an older version, you may have to downgrade its syntax
by removing data type and visibility hints, and probably make other small changes.

The front-end technology behind ALTCHA is supported by absolute majority of the browsers currently on the market, but
it requires JavaScript to be enabled and that the page using it is served over HTTPS (or locally).

## Installation

1. Copy `config/altcha.php` to your `application/config/` folder and `libraries/Altcha.php` to `application/libraries/`.

2. Review and update the newly added `application/config/altcha.php` file. In particular, you **must** at least change
    the value of `$config[Altcha::CONFIG_KEY_HMAC_KEY]`. You can generate a key by running the following piece of code
    in PHP console:
    ```php
    echo md5(random_bytes(128))
    ```
 
3. Include the ALTCHA widget in your user registration form (and/or other forms) and add the ALTCHA script to the
    appropriate pages. Refer to the [website integration](https://altcha.org/docs/website-integration) document for this
    step, but here's a short CI-specific example (note I'm using `challengejson` here to avoid an additional request and
    a separate controller):
    ```php
    /*
     * Your controller:
     */
    // Load the library
    $this->load->library('altcha');

    // When handling form submission, add validation rules and a message:
    $this->form_validation->set_rules('altcha', 'ALTCHA', ['required', ['verify_altcha', [$this->altcha, 'verifySolution']]]);
    $this->form_validation->set_message('verify_altcha', '%s: it looks like you might be a robot. Please try again.');
      
    // When displaying the form, just pass `altcha_challenge` string variable to the view:
    $data['altcha_challenge'] = json_encode($this->altcha->getChallenge());
    
    /*
     * Your view:
     */
    // Output the ALTCHA widget along with your form:
    <altcha-widget challengejson="<?php echo html_escape($altcha_challenge); ?>"></altcha-widget>
    // Reference the ALTCHA JavaScript file somewhere on your page:
    <script async defer src="/js/altcha.js" type="module"></script>
    ```

4. Create a table which will store issued and unsolved ALTCHA challenges. Here's an example MySQL query:
    ```mysql
    CREATE TABLE IF NOT EXISTS altcha_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge VARCHAR(512) NOT NULL,
        expires_at INT NOT NULL,
        CONSTRAINT altcha_challenges_pk
        UNIQUE (challenge)
    );
    ```
    Issued challenges are stored in this table and removed from it whenever they are solved. This ensures that solutions
    may not be re-used (unless the same challenge has been re-issued). Challenges are also deleted upon expiration,
    which ensures that the database table storing them is kept tidy, and that old challenges are automatically
    invalidated. Validity time is configurable in the `altcha.php` settings file.

5. Reap the fruit of your labor! Go ahead and test our your newly updated form to make sure that everything indeed works
    the way you expect it to.

## Notes on porting and CodeIgniter dependency

While the code which generates the challenges and later verifies solutions is fairly trivial, here it is somewhat
tightly integrated with CodeIgniter, namely:
- The `Altcha` class constructor makes use of the CI instance to pull configuration and get a reference to the Query
    Builder object.
- The aforementioned Query Builder object (`$this->database`) is used to store issued challenges and later check that
    the solution received is to a challenge that is still valid.
- `getChallenge()` makes a call to CI's `random_string()` helper function.
- The usual check for `BASEPATH` being defined is made to ensure that the PHP file is not being referenced directly.

That said, I believe it should be easy enough to use this library as a reference when implementing an integration with
whatever other framework you are using, or even plain PHP. While writing it, I consciously opted to keep it minimal and
avoid extra complexity by not introducing unnecessary classes and/or interfaces. That's why `getChallenge()` returns an
array instead of a data object and why the database is not hidden behind a `ChallengeStorage` abstraction. Because of
all of this, I also decided not to turn this library into a proper Composer package. From my point of view, this is more
of a Gist than an actual package. :)
