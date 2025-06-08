<?php
/**
 * MediaWiki Extension: ImgTag
 * Allows <img> tags in wikitext with sanitized src attributes
 */

if (!defined('MEDIAWIKI')) {
    die('This file is a MediaWiki extension, it is not a valid entry point');
}

$wgExtensionCredits['parserhook'][] = array(
    'path' => __FILE__,
    'name' => 'ImgTag',
    'author' => 'Your Name',
    'version' => '1.0.0',
    'description' => 'Allows <img> tags in wikitext with URL sanitization',
    'descriptionmsg' => 'allowimgtag-desc',
);

// Configuration - allowed domains/protocols
$wgImgTagDomains = [
    'upload.wikimedia.org',
    'commons.wikimedia.org',
    // Add your trusted domains here
];

$wgImgTagProtocols = ['https', 'http'];
$wgImgTagMaxSize = 1000; // Max width/height in pixels

class ImgTagHooks {
    
    /**
     * Hook into parser setup to register the img tag
     */
    public static function onParserFirstCallInit(Parser $parser) {
        // Register <img> as a valid tag
        $parser->setHook('img', [self::class, 'renderImgTag']);
        return true;
    }
    
    /**
     * Hook into HTML sanitization to allow img tags
     */
    public static function onHtmlCacheUpdaterAppendQuery(&$query) {
        return true;
    }
    
    /**
     * Modify the sanitizer whitelist to allow img tags
     */
    public static function onSanitizerAfterFixTagAttributes(&$text, $element, &$attribs, $parser) {
        if ($element === 'img') {
            // Allow img element through sanitizer
            return true;
        }
        return true;
    }
    
    /**
     * Main rendering function for <img> tags
     */
    public static function renderImgTag($input, array $args, Parser $parser, PPFrame $frame) {
        global $wgImgTagDomains, $wgAllowImgTagProtocols, $wgAllowImgTagMaxSize;
        
        // Get and sanitize the src attribute
        $src = isset($args['src']) ? trim($args['src']) : '';
        
        if (empty($src)) {
            return '<span class="error">Error: img tag requires src attribute</span>';
        }
        
        // Sanitize the URL
        $sanitizedSrc = self::sanitizeImageUrl($src, $wgImgTagDomains, $wgAllowImgTagProtocols);
        
        if (!$sanitizedSrc) {
            return '<span class="error">Error: Invalid or unauthorized image URL</span>';
        }
        
        // Sanitize other attributes
        $allowedAttribs = ['alt', 'title', 'width', 'height', 'class'];
        $safeAttribs = [];
        
        foreach ($allowedAttribs as $attrib) {
            if (isset($args[$attrib])) {
                $value = htmlspecialchars(trim($args[$attrib]), ENT_QUOTES);
                
                // Special handling for width/height
                if (($attrib === 'width' || $attrib === 'height') && is_numeric($value)) {
                    $value = min((int)$value, $wgImgTagMaxSize);
                }
                
                $safeAttribs[$attrib] = $value;
            }
        }
        
        // Build the img tag
        $imgTag = '<img src="' . htmlspecialchars($sanitizedSrc, ENT_QUOTES) . '"';
        
        foreach ($safeAttribs as $attr => $value) {
            $imgTag .= ' ' . $attr . '="' . $value . '"';
        }
        
        $imgTag .= ' />';
        
        // Mark as raw HTML so MediaWiki doesn't escape it
        return $parser->insertStripItem($imgTag);
    }
    
    /**
     * Sanitize image URLs
     */
    private static function sanitizeImageUrl($url, $allowedDomains, $allowedProtocols) {
        // Parse the URL
        $parsed = parse_url($url);
        
        if (!$parsed) {
            return false;
        }
        
        // Check protocol
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), $allowedProtocols)) {
            return false;
        }
        
        // Check domain whitelist
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $host = strtolower($parsed['host']);
        $domainAllowed = false;
        
        foreach ($allowedDomains as $allowedDomain) {
            if ($host === strtolower($allowedDomain) || 
                str_ends_with($host, '.' . strtolower($allowedDomain))) {
                $domainAllowed = true;
                break;
            }
        }
        
        if (!$domainAllowed) {
            return false;
        }
        
        // Additional security checks
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        
        // Block potentially dangerous file extensions
        $dangerousExtensions = ['.php', '.asp', '.jsp', '.exe', '.bat', '.sh'];
        foreach ($dangerousExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                return false;
            }
        }
        
        // Allow only image file extensions
        $imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.bmp'];
        $hasImageExtension = false;
        foreach ($imageExtensions as $ext) {
            if (str_ends_with(strtolower($path), $ext)) {
                $hasImageExtension = true;
                break;
            }
        }
        
        if (!$hasImageExtension) {
            return false;
        }
        
        // Reconstruct clean URL
        $cleanUrl = $parsed['scheme'] . '://' . $parsed['host'];
        
        if (isset($parsed['port'])) {
            $cleanUrl .= ':' . $parsed['port'];
        }
        
        $cleanUrl .= $path;
        
        if (isset($parsed['query'])) {
            $cleanUrl .= '?' . $parsed['query'];
        }
        
        return $cleanUrl;
    }
    
    /**
     * Hook to modify HTML sanitizer rules
     */
    public static function onParserAfterTidy(Parser $parser, &$text) {
        // Additional post-processing if needed
        return true;
    }
}

// Register hooks
$wgHooks['ParserFirstCallInit'][] = 'ImgTagHooks::onParserFirstCallInit';
$wgHooks['SanitizerAfterFixTagAttributes'][] = 'ImgTagHooks::onSanitizerAfterFixTagAttributes';
$wgHooks['ParserAfterTidy'][] = 'ImgTagHooks::onParserAfterTidy';

// Add to HTML sanitizer whitelist
$wgHooks['ParserBeforeInternalParse'][] = function($parser, $text, $stripState) {
    global $wgHtmlEntities;
    
    // Temporarily allow img tags in the sanitizer
    if (!isset($wgHtmlEntities['img'])) {
        $wgHtmlEntities['img'] = true;
    }
    
    return true;
};
