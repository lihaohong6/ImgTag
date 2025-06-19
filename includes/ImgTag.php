<?php

namespace MediaWiki\Extension\ImgTag;

use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class ImgTag {

    private static $config;

    public static function onParserFirstCallInit( Parser $parser ) {
        $parser->setHook('img', [self::class, 'renderImgTag']);
        $parser->setFunctionHook('fileused', [self::class, 'markFileAsUsed']);
    }

    public static function markFileAsUsed( Parser $parser, $filename = '' ) {
        $filename = $parser->recursiveTagParse( $filename );
        $filename = trim($filename);
        // Remove File: prefix if present
        if (preg_match('/^(File|Image):/i', $filename)) {
            $filename = preg_replace('/^(File|Image):/i', '', $filename);
        }
        $title = Title::makeTitleSafe(NS_FILE, $filename);
        if ( !$title->exists() ) {
            return '';
        }
        $parser->getOutput()->addImage( $title->getDBkey() );
        return '';
    }

    public static function renderImgTag($input, array $args, Parser $parser, PPFrame $frame) {
        if ( self::$config === null ) {
            self::$config = MediaWikiServices::getInstance()->getMainConfig();
        }

        // Get and sanitize the src attribute
        $src = isset($args['src']) ? trim($args['src']) : '';
        $src = $parser->recursivePreprocess($src, $frame);

        if (empty($src)) {
            return '<span class="error">Error: img tag requires src attribute</span>';
        }

        $sanitizeDomain = self::$config->get("ImgTagSanitizeDomain");
        if ($sanitizeDomain) { 
            // Sanitize the URL
            $domains = self::$config->get("ImgTagDomains");
        } else {
            $domains = true;
        }
        $protocols = self::$config->get("ImgTagProtocols");
        [$sanitizedSrc, $sanitizationError] = self::sanitizeImageUrl($src, $domains, $protocols);
        if ($sanitizationError) {
            return '<span class="error">' . $sanitizationError . '</span>';
        }

        $safeAttribs = [];
        $safeAttribs['src'] = $sanitizedSrc;

        // Sanitize other attributes
        $allowedAttribs = ['style', 'alt', 'title', 'width', 'height', 'class', 'fetchpriority', 'loading', 'sizes'];
        foreach ($args as $attrib => $value) {
            if (in_array($attrib, $allowedAttribs)) {
                $value = $parser->recursivePreprocess($value, $frame);
                if ($attrib === 'style') {
                    $value = Sanitizer::checkCss($value);
                } else {
                    $value = htmlspecialchars(trim($value), ENT_QUOTES);
                }
                $safeAttribs[$attrib] = $value;
            }
        } 

        return Html::rawElement('img', $safeAttribs); 
    }

    /**
     * Sanitize image URLs
     */
    private static function sanitizeImageUrl($url, $allowedDomains, $allowedProtocols) {
        // Parse the URL
        $parsed = parse_url($url);

        if (!$parsed) {
            return [false, "Image src must be non-empty"];
        }

        // Check protocol (e.g. https)
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), $allowedProtocols)) {
            return [false, "Image src must have a valid protocol"];
        }

        if (!isset($parsed['host'])) {
            return [false, "Image src must have a valid host"];
        }

        $host = strtolower($parsed['host']);
        if ($allowedDomains === true) {
            $domainAllowed = true;
        } else {
            $domainAllowed = false;
            // Check host domain
            foreach ($allowedDomains as $allowedDomain) {
                if ($host === strtolower($allowedDomain) || 
                        str_ends_with($host, '.' . strtolower($allowedDomain))) {
                    $domainAllowed = true;
                    break;
                }
            }
        }

        if (!$domainAllowed) {
            return [false, "Image src must have a valid domain"];
        }

        return [$url, false];
    }
}
