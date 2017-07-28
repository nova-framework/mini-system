<?php
/**
 * Language - simple language handler.
 *
 * @author Bartek Kuśmierczuk - contact@qsma.pl - http://qsma.pl
 * @version 3.0
 */

namespace Mini\Language;

use Mini\Language\LanguageManager;

use MessageFormatter;


/**
 * A Language class to load the requested language file.
 */
class Language
{
    /**
     * The Language Manager Instance.
     *
     * @var \Mini\Language\LanguageManager
     */
    protected $manager;

    /**
     * Holds an array with the Domain's Messages.
     *
     * @var array
     */
    private $messages = array();

    /**
     * The current Language Domain.
     */
    private $domain = null;

    /**
     * The current Language information.
     */
    private $code        = 'en';
    private $info        = 'English';
    private $name        = 'English';
    private $locale        = 'en-US';
    private $direction    = 'ltr';


    /**
     * Create an new Language instance.
     *
     * @param string $domain
     * @param string $code
     * @param string $path
     */
    public function __construct(LanguageManager $manager, $domain, $code)
    {
        $languages = $manager->getLanguages();

        if (isset($languages[$code]) && ! empty($languages[$code])) {
            $info = $languages[$code];

            $this->code = $code;

            //
            $this->info            = $info['info'];
            $this->name            = $info['name'];
            $this->locale        = $info['locale'];
            $this->direction    = $info['dir'];
        } else {
            $code = 'en';
        }

        $this->domain = $domain;

        // Determine the current Language file path.
        $namespaces = $manager->getNamespaces();

        // Check if the language file is readable.
        if (! array_key_exists($domain, $namespaces)) return;

        // Determine the Language(s) path.
        $namespace = $namespaces[$domain];

        $filePath = $namespace .DS .strtoupper($code) .DS .'messages.php';

        if (is_readable($filePath)) {
            // The requested language file exists; retrieve the messages from it.
            $messages = require $filePath;

            // Some consistency check of the messages, before setting them.
            if (is_array($messages) && ! empty($messages)) {
                $this->messages = $messages;
            }
        }
    }

    /**
     * Translate a message with optional formatting
     * @param string $message Original message.
     * @param array $params Optional params for formatting.
     * @return string
     */
    public function translate($message, array $params = array())
    {
        // Update the current message with the domain translation, if we have one.
        if (isset($this->messages[$message]) && ! empty($this->messages[$message])) {
            $message = $this->messages[$message];
        }

        if (empty($params)) {
            return $message;
        }

        // Standard Message formatting, using the standard PHP Intl and its MessageFormatter.
        // The message string should be formatted using the standard ICU commands.
        return MessageFormatter::formatMessage($this->locale, $message, $params);

        // The VSPRINTF alternative for Message formatting, for those die-hard against ICU.
        // The message string should be formatted using the standard PRINTF commands.
        //return vsprintf($message, $arguments);
    }

    // Public Getters

    /**
     * Get current domain
     * @return string
     */
    public function domain()
    {
        return $this->domain;
    }

    /**
     * Get current code
     * @return string
     */
    public function code()
    {
        return $this->code;
    }

    /**
     * Get current info
     * @return string
     */
    public function info()
    {
        return $this->info;
    }

    /**
     * Get current name
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Get current locale
     * @return string
     */
    public function locale()
    {
        return $this->locale;
    }

    /**
     * Get all messages
     * @return array
     */
    public function messages()
    {
        return $this->messages;
    }

    /**
     * Get the current direction
     *
     * @return string rtl or ltr
     */
    public function direction()
    {
        return $this->direction;
    }

}
