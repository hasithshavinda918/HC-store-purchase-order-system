<?php
/**
 * Language Translation System
 * Supports English, Sinhala, and Tamil
 */

class LanguageManager {
    private static $instance = null;
    private $currentLanguage = 'en';
    private $translations = [];
    private $defaultLanguage = 'en';
    private $supportedLanguages = ['en', 'si', 'ta'];
    
    private function __construct() {
        $this->initializeLanguage();
        $this->loadTranslations();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize language from session or browser
     */
    private function initializeLanguage() {
        // Check if language is set in session
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], $this->supportedLanguages)) {
            $this->currentLanguage = $_SESSION['language'];
        } 
        // Check if language is set via GET parameter
        elseif (isset($_GET['lang']) && in_array($_GET['lang'], $this->supportedLanguages)) {
            $this->setLanguage($_GET['lang']);
        }
        // Auto-detect from browser language
        else {
            $this->currentLanguage = $this->detectBrowserLanguage();
        }
    }
    
    /**
     * Detect browser language
     */
    private function detectBrowserLanguage() {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return $this->defaultLanguage;
        }
        
        $acceptLanguages = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        
        // Check for Sinhala
        if (strpos($acceptLanguages, 'si') !== false) {
            return 'si';
        }
        
        // Check for Tamil
        if (strpos($acceptLanguages, 'ta') !== false) {
            return 'ta';
        }
        
        // Default to English
        return 'en';
    }
    
    /**
     * Load translation files
     */
    private function loadTranslations() {
        $langFile = __DIR__ . '/languages/' . $this->currentLanguage . '.php';
        
        if (file_exists($langFile)) {
            $this->translations = include $langFile;
        } else {
            // Fallback to English
            $this->translations = include __DIR__ . '/languages/en.php';
        }
    }
    
    /**
     * Set current language
     */
    public function setLanguage($lang) {
        if (in_array($lang, $this->supportedLanguages)) {
            $this->currentLanguage = $lang;
            $_SESSION['language'] = $lang;
            $this->loadTranslations();
            return true;
        }
        return false;
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Get supported languages
     */
    public function getSupportedLanguages() {
        return [
            'en' => 'English',
            'si' => 'සිංහල',
            'ta' => 'தமிழ்'
        ];
    }
    
    /**
     * Translate a key
     */
    public function translate($key, $replacements = []) {
        $translation = $this->translations[$key] ?? $key;
        
        // Handle replacements
        if (!empty($replacements)) {
            foreach ($replacements as $placeholder => $value) {
                $translation = str_replace(':' . $placeholder, $value, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Get all translations for JavaScript
     */
    public function getAllTranslations() {
        return $this->translations;
    }
    
    /**
     * Check if key exists
     */
    public function hasTranslation($key) {
        return isset($this->translations[$key]);
    }
    
    /**
     * Get language direction (for RTL support if needed)
     */
    public function getDirection() {
        // All supported languages are LTR
        return 'ltr';
    }
    
    /**
     * Get language name in native script
     */
    public function getLanguageName($lang = null) {
        $lang = $lang ?? $this->currentLanguage;
        
        $names = [
            'en' => 'English',
            'si' => 'සිංහල',
            'ta' => 'தமிழ்'
        ];
        
        return $names[$lang] ?? $names['en'];
    }
}

/**
 * Helper functions for easy translation
 */

/**
 * Main translation function
 */
function __($key, $replacements = []) {
    return LanguageManager::getInstance()->translate($key, $replacements);
}

/**
 * Echo translation
 */
function _e($key, $replacements = []) {
    echo __($key, $replacements);
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    return LanguageManager::getInstance()->getCurrentLanguage();
}

/**
 * Set language
 */
function setLanguage($lang) {
    return LanguageManager::getInstance()->setLanguage($lang);
}

/**
 * Get supported languages
 */
function getSupportedLanguages() {
    return LanguageManager::getInstance()->getSupportedLanguages();
}

/**
 * Generate language selector HTML
 */
function generateLanguageSelector($currentPage = '') {
    $langManager = LanguageManager::getInstance();
    $supportedLangs = $langManager->getSupportedLanguages();
    $currentLang = $langManager->getCurrentLanguage();
    
    $html = '<div class="relative inline-block text-left">';
    $html .= '<div class="language-selector">';
    $html .= '<button type="button" class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="language-menu" aria-expanded="true" aria-haspopup="true">';
    $html .= '<span class="mr-2">' . $supportedLangs[$currentLang] . '</span>';
    $html .= '<svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
    $html .= '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
    $html .= '</svg></button></div>';
    
    $html .= '<div class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none hidden" id="language-dropdown" role="menu" aria-orientation="vertical" aria-labelledby="language-menu">';
    $html .= '<div class="py-1" role="none">';
    
    foreach ($supportedLangs as $code => $name) {
        $activeClass = $currentLang === $code ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100';
        $url = $_SERVER['PHP_SELF'] . '?lang=' . $code;
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $params);
            $params['lang'] = $code;
            $url = $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
        }
        
        $html .= '<a href="' . $url . '" class="' . $activeClass . ' block px-4 py-2 text-sm" role="menuitem">';
        $html .= '<div class="flex items-center">';
        
        // Add flag icons
        if ($code === 'en') {
            $html .= '<span class="mr-3">🇬🇧</span>';
        } elseif ($code === 'si') {
            $html .= '<span class="mr-3">🇱🇰</span>';
        } elseif ($code === 'ta') {
            $html .= '<span class="mr-3">🇱🇰</span>';
        }
        
        $html .= '<span>' . $name . '</span>';
        if ($currentLang === $code) {
            $html .= '<span class="ml-auto"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg></span>';
        }
        
        $html .= '</div></a>';
    }
    
    $html .= '</div></div></div>';
    
    // JavaScript for dropdown toggle
    $html .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const menuButton = document.getElementById("language-menu");
        const dropdown = document.getElementById("language-dropdown");
        
        if (menuButton && dropdown) {
            menuButton.addEventListener("click", function(e) {
                e.preventDefault();
                dropdown.classList.toggle("hidden");
            });
            
            // Close dropdown when clicking outside
            document.addEventListener("click", function(e) {
                if (!menuButton.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add("hidden");
                }
            });
        }
    });
    </script>';
    
    return $html;
}

// Initialize the language manager
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-initialize
LanguageManager::getInstance();
?>
