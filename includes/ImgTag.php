<?php

# namespace MediaWiki\Extension\ImgTag;

use MediaWiki\Parser\PPFrame;
use MediaWiki\MediaWikiServices;

class ImgTagHooks {

    private static $config;
    
    public static function onParserFirstCallInit($parser) {
        $parser->setHook('img', [self::class, 'renderImgTag']);
    }
    
    public static function renderImgTag($input, array $args, Parser $parser, PPFrame $frame) {
        if ( self::$config === null ) {
            self::$config = MediaWikiServices::getInstance()->getMainConfig();
        }

        // Get and sanitize the src attribute
        $src = isset($args['src']) ? trim($args['src']) : '';
        
        if (empty($src)) {
            return '<span class="error">Error: img tag requires src attribute</span>';
        }
        
        // Sanitize the URL
        $domains = self::$config->get("ImgTagDomains");
        $protocols = self::$config->get("ImgTagProtocols");
        $sanitizedSrc = self::sanitizeImageUrl($src, $domains, $protocols);
        
        if (!$sanitizedSrc) {
            return '<span class="error">Error: Invalid or unauthorized image URL</span>';
        }
        
        // Sanitize other attributes
        $allowedAttribs = ['alt', 'title', 'width', 'height', 'class', 'fetchpriority', 'loading', 'sizes'];
        $safeAttribs = [];
        
        foreach ($allowedAttribs as $attrib) {
            if (isset($args[$attrib])) {
                $value = htmlspecialchars(trim($args[$attrib]), ENT_QUOTES);
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
}
